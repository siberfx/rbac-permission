<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Siberfx\RbacPermission\RbacPermissionServiceProvider;

function rbacMigration(): object
{
    return require __DIR__.'/../../database/migrations/create_rbac_permission_tables.php';
}

function dropRbacTables(): void
{
    foreach (array_reverse(config('rbac.tables')) as $table) {
        Schema::dropIfExists($table);
    }
}

function migrationsTable(): string
{
    $repository = config('database.migrations');

    return is_array($repository) ? ($repository['table'] ?? 'migrations') : ($repository ?? 'migrations');
}

/**
 * Points database_path() at a folder and registers the publish paths again,
 * as they depend on what is already in database/migrations.
 */
function publishInto(string $databasePath): array
{
    app()->useDatabasePath($databasePath);

    $provider = new RbacPermissionServiceProvider(app());
    (new ReflectionMethod($provider, 'registerPublishing'))->invoke($provider);

    test()->artisan('vendor:publish', ['--tag' => 'rbac-migrations'])->assertSuccessful();

    $files = array_map('basename', glob("{$databasePath}/migrations/*.php"));
    sort($files);

    return $files;
}

test('on an empty database it creates the whole schema', function () {
    dropRbacTables();

    rbacMigration()->up();

    expect(Schema::hasColumns(config('rbac.tables.roles'), ['slug', 'guard', 'rank', 'name', 'description']))->toBeTrue()
        ->and(Schema::hasTable(config('rbac.tables.role_grants')))->toBeTrue()
        ->and(Schema::hasColumn(config('rbac.tables.role_assignments'), 'expires_at'))->toBeTrue()
        ->and(Schema::hasColumn(config('rbac.tables.user_grants'), 'expires_at'))->toBeTrue()
        ->and(Schema::hasTable(config('rbac.tables.audit_logs')))->toBeTrue();
});

test('running it again changes nothing', function () {
    // TestCase already ran it once.
    rbacMigration()->up();

    expect(Schema::hasColumns(config('rbac.tables.roles'), ['guard', 'rank']))->toBeTrue();
});

test('on the schema of the first release it adds only what is missing', function () {
    dropRbacTables();

    // The 1.0 schema: no rank, no guard, no expiry, no audit table.
    Schema::create(config('rbac.tables.roles'), function (Blueprint $table) {
        $table->id();
        $table->string('slug', 64)->unique();
        $table->string('name');
        $table->string('description')->nullable();
        $table->timestamps();
    });
    Schema::create(config('rbac.tables.role_grants'), function (Blueprint $table) {
        $table->id();
        $table->foreignId('role_id')->constrained(config('rbac.tables.roles'))->cascadeOnDelete();
        $table->string('permission', 191);
        $table->string('scope', 32);
        $table->timestamps();
    });
    Schema::create(config('rbac.tables.role_assignments'), function (Blueprint $table) {
        $table->id();
        $table->foreignId('role_id')->constrained(config('rbac.tables.roles'))->cascadeOnDelete();
        $table->string('model_type', 191);
        $table->string('model_id', 64);
        $table->timestamps();
    });
    Schema::create(config('rbac.tables.user_grants'), function (Blueprint $table) {
        $table->id();
        $table->string('model_type', 191);
        $table->string('model_id', 64);
        $table->string('permission', 191);
        $table->string('scope', 32);
        $table->string('effect', 8)->default('allow');
        $table->timestamps();
    });
    DB::table(config('rbac.tables.roles'))->insert(['slug' => 'manager', 'name' => 'Manager']);

    rbacMigration()->up();

    expect(Schema::hasColumns(config('rbac.tables.roles'), ['guard', 'rank']))->toBeTrue()
        ->and(Schema::hasColumn(config('rbac.tables.role_assignments'), 'expires_at'))->toBeTrue()
        ->and(Schema::hasColumn(config('rbac.tables.user_grants'), 'expires_at'))->toBeTrue()
        ->and(Schema::hasTable(config('rbac.tables.audit_logs')))->toBeTrue()
        ->and(DB::table(config('rbac.tables.roles'))->where('slug', 'manager')->value('guard'))->toBe(config('auth.defaults.guard'));

    // The slug is now unique per guard, not globally.
    DB::table(config('rbac.tables.roles'))->insert(['slug' => 'manager', 'guard' => 'admin', 'name' => 'Manager']);

    expect(DB::table(config('rbac.tables.roles'))->where('slug', 'manager')->count())->toBe(2);
});

test('rolling back drops the tables it owns', function () {
    rbacMigration()->down();

    expect(Schema::hasTable(config('rbac.tables.roles')))->toBeFalse()
        ->and(Schema::hasTable(config('rbac.tables.audit_logs')))->toBeFalse();
});

test('rolling back keeps tables that the older migrations created', function () {
    DB::table(migrationsTable())->insert(['migration' => '2025_01_10_120000_create_rbac_tables', 'batch' => 1]);

    rbacMigration()->down();

    expect(Schema::hasTable(config('rbac.tables.roles')))->toBeTrue()
        ->and(Schema::hasTable(config('rbac.tables.role_assignments')))->toBeTrue();
});

describe('publishing', function () {
    beforeEach(function () {
        $this->originalDatabasePath = database_path();
        $this->databasePath = sys_get_temp_dir().'/rbac-migrations-'.uniqid();
        File::ensureDirectoryExists("{$this->databasePath}/migrations");
    });

    afterEach(function () {
        app()->useDatabasePath($this->originalDatabasePath);
        File::deleteDirectory($this->databasePath);
    });

    test('the migration is published with a date prefix', function () {
        $files = publishInto($this->databasePath);

        expect($files)->toHaveCount(1)
            ->and($files[0])->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_rbac_permission_tables\.php$/');
    });

    test('next to the older migrations of an app that is upgrading', function () {
        File::put("{$this->databasePath}/migrations/create_rbac_tables.php", '<?php // old');
        File::put("{$this->databasePath}/migrations/2025_03_01_120000_add_rank_to_rbac_roles.php", '<?php // old');

        $files = publishInto($this->databasePath);

        expect($files)->toHaveCount(3)
            ->and($files)->toContain('create_rbac_tables.php')
            ->and($files)->toContain('2025_03_01_120000_add_rank_to_rbac_roles.php');
    });

    test('publishing twice does not duplicate it', function () {
        $first = publishInto($this->databasePath);
        $second = publishInto($this->databasePath);

        expect($second)->toBe($first);
    });
});
