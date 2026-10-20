<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_custom_reports', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160); $table->string('dataset', 40); $table->json('definition');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); $table->timestamps();
            $table->index(['organization_id', 'created_by']);
        });
        Schema::create('bi_kpi_definitions', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140); $table->string('dataset', 40); $table->string('measure', 80); $table->decimal('target', 16, 2)->nullable(); $table->string('unit', 24)->nullable();
            $table->string('filter_field', 80)->nullable(); $table->string('filter_value', 180)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); $table->timestamps();
            $table->index(['organization_id', 'dataset']);
        });
        Schema::create('bi_report_schedules', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('bi_custom_reports')->cascadeOnDelete(); $table->json('recipient_user_ids');
            $table->string('frequency', 16); $table->timestamp('next_run_at'); $table->timestamp('last_run_at')->nullable(); $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); $table->timestamps();
            $table->index(['is_active', 'next_run_at']);
        });
        Schema::create('bi_report_runs', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('report_id')->nullable()->constrained('bi_custom_reports')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('status', 20); $table->unsignedInteger('row_count')->default(0); $table->text('message')->nullable(); $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'reports'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'reports')->delete();
        foreach (['bi_report_runs', 'bi_report_schedules', 'bi_kpi_definitions', 'bi_custom_reports'] as $table) Schema::dropIfExists($table);
    }
};
