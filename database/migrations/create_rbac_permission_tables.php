<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The whole RBAC schema in one migration.
 *
 * Every table and column is checked first, so this runs safely on a fresh app,
 * on an app that already ran the older per-feature migrations (it only adds what
 * is missing), and a second time.
 */
return new class extends Migration
{
    /**
     * The migrations this file replaces. When an app has run any of them, the
     * tables are not ours to drop on rollback.
     */
    private const LEGACY = [
        'create_rbac_tables',
        'add_rank_to_rbac_roles',
        'add_expires_at_to_rbac_grants',
        'create_rbac_audit_logs_table',
        'add_guard_to_rbac_roles',
    ];

    public function up(): void
    {
        $this->roles();
        $this->roleGrants();
        $this->roleAssignments();
        $this->userGrants();
        $this->auditLogs();
    }

    public function down(): void
    {
        if ($this->hasLegacyMigrations()) {
            return;
        }

        Schema::dropIfExists(config('rbac.tables.audit_logs', 'rbac_audit_logs'));
        Schema::dropIfExists(config('rbac.tables.user_grants'));
        Schema::dropIfExists(config('rbac.tables.role_assignments'));
        Schema::dropIfExists(config('rbac.tables.role_grants'));
        Schema::dropIfExists(config('rbac.tables.roles'));
    }

    private function roles(): void
    {
        $table = config('rbac.tables.roles');
        $defaultGuard = config('auth.defaults.guard', 'web');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) use ($defaultGuard) {
                $table->id();
                $table->string('slug', 64);
                // A slug is unique per guard, so "manager" can exist for "web" and "admin".
                $table->string('guard', 64)->default($defaultGuard);
                $table->string('name');
                $table->string('description')->nullable();
                $table->unsignedInteger('rank')->default(0);
                $table->timestamps();

                $table->unique(['guard', 'slug']);
            });

            return;
        }

        if (! Schema::hasColumn($table, 'rank')) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('rank')->default(0)->after('description');
            });
        }

        if (! Schema::hasColumn($table, 'guard')) {
            // Existing roles get the default guard.
            Schema::table($table, function (Blueprint $table) use ($defaultGuard) {
                $table->string('guard', 64)->default($defaultGuard)->after('slug');
            });

            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['guard', 'slug']);
            });
        }
    }

    private function roleGrants(): void
    {
        $table = config('rbac.tables.role_grants');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained(config('rbac.tables.roles'))->cascadeOnDelete();
            $table->string('permission', 191);
            $table->string('scope', 32);
            $table->timestamps();

            $table->unique(['role_id', 'permission', 'scope']);
        });
    }

    private function roleAssignments(): void
    {
        $table = config('rbac.tables.role_assignments');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained(config('rbac.tables.roles'))->cascadeOnDelete();
                $table->string('model_type', 191);
                $table->string('model_id', 64);
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['role_id', 'model_type', 'model_id']);
                $table->index(['model_type', 'model_id']);
            });

            return;
        }

        $this->addExpiresAt($table);
    }

    private function userGrants(): void
    {
        $table = config('rbac.tables.user_grants');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('model_type', 191);
                $table->string('model_id', 64);
                $table->string('permission', 191);
                $table->string('scope', 32);
                $table->string('effect', 8)->default('allow');
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['model_type', 'model_id', 'permission', 'scope', 'effect'], 'rbac_user_grants_unique');
            });

            return;
        }

        $this->addExpiresAt($table);
    }

    private function auditLogs(): void
    {
        $table = config('rbac.tables.audit_logs', 'rbac_audit_logs');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('action', 64)->index();
            $table->string('actor_type', 191)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('subject_type', 191)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('permission', 191)->nullable();
            $table->string('scope', 32)->nullable();
            $table->string('effect', 8)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    private function addExpiresAt(string $table): void
    {
        if (Schema::hasColumn($table, 'expires_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    private function hasLegacyMigrations(): bool
    {
        $repository = config('database.migrations');
        $repository = is_array($repository) ? ($repository['table'] ?? 'migrations') : ($repository ?? 'migrations');

        if (! Schema::hasTable($repository)) {
            return false;
        }

        return DB::table($repository)
            ->where(function ($query) {
                foreach (self::LEGACY as $name) {
                    // Published as "<name>" or "<date>_<name>".
                    $query->orWhere('migration', 'like', "%{$name}");
                }
            })
            ->exists();
    }
};
