<?php

namespace Siberfx\RbacPermission\Backpack;

use Siberfx\RbacPermission\Concerns\HasRbac;

/**
 * For the Backpack admin user model. Its roles live in the Backpack guard
 * (config backpack.base.guard), so they stay separate from the app's users.
 *
 *   class BackpackUser extends Authenticatable
 *   {
 *       use HasBackpackRbac;
 *   }
 */
trait HasBackpackRbac
{
    use HasRbac;

    public function rbacGuard(): string
    {
        return (string) config('backpack.base.guard', config('auth.defaults.guard', 'web'));
    }
}
