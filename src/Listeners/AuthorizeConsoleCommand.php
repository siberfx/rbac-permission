<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Siberfx\RbacPermission\Attributes\RequiresPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Rbac;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * Enforces #[RequiresPermission] on console commands before they run.
 * The command runs as the user from config('rbac.console').
 */
final class AuthorizeConsoleCommand
{
    /** Container key of the running Artisan application, set by the service provider. */
    public const ARTISAN = 'rbac.artisan';

    public function __construct(private readonly Rbac $rbac) {}

    public function handle(CommandStarting $event): void
    {
        $command = $this->command((string) $event->command);

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

    /**
     * Loads only the command being run. Artisan::all() would build every
     * registered command on each run, so it is only the fallback.
     */
    private function command(string $name): ?Command
    {
        if (! app()->bound(self::ARTISAN)) {
            return Artisan::all()[$name] ?? null;
        }

        $artisan = app(self::ARTISAN);

        if ($name === '' || ! $artisan->has($name)) {
            return null;
        }

        $command = $artisan->get($name);

        return $command instanceof LazyCommand ? $command->getCommand() : $command;
    }
}
