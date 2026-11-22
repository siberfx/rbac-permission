<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Console;

use Illuminate\Console\Command;
use Siberfx\RbacPermission\Console\Concerns\FindsModels;
use Siberfx\RbacPermission\Rbac;

class ExplainCommand extends Command
{
    use FindsModels;

    protected $signature = 'rbac:explain
        {permission : Permission key, e.g. billing.invoice.edit}
        {user : User id}
        {--model= : User model class (defaults to the model of the guard)}
        {--guard= : Guard whose user model to use (defaults to the default guard)}
        {--on-model= : Subject model class, e.g. App\\Models\\Invoice}
        {--on= : Subject id}';

    protected $description = 'Explain why a user is allowed or denied a permission. Exit code 0 means allowed.';

    public function handle(Rbac $rbac): int
    {
        $userClass = $this->option('model') ?: $rbac->userModel($this->option('guard') ?: null);
        $user = $this->findModel($userClass, $this->argument('user'), 'User');

        if ($user === null) {
            return self::FAILURE;
        }

        $subject = null;
        $subjectClass = $this->option('on-model');

        if ($subjectClass && $this->option('on') !== null) {
            $subject = $this->findModel($subjectClass, $this->option('on'), 'Subject');

            if ($subject === null) {
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
