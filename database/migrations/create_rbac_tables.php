<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->unique(['role_id', 'permission', 'scope']);
        });

        Schema::create(config('rbac.tables.role_assignments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained(config('rbac.tables.roles'))->cascadeOnDelete();
            $table->string('model_type', 191);
            $table->string('model_id', 64);
            $table->timestamps();

            $table->unique(['role_id', 'model_type', 'model_id']);
            $table->index(['model_type', 'model_id']);
        });

        Schema::create(config('rbac.tables.user_grants'), function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 191);
            $table->string('model_id', 64);
            $table->string('permission', 191);
            $table->string('scope', 32);
            $table->string('effect', 8)->default('allow');
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'permission', 'scope', 'effect'], 'rbac_user_grants_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rbac.tables.user_grants'));
        Schema::dropIfExists(config('rbac.tables.role_assignments'));
        Schema::dropIfExists(config('rbac.tables.role_grants'));
        Schema::dropIfExists(config('rbac.tables.roles'));
    }
};
