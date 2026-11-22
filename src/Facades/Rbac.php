<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Facades;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Facade;
use Siberfx\RbacPermission\Enums\Decision;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Models\RoleGrant;
use Siberfx\RbacPermission\Models\UserGrant;
use Siberfx\RbacPermission\Rbac as RbacService;
use Siberfx\RbacPermission\Scopes\ScopeRegistry;
use Siberfx\RbacPermission\Support\Catalog;

/**
 * @method static bool allows(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static bool denies(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static void enforce(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static Decision decision(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static string decide(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static array<string, string> permissionsOf(?Model $user = null)
 * @method static array<string, mixed> explain(string $permission, mixed $subject = null, ?Model $user = null)
 * @method static ?bool gate(string $ability, mixed $user, array<array-key, mixed> $arguments = [])
 * @method static ?string widestScope(string $permission, ?Model $user = null)
 * @method static bool canGrant(Model $actor, string $input)
 * @method static bool canAssign(Model $actor, Role|string $role)
 * @method static bool canEditRole(Model $actor, Role $role)
 * @method static ?int rankOf(Model $user)
 * @method static Builder<Model> constrain(Builder<Model> $query, string $permission, ?Model $user = null)
 * @method static Role role(string $slug, ?string $name = null, ?string $description = null, ?int $rank = null, ?string $guard = null)
 * @method static RoleGrant grant(Role|string $role, string $permission, ?string $guard = null)
 * @method static int revoke(Role|string $role, string $permission, ?string $guard = null)
 * @method static void assign(Model $user, Role|string $role, ?Model $by = null, ?DateTimeInterface $expiresAt = null)
 * @method static int unassign(Model $user, Role|string $role, ?Model $by = null)
 * @method static UserGrant allow(Model $user, string $permission, ?DateTimeInterface $expiresAt = null)
 * @method static UserGrant deny(Model $user, string $permission, ?DateTimeInterface $expiresAt = null)
 * @method static int withdraw(Model $user, string $permission)
 * @method static int pruneExpired()
 * @method static array<string, int> sync(array<string, list<string>> $definitions, bool $prune = false, ?string $guard = null)
 * @method static array{roles: list<string>, rows: array<string, array<string, ?string>>} matrix(?string $guard = null)
 * @method static ?Model consoleActor()
 * @method static string userModel(?string $guard = null)
 * @method static string guardOf(Model $user)
 * @method static MorphToMany<Role, Model> rolesOf(Model $user)
 * @method static Catalog catalog()
 * @method static ScopeRegistry scopes()
 * @method static void flush()
 *
 * @see RbacService
 */
class Rbac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RbacService::class;
    }
}
