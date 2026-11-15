<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles belong to an authentication guard. Existing roles get the default guard,
     * and a slug is now unique per guard, so "manager" can exist for "web" and "admin".
     */
    public function up(): void
    {
        Schema::table(config('rbac.tables.roles'), function (Blueprint $table) {
            $table->string('guard', 64)->default(config('auth.defaults.guard', 'web'))->after('slug');
        });

        Schema::table(config('rbac.tables.roles'), function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['guard', 'slug']);
        });
    }

    /**
     * Rolling back fails if the same slug exists under two guards, because the
     * original unique index on slug alone cannot hold both.
     */
    public function down(): void
    {
        Schema::table(config('rbac.tables.roles'), function (Blueprint $table) {
            $table->dropUnique(['guard', 'slug']);
            $table->unique(['slug']);
            $table->dropColumn('guard');
        });
    }
};
