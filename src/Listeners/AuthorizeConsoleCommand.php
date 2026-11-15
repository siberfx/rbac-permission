<?php

namespace Siberfx\RbacPermission\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Siberfx\RbacPermission\Attributes\RequiresPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Rbac;

/**
 * Enforces #[RequiresPermission] on console commands before they run.
 * The command runs as the user from config('rbac.console').
 */
final class AuthorizeConsoleCommand
{
    public function __construct(private readonly Rbac $rbac) {}

    public function handle(CommandStarting $event): void
    {
        $command = Artisan::all()[$event->command] ?? null;

        if ($command === null) {
            return;
        }

        $required = [];

        foreach ((new ReflectionClass($command))->getAttributes(RequiresPermission::class) as $attribute) {
            $required[] = $attribute->newInstance();
        }

        if ($required === []) {
            return;
        }

        $actor = $this->rbac->consoleActor();

        if ($actor === null) {
            if (config('rbac.console.allow_unattended')) {
                return;
            }

            throw new PermissionDenied($required[0]->permission, 'none', "Command [{$event->command}] needs a console actor. Set RBAC_CONSOLE_USER_ID.");
        }

        foreach ($required as $item) {
            $this->rbac->enforce($item->permission, null, $actor);
        }
    }
}
