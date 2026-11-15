<?php

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class Role extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('rbac.tables.roles', 'rbac_roles');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(RoleGrant::class, 'role_id');
    }
}
