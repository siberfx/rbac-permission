<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A direct allow or deny on one user, on top of their roles. A deny always wins.
 *
 * @property int $id
 * @property string $model_type
 * @property string $model_id
 * @property string $permission
 * @property string $scope
 * @property string $effect
 * @property ?Carbon $expires_at
 */
class UserGrant extends Model
{
    protected $fillable = ['model_type', 'model_id', 'permission', 'scope', 'effect', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
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
        return config('rbac.tables.user_grants', 'rbac_user_grants');
    }
}
