<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Support\Columns;

/**
 * "own": records whose owner column is the user.
 */
final class OwnScope implements ExcludesRows, ScopeContract
{
    public function covers(Model $user, Model $subject): bool
    {
        $owner = Columns::key($subject, 'owner');

        return $owner !== null && (string) $owner === (string) $user->getKey();
    }

    public function constrain($query, Model $user, Model $model): void
    {
        $query->where($model->qualifyColumn(Columns::name($model, 'owner')), $user->getKey());
    }

    public function exclude($query, Model $user, Model $model): void
    {
        $column = $model->qualifyColumn(Columns::name($model, 'owner'));

        $query->whereNull($column)->orWhere($column, '!=', $user->getKey());
    }
}
