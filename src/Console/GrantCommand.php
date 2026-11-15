<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

class GrantCommand extends Command
{
    protected $signature = 'rbac:grant
        {role : Role slug (created if missing)}
        {permission : Grant in the form module.resource.verb@scope, wildcards allowed}
        {--revoke : Remove the grant instead of adding it}
        {--guard= : Guard the role belongs to (defaults to the default guard)}';

    protected $description = 'Add or remove a permission grant on a role';

    public function handle(Rbac $rbac): int
    {
        $guard = $this->option('guard') ?: null;

        if ($this->option('revoke')) {
            $removed = $rbac->revoke($this->argument('role'), $this->argument('permission'), $guard);
            $this->info("Removed {$removed} grant(s).");

            return self::SUCCESS;
        }

        $grant = $rbac->grant($this->argument('role'), $this->argument('permission'), $guard);
        $this->info("Granted [{$grant->permission}@{$grant->scope}] to role [{$this->argument('role')}].");

        return self::SUCCESS;
    }
}
