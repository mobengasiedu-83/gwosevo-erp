<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable();
        });

        Schema::create('workspace_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('widgets');
            $table->timestamps();
        });

        Schema::create('workspace_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 20)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'assigned_to', 'status', 'due_at'], 'workspace_tasks_assigned_idx');
            $table->index(['organization_id', 'created_by', 'status'], 'workspace_tasks_created_idx');
        });

        Schema::create('workspace_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('workspace_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('workspace_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id', 'starts_at'], 'workspace_calendar_user_start_idx');
            $table->index(['reminder_at', 'reminder_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_calendar_events');
        Schema::dropIfExists('workspace_dashboard_preferences');
        Schema::dropIfExists('workspace_task_comments');
        Schema::dropIfExists('workspace_tasks');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
