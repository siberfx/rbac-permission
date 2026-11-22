<?php

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;

class RoleGrant extends Model
{
    protected $guarded = [];

    /**
     * rbac.connection, or the default connection.
     */
    public function getConnectionName(): ?string
    {
        return config('rbac.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('rbac.tables.role_grants', 'rbac_role_grants');
    }
}
