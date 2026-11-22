<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $guard
 * @property string $name
 * @property ?string $description
 * @property int $rank
 */
class Role extends Model
{
    protected $fillable = ['slug', 'guard', 'name', 'description', 'rank'];

    protected function casts(): array
    {
        return ['rank' => 'integer'];
    }

    /**
     * rbac.connection, or the default connection.
     */
    public function getConnectionName(): ?string
    {
        return config('rbac.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('rbac.tables.roles', 'rbac_roles');
    }

    /**
     * @return HasMany<RoleGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(RoleGrant::class, 'role_id');
    }
}
