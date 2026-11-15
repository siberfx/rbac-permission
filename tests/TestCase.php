<?php

namespace Siberfx\RbacPermission\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\RbacPermission\RbacPermissionServiceProvider;
use Siberfx\RbacPermission\Tests\Fixtures\Admin;
use Siberfx\RbacPermission\Tests\Fixtures\Invoice;
use Siberfx\RbacPermission\Tests\Fixtures\InvoiceController;
use Siberfx\RbacPermission\Tests\Fixtures\Team;
use Siberfx\RbacPermission\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [RbacPermissionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('rbac.default_scope', 'own');
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
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

        (require __DIR__.'/../database/migrations/create_rbac_tables.php')->up();
        (require __DIR__.'/../database/migrations/add_rank_to_rbac_roles.php')->up();
        (require __DIR__.'/../database/migrations/add_expires_at_to_rbac_grants.php')->up();
        (require __DIR__.'/../database/migrations/create_rbac_audit_logs_table.php')->up();
        (require __DIR__.'/../database/migrations/add_guard_to_rbac_roles.php')->up();
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
