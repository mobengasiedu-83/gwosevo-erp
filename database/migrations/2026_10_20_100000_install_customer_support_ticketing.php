<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('ticket_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('priority', 16);
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->foreignId('escalation_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'priority']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32);
            $table->string('subject', 200);
            $table->string('status', 24)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('channel', 20)->default('manual');
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalation_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name', 150);
            $table->string('requester_email')->nullable();
            $table->string('requester_phone', 40)->nullable();
            $table->char('portal_token_hash', 64)->nullable()->unique();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('response_breached_at')->nullable();
            $table->timestamp('resolution_breached_at')->nullable();
            $table->unsignedSmallInteger('reopen_count')->default(0);
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamp('satisfaction_received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status', 'priority']);
            $table->index(['organization_id', 'assigned_to_id', 'updated_at']);
            $table->index(['organization_id', 'resolution_due_at', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 150);
            $table->string('author_email')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->string('channel', 20)->default('manual');
            $table->longText('body');
            $table->timestamps();
            $table->index(['ticket_id', 'visibility', 'created_at']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('ticket_messages')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('occurred_at');
            $table->foreignId('notified_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'type']);
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'support'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'support')->delete();
        foreach (['ticket_sla_events', 'ticket_attachments', 'ticket_messages', 'tickets', 'ticket_sla_policies', 'ticket_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
