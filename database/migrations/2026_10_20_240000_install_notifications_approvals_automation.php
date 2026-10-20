<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('stages')->default(1);
            $table->json('stage_approvers');
            $table->unsignedInteger('sla_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('automation_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('automation_workflows')->nullOnDelete();
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('current_stage')->default(1);
            $table->string('status', 24)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'due_at']);
        });

        Schema::create('automation_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('automation_approval_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'stage', 'approver_id']);
            $table->index(['approver_id', 'status']);
        });

        Schema::create('automation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('event_key', 100);
            $table->string('channel', 16);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'event_key', 'channel']);
        });

        Schema::create('automation_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('sms')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'event_key']);
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('event_key', 100);
            $table->string('condition_field', 100)->nullable();
            $table->string('condition_operator', 16)->default('equals');
            $table->string('condition_value', 200)->nullable();
            $table->string('action', 32);
            $table->json('action_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'event_key', 'is_active']);
        });

        Schema::create('automation_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key', 100);
            $table->string('channel', 16);
            $table->string('recipient', 254)->nullable();
            $table->string('status', 24);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'automation'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'automation')->delete();
        Schema::dropIfExists('automation_delivery_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('automation_preferences');
        Schema::dropIfExists('automation_templates');
        Schema::dropIfExists('automation_approval_steps');
        Schema::dropIfExists('automation_approval_requests');
        Schema::dropIfExists('automation_workflows');
    }
};
