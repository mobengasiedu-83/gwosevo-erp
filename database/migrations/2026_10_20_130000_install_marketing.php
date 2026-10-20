<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('objective')->nullable();
            $table->string('channel', 20);
            $table->string('status', 20)->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('actual_spend', 15, 2)->default(0);
            $table->json('audience_rules')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'starts_on'], 'mkt_campaign_org_status_start_idx');
        });

        Schema::create('marketing_affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 50);
            $table->string('email')->nullable();
            $table->decimal('approved_commission_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('marketing_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'campaign_id', 'is_published'], 'mkt_landing_org_campaign_pub_idx');
        });

        Schema::create('marketing_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('subject', 180);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'campaign_id', 'due_at', 'completed_at'], 'mkt_task_org_campaign_due_done_idx');
        });

        Schema::create('marketing_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('channel', 12);
            $table->string('destination', 255);
            $table->string('status', 16)->default('withdrawn');
            $table->string('source', 120)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'channel', 'destination'], 'marketing_consent_unique');
            $table->index(['organization_id', 'channel', 'status'], 'mkt_consent_org_channel_status_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('marketing_campaign_id')->nullable()->after('source_detail')->constrained('marketing_campaigns')->nullOnDelete();
            $table->foreignId('marketing_affiliate_id')->nullable()->after('marketing_campaign_id')->constrained('marketing_affiliates')->nullOnDelete();
            $table->index(['organization_id', 'marketing_campaign_id'], 'leads_org_mkt_campaign_idx');
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'marketing'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'marketing')->delete();
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['marketing_campaign_id']);
            $table->dropForeign(['marketing_affiliate_id']);
            $table->dropIndex('leads_org_mkt_campaign_idx');
            $table->dropColumn(['marketing_campaign_id', 'marketing_affiliate_id']);
        });
        foreach (['marketing_consents', 'marketing_tasks', 'marketing_landing_pages', 'marketing_affiliates', 'marketing_campaigns'] as $table) Schema::dropIfExists($table);
    }
};
