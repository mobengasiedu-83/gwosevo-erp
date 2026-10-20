<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180); $table->text('description')->nullable(); $table->string('client_name', 180)->nullable();
            $table->decimal('budget', 14, 2)->nullable(); $table->date('starts_on')->nullable(); $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active'); $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['organization_id', 'status', 'ends_on'], 'projects_org_status_end_idx');
        });
        Schema::create('project_members', function (Blueprint $table) {
            $table->id(); $table->foreignId('project_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('member_role', 80)->default('member'); $table->timestamps(); $table->unique(['project_id', 'user_id']);
        });
        Schema::create('project_workstreams', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160); $table->string('department_name', 120)->nullable(); $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active'); $table->timestamps();
        });
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workstream_id')->nullable()->constrained('project_workstreams')->nullOnDelete(); $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('title', 200); $table->text('description')->nullable(); $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('priority', 20)->default('medium');
            $table->string('status', 24)->default('todo'); $table->date('starts_on')->nullable(); $table->date('due_on')->nullable();
            $table->decimal('estimate_hours', 7, 2)->nullable(); $table->boolean('is_deliverable')->default(false);
            $table->string('approval_status', 24)->default('not_required'); $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('approved_at')->nullable();
            $table->timestamps(); $table->index(['organization_id', 'project_id', 'status', 'due_on'], 'project_tasks_board_idx');
            $table->index(['organization_id', 'owner_id', 'status'], 'project_tasks_owner_idx');
        });
        Schema::create('project_task_dependencies', function (Blueprint $table) {
            $table->id(); $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete(); $table->foreignId('depends_on_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->timestamps(); $table->unique(['task_id', 'depends_on_task_id']);
        });
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workstream_id')->nullable()->constrained('project_workstreams')->nullOnDelete(); $table->string('title', 180); $table->text('description')->nullable();
            $table->date('due_on')->nullable(); $table->string('status', 24)->default('upcoming'); $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('approved_at')->nullable(); $table->timestamps();
        });
        Schema::create('project_time_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date'); $table->decimal('hours', 5, 2); $table->text('notes')->nullable(); $table->timestamps();
            $table->index(['organization_id', 'project_id', 'work_date'], 'project_time_report_idx');
        });
        Schema::create('project_comments', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body'); $table->json('mentioned_user_ids')->nullable(); $table->timestamps(); $table->index(['project_id', 'task_id', 'created_at']);
        });
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->cascadeOnDelete(); $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name', 255); $table->string('mime_type', 120); $table->unsignedBigInteger('size_bytes'); $table->string('storage_path', 500); $table->timestamps();
        });
        Schema::create('project_risks', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('risk'); $table->string('title', 180); $table->text('description')->nullable(); $table->text('mitigation')->nullable();
            $table->string('probability', 16)->default('medium'); $table->string('impact', 16)->default('medium'); $table->string('status', 24)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable(); $table->timestamps();
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'projects'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'projects')->delete();
        foreach (['project_risks', 'project_attachments', 'project_comments', 'project_time_entries', 'project_milestones', 'project_task_dependencies', 'project_tasks', 'project_workstreams', 'project_members', 'projects'] as $table) Schema::dropIfExists($table);
    }
};
