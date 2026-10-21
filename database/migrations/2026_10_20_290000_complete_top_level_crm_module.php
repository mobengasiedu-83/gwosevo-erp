<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('industry')->constrained('users')->nullOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('preferred_channel', 20)->nullable()->after('phone');
            $table->boolean('email_opt_in')->default(false)->after('preferred_channel');
            $table->boolean('sms_opt_in')->default(false)->after('email_opt_in');
        });
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });
        Schema::create('crm_saved_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->json('filters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_saved_segments');
        Schema::dropIfExists('crm_lead_sources');
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['preferred_channel', 'email_opt_in', 'sms_opt_in']);
        });
        Schema::table('accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('owner_id'));
    }
};
