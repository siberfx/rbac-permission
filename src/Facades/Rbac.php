<?php

namespace Siberfx\RbacPermission\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\RbacPermission\Rbac as RbacService;

/**
 * @method static bool allows(string $permission, mixed $subject = null, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static bool denies(string $permission, mixed $subject = null, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static void enforce(string $permission, mixed $subject = null, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static string decide(string $permission, mixed $subject = null, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static array explain(string $permission, mixed $subject = null, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static ?string widestScope(string $permission, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder constrain(\Illuminate\Database\Eloquent\Builder $query, string $permission, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static \Siberfx\RbacPermission\Models\Role role(string $slug, ?string $name = null, ?string $description = null)
 * @method static \Siberfx\RbacPermission\Models\RoleGrant grant(\Siberfx\RbacPermission\Models\Role|string $role, string $permission)
 * @method static int revoke(\Siberfx\RbacPermission\Models\Role|string $role, string $permission)
 * @method static void assign(\Illuminate\Database\Eloquent\Model $user, \Siberfx\RbacPermission\Models\Role|string $role)
 * @method static int unassign(\Illuminate\Database\Eloquent\Model $user, \Siberfx\RbacPermission\Models\Role|string $role)
 * @method static \Siberfx\RbacPermission\Models\UserGrant allow(\Illuminate\Database\Eloquent\Model $user, string $permission)
 * @method static \Siberfx\RbacPermission\Models\UserGrant deny(\Illuminate\Database\Eloquent\Model $user, string $permission)
 * @method static int withdraw(\Illuminate\Database\Eloquent\Model $user, string $permission)
 * @method static array sync(array $definitions, bool $prune = false)
 * @method static array matrix()
 * @method static ?\Illuminate\Database\Eloquent\Model consoleActor()
 * @method static void flush()
 *
 * @see \Siberfx\RbacPermission\Rbac
 */
class Rbac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RbacService::class;
    }
}
