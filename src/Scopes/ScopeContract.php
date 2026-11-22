<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A scope answers two questions for a grant:
 *  - covers():    may this user act on this one record?
 *  - constrain(): which rows of a query does this user reach? (lists, exports)
 *
 * constrain() must add at least one WHERE clause, unless the scope is global.
 */
interface ScopeContract
{
    public function covers(Model $user, Model $subject): bool;

    /**
     * @param  Builder<Model>|QueryBuilder  $query
     */
    public function constrain($query, Model $user, Model $model): void;
}
