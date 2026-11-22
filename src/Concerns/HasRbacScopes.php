<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Concerns;

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
}
