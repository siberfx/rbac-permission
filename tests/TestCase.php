<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Tests;

use Backpack\Basset\BassetServiceProvider;
use Backpack\CRUD\BackpackServiceProvider;
use Filament\FilamentServiceProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\RbacPermission\RbacPermissionServiceProvider;
use Siberfx\RbacPermission\Tests\Fixtures\Admin;
use Siberfx\RbacPermission\Tests\Fixtures\BackpackUser;
use Siberfx\RbacPermission\Tests\Fixtures\Invoice;
use Siberfx\RbacPermission\Tests\Fixtures\InvoiceController;
use Siberfx\RbacPermission\Tests\Fixtures\Team;
use Siberfx\RbacPermission\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            RbacPermissionServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            BassetServiceProvider::class,
            BackpackServiceProvider::class,
        ];
    }

    /** Tables the tests create: fixtures first, then the RBAC tables (dropped in reverse). */
    private const FIXTURE_TABLES = ['users', 'admins', 'teams', 'team_user', 'invoices'];

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', $this->configureTestDatabase($app));

        // RBAC_TEST_CACHE=1 runs every test with the cross-request grant cache on, as CI does once.
        if (getenv('RBAC_TEST_CACHE')) {
            $app['config']->set('rbac.cache', ['store' => 'array', 'ttl' => 3600]);
        }
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('rbac.default_scope', 'own');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('rbac.ui.enabled', true);
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('auth.guards.backpack', ['driver' => 'session', 'provider' => 'backpack_users']);
        $app['config']->set('auth.providers.backpack_users', ['driver' => 'eloquent', 'model' => BackpackUser::class]);
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
    }

    /**
     * In-memory SQLite by default. Set RBAC_TEST_DB=mysql|mariadb|pgsql (with RBAC_TEST_DB_HOST,
     * _PORT, _DATABASE, _USERNAME, _PASSWORD) to run on a real server, as CI does.
     */
    private function configureTestDatabase($app): string
    {
        $driver = getenv('RBAC_TEST_DB') ?: 'sqlite';

        if ($driver === 'sqlite') {
            return 'testing';
        }

        $app['config']->set("database.connections.{$driver}", array_merge(
            (array) $app['config']->get("database.connections.{$driver}", []),
            array_filter([
                'driver' => $driver,
                'host' => getenv('RBAC_TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('RBAC_TEST_DB_PORT') ?: null,
                'database' => getenv('RBAC_TEST_DB_DATABASE') ?: 'rbac_permission_test',
                'username' => getenv('RBAC_TEST_DB_USERNAME') ?: 'root',
                'password' => getenv('RBAC_TEST_DB_PASSWORD') ?: '',
            ], fn ($value) => $value !== null),
        ));

        return $driver;
    }

    /**
     * Server databases only (in-memory SQLite takes its own path in RefreshDatabase).
     * migrate:fresh would drop the tables defineDatabaseMigrations() just built, so
     * only Laravel's migrations table is ensured here, then each test runs in a transaction.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            if (! Schema::hasTable($this->migrationsTable())) {
                $this->artisan('migrate:install');

                // As Laravel's own refreshTestDatabase() does: drop the Artisan application
                // that command built, so the next one picks up rerouted console events.
                $this->app[ConsoleKernel::class]->setArtisan(null);
            }

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function defineDatabaseMigrations(): void
    {
        // A server database outlives the test: start every test from empty tables.
        // The migrations table is kept, as the migration tests read it, but emptied:
        // on MySQL a schema change commits, so a test's rows could survive its rollback.
        foreach ([...array_reverse(array_values((array) config('rbac.tables'))), ...self::FIXTURE_TABLES] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable($this->migrationsTable())) {
            DB::table($this->migrationsTable())->delete();
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('amount')->default(0);
        });

        // The same order the provider publishes them in.
        foreach (RbacPermissionServiceProvider::MIGRATIONS as $migration) {
            (require __DIR__."/../database/migrations/{$migration}.php")->up();
        }
    }

    private function migrationsTable(): string
    {
        $repository = config('database.migrations');

        return (string) (is_array($repository) ? ($repository['table'] ?? 'migrations') : ($repository ?? 'migrations'));
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('rbac');
        $router->delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('rbac');
        $router->get('/legacy/{invoice}', fn (Invoice $invoice) => 'ok')->middleware('rbac:billing.invoice.show,invoice');
    }

    /**
     * A tenant-1 user in team 10 by default.
     */
    protected function makeUser(array $attributes = [], array $teamIds = [10]): User
    {
        $user = User::create(['name' => 'Jane', 'tenant_id' => 1, ...$attributes]);

        foreach ($teamIds as $teamId) {
            Team::query()->firstOrCreate(['id' => $teamId], ['name' => "Team {$teamId}", 'tenant_id' => 1]);
            $user->teams()->attach($teamId);
        }

        return $user->refresh();
    }

    protected function makeInvoice(array $attributes = []): Invoice
    {
        return Invoice::create(['user_id' => 1, 'team_id' => 10, 'tenant_id' => 1, ...$attributes]);
    }
}
