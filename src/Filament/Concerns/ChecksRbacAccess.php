<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Facades\Rbac;

/**
 * Permission checks for the signed-in Filament user, using the catalog permissions.
 */
trait ChecksRbacAccess
{
    public static function rbacActor(): ?Model
    {
        $user = Filament::auth()->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * The signed-in user, or a 403 when nobody is signed in.
     */
    public static function requireRbacActor(): Model
    {
        return static::rbacActor() ?? throw new PermissionDenied('', 'none', 'Sign in to manage access.');
    }

    public static function rbacAllows(string $permission, mixed $subject = null): bool
    {
        $actor = static::rbacActor();

        return $actor !== null && Rbac::allows($permission, $subject, $actor);
    }
}
