<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('work_email')->nullable();
            $table->string('work_phone', 50)->nullable();
            $table->text('personal_data')->nullable();
            $table->string('employment_type', 30)->default('permanent');
            $table->string('status', 24)->default('preboarding');
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'employee_number'], 'hr_employee_org_number_uq');
            $table->unique(['organization_id', 'user_id'], 'hr_employee_org_user_uq');
            $table->index(['organization_id', 'status', 'hire_date'], 'hr_employee_org_status_hire_idx');
        });

        Schema::create('hr_employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('reference', 80);
            $table->string('contract_type', 40);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->longText('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'status'], 'hr_contract_org_employee_status_idx');
        });

        Schema::create('hr_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('hr_employment_contracts')->nullOnDelete();
            $table->string('category', 40);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 255);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'category'], 'hr_doc_org_employee_category_idx');
        });

        Schema::create('hr_job_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('effective_on');
            $table->string('change_type', 40);
            $table->text('previous_assignment')->nullable();
            $table->text('new_assignment')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'effective_on'], 'hr_job_org_employee_date_idx');
        });

        Schema::create('hr_lifecycle_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('phase', 20);
            $table->string('title', 180);
            $table->text('notes')->nullable();
            $table->date('due_on')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'phase', 'completed_at'], 'hr_lifecycle_org_employee_idx');
        });

        Schema::create('hr_disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('opened_on');
            $table->string('category', 60);
            $table->string('status', 20)->default('open');
            $table->text('details');
            $table->text('resolution')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'status'], 'hr_case_org_employee_status_idx');
        });

        Schema::create('hr_training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('provider', 180)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'hr_course_org_name_uq');
        });

        Schema::create('hr_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('hr_training_courses')->cascadeOnDelete();
            $table->string('status', 20)->default('planned');
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('certificate_document_id')->nullable()->constrained('hr_employee_documents')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'course_id'], 'hr_training_employee_course_uq');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'hr'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'hr')->delete();
        foreach (['hr_training_records', 'hr_training_courses', 'hr_disciplinary_cases', 'hr_lifecycle_tasks', 'hr_job_history', 'hr_employee_documents', 'hr_employment_contracts', 'hr_employees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
