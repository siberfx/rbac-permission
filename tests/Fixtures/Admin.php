<?php

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Siberfx\RbacPermission\Concerns\HasRbac;

/**
 * A second user model that authenticates through the "admin" guard.
 */
class Admin extends Authenticatable
{
    use HasRbac;

    protected $table = 'admins';

    protected $guarded = [];

    public function rbacGuard(): string
    {
        return 'admin';
    }
}
