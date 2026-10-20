<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->json('weekdays');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'hr_shift_org_name_uq');
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('manager_employee_id')->constrained('hr_shifts')->nullOnDelete();
        });

        Schema::create('hr_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20)->default('present');
            $table->string('source', 20)->default('self_service');
            $table->text('exception_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date'], 'hr_attendance_employee_date_uq');
            $table->index(['organization_id', 'work_date', 'status'], 'hr_attendance_org_date_status_idx');
        });

        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->decimal('default_entitlement', 6, 2)->default(0);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'hr_leave_type_org_code_uq');
        });

        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitlement_days', 6, 2)->default(0);
            $table->decimal('carryover_days', 6, 2)->default(0);
            $table->decimal('adjustment_days', 6, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'hr_leave_balance_employee_type_year_uq');
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('requested_days', 6, 2);
            $table->longText('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'starts_on'], 'hr_leave_req_org_status_start_idx');
        });

        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_on');
            $table->string('name', 180);
            $table->string('scope', 20)->default('company');
            $table->timestamps();
            $table->unique(['organization_id', 'holiday_on', 'name'], 'hr_holiday_org_date_name_uq');
            $table->index(['organization_id', 'holiday_on'], 'hr_holiday_org_date_idx');
        });

        Schema::create('hr_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('activity', 180);
            $table->text('description')->nullable();
            $table->decimal('hours', 5, 2);
            $table->string('status', 20)->default('draft');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'work_date', 'status'], 'hr_timesheet_org_date_status_idx');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'attendance'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'attendance')->delete();
        foreach (['hr_timesheets', 'hr_holidays', 'hr_leave_requests', 'hr_leave_balances', 'hr_leave_types', 'hr_attendance_records'] as $table) Schema::dropIfExists($table);
        Schema::table('hr_employees', function (Blueprint $table) { $table->dropConstrainedForeignId('shift_id'); });
        Schema::dropIfExists('hr_shifts');
    }
};
