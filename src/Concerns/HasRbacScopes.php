<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Rbac;

/**
 * Add to a resource model (invoice, project, ...) that the scopes apply to.
 * Override any column name when yours differs from config('rbac.columns').
 */
trait HasRbacScopes
{
    public function rbacOwnerColumn(): string
    {
        return (string) config('rbac.columns.owner', 'user_id');
    }

    public function rbacTeamColumn(): string
    {
        return (string) config('rbac.columns.team', 'team_id');
    }

    public function rbacTenantColumn(): string
    {
        return (string) config('rbac.columns.tenant', 'tenant_id');
    }

    /**
     * Only the rows the user may reach for this permission (the signed-in user when none is given).
     *   Invoice::query()->whereRbac('billing.invoice.show')->paginate();
     * The same as Rbac::constrain().
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereRbac(Builder $query, string $permission, ?Model $user = null): void
    {
        app(Rbac::class)->constrain($query, $permission, $user);
    }
}
