<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('reference')->unique();
            $table->string('status', 24)->default('draft');
            $table->string('currency', 3)->default('GHS');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->unique(['opportunity_id', 'version']);
        });
        Schema::create('commercial_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->foreignId('service_line_id')->nullable()->constrained('sales_service_lines')->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained('sales_service_packages')->nullOnDelete();
            $table->string('description');
            $table->string('pricing_type', 20);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('billing_period', 30)->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('commercial_quote_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('justification');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['quote_id', 'status']);
        });
        Schema::create('commercial_contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_path');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });
        Schema::create('commercial_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('commercial_quotes')->nullOnDelete();
            $table->foreignId('renewal_opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('commercial_contract_templates')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('title');
            $table->string('status', 24)->default('draft');
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->timestamp('renewal_alert_sent_at')->nullable();
            $table->string('document_path')->nullable();
            $table->boolean('client_contract_visible')->default(false);
            $table->text('sla_terms')->nullable();
            $table->text('deliverables')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'renewal_date']);
            $table->unique('quote_id');
        });
        Schema::create('commercial_onboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('commercial_contracts')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('client_visible')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('commercial_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('commercial_contracts')->cascadeOnDelete();
            $table->foreignId('onboarding_item_id')->nullable()->constrained('commercial_onboarding_items')->nullOnDelete();
            $table->string('name');
            $table->string('document_path');
            $table->boolean('client_visible')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('commercial_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('commercial_contracts')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('commercial_portal_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('commercial_contracts')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['contract_id', 'expires_at', 'revoked_at']);
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'contracts'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'contracts')->delete();
        Schema::dropIfExists('commercial_portal_tokens');
        Schema::dropIfExists('commercial_handovers');
        Schema::dropIfExists('commercial_documents');
        Schema::dropIfExists('commercial_onboarding_items');
        Schema::dropIfExists('commercial_contracts');
        Schema::dropIfExists('commercial_contract_templates');
        Schema::dropIfExists('commercial_quote_approvals');
        Schema::dropIfExists('commercial_quote_lines');
        Schema::dropIfExists('commercial_quotes');
    }
};
