<?php

namespace Siberfx\RbacPermission\Backpack;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Facades\Rbac;

/**
 * Connects a Backpack CRUD panel to the catalog. Each Backpack operation maps to a
 * verb, so "{resource}.{verb}" decides it. Lists are narrowed with Rbac::constrain(),
 * and single records are checked against their scope.
 */
final class Access
{
    /** Backpack operation => catalog verb. */
    public const VERBS = [
        'list' => 'show',
        'show' => 'show',
        'create' => 'create',
        'store' => 'create',
        'update' => 'edit',
        'edit' => 'edit',
        'reorder' => 'edit',
        'delete' => 'delete',
    ];

    /**
     * Apply the rules to every mapped operation of the panel. Any access condition
     * already set on an operation still has to pass.
     */
    public static function apply(CrudPanel $crud, string $resource, ?Model $user): void
    {
        $crud->addClause(function (Builder $query) use ($resource, $user): void {
            if ($user === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            Rbac::constrain($query, "{$resource}.show", $user);
        });

        foreach (self::VERBS as $operation => $verb) {
            $existing = $crud->getAccessCondition($operation);

            $crud->setAccessCondition($operation, function ($entry = null) use ($existing, $resource, $verb, $user): bool {
                if ($user === null || ! self::passes($existing, $entry)) {
                    return false;
                }

                return Rbac::allows("{$resource}.{$verb}", $entry instanceof Model ? $entry : null, $user);
            });
        }
    }

    private static function passes(mixed $condition, mixed $entry): bool
    {
        if ($condition === null) {
            return true;
        }

        return is_callable($condition) ? (bool) $condition($entry) : (bool) $condition;
    }
}
