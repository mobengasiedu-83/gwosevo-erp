<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('title', 180);
            $table->text('details');
            $table->string('severity', 20)->default('medium');
            $table->string('status', 24)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'severity']);
        });

        Schema::create('security_backup_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('backup_type', 24);
            $table->string('label', 160);
            $table->string('status', 24)->default('completed');
            $table->string('storage_location', 255);
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('backup_at');
            $table->string('restore_result', 24)->default('not_tested');
            $table->timestamp('restore_tested_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'backup_at']);
        });

        Schema::create('security_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category', 48);
            $table->unsignedSmallInteger('retention_days')->default(365);
            $table->boolean('enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'category'], 'security_retention_org_category_uq');
        });

        Schema::create('security_retention_runs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 48);
            $table->unsignedInteger('deleted_rows')->default(0);
            $table->timestamp('cutoff_at');
            $table->timestamp('run_at');
            $table->string('run_by', 120)->nullable();
            $table->index(['category', 'run_at']);
        });

        Schema::create('security_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 80)->unique();
            $table->string('classification', 24)->default('internal');
            $table->text('handling_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('security_rotation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('system', 60);
            $table->string('secret_label', 120);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->string('status', 24)->default('tracked');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'next_due_at']);
        });

        foreach ([
            ['dataset' => 'identity_credentials', 'classification' => 'restricted', 'handling_notes' => 'Passwords, MFA secrets and session tokens are not displayed in this registry.'],
            ['dataset' => 'hr_records', 'classification' => 'restricted', 'handling_notes' => 'Use HR sensitive permissions and private document storage.'],
            ['dataset' => 'candidate_records', 'classification' => 'confidential', 'handling_notes' => 'Candidate personal data; apply approved retention and access rules.'],
            ['dataset' => 'customer_and_crm', 'classification' => 'confidential', 'handling_notes' => 'Customer and lead contact details are personal information.'],
            ['dataset' => 'finance_records', 'classification' => 'confidential', 'handling_notes' => 'Restrict finance access and exports to approved staff.'],
            ['dataset' => 'operational_records', 'classification' => 'internal', 'handling_notes' => 'Organization operational data; follow role and organization scope.'],
            ['dataset' => 'public_content', 'classification' => 'public', 'handling_notes' => 'Only approved content intended for public access.'],
        ] as $row) {
            DB::table('security_classifications')->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach ([['audit_events', 365], ['login_events', 180], ['integration_health_logs', 90], ['failed_jobs', 90]] as [$category, $days]) {
            DB::table('security_retention_policies')->insert(['organization_id' => null, 'category' => $category, 'retention_days' => $days, 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('module_settings')->updateOrInsert(['key' => 'security'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'security')->delete();
        foreach (['security_rotation_records', 'security_classifications', 'security_retention_runs', 'security_retention_policies', 'security_backup_records', 'security_incidents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
