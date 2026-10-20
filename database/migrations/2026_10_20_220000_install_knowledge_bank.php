<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_spaces', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160); $table->string('slug', 180); $table->string('scope_type', 24)->default('shared');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['organization_id', 'slug']); $table->index(['organization_id', 'is_active']);
        });
        Schema::create('knowledge_space_members', function (Blueprint $table) {
            $table->id(); $table->foreignId('space_id')->constrained('knowledge_spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('access_level', 20)->default('reader'); $table->timestamps();
            $table->unique(['space_id', 'user_id']);
        });
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name', 120); $table->string('slug', 140); $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });
        Schema::create('knowledge_tags', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name', 80); $table->string('slug', 100); $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('space_id')->constrained('knowledge_spaces')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete(); $table->string('title', 220); $table->string('slug', 240);
            $table->text('summary')->nullable(); $table->longText('body'); $table->string('status', 24)->default('draft'); $table->unsignedInteger('version')->default(1);
            $table->string('source_provider', 24)->default('local'); $table->unsignedBigInteger('source_connection_id')->nullable(); $table->string('external_item_id', 255)->nullable(); $table->timestamp('external_modified_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamp('archived_at')->nullable(); $table->timestamps();
            $table->unique(['space_id', 'slug']); $table->unique(['organization_id', 'source_connection_id', 'external_item_id'], 'knowledge_article_source_uq'); $table->index(['organization_id', 'status', 'published_at']); $table->index(['space_id', 'category_id', 'status']);
        });
        Schema::create('knowledge_article_versions', function (Blueprint $table) {
            $table->id(); $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete(); $table->unsignedInteger('version');
            $table->string('title', 220); $table->text('summary')->nullable(); $table->longText('body'); $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['article_id', 'version']);
        });
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->string('original_name', 255); $table->string('mime_type', 120); $table->unsignedBigInteger('size_bytes'); $table->string('storage_path', 500); $table->string('extraction_status', 24)->default('pending');
            $table->text('extraction_note')->nullable(); $table->longText('extracted_text')->nullable(); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['organization_id', 'article_id']);
        });
        Schema::create('knowledge_article_tags', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete(); $table->foreignId('tag_id')->constrained('knowledge_tags')->cascadeOnDelete(); $table->primary(['article_id', 'tag_id']);
        });
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('space_id')->constrained('knowledge_spaces')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete(); $table->unsignedSmallInteger('chunk_index'); $table->longText('content'); $table->string('embedding_id', 120)->nullable(); $table->timestamps();
            $table->unique(['article_id', 'chunk_index']); $table->index(['organization_id', 'space_id']);
        });
        Schema::create('knowledge_search_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query_hash', 64); $table->string('query_text', 500)->nullable(); $table->unsignedSmallInteger('result_count')->default(0); $table->string('search_mode', 20)->default('keyword'); $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });
        Schema::create('knowledge_feedback', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->unsignedTinyInteger('rating'); $table->text('comment')->nullable(); $table->timestamps();
            $table->unique(['article_id', 'user_id']);
        });
        Schema::create('knowledge_sync_connections', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('space_id')->constrained('knowledge_spaces')->cascadeOnDelete(); $table->string('provider', 24)->default('microsoft_graph');
            $table->string('name', 160); $table->text('encrypted_credentials')->nullable(); $table->string('status', 24)->default('disabled'); $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('knowledge_sync_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('connection_id')->constrained('knowledge_sync_connections')->cascadeOnDelete(); $table->string('status', 24);
            $table->unsignedInteger('created_count')->default(0); $table->unsignedInteger('updated_count')->default(0); $table->unsignedInteger('deleted_count')->default(0); $table->text('message')->nullable(); $table->timestamps();
            $table->index(['connection_id', 'created_at']);
        });

        DB::table('module_settings')->updateOrInsert(['key' => 'knowledge'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'knowledge')->delete();
        foreach (['knowledge_sync_logs','knowledge_sync_connections','knowledge_feedback','knowledge_search_events','knowledge_chunks','knowledge_article_tags','knowledge_documents','knowledge_article_versions','knowledge_articles','knowledge_tags','knowledge_categories','knowledge_space_members','knowledge_spaces'] as $table) Schema::dropIfExists($table);
    }
};
