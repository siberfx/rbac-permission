<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

class SyncCommand extends Command
{
    protected $signature = 'rbac:sync {--prune : Delete roles that are no longer defined in config/rbac.php}';

    protected $description = 'Write the roles defined in config/rbac.php to the database';

    public function handle(Rbac $rbac): int
    {
        $stats = $rbac->sync(config('rbac.roles', []), (bool) $this->option('prune'));

        $this->info("Synced {$stats['roles']} role(s) with {$stats['grants']} grant(s).");

        if ($this->option('prune')) {
            $this->line("Pruned {$stats['pruned']} role(s).");
        }

        return self::SUCCESS;
    }
}
