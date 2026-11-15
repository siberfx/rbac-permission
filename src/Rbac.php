<?php

namespace Siberfx\RbacPermission;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\Eloquent\Builder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Models\RoleGrant;
use Siberfx\RbacPermission\Models\UserGrant;
use Siberfx\RbacPermission\Scopes\ScopeRegistry;
use Siberfx\RbacPermission\Support\Catalog;
use Siberfx\RbacPermission\Support\Grant;
use Siberfx\RbacPermission\Support\PermissionKey;

final class Rbac
{
    public const ALLOW = 'allow';

    public const DENY = 'deny';

    public const NONE = 'none';

    /** @var array<string, list<Grant>> */
    private array $memo = [];

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
                'covers' => $user ? $this->covers($grant, $user, $subject) : false,
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
            if ($grant->effect === Grant::DENY && $grant->scope === 'global') {
                return $query->whereRaw('1 = 0');
            }
        }

        $allows = array_values(array_filter($grants, fn (Grant $grant) => $grant->effect === Grant::ALLOW));

        if ($allows === []) {
            return $query->whereRaw('1 = 0');
        }

        $hasGlobal = array_any($allows, fn (Grant $grant) => $grant->scope === 'global');

        if (! $hasGlobal) {
            $query->where(function ($q) use ($allows, $user, $model) {
                foreach ($allows as $grant) {
                    $q->orWhere(fn ($inner) => $this->scopes->get($grant->scope)->constrain($inner, $user, $model));
                }
            });
        }

        foreach ($grants as $grant) {
            if ($grant->effect === Grant::DENY) {
                $query->whereNot(fn ($q) => $this->scopes->get($grant->scope)->constrain($q, $user, $model));
            }
        }

        return $query;
    }

    /* ------------------------------------------------------------------
     | Management: roles, role grants, assignments, direct overrides
     | ------------------------------------------------------------------ */

    public function role(string $slug, ?string $name = null, ?string $description = null, ?int $rank = null): Role
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            throw new InvalidArgumentException("Invalid role slug [{$slug}].");
        }

        return Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name ?? Str::headline($slug), 'description' => $description, 'rank' => $rank ?? 0],
        );
    }

    /**
     * The highest rank among the roles the user holds, or null when they hold none.
     */
    public function rankOf(Model $user): ?int
    {
        $rank = $this->whereActive($user->getConnection()->table($this->table('role_assignments').' as a')
            ->join($this->table('roles').' as r', 'r.id', '=', 'a.role_id')
            ->where('a.model_type', $user->getMorphClass())
            ->where('a.model_id', (string) $user->getKey()), 'a.expires_at')
            ->max('r.rank');

        return $rank === null ? null : (int) $rank;
    }

    /**
     * May this actor assign or remove the role? Only roles strictly below their own rank.
     */
    public function canAssign(Model $actor, Role|string $role): bool
    {
        $role = $role instanceof Role ? $role : $this->findRole($role);
        $rank = $this->rankOf($actor);

        return $rank !== null && $rank > (int) $role->rank;
    }

    /**
     * Add a grant to a role. Creates the role if it does not exist yet.
     */
    public function grant(Role|string $role, string $permission): RoleGrant
    {
        [$pattern, $scope] = $this->parseGrant($permission);
        $role = $role instanceof Role ? $role : $this->role($role);

        $grant = RoleGrant::query()->firstOrCreate([
            'role_id' => $role->getKey(),
            'permission' => $pattern,
            'scope' => $scope,
        ]);

        $this->flush();

        return $grant;
    }

    public function revoke(Role|string $role, string $permission): int
    {
        [$pattern, $scope] = $this->parseGrant($permission);
        $role = $role instanceof Role ? $role : $this->findRole($role);

        $deleted = RoleGrant::query()
            ->where('role_id', $role->getKey())
            ->where('permission', $pattern)
            ->where('scope', $scope)
            ->delete();

        $this->flush();

        return $deleted;
    }

    /**
     * Give the user a role. When $by is given (the actor making the change), the
     * role must sit strictly below the actor's rank. Without $by nothing is checked,
     * which is how seeders and trusted commands run.
     */
    public function assign(Model $user, Role|string $role, ?Model $by = null, ?DateTimeInterface $expiresAt = null): void
    {
        $role = $role instanceof Role ? $role : $this->findRole($role);
        $this->assertCanManage($by, $role);

        // Re-assigning replaces the expiry: no date makes the role permanent again.
        $user->getConnection()->table($this->table('role_assignments'))->updateOrInsert(
            ['role_id' => $role->getKey(), 'model_type' => $user->getMorphClass(), 'model_id' => (string) $user->getKey()],
            ['expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->flush();
    }

    public function unassign(Model $user, Role|string $role, ?Model $by = null): int
    {
        $role = $role instanceof Role ? $role : $this->findRole($role);
        $this->assertCanManage($by, $role);

        $deleted = $user->getConnection()->table($this->table('role_assignments'))
            ->where('role_id', $role->getKey())
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getKey())
            ->delete();

        $this->flush();

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
        $connection = DB::connection();

        $assignments = $connection->table($this->table('role_assignments'))
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        $grants = $connection->table($this->table('user_grants'))
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        $this->flush();

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

        return $deleted;
    }

    /**
     * Make the database match config('rbac.roles'). Roles are written in one
     * transaction, after every grant has been validated.
     *
     * @param  array<string, list<string>>  $definitions
     * @return array{roles: int, grants: int, pruned: int}
     */
    public function sync(array $definitions, bool $prune = false): array
    {
        $parsed = [];

        foreach ($definitions as $slug => $specs) {
            $parsed[(string) $slug] = array_map(fn ($spec) => $this->parseGrant((string) $spec), (array) $specs);
        }

        return DB::transaction(function () use ($parsed, $prune) {
            $stats = ['roles' => 0, 'grants' => 0, 'pruned' => 0];

            $ranks = (array) config('rbac.ranks', []);

            foreach ($parsed as $slug => $grants) {
                $role = $this->role($slug);

                if (array_key_exists($slug, $ranks)) {
                    $role->update(['rank' => (int) $ranks[$slug]]);
                }

                RoleGrant::query()->where('role_id', $role->getKey())->delete();

                foreach ($grants as [$pattern, $scope]) {
                    RoleGrant::query()->create([
                        'role_id' => $role->getKey(),
                        'permission' => $pattern,
                        'scope' => $scope,
                    ]);
                    $stats['grants']++;
                }

                $stats['roles']++;
            }

            if ($prune) {
                foreach (Role::query()->whereNotIn('slug', array_keys($parsed))->get() as $role) {
                    RoleGrant::query()->where('role_id', $role->getKey())->delete();
                    DB::table($this->table('role_assignments'))->where('role_id', $role->getKey())->delete();
                    $role->delete();
                    $stats['pruned']++;
                }
            }

            $this->flush();

            return $stats;
        });
    }

    /**
     * Role x permission grid for the matrix command. Each cell is the widest scope, or null.
     *
     * @return array{roles: list<string>, rows: array<string, array<string, ?string>>}
     */
    public function matrix(): array
    {
        $roles = Role::query()->with('grants')->orderBy('slug')->get();
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

        $class = config('auth.providers.users.model');

        return $class::query()->find($id);
    }

    /**
     * Forget memoised grants. Called automatically by every write method.
     */
    public function flush(): void
    {
        $this->memo = [];

        if ((int) config('rbac.cache.ttl', 0) > 0) {
            $this->store()->forever('rbac.version', Str::random(12));
        }
    }

    /* ------------------------------------------------------------------
     | Internals
     | ------------------------------------------------------------------ */

    /**
     * @return list<Grant>
     */
    private function matching(Model $user, string $permission): array
    {
        return array_values(array_filter(
            $this->grantsFor($user),
            fn (Grant $grant) => PermissionKey::matches($grant->permission, $permission),
        ));
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
            return $grant->effect === Grant::ALLOW || $grant->scope === 'global';
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
        $connection = $user->getConnection();
        $morph = $user->getMorphClass();
        $id = (string) $user->getKey();

        $fromRoles = $this->whereActive($connection->table($this->table('role_assignments').' as a')
            ->join($this->table('roles').' as r', 'r.id', '=', 'a.role_id')
            ->join($this->table('role_grants').' as g', 'g.role_id', '=', 'a.role_id')
            ->where('a.model_type', $morph)
            ->where('a.model_id', $id), 'a.expires_at')
            ->get(['r.slug as role', 'g.permission', 'g.scope']);

        $direct = $this->whereActive($connection->table($this->table('user_grants'))
            ->where('model_type', $morph)
            ->where('model_id', $id), 'expires_at')
            ->get(['permission', 'scope', 'effect']);

        $grants = [];

        foreach ($fromRoles as $row) {
            $grants[] = new Grant($row->permission, $row->scope, Grant::ALLOW, 'role:'.$row->role);
        }

        foreach ($direct as $row) {
            $grants[] = new Grant($row->permission, $row->scope, $row->effect, 'direct');
        }

        return $grants;
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
    private function whereActive(Builder|QueryBuilder $query, string $column): Builder|QueryBuilder
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

    private function findRole(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->first()
            ?? throw new InvalidArgumentException("Role [{$slug}] does not exist. Create it with Rbac::role() or rbac:sync.");
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

    private function currentUser(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }

    private function memoKey(Model $user): string
    {
        return $user->getMorphClass().':'.$user->getKey();
    }

    private function cacheKey(Model $user): string
    {
        return 'rbac.grants.'.$this->cacheVersion().'.'.$this->memoKey($user);
    }

    private function cacheVersion(): string
    {
        return $this->store()->rememberForever('rbac.version', fn () => Str::random(12));
    }

    private function store()
    {
        return $this->cache->store(config('rbac.cache.store'));
    }

    private function table(string $key): string
    {
        return (string) config("rbac.tables.{$key}");
    }
}
