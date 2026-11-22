<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Rbac;

class AssignCommand extends Command
{
    protected $signature = 'rbac:assign
        {role : Role slug}
        {user : User id}
        {--model= : User model class (defaults to the model of the guard)}
        {--guard= : Guard whose user model to use (defaults to the default guard)}
        {--revoke : Remove the role instead of assigning it}
        {--expires= : When the role stops applying, e.g. "+30 days" or "2027-01-31"}';

    protected $description = 'Assign or revoke a role for a user';

    public function handle(Rbac $rbac): int
    {
        $userClass = $this->option('model') ?: $rbac->userModel($this->option('guard') ?: null);
        $user = $userClass::query()->find($this->argument('user'));

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        // The configured console actor (if any) is held to the role hierarchy.
        $actor = $rbac->consoleActor();

        try {
            if ($this->option('revoke')) {
                $rbac->unassign($user, $this->argument('role'), $actor);
                $this->info("Revoked [{$this->argument('role')}] from user {$user->getKey()}.");

                return self::SUCCESS;
            }

            $expires = $this->option('expires') ? Carbon::parse($this->option('expires')) : null;

            $rbac->assign($user, $this->argument('role'), $actor, $expires);
        } catch (RoleAssignmentDenied $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Assigned [{$this->argument('role')}] to user {$user->getKey()}.");

        return self::SUCCESS;
    }
}
