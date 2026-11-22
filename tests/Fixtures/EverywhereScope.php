<?php

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Scopes\ScopeContract;
use Siberfx\RbacPermission\Scopes\Unrestricted;

/**
 * A custom top scope under another name than "global".
 */
final class EverywhereScope implements ScopeContract, Unrestricted
{
    public function covers(Model $user, Model $subject): bool
    {
        return true;
    }

    public function constrain($query, Model $user, Model $model): void {}
}
