<?php

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A direct allow or deny on one user, on top of their roles. A deny always wins.
 */
class UserGrant extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('rbac.tables.user_grants', 'rbac_user_grants');
    }
}
