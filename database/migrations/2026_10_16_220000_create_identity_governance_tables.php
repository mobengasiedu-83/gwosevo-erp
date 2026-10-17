<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->boolean('requires_mfa')->default(false));
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('auth_version')->default(0);
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedBigInteger('mfa_last_used')->nullable();
            $table->boolean('require_mfa')->default(false);
            $table->string('entra_tenant_id', 36)->nullable();
            $table->string('entra_object_id', 36)->nullable();
            $table->unique(['entra_tenant_id', 'entra_object_id']);
        });
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('identity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('method', 20)->default('password');
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event', 50);
            $table->string('method', 20)->default('password');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('access_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('access_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->string('decision', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['access_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('requires_mfa'));
        foreach (['access_review_items', 'access_reviews', 'approval_delegations', 'login_events', 'identity_sessions', 'user_invitations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['entra_tenant_id', 'entra_object_id']);
            $table->dropColumn(['auth_version', 'mfa_secret', 'mfa_recovery_codes', 'mfa_confirmed_at', 'mfa_last_used', 'require_mfa', 'entra_tenant_id', 'entra_object_id']);
        });
    }
};
