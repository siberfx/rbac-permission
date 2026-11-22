<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Optional for a scope: how to remove its rows from a query when it is denied.
 *
 * Without it, a deny is applied as NOT (constrain()). In SQL that also drops rows
 * whose scope column is NULL, although covers() would not deny them. exclude() must
 * keep every row that covers() would not reach, NULL columns included.
 */
interface ExcludesRows
{
    /**
     * @param  Builder<covariant Model>|QueryBuilder  $query
     */
    public function exclude($query, Model $user, Model $model): void;
}
