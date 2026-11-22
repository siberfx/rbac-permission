<?php

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A direct allow or deny on one user, on top of their roles. A deny always wins.
 */
class UserGrant extends Model
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
        return config('rbac.tables.user_grants', 'rbac_user_grants');
    }
}
