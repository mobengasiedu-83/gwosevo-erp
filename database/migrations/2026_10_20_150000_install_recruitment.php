<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('title', 180);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->text('justification');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference'], 'hr_req_org_ref_uq');
            $table->index(['organization_id', 'status'], 'hr_req_org_status_idx');
        });

        Schema::create('hr_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('hr_requisitions')->cascadeOnDelete();
            $table->string('slug', 200)->unique();
            $table->string('title', 180);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'closes_on'], 'hr_vacancy_org_status_close_idx');
        });

        Schema::create('hr_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->text('profile');
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at'], 'hr_candidate_org_created_idx');
        });

        Schema::create('hr_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vacancy_id')->constrained('hr_vacancies')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('hr_candidates')->cascadeOnDelete();
            $table->string('stage', 24)->default('new');
            $table->longText('cover_letter')->nullable();
            $table->string('resume_path', 255)->nullable();
            $table->text('screening_notes')->nullable();
            $table->text('stage_history');
            $table->timestamp('applied_at');
            $table->foreignId('hired_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vacancy_id', 'candidate_id'], 'hr_application_vacancy_candidate_uq');
            $table->index(['organization_id', 'stage', 'applied_at'], 'hr_application_org_stage_applied_idx');
        });

        Schema::create('hr_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('hr_applications')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('format', 30)->default('panel');
            $table->string('status', 20)->default('scheduled');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('panel_notes')->nullable();
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'scheduled_at'], 'hr_interview_org_scheduled_idx');
        });

        Schema::create('hr_assessment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('assessment_type', 20);
            $table->text('instructions')->nullable();
            $table->text('questions');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedTinyInteger('passing_score')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'hr_assessment_org_name_uq');
        });

        Schema::create('hr_candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('hr_applications')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('hr_assessment_templates')->cascadeOnDelete();
            $table->char('access_token_hash', 64)->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('responses')->nullable();
            $table->string('artifact_path', 255)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('assessor_notes')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'application_id'], 'hr_cand_assess_org_app_idx');
        });

        Schema::create('hr_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('hr_applications')->cascadeOnDelete();
            $table->text('terms');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->char('response_token_hash', 64)->nullable()->unique();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status'], 'hr_offer_org_status_idx');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'recruitment'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'recruitment')->delete();
        foreach (['hr_offers', 'hr_candidate_assessments', 'hr_assessment_templates', 'hr_interviews', 'hr_applications', 'hr_candidates', 'hr_vacancies', 'hr_requisitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
