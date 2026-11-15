<?php

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;

class RoleGrant extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('rbac.tables.role_grants', 'rbac_role_grants');
    }
}
