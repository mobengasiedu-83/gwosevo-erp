<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_tax_codes', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('code', 30); $table->string('name', 120);
            $table->decimal('rate_percent', 7, 4)->default(0); $table->boolean('is_inclusive')->default(false); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['organization_id','code']);
        });
        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('reference', 40); $table->string('customer_name', 180); $table->string('customer_email', 180)->nullable();
            $table->string('currency', 3)->default('GHS'); $table->date('invoice_date'); $table->date('due_date'); $table->decimal('subtotal', 14, 2)->default(0); $table->decimal('tax_total', 14, 2)->default(0); $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 24)->default('draft'); $table->text('notes')->nullable(); $table->string('service_name', 160)->nullable(); $table->string('department_name', 120)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable(); $table->timestamp('issued_at')->nullable(); $table->timestamps(); $table->unique(['organization_id','reference']);
            $table->index(['organization_id','status','due_date'],'finance_invoice_receivables_idx');
        });
        Schema::create('finance_invoice_lines', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_id')->constrained('finance_invoices')->cascadeOnDelete(); $table->string('description', 240); $table->decimal('quantity', 10, 2)->default(1); $table->decimal('unit_price', 14, 2); $table->foreignId('tax_code_id')->nullable()->constrained('finance_tax_codes')->nullOnDelete();
            $table->decimal('net_amount', 14, 2); $table->decimal('tax_amount', 14, 2)->default(0); $table->decimal('gross_amount', 14, 2); $table->timestamps();
        });
        Schema::create('finance_invoice_approvals', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_id')->constrained('finance_invoices')->cascadeOnDelete(); $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('status', 20)->default('pending'); $table->text('request_note')->nullable(); $table->text('decision_note')->nullable(); $table->timestamp('decided_at')->nullable(); $table->timestamps();
            $table->index(['invoice_id','status']);
        });
        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('reference', 80)->nullable(); $table->string('customer_name', 180); $table->string('currency', 3)->default('GHS'); $table->date('paid_on'); $table->decimal('amount', 14, 2); $table->string('method', 40)->nullable(); $table->string('status', 20)->default('posted'); $table->text('memo')->nullable(); $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['organization_id','paid_on','status'],'finance_payment_date_idx');
        });
        Schema::create('finance_payment_allocations', function (Blueprint $table) {
            $table->id(); $table->foreignId('payment_id')->constrained('finance_payments')->cascadeOnDelete(); $table->foreignId('invoice_id')->constrained('finance_invoices')->cascadeOnDelete(); $table->decimal('amount', 14, 2); $table->timestamps(); $table->unique(['payment_id','invoice_id']);
        });
        Schema::create('finance_credit_notes', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('invoice_id')->constrained('finance_invoices')->restrictOnDelete(); $table->string('reference', 40); $table->string('reason', 240); $table->decimal('subtotal', 14, 2); $table->decimal('tax_total', 14, 2); $table->decimal('total', 14, 2); $table->string('status', 20)->default('draft'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('approved_at')->nullable(); $table->timestamp('issued_at')->nullable(); $table->timestamps(); $table->unique(['organization_id','reference']);
        });
        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('claimant_id')->constrained('users')->cascadeOnDelete(); $table->string('reference', 40); $table->string('description', 240); $table->string('category', 100)->nullable(); $table->string('currency', 3)->default('GHS'); $table->date('spent_on'); $table->decimal('amount', 14, 2); $table->string('status', 24)->default('draft'); $table->text('notes')->nullable(); $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('decided_at')->nullable(); $table->timestamp('reimbursed_at')->nullable(); $table->timestamps(); $table->unique(['organization_id','reference']); $table->index(['organization_id','status','spent_on'],'finance_expense_review_idx');
        });
        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name', 160); $table->string('department_name', 120)->nullable(); $table->string('service_name', 160)->nullable(); $table->string('currency', 3)->default('GHS'); $table->date('starts_on'); $table->date('ends_on'); $table->decimal('amount', 14, 2); $table->string('status', 20)->default('active'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['organization_id','starts_on','ends_on'],'finance_budget_period_idx');
        });
        Schema::create('finance_recurring_schedules', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('customer_name', 180); $table->string('customer_email', 180)->nullable(); $table->string('description', 240); $table->decimal('quantity', 10, 2)->default(1); $table->decimal('unit_price', 14, 2); $table->string('currency', 3)->default('GHS'); $table->foreignId('tax_code_id')->nullable()->constrained('finance_tax_codes')->nullOnDelete(); $table->string('frequency', 20); $table->date('next_invoice_on'); $table->date('ends_on')->nullable(); $table->boolean('is_active')->default(true); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['is_active','next_invoice_on'],'finance_recurring_due_idx');
        });
        Schema::create('finance_approval_limits', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('role_name', 100); $table->string('currency', 3)->default('GHS'); $table->decimal('maximum_amount', 14, 2); $table->timestamps(); $table->unique(['organization_id','role_name','currency'],'finance_limit_org_role_ccy_uq');
        });
        foreach (['credit_note'=>'CRN-','expense'=>'EXP-'] as $key=>$prefix) DB::table('number_sequences')->updateOrInsert(['key'=>$key],['prefix'=>$prefix,'padding'=>5,'next_number'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('module_settings')->updateOrInsert(['key'=>'finance'],['enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key','finance')->delete();
        DB::table('number_sequences')->whereIn('key',['credit_note','expense'])->delete();
        foreach (['finance_approval_limits','finance_recurring_schedules','finance_budgets','finance_expenses','finance_credit_notes','finance_payment_allocations','finance_payments','finance_invoice_approvals','finance_invoice_lines','finance_invoices','finance_tax_codes'] as $table) Schema::dropIfExists($table);
    }
};
