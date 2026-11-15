<?php

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Siberfx\RbacPermission\Backpack\HasBackpackRbac;

/**
 * A Backpack admin user. Its roles live in the Backpack guard.
 */
class BackpackUser extends Authenticatable
{
    use HasBackpackRbac;

    protected $table = 'admins';

    protected $guarded = [];
}
