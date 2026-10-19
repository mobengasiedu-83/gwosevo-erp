<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbx_connections', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('driver', 40)->default('vitalpbx'); $table->string('base_url')->nullable();
            $table->text('api_token')->nullable(); $table->text('webhook_secret'); $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false); $table->timestamp('last_connected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });
        Schema::create('pbx_extensions', function (Blueprint $table) {
            $table->id(); $table->foreignId('connection_id')->constrained('pbx_connections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('extension', 32); $table->string('queue', 100)->nullable();
            $table->string('availability', 24)->default('unknown'); $table->timestamp('activity_at')->nullable(); $table->timestamps();
            $table->unique(['connection_id', 'extension']); $table->unique(['connection_id', 'user_id']);
        });
        Schema::create('call_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('pbx_connections')->nullOnDelete();
            $table->string('external_id')->nullable(); $table->string('direction', 16); $table->string('status', 20);
            $table->string('from_number', 40)->nullable(); $table->string('to_number', 40)->nullable(); $table->string('queue', 100)->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete(); $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable(); $table->timestamp('ended_at')->nullable(); $table->unsignedInteger('wait_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0); $table->string('disposition', 60)->nullable(); $table->text('notes')->nullable();
            $table->timestamp('follow_up_at')->nullable(); $table->foreignId('follow_up_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recording_reference')->nullable(); $table->timestamp('recording_expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['connection_id', 'external_id']); $table->index(['organization_id', 'started_at']);
            $table->index(['organization_id', 'queue', 'status']); $table->index(['organization_id', 'agent_id', 'started_at']);
        });
        Schema::create('call_evaluation_forms', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name');
            $table->json('criteria'); $table->boolean('is_active')->default(true); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('call_evaluations', function (Blueprint $table) {
            $table->id(); $table->foreignId('call_record_id')->constrained()->cascadeOnDelete(); $table->foreignId('form_id')->constrained('call_evaluation_forms')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete(); $table->json('scores'); $table->unsignedTinyInteger('score_percent'); $table->text('notes')->nullable(); $table->timestamps();
            $table->index(['call_record_id', 'created_at']);
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'contact_centre'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'contact_centre')->delete();
        Schema::dropIfExists('call_evaluations'); Schema::dropIfExists('call_evaluation_forms');
        Schema::dropIfExists('call_records'); Schema::dropIfExists('pbx_extensions'); Schema::dropIfExists('pbx_connections');
    }
};
