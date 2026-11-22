<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $role_id
 * @property string $permission
 * @property string $scope
 */
class RoleGrant extends Model
{
    // Not #[Fillable]: that attribute is Laravel 13 only, and Laravel 12 would silently ignore it.
    protected $fillable = ['role_id', 'permission', 'scope'];

    protected function casts(): array
    {
        return ['role_id' => 'integer'];
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
        return config('rbac.tables.role_grants', 'rbac_role_grants');
    }
}
