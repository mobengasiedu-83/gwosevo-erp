<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('account_name', 160);
            $table->string('handle', 160)->nullable();
            $table->string('connection_status', 24)->default('not_connected');
            $table->text('permission_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'platform', 'connection_status']);
        });
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->timestamp('planned_for')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->string('publish_result', 40)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'planned_for']);
        });
        Schema::create('social_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('kind', 24);
            $table->string('external_reference', 180)->nullable();
            $table->string('author_name', 180)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('status', 24)->default('new');
            $table->json('tags')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'kind', 'status', 'received_at']);
        });
        Schema::create('social_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 500);
            $table->json('tags')->nullable();
            $table->text('usage_rights')->nullable();
            $table->string('approval_status', 24)->default('pending_review');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'approval_status']);
        });
        Schema::create('social_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('trigger_kind', 24);
            $table->string('match_text', 180)->nullable();
            $table->foreignId('assign_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('social_workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('settings');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'social_media'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'social_media')->delete();
        foreach (['social_workspace_settings','social_automation_rules','social_assets','social_inbox_items','social_posts','social_accounts'] as $table) Schema::dropIfExists($table);
    }
};
