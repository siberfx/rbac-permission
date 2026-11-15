<?php

namespace Siberfx\RbacPermission;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Siberfx\RbacPermission\Console\AssignCommand;
use Siberfx\RbacPermission\Console\ExplainCommand;
use Siberfx\RbacPermission\Console\GrantCommand;
use Siberfx\RbacPermission\Console\MatrixCommand;
use Siberfx\RbacPermission\Console\SyncCommand;
use Siberfx\RbacPermission\Http\Middleware\EnsurePermission;
use Siberfx\RbacPermission\Listeners\AuthorizeConsoleCommand;
use Siberfx\RbacPermission\Scopes\ScopeRegistry;
use Siberfx\RbacPermission\Support\Catalog;

class RbacPermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rbac.php', 'rbac');

        $this->app->singleton(Catalog::class, fn () => new Catalog(
            config('rbac.modules', []),
            config('rbac.verbs', []),
        ));

        $this->app->singleton(ScopeRegistry::class, fn () => new ScopeRegistry(config('rbac.scopes', [])));

        $this->app->singleton(Rbac::class, fn ($app) => new Rbac(
            $app->make(Catalog::class),
            $app->make(ScopeRegistry::class),
            $app['cache'],
        ));
    }

    public function boot(): void
    {
        $this->registerPublishing();

        $this->app['router']->aliasMiddleware('rbac', EnsurePermission::class);

        // A bare @elserbac compiles to an elseif with no arguments; treat that as "not allowed".
        Blade::if('rbac', fn (?string $permission = null, mixed $subject = null) => $permission !== null
            && $this->app->make(Rbac::class)->allows($permission, $subject));

        if (config('rbac.register_gate', true)) {
            Gate::before(fn ($user, $ability, $arguments = []) => $this->app->make(Rbac::class)->gate($ability, $user, $arguments));
        }

        Event::listen(CommandStarting::class, AuthorizeConsoleCommand::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
                MatrixCommand::class,
                ExplainCommand::class,
                AssignCommand::class,
                GrantCommand::class,
            ]);
        }
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/rbac.php' => config_path('rbac.php'),
        ], 'rbac-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'rbac-migrations');
    }
}
