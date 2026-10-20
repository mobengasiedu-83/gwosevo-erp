<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 24)->default('transactional');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('sms_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 40);
            $table->string('name', 150)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('status', 16)->default('opted_out');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'phone']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('body');
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'scheduled_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('sms_campaigns')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 40);
            $table->string('sender', 32);
            $table->text('body');
            $table->string('status', 20)->default('queued');
            $table->string('idempotency_key', 64)->unique();
            $table->string('provider_message_id', 100)->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['campaign_id', 'status']);
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'sms'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'sms')->delete();
        foreach (['sms_messages', 'sms_campaigns', 'sms_consents', 'sms_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
