<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('period_type', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24)->default('draft');
            $table->unsignedTinyInteger('goal_weight')->default(60);
            $table->unsignedTinyInteger('competency_weight')->default(40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'hr_perf_cycle_org_name_uq');
            $table->index(['organization_id', 'status', 'starts_on'], 'hr_perf_cycle_org_status_idx');
        });

        Schema::create('hr_performance_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('measure', 120)->nullable();
            $table->decimal('default_target', 12, 2)->nullable();
            $table->unsignedTinyInteger('weight')->default(10);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'hr_perf_kpi_org_active_idx');
        });

        Schema::create('hr_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('hr_performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('status', 24)->default('self_review');
            $table->longText('self_assessment')->nullable();
            $table->longText('manager_assessment')->nullable();
            $table->longText('competency_scores')->nullable();
            $table->unsignedTinyInteger('self_score')->nullable();
            $table->unsignedTinyInteger('manager_score')->nullable();
            $table->unsignedTinyInteger('calibrated_score')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->text('calibration_note')->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calibrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'employee_id'], 'hr_perf_review_cycle_employee_uq');
            $table->index(['organization_id', 'status'], 'hr_perf_review_org_status_idx');
        });

        Schema::create('hr_performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('hr_performance_reviews')->cascadeOnDelete();
            $table->foreignId('kpi_id')->nullable()->constrained('hr_performance_kpis')->nullOnDelete();
            $table->string('title', 180);
            $table->longText('smart_description')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->unsignedTinyInteger('weight')->default(10);
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('self_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->unsignedTinyInteger('attainment_score')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'review_id'], 'hr_perf_goal_org_review_idx');
        });

        Schema::create('hr_performance_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('hr_performance_reviews')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 40)->default('peer');
            $table->longText('response')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'reviewer_user_id'], 'hr_perf_feedback_review_user_uq');
        });

        Schema::create('hr_performance_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('hr_performance_reviews')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 255);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'review_id'], 'hr_perf_evidence_org_review_idx');
        });

        Schema::create('hr_performance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('hr_performance_reviews')->cascadeOnDelete();
            $table->string('plan_type', 20);
            $table->string('title', 180);
            $table->longText('actions');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('follow_up_note')->nullable();
            $table->foreignId('course_id')->nullable()->constrained('hr_training_courses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'plan_type', 'status'], 'hr_perf_plan_org_type_status_idx');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'performance'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'performance')->delete();
        foreach (['hr_performance_plans', 'hr_performance_evidence', 'hr_performance_feedback', 'hr_performance_goals', 'hr_performance_reviews', 'hr_performance_kpis', 'hr_performance_cycles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
