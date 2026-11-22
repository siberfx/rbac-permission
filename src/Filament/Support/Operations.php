<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Models\RoleGrant;
use Siberfx\RbacPermission\Rbac;

/**
 * The write operations behind the Filament screens. Each one checks that the
 * actor may do it, then calls the same service methods the rest of the package uses,
 * so the cache is flushed and the audit event is fired.
 */
final class Operations
{
    /**
     * Add a grant to a role. The role must be below the actor's rank, and the actor
     * must hold the permission at that scope or wider.
     *
     * @throws ValidationException
     */
    public static function grant(Model $actor, Role $role, string $permission, string $scope): RoleGrant
    {
        $input = "{$permission}@{$scope}";

        self::assertCanEditRole($actor, $role);
        self::assertCanGrant($actor, $input);

        try {
            return app(Rbac::class)->grant($role, $input);
        } catch (InvalidArgumentException|InvalidPermission $e) {
            throw ValidationException::withMessages(['data.permission' => $e->getMessage()]);
        }
    }

    /**
     * Remove a grant from a role, under the same rule as grant().
     */
    public static function revoke(Model $actor, Role $role, RoleGrant $grant): void
    {
        self::assertCanEditRole($actor, $role);
        self::assertCanGrant($actor, "{$grant->permission}@{$grant->scope}");

        app(Rbac::class)->revoke($role, "{$grant->permission}@{$grant->scope}");
    }

    /**
     * Assign a role to a user, held to the rank rules. $expiresAt is optional.
     * The actor needs admin.user.edit reaching this user (e.g. the same tenant).
     *
     * @throws ValidationException
     */
    public static function assignRole(Model $actor, Model $user, string $slug, ?Carbon $expiresAt = null): void
    {
        app(Rbac::class)->enforce('admin.user.edit', $user, $actor);

        try {
            app(Rbac::class)->assign($user, $slug, $actor, $expiresAt);
        } catch (RoleAssignmentDenied|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['data.role' => $e->getMessage()]);
        }
    }

    /**
     * Remove a role from a user, held to the rank rules and admin.user.edit like assignRole().
     */
    public static function unassignRole(Model $actor, Model $user, string $slug): void
    {
        app(Rbac::class)->enforce('admin.user.edit', $user, $actor);

        try {
            app(Rbac::class)->unassign($user, $slug, $actor);
        } catch (RoleAssignmentDenied|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['data.role' => $e->getMessage()]);
        }
    }

    /**
     * Change a role's rank. The actor may not set a rank at or above their own.
     */
    public static function setRank(Model $actor, Role $role, int $rank): void
    {
        $actorRank = app(Rbac::class)->rankOf($actor);

        if ($actorRank === null || $rank >= $actorRank) {
            throw ValidationException::withMessages([
                'data.rank' => "A rank must be below your own rank [{$actorRank}].",
            ]);
        }

        $role->update(['rank' => $rank]);
        app(Rbac::class)->flush();
    }

    private static function assertCanEditRole(Model $actor, Role $role): void
    {
        if (! app(Rbac::class)->canEditRole($actor, $role)) {
            throw new PermissionDenied('admin.role.edit', 'none', "You may not change [{$role->slug}]: it is not below your rank.");
        }
    }

    private static function assertCanGrant(Model $actor, string $input): void
    {
        if (! app(Rbac::class)->canGrant($actor, $input)) {
            throw new PermissionDenied($input, 'none', "You may not grant [{$input}]: you do not hold it at that scope.");
        }
    }
}
