<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('base_url', 500)->nullable();
            $table->string('credential_source', 32)->default('environment');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'provider', 'name']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('integration_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registry_id')->nullable()->constrained('integration_registry')->nullOnDelete();
            $table->string('provider', 40);
            $table->uuid('correlation_id')->index();
            $table->string('outcome', 24);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('summary', 500);
            $table->foreignId('checked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['organization_id', 'provider', 'checked_at'], 'integration_health_org_provider_checked_idx');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'integrations'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'integrations')->delete();
        Schema::dropIfExists('integration_health_logs');
        Schema::dropIfExists('integration_registry');
    }
};
