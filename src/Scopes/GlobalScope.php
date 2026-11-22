<?php

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Model;

/**
 * "global": every record. Adds no constraint to queries.
 */
final class GlobalScope implements ScopeContract, Unrestricted
{
    public function covers(Model $user, Model $subject): bool
    {
        return true;
    }

    public function constrain($query, Model $user, Model $model): void
    {
        // Intentionally empty: global reach means no row filter.
    }
}
