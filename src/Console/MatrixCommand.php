<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

class MatrixCommand extends Command
{
    protected $signature = 'rbac:matrix {--only-granted : Hide permissions that no role holds}';

    protected $description = 'Print the role by permission matrix with the widest scope of each grant';

    public function handle(Rbac $rbac): int
    {
        $matrix = $rbac->matrix();

        if ($matrix['roles'] === []) {
            $this->warn('No roles found. Run php artisan rbac:sync first.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($matrix['rows'] as $permission => $cells) {
            $values = array_values($cells);

            if ($this->option('only-granted') && array_filter($values) === []) {
                continue;
            }

            $rows[] = [$permission, ...array_map(fn (?string $scope) => $scope ?? '-', $values)];
        }

        $this->table(['Permission', ...$matrix['roles']], $rows);

        return self::SUCCESS;
    }
}
