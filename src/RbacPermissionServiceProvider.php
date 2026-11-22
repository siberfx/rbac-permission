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
use Siberfx\RbacPermission\Events\RbacChanged;
use Siberfx\RbacPermission\Http\Middleware\EnsurePermission;
use Siberfx\RbacPermission\Listeners\AuthorizeConsoleCommand;
use Siberfx\RbacPermission\Listeners\WriteAuditLog;
use Siberfx\RbacPermission\Scopes\ScopeRegistry;
use Siberfx\RbacPermission\Support\Catalog;

class RbacPermissionServiceProvider extends ServiceProvider
{
    /**
     * The package migrations, in the order they must run. A later schema change goes
     * in a new file added at the end, and checks its table or column before acting.
     *
     * @var list<string>
     */
    public const MIGRATIONS = [
        'create_rbac_permission_tables',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rbac.php', 'rbac');

        $this->app->singleton(Catalog::class, fn () => new Catalog(
            config('rbac.modules', []),
            config('rbac.verbs', []),
        ));

        $this->app->singleton(ScopeRegistry::class, fn () => new ScopeRegistry(config('rbac.scopes', [])));

        // Scoped, not singleton: Octane and queue workers start each request or job
        // with a fresh instance, so memoised grants never outlive their lifecycle.
        $this->app->scoped(Rbac::class, fn ($app) => new Rbac(
            $app->make(Catalog::class),
            $app->make(ScopeRegistry::class),
            $app['cache'],
        ));
    }

    public function boot(): void
    {
        $this->registerPublishing();

        $this->app['router']->aliasMiddleware('rbac', EnsurePermission::class);

        if (config('rbac.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/rbac.php');
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'rbac');
        }

        // A bare @elserbac compiles to an elseif with no arguments; treat that as "not allowed".
        Blade::if('rbac', fn (?string $permission = null, mixed $subject = null) => $permission !== null
            && $this->app->make(Rbac::class)->allows($permission, $subject));

        if (config('rbac.register_gate', true)) {
            Gate::before(fn ($user, $ability, $arguments = []) => $this->app->make(Rbac::class)->gate($ability, $user, $arguments));
        }

        Event::listen(CommandStarting::class, AuthorizeConsoleCommand::class);
        Event::listen(RbacChanged::class, WriteAuditLog::class);

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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/rbac'),
        ], 'rbac-views');

        $this->publishesMigrations($this->migrationPublishPaths(), 'rbac-migrations');
    }

    /**
     * Source file => destination, in the order the migrations must run.
     *
     * Laravel runs migrations sorted by file name and the source files have no date
     * prefix, so each file gets an ascending date prefix here. A migration the app
     * has already published, with or without a prefix, keeps its path and is skipped.
     *
     * @return array<string, string>
     */
    private function migrationPublishPaths(): array
    {
        $paths = [];
        $now = time();

        foreach (self::MIGRATIONS as $i => $name) {
            $existing = glob(database_path("migrations/*{$name}.php")) ?: [];
            $existing = array_values(array_filter(
                $existing,
                fn (string $path) => preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6}_)?'.preg_quote($name, '/').'\.php$/', basename($path)) === 1,
            ));

            $paths[__DIR__."/../database/migrations/{$name}.php"] = $existing[0]
                ?? database_path('migrations/'.date('Y_m_d_His', $now + $i)."_{$name}.php");
        }

        return $paths;
    }
}
