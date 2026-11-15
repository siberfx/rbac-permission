<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Tests\Fixtures\ClearCacheCommand;

beforeEach(function () {
    // Laravel only forwards Symfony command events in a real console run, not in
    // unit tests. Reroute them so CommandStarting fires exactly as in production.
    $kernel = $this->app->make(Kernel::class);
    $kernel->rerouteSymfonyCommandEvents();
    $kernel->registerCommand(new ClearCacheCommand);
});

/**
 * Run through Kernel::handle(), the same entry point as the real `php artisan`,
 * which renders the exception and returns its exit code.
 */
function runConsole(string $command): array
{
    $output = new BufferedOutput;
    $exit = app(Kernel::class)->handle(new StringInput($command), $output);

    return [$exit, $output->fetch()];
}

test('a guarded command is refused when no console actor is configured', function () {
    config(['rbac.console.user_id' => null, 'rbac.console.allow_unattended' => false]);

    // Testbench rethrows console exceptions; a real `php artisan` renders them and exits 1.
    expect(fn () => runConsole('ops:cache-clear'))
        ->toThrow(PermissionDenied::class, 'needs a console actor');
});

test('a guarded command runs when the console actor holds the permission', function () {
    $actor = $this->makeUser(['name' => 'Ops'], [10]);
    Rbac::role('ops');
    Rbac::grant('ops', 'ops.cache.run@global');
    $actor->assignRole('ops');

    config(['rbac.console.user_id' => $actor->id]);

    $this->artisan('ops:cache-clear')
        ->expectsOutput('cache cleared')
        ->assertExitCode(0);
});

test('a guarded command is refused when the actor lacks the permission', function () {
    $actor = $this->makeUser(['name' => 'Intern'], [10]);
    config(['rbac.console.user_id' => $actor->id]);

    expect(fn () => runConsole('ops:cache-clear'))
        ->toThrow(PermissionDenied::class, 'Missing permission [ops.cache.run].');
});

test('unattended runs are allowed only when explicitly enabled', function () {
    config(['rbac.console.user_id' => null, 'rbac.console.allow_unattended' => true]);

    $this->artisan('ops:cache-clear')
        ->expectsOutput('cache cleared')
        ->assertExitCode(0);
});
