<?php

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Rbac;

class ExplainCommand extends Command
{
    protected $signature = 'rbac:explain
        {permission : Permission key, e.g. billing.invoice.edit}
        {user : User id}
        {--model= : User model class (defaults to the auth users model)}
        {--on-model= : Subject model class, e.g. App\\Models\\Invoice}
        {--on= : Subject id}';

    protected $description = 'Explain why a user is allowed or denied a permission. Exit code 0 means allowed.';

    public function handle(Rbac $rbac): int
    {
        $userClass = $this->option('model') ?: config('auth.providers.users.model');
        $user = $userClass::query()->find($this->argument('user'));

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $subject = null;
        $subjectClass = $this->option('on-model');

        if ($subjectClass && $this->option('on') !== null) {
            $subject = $subjectClass::query()->find($this->option('on'));

            if ($subject === null) {
                $this->error('Subject not found.');

                return self::FAILURE;
            }
        }

        $result = $rbac->explain($this->argument('permission'), $subject, $user);

        $this->line('Decision: <options=bold>'.strtoupper($result['decision']).'</>');
        $this->line("User: {$result['user']}");
        $this->line('Subject: '.($result['subject'] ?? 'none (any record)'));
        $this->line('Roles: '.($result['roles'] !== [] ? implode(', ', $result['roles']) : 'none'));

        if ($result['grants'] !== []) {
            $this->table(
                ['Grant', 'Source', 'Covers subject'],
                array_map(fn (array $row) => [$row['grant'], $row['source'], $row['covers'] ? 'yes' : 'no'], $result['grants']),
            );
        } else {
            $this->warn('No grant matches this permission.');
        }

        return $result['decision'] === Rbac::ALLOW ? self::SUCCESS : self::FAILURE;
    }
}
