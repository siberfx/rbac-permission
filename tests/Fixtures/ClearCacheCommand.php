<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Attributes\RequiresPermission;

#[RequiresPermission('ops.cache.run')]
class ClearCacheCommand extends Command
{
    protected $signature = 'ops:cache-clear';

    protected $description = 'Clear the application cache (test fixture)';

    public function handle(): int
    {
        $this->info('cache cleared');

        return self::SUCCESS;
    }
}
