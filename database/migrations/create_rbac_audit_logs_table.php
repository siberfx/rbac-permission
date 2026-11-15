<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rbac.tables.audit_logs', 'rbac_audit_logs'), function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists(config('rbac.tables.audit_logs', 'rbac_audit_logs'));
    }
};
