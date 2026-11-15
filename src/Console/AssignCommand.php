<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Rbac;

class AssignCommand extends Command
{
    protected $signature = 'rbac:assign
        {role : Role slug}
        {user : User id}
        {--model= : User model class (defaults to the auth users model)}
        {--revoke : Remove the role instead of assigning it}';

    protected $description = 'Assign or revoke a role for a user';

    public function handle(Rbac $rbac): int
    {
        $userClass = $this->option('model') ?: config('auth.providers.users.model');
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

            $rbac->assign($user, $this->argument('role'), $actor);
        } catch (RoleAssignmentDenied $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Assigned [{$this->argument('role')}] to user {$user->getKey()}.");

        return self::SUCCESS;
    }
}
