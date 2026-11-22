<?php

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Support\Columns;

/**
 * "tenant": records that share the user's tenant.
 */
final class TenantScope implements ExcludesRows, ScopeContract
{
    public function covers(Model $user, Model $subject): bool
    {
        $tenant = Columns::key($subject, 'tenant');
        $mine = Columns::key($user, 'tenant');

        return $tenant !== null && $mine !== null && (string) $tenant === (string) $mine;
    }

    public function constrain($query, Model $user, Model $model): void
    {
        $mine = Columns::key($user, 'tenant');

        if ($mine === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($model->qualifyColumn(Columns::name($model, 'tenant')), $mine);
    }

    public function exclude($query, Model $user, Model $model): void
    {
        $mine = Columns::key($user, 'tenant');

        // No tenant: the scope reaches nothing, so a deny removes nothing.
        if ($mine === null) {
            return;
        }

        $column = $model->qualifyColumn(Columns::name($model, 'tenant'));

        $query->whereNull($column)->orWhere($column, '!=', $mine);
    }
}
