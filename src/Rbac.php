<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Siberfx\RbacPermission\Events\RbacChanged;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Models\RoleGrant;
use Siberfx\RbacPermission\Models\UserGrant;
use Siberfx\RbacPermission\Scopes\ExcludesRows;
use Siberfx\RbacPermission\Scopes\ScopeRegistry;
use Siberfx\RbacPermission\Scopes\Unrestricted;
use Siberfx\RbacPermission\Support\Catalog;
use Siberfx\RbacPermission\Support\Grant;
use Siberfx\RbacPermission\Support\PermissionKey;

final class Rbac
{
    public const string ALLOW = 'allow';

    public const string DENY = 'deny';

    public const string NONE = 'none';

    /** @var array<string, list<Grant>> */
    private array $memo = [];

    /**
     * Grants per user and permission key, so each pattern is matched once per request.
     *
     * @var array<string, array<string, list<Grant>>>
     */
    private array $matched = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ScopeRegistry $scopes,
        private readonly CacheFactory $cache,
    ) {}

    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    public function scopes(): ScopeRegistry
    {
        return $this->scopes;
    }

    /* ------------------------------------------------------------------
     | Decisions
     | ------------------------------------------------------------------ */

    public function allows(string $permission, mixed $subject = null, ?Model $user = null): bool
    {
        return $this->decide($permission, $subject, $user) === self::ALLOW;
    }

    public function denies(string $permission, mixed $subject = null, ?Model $user = null): bool
    {
        return ! $this->allows($permission, $subject, $user);
    }

    public function enforce(string $permission, mixed $subject = null, ?Model $user = null): void
    {
        $decision = $this->decide($permission, $subject, $user);

        if ($decision !== self::ALLOW) {
            throw new PermissionDenied($permission, $decision);
        }
    }

    /**
     * Returns ALLOW, DENY or NONE. A matching deny always beats an allow.
     */
    public function decide(string $permission, mixed $subject = null, ?Model $user = null): string
    {
        $this->assertKnown($permission);

        $user ??= $this->currentUser();

        if ($user === null) {
            return self::NONE;
        }

        $subject = $this->asSubject($subject);
        $matching = $this->matching($user, $permission);

        foreach ($matching as $grant) {
            if ($grant->effect === Grant::DENY && $this->covers($grant, $user, $subject)) {
                return self::DENY;
            }
        }

        foreach ($matching as $grant) {
            if ($grant->effect === Grant::ALLOW && $this->covers($grant, $user, $subject)) {
                return self::ALLOW;
            }
        }

        return self::NONE;
    }

    /**
     * Gate::before hook. Returns null for names outside the catalog, so other
     * gates keep working.
     *
     * @param  array<array-key, mixed>  $arguments  the Gate arguments; the first may be the subject
     */
    public function gate(string $ability, mixed $user, array $arguments = []): ?bool
    {
        if (! $user instanceof Model || ! $this->catalog->has($ability)) {
            return null;
        }

        $subject = $arguments[0] ?? null;

        return match ($this->decide($ability, $subject instanceof Model ? $subject : null, $user)) {
            self::ALLOW => true,
            self::DENY => false,
            default => null,
        };
    }

    /**
     * Full decision trace: which roles and grants were considered and which covered the subject.
     *
     * @return array{permission: string, user: ?string, subject: ?string, decision: string, roles: list<string>, grants: list<array{grant: string, source: string, covers: bool}>}
     */
    public function explain(string $permission, mixed $subject = null, ?Model $user = null): array
    {
        $this->assertKnown($permission);

        $user ??= $this->currentUser();
        $subject = $this->asSubject($subject);
        $matching = $user ? $this->matching($user, $permission) : [];

        $roles = [];
        $rows = [];

        foreach ($matching as $grant) {
            if (str_starts_with($grant->source, 'role:')) {
                $roles[] = substr($grant->source, 5);
            }

            $rows[] = [
                'grant' => $grant->label(),
                'source' => $grant->source,
                'covers' => $this->covers($grant, $user, $subject),
            ];
        }

        return [
            'permission' => $permission,
            'user' => $user ? $user->getMorphClass().'#'.$user->getKey() : null,
            'subject' => $subject ? class_basename($subject).'#'.$subject->getKey() : null,
            'decision' => $this->decide($permission, $subject, $user),
            'roles' => array_values(array_unique($roles)),
            'grants' => $rows,
        ];
    }

    /**
     * May this actor put this permission on a role? They must hold every matching
     * permission at a scope at least as wide, and must not be denied any of them.
     * This stops a role editor from handing out permissions they do not have themselves.
     */
    public function canGrant(Model $actor, string $input): bool
    {
        [$pattern, $scope] = PermissionKey::parse($input, (string) config('rbac.default_scope', 'own'));

        $keys = array_values(array_filter(
            $this->catalog->permissions(),
            fn (string $key) => PermissionKey::matches($pattern, $key),
        ));

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            // A deny on the key, at any scope, means the actor may not hand it out.
            if (array_any($this->matching($actor, $key), fn (Grant $grant) => $grant->effect === Grant::DENY)) {
                return false;
            }

            $widest = $this->widestScope($key, $actor);

            if ($widest === null || $this->scopes->level($widest) < $this->scopes->level($scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The widest scope the user holds for a permission, ignoring denies (e.g. "tenant").
     */
    public function widestScope(string $permission, ?Model $user = null): ?string
    {
        $this->assertKnown($permission);

        $user ??= $this->currentUser();

        if ($user === null) {
            return null;
        }

        $best = null;

        foreach ($this->matching($user, $permission) as $grant) {
            if ($grant->effect !== Grant::ALLOW || ! $this->scopes->has($grant->scope)) {
                continue;
            }

            if ($best === null || $this->scopes->level($grant->scope) > $this->scopes->level($best)) {
                $best = $grant->scope;
            }
        }

        return $best;
    }

    /* ------------------------------------------------------------------
     | Query constraints ("show only what I may see")
     | ------------------------------------------------------------------ */

    /**
     * Narrow a query to the rows the user may reach for this permission.
     *   Rbac::constrain(Invoice::query(), 'billing.invoice.show')->paginate();
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, string $permission, ?Model $user = null): Builder
    {
        $this->assertKnown($permission);

        $user ??= $this->currentUser();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $model = $query->getModel();
        $grants = array_values(array_filter(
            $this->matching($user, $permission),
            fn (Grant $grant) => $this->scopes->has($grant->scope),
        ));

        foreach ($grants as $grant) {
            if ($grant->effect === Grant::DENY && $this->isUnrestricted($grant->scope)) {
                return $query->whereRaw('1 = 0');
            }
        }

        $allows = array_values(array_filter($grants, fn (Grant $grant) => $grant->effect === Grant::ALLOW));

        if ($allows === []) {
            return $query->whereRaw('1 = 0');
        }

        $hasGlobal = array_any($allows, fn (Grant $grant) => $this->isUnrestricted($grant->scope));

        if (! $hasGlobal) {
            $query->where(function ($q) use ($allows, $user, $model) {
                foreach ($allows as $grant) {
                    $q->orWhere(fn ($inner) => $this->scopes->get($grant->scope)->constrain($inner, $user, $model));
                }
            });
        }

        foreach ($grants as $grant) {
            if ($grant->effect !== Grant::DENY) {
                continue;
            }

            $scope = $this->scopes->get($grant->scope);

            // A plain NOT (...) also drops rows whose scope column is NULL, which
            // covers() does not treat as denied. Scopes that know their columns
            // exclude rows null-safely.
            if ($scope instanceof ExcludesRows) {
                $query->where(fn ($q) => $scope->exclude($q, $user, $model));
            } else {
                $query->whereNot(fn ($q) => $scope->constrain($q, $user, $model));
            }
        }

        return $query;
    }

    /* ------------------------------------------------------------------
     | Management: roles, role grants, assignments, direct overrides
     | ------------------------------------------------------------------ */

    public function role(string $slug, ?string $name = null, ?string $description = null, ?int $rank = null, ?string $guard = null): Role
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            throw new InvalidArgumentException("Invalid role slug [{$slug}].");
        }

        $guard ??= $this->defaultGuard();

        return Role::query()->firstOrCreate(
            ['slug' => $slug, 'guard' => $guard],
            ['name' => $name ?? Str::headline($slug), 'description' => $description, 'rank' => $rank ?? 0],
        );
    }

    /**
     * The highest rank among the roles the user holds in their guard, or null when they hold none.
     */
    public function rankOf(Model $user): ?int
    {
        $rank = $this->whereActive($this->db()->table($this->table('role_assignments').' as a')
            ->join($this->table('roles').' as r', 'r.id', '=', 'a.role_id')
            ->where('a.model_type', $user->getMorphClass())
            ->where('a.model_id', (string) $user->getKey())
            ->where('r.guard', $this->guardOf($user)), 'a.expires_at')
            ->max('r.rank');

        return $rank === null ? null : (int) $rank;
    }

    /**
     * May this actor assign or remove the role? Only roles strictly below their own rank.
     * A string role is looked up in the actor's guard; pass a Role to check across guards.
     */
    public function canAssign(Model $actor, Role|string $role): bool
    {
        $role = $role instanceof Role ? $role : $this->findRole($role, $this->guardOf($actor));
        $rank = $this->rankOf($actor);

        return $rank !== null && $rank > (int) $role->rank;
    }

    /**
     * Add a grant to a role. Creates the role if it does not exist yet.
     */
    public function grant(Role|string $role, string $permission, ?string $guard = null): RoleGrant
    {
        [$pattern, $scope] = $this->parseGrant($permission);
        $role = $role instanceof Role ? $role : $this->role($role, guard: $guard);

        $grant = RoleGrant::query()->firstOrCreate([
            'role_id' => $role->getKey(),
            'permission' => $pattern,
            'scope' => $scope,
        ]);

        $this->flush();

        if ($grant->wasRecentlyCreated) {
            $this->record('role.granted', $role, ['role' => $role->slug, 'permission' => $pattern, 'scope' => $scope]);
        }

        return $grant;
    }

    public function revoke(Role|string $role, string $permission, ?string $guard = null): int
    {
        [$pattern, $scope] = $this->parseGrant($permission);
        $role = $role instanceof Role ? $role : $this->findRole($role, $guard);

        $deleted = RoleGrant::query()
            ->where('role_id', $role->getKey())
            ->where('permission', $pattern)
            ->where('scope', $scope)
            ->delete();

        $this->flush();

        if ($deleted > 0) {
            $this->record('role.revoked', $role, ['role' => $role->slug, 'permission' => $pattern, 'scope' => $scope]);
        }

        return $deleted;
    }

    /**
     * Give the user a role. When $by is given (the actor making the change), the
     * role must sit strictly below the actor's rank. Without $by nothing is checked,
     * which is how seeders and trusted commands run.
     */
    public function assign(Model $user, Role|string $role, ?Model $by = null, ?DateTimeInterface $expiresAt = null): void
    {
        $role = $role instanceof Role ? $role : $this->findRole($role, $this->guardOf($user));
        $this->assertCanManage($by, $role);

        $table = $this->db()->table($this->table('role_assignments'));
        $key = ['role_id' => $role->getKey(), 'model_type' => $user->getMorphClass(), 'model_id' => (string) $user->getKey()];
        $existing = (clone $table)->where($key)->first(['expires_at']);

        if ($existing === null) {
            $table->insert([...$key, 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($this->timestamp($existing->expires_at) === $expiresAt?->getTimestamp()) {
            // Already held with the same expiry: nothing to write or record.
            return;
        } else {
            // Re-assigning replaces the expiry: no date makes the role permanent again.
            $table->where($key)->update(['expires_at' => $expiresAt, 'updated_at' => now()]);
        }

        $this->flush();

        $this->record('role.assigned', $user, ['role' => $role->slug, 'expires_at' => $expiresAt], $by);
    }

    public function unassign(Model $user, Role|string $role, ?Model $by = null): int
    {
        $role = $role instanceof Role ? $role : $this->findRole($role, $this->guardOf($user));
        $this->assertCanManage($by, $role);

        $deleted = $this->db()->table($this->table('role_assignments'))
            ->where('role_id', $role->getKey())
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->delete();

        $this->flush();

        if ($deleted > 0) {
            $this->record('role.unassigned', $user, ['role' => $role->slug], $by);
        }

        return $deleted;
    }

    /**
     * Direct allow. Pass $expiresAt for a time-boxed grant; it stops applying once that moment passes.
     */
    public function allow(Model $user, string $permission, ?DateTimeInterface $expiresAt = null): UserGrant
    {
        return $this->directGrant($user, $permission, Grant::ALLOW, $expiresAt);
    }

    /**
     * Direct deny. Pass $expiresAt to lift the deny automatically.
     */
    public function deny(Model $user, string $permission, ?DateTimeInterface $expiresAt = null): UserGrant
    {
        return $this->directGrant($user, $permission, Grant::DENY, $expiresAt);
    }

    /**
     * Delete role assignments and direct grants whose expiry has passed. Returns the number removed.
     */
    public function pruneExpired(): int
    {
        $connection = $this->db();

        $assignments = $connection->table($this->table('role_assignments'))
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        $grants = $connection->table($this->table('user_grants'))
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        $this->flush();

        if ($assignments + $grants > 0) {
            $this->record('grants.pruned', null, ['assignments' => $assignments, 'grants' => $grants]);
        }

        return $assignments + $grants;
    }

    /**
     * Remove a direct allow or deny for this exact permission and scope.
     */
    public function withdraw(Model $user, string $permission): int
    {
        [$pattern, $scope] = $this->parseGrant($permission);

        $deleted = UserGrant::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->where('permission', $pattern)
            ->where('scope', $scope)
            ->delete();

        $this->flush();

        if ($deleted > 0) {
            $this->record('permission.withdrawn', $user, ['permission' => $pattern, 'scope' => $scope]);
        }

        return $deleted;
    }

    /**
     * Make the database match config('rbac.roles'). Roles are written in one
     * transaction, after every grant has been validated. Only grants that changed
     * are written: unchanged rows keep their ids, and a duplicate entry is ignored.
     *
     * @param  array<string, list<string>>  $definitions
     * @return array{roles: int, grants: int, added: int, removed: int, pruned: int}
     */
    public function sync(array $definitions, bool $prune = false, ?string $guard = null): array
    {
        $guard ??= $this->defaultGuard();

        $parsed = [];

        foreach ($definitions as $slug => $specs) {
            $parsed[(string) $slug] = array_map(fn ($spec) => $this->parseGrant((string) $spec), (array) $specs);
        }

        return $this->db()->transaction(function () use ($parsed, $prune, $guard) {
            $stats = ['roles' => 0, 'grants' => 0, 'added' => 0, 'removed' => 0, 'pruned' => 0];

            $ranks = (array) $this->guardSetting($guard, 'ranks');

            foreach ($parsed as $slug => $grants) {
                $role = $this->role($slug, guard: $guard);

                if (array_key_exists($slug, $ranks)) {
                    $role->update(['rank' => (int) $ranks[$slug]]);
                }

                $wanted = [];

                foreach ($grants as [$pattern, $scope]) {
                    $wanted["{$pattern}@{$scope}"] = [$pattern, $scope];
                }

                $existing = RoleGrant::query()->where('role_id', $role->getKey())->get()
                    ->keyBy(fn (RoleGrant $grant) => "{$grant->permission}@{$grant->scope}");

                $stale = array_diff_key($existing->all(), $wanted);

                if ($stale !== []) {
                    RoleGrant::query()->whereKey(array_map(fn (RoleGrant $grant) => $grant->getKey(), array_values($stale)))->delete();
                    $stats['removed'] += count($stale);
                }

                foreach (array_diff_key($wanted, $existing->all()) as [$pattern, $scope]) {
                    RoleGrant::query()->create([
                        'role_id' => $role->getKey(),
                        'permission' => $pattern,
                        'scope' => $scope,
                    ]);
                    $stats['added']++;
                }

                $stats['grants'] += count($wanted);
                $stats['roles']++;
            }

            if ($prune) {
                foreach (Role::query()->where('guard', $guard)->whereNotIn('slug', array_keys($parsed))->get() as $role) {
                    RoleGrant::query()->where('role_id', $role->getKey())->delete();
                    $this->db()->table($this->table('role_assignments'))->where('role_id', $role->getKey())->delete();
                    $role->delete();
                    $stats['pruned']++;
                }
            }

            $this->flush();

            $this->record('roles.synced', null, $stats);

            return $stats;
        });
    }

    /**
     * Role x permission grid for the matrix command. Each cell is the widest scope, or null.
     *
     * @return array{roles: list<string>, rows: array<string, array<string, ?string>>}
     */
    public function matrix(?string $guard = null): array
    {
        $guard ??= $this->defaultGuard();
        $roles = Role::query()->where('guard', $guard)->with('grants')->orderBy('slug')->get();
        $rows = [];

        foreach ($this->catalog->permissions() as $key) {
            $cells = [];

            foreach ($roles as $role) {
                $best = null;

                foreach ($role->grants as $grant) {
                    if (! PermissionKey::matches($grant->permission, $key)) {
                        continue;
                    }

                    if ($best === null || $this->scopes->level($grant->scope) > $this->scopes->level($best)) {
                        $best = $grant->scope;
                    }
                }

                $cells[$role->slug] = $best;
            }

            $rows[$key] = $cells;
        }

        return ['roles' => $roles->pluck('slug')->all(), 'rows' => $rows];
    }

    /**
     * The user that console commands run as (see config('rbac.console')).
     */
    public function consoleActor(): ?Model
    {
        $resolver = config('rbac.console.resolver');

        if (is_callable($resolver)) {
            $user = $resolver();

            return $user instanceof Model ? $user : null;
        }

        $id = config('rbac.console.user_id');

        if ($id === null || $id === '') {
            return null;
        }

        $class = $this->userModel(config('rbac.console.guard'));

        return $class::query()->find($id);
    }

    /**
     * The user model a guard authenticates, read from its auth provider.
     * Defaults to the default guard; falls back to auth.providers.users.model.
     *
     * @return class-string<Model>
     */
    public function userModel(?string $guard = null): string
    {
        $guard ??= $this->defaultGuard();
        $provider = config("auth.guards.{$guard}.provider");
        $model = $provider ? config("auth.providers.{$provider}.model") : null;

        return (string) ($model ?? config('auth.providers.users.model'));
    }

    /**
     * Forget memoised grants. Called automatically by every write method.
     *
     * Inside a transaction the cache version is bumped only once it commits.
     * Bumping earlier would let another request load the old rows and cache
     * them under the new version. A rollback leaves the cache alone.
     */
    public function flush(): void
    {
        $this->forgetMemo();

        if ((int) config('rbac.cache.ttl', 0) > 0) {
            $this->db()->afterCommit(function (): void {
                $this->forgetMemo();
                $this->store()->forever('rbac.version', Str::random(12));
            });
        }
    }

    /**
     * May this actor change the grants of this role? Same rule as canAssign():
     * only roles strictly below the actor's own rank, so nobody edits their own
     * role or one above it.
     */
    public function canEditRole(Model $actor, Role $role): bool
    {
        return $this->canAssign($actor, $role);
    }

    /* ------------------------------------------------------------------
     | Internals
     | ------------------------------------------------------------------ */

    /**
     * @return list<Grant>
     */
    private function matching(Model $user, string $permission): array
    {
        // Pattern matching is memoised; expiry is checked on every call, as time moves on.
        $grants = $this->matched[$this->memoKey($user)][$permission] ??= array_values(array_filter(
            $this->grantsFor($user),
            fn (Grant $grant) => PermissionKey::matches($grant->permission, $permission),
        ));

        $now = now()->getTimestamp();

        return array_values(array_filter($grants, fn (Grant $grant) => $grant->isActive($now)));
    }

    /**
     * Does the scope reach every record? True for "global" and any scope marked Unrestricted.
     */
    private function isUnrestricted(string $scope): bool
    {
        return $this->scopes->get($scope) instanceof Unrestricted;
    }

    private function forgetMemo(): void
    {
        $this->memo = [];
        $this->matched = [];
    }

    /**
     * Does a grant reach this subject?
     *
     * Without a subject (a list page, a menu check) any allow counts, because the
     * list itself is then narrowed with constrain(). A deny without a subject only
     * counts when it is global, so an "own" deny never hides a whole page.
     */
    private function covers(Grant $grant, Model $user, ?Model $subject): bool
    {
        $scope = $this->scopes->get($grant->scope);

        if ($scope === null) {
            return false;
        }

        if ($subject === null) {
            return $grant->effect === Grant::ALLOW || $scope instanceof Unrestricted;
        }

        return $scope->covers($user, $subject);
    }

    /**
     * @return list<Grant>
     */
    private function grantsFor(Model $user): array
    {
        $key = $this->memoKey($user);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $ttl = (int) config('rbac.cache.ttl', 0);

        $grants = $ttl > 0
            ? $this->store()->remember($this->cacheKey($user), $ttl, fn () => $this->loadGrants($user))
            : $this->loadGrants($user);

        return $this->memo[$key] = $grants;
    }

    /**
     * @return list<Grant>
     */
    private function loadGrants(Model $user): array
    {
        $connection = $this->db();
        $morph = $user->getMorphClass();
        $id = (string) $user->getKey();

        $fromRoles = $this->whereActive($connection->table($this->table('role_assignments').' as a')
            ->join($this->table('roles').' as r', 'r.id', '=', 'a.role_id')
            ->join($this->table('role_grants').' as g', 'g.role_id', '=', 'a.role_id')
            ->where('a.model_type', $morph)
            ->where('a.model_id', $id)
            ->where('r.guard', $this->guardOf($user)), 'a.expires_at')
            ->get(['r.slug as role', 'g.permission', 'g.scope', 'a.expires_at']);

        $direct = $this->whereActive($connection->table($this->table('user_grants'))
            ->where('model_type', $morph)
            ->where('model_id', $id), 'expires_at')
            ->get(['permission', 'scope', 'effect', 'expires_at']);

        $grants = [];

        // The expiry travels with each grant, so a cached list still drops it on time.
        foreach ($fromRoles as $row) {
            $grants[] = new Grant($row->permission, $row->scope, Grant::ALLOW, 'role:'.$row->role, $this->timestamp($row->expires_at));
        }

        foreach ($direct as $row) {
            $grants[] = new Grant($row->permission, $row->scope, $row->effect, 'direct', $this->timestamp($row->expires_at));
        }

        return $grants;
    }

    private function timestamp(mixed $value): ?int
    {
        return $value === null ? null : Carbon::parse($value)->getTimestamp();
    }

    private function directGrant(Model $user, string $input, string $effect, ?DateTimeInterface $expiresAt = null): UserGrant
    {
        [$pattern, $scope] = $this->parseGrant($input);

        $grant = UserGrant::query()->updateOrCreate([
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getKey(),
            'permission' => $pattern,
            'scope' => $scope,
            'effect' => $effect,
        ], ['expires_at' => $expiresAt]);

        $this->flush();

        $this->record(
            $effect === Grant::DENY ? 'permission.denied' : 'permission.allowed',
            $user,
            ['permission' => $pattern, 'scope' => $scope, 'effect' => $effect, 'expires_at' => $expiresAt],
        );

        return $grant;
    }

    /**
     * @return array{0: string, 1: string} [pattern, scope]
     */
    private function parseGrant(string $input): array
    {
        [$pattern, $scope] = PermissionKey::parse($input, (string) config('rbac.default_scope', 'own'));

        $this->scopes->assertKnown($scope);
        $this->catalog->assertGrantable($pattern);

        return [$pattern, $scope];
    }

    /**
     * Keep rows that have no expiry or whose expiry is still in the future.
     */
    private function whereActive(QueryBuilder $query, string $column): QueryBuilder
    {
        return $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, '>', now()));
    }

    private function assertCanManage(?Model $actor, Role $role): void
    {
        if ($actor === null || $this->canAssign($actor, $role)) {
            return;
        }

        throw new RoleAssignmentDenied($role->slug, $this->rankOf($actor));
    }

    private function findRole(string $slug, ?string $guard = null): Role
    {
        $guard ??= $this->defaultGuard();

        return Role::query()->where('slug', $slug)->where('guard', $guard)->first()
            ?? throw new InvalidArgumentException("Role [{$slug}] does not exist for guard [{$guard}]. Create it with Rbac::role() or rbac:sync.");
    }

    /**
     * The guard a user model authenticates with. Models that define rbacGuard() (the HasRbac trait does) choose their own.
     */
    public function guardOf(Model $user): string
    {
        return method_exists($user, 'rbacGuard') ? (string) $user->rbacGuard() : $this->defaultGuard();
    }

    /**
     * The user's role assignments as a relation, with expires_at on the pivot.
     * Works for any user model; HasRbac::roles() returns the same relation.
     *
     * @return MorphToMany<Role, Model>
     */
    public function rolesOf(Model $user): MorphToMany
    {
        return $user->morphToMany(Role::class, 'model', $this->table('role_assignments'), 'model_id', 'role_id')
            ->withPivot('expires_at');
    }

    private function defaultGuard(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * A setting for a guard. The default guard reads the top-level key (roles, ranks);
     * other guards read rbac.guards.{guard}.{key}.
     *
     * @return array<string, mixed>
     */
    private function guardSetting(string $guard, string $key): array
    {
        return $guard === $this->defaultGuard()
            ? (array) config("rbac.{$key}", [])
            : (array) config("rbac.guards.{$guard}.{$key}", []);
    }

    private function assertKnown(string $permission): void
    {
        if (! $this->catalog->has($permission)) {
            throw new InvalidPermission("Permission [{$permission}] is not in the rbac.modules catalog.");
        }
    }

    private function asSubject(mixed $subject): ?Model
    {
        if ($subject === null || $subject instanceof Model) {
            return $subject;
        }

        throw new InvalidArgumentException('The subject of a permission check must be an Eloquent model or null.');
    }

    /**
     * Announce a change. The actor is the explicit one when given, otherwise the authenticated user.
     *
     * @param  array<string, mixed>  $details
     */
    private function record(string $action, ?Model $subject, array $details = [], ?Model $actor = null): void
    {
        event(new RbacChanged($action, $actor ?? $this->currentUser(), $subject, $details));
    }

    private function currentUser(): ?Model
    {
        // The default guard first, then every guard named in rbac.guards.
        foreach (array_unique([$this->defaultGuard(), ...array_keys((array) config('rbac.guards', []))]) as $guard) {
            $user = auth($guard)->user();

            if ($user instanceof Model) {
                return $user;
            }
        }

        return null;
    }

    private function memoKey(Model $user): string
    {
        return $user->getMorphClass().':'.$user->getKey();
    }

    private function cacheKey(Model $user): string
    {
        // "v2": Grant gained expiresAt in 1.8.0, so entries cached by older versions are not reused.
        return 'rbac.grants.v2.'.$this->cacheVersion().'.'.$this->memoKey($user);
    }

    private function cacheVersion(): string
    {
        return $this->store()->rememberForever('rbac.version', fn () => Str::random(12));
    }

    private function store(): CacheRepository
    {
        return $this->cache->store(config('rbac.cache.store'));
    }

    /**
     * The connection for every RBAC table: rbac.connection, or the default connection.
     */
    private function db(): Connection
    {
        return DB::connection(config('rbac.connection'));
    }

    private function table(string $key): string
    {
        return (string) config("rbac.tables.{$key}");
    }
}
