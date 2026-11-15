<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

class SyncCommand extends Command
{
    protected $signature = 'rbac:sync
        {--prune : Delete roles that are no longer defined in config/rbac.php}
        {--guard= : Sync only this guard (defaults to every guard)}';

    protected $description = 'Write the roles defined in config/rbac.php to the database, for every guard';

    public function handle(Rbac $rbac): int
    {
        $defaultGuard = (string) config('auth.defaults.guard', 'web');
        $guards = $this->option('guard')
            ? [(string) $this->option('guard')]
            : array_values(array_unique([$defaultGuard, ...array_keys((array) config('rbac.guards', []))]));

        foreach ($guards as $guard) {
            $definitions = $guard === $defaultGuard
                ? config('rbac.roles', [])
                : config("rbac.guards.{$guard}.roles", []);

            $stats = $rbac->sync($definitions, (bool) $this->option('prune'), $guard);

            $this->info("[{$guard}] Synced {$stats['roles']} role(s) with {$stats['grants']} grant(s).");

            if ($this->option('prune')) {
                $this->line("[{$guard}] Pruned {$stats['pruned']} role(s).");
            }
        }

        return self::SUCCESS;
    }
}
