<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

/**
 * Expired rows already grant nothing; this only keeps the tables small.
 * Schedule it, e.g. Schedule::command('rbac:prune-expired')->daily();
 */
class PruneExpiredCommand extends Command
{
    protected $signature = 'rbac:prune-expired';

    protected $description = 'Delete role assignments and direct grants whose expiry has passed';

    public function handle(Rbac $rbac): int
    {
        $removed = $rbac->pruneExpired();

        $this->info("Removed {$removed} expired row(s).");

        return self::SUCCESS;
    }
}
