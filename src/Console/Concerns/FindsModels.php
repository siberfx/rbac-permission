<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Console\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Finds a record from a class name given on the command line, and prints why when it cannot.
 */
trait FindsModels
{
    protected function findModel(mixed $class, mixed $id, string $label): ?Model
    {
        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            $this->error("[{$this->stringify($class)}] is not an Eloquent model class.");

            return null;
        }

        $record = $class::query()->find($id);

        if (! $record instanceof Model) {
            $this->error("{$label} not found.");

            return null;
        }

        return $record;
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
