<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_suppliers', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->string('code', 40); $t->string('name', 180); $t->string('email')->nullable(); $t->string('phone', 50)->nullable(); $t->text('address')->nullable(); $t->string('tax_number', 80)->nullable(); $t->text('due_diligence_notes')->nullable(); $t->string('status', 20)->default('active'); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->unique(['organization_id','code']); $t->index(['organization_id','status']);
        });
        Schema::create('procurement_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->string('reference', 40); $t->string('title', 180); $t->text('description')->nullable(); $t->string('department_name', 120)->nullable(); $t->date('needed_by')->nullable(); $t->string('currency',3)->default('GHS'); $t->decimal('estimated_total',14,2); $t->string('status',24)->default('pending_approval'); $t->text('decision_note')->nullable(); $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete(); $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('decided_at')->nullable(); $t->timestamps(); $t->unique(['organization_id','reference']); $t->index(['organization_id','status']);
        });
        Schema::create('procurement_request_approvals', function (Blueprint $t) {
            $t->id(); $t->foreignId('request_id')->constrained('procurement_requests')->cascadeOnDelete(); $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete(); $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('status',20)->default('pending'); $t->text('decision_note')->nullable(); $t->timestamp('decided_at')->nullable(); $t->timestamps(); $t->index(['request_id','status']);
        });
        Schema::create('procurement_stock_locations', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->string('code',40); $t->string('name',140); $t->string('description')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique(['organization_id','code']);
        });
        Schema::create('procurement_stock_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->string('sku',60); $t->string('name',180); $t->string('category',100)->nullable(); $t->string('unit',30)->default('each'); $t->decimal('reorder_level',12,2)->default(0); $t->boolean('is_active')->default(true); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->unique(['organization_id','sku']);
        });
        Schema::create('procurement_purchase_orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->foreignId('request_id')->constrained('procurement_requests')->restrictOnDelete(); $t->foreignId('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete(); $t->string('reference',40); $t->string('currency',3); $t->decimal('total',14,2)->default(0); $t->string('status',24)->default('issued'); $t->date('ordered_on'); $t->date('expected_on')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->unique(['organization_id','reference']); $t->index(['organization_id','status']);
        });
        Schema::create('procurement_purchase_order_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->constrained('procurement_purchase_orders')->cascadeOnDelete(); $t->foreignId('stock_item_id')->nullable()->constrained('procurement_stock_items')->nullOnDelete(); $t->string('description',240); $t->decimal('quantity',12,2); $t->decimal('unit_price',14,2); $t->decimal('received_quantity',12,2)->default(0); $t->decimal('line_total',14,2); $t->timestamps();
        });
        Schema::create('procurement_goods_receipts', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->foreignId('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete(); $t->string('reference',40); $t->date('received_on'); $t->text('notes')->nullable(); $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->unique(['organization_id','reference']);
        });
        Schema::create('procurement_goods_receipt_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('receipt_id')->constrained('procurement_goods_receipts')->cascadeOnDelete(); $t->foreignId('purchase_order_line_id')->constrained('procurement_purchase_order_lines')->restrictOnDelete(); $t->foreignId('location_id')->nullable()->constrained('procurement_stock_locations')->nullOnDelete(); $t->decimal('quantity',12,2); $t->string('discrepancy_note')->nullable(); $t->timestamps();
        });
        Schema::create('procurement_stock_balances', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->foreignId('stock_item_id')->constrained('procurement_stock_items')->cascadeOnDelete(); $t->foreignId('location_id')->constrained('procurement_stock_locations')->cascadeOnDelete(); $t->decimal('quantity',12,2)->default(0); $t->timestamps(); $t->unique(['stock_item_id','location_id']);
        });
        Schema::create('procurement_stock_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->foreignId('stock_item_id')->constrained('procurement_stock_items')->restrictOnDelete(); $t->foreignId('from_location_id')->nullable()->constrained('procurement_stock_locations')->nullOnDelete(); $t->foreignId('to_location_id')->nullable()->constrained('procurement_stock_locations')->nullOnDelete(); $t->string('movement_type',24); $t->decimal('quantity',12,2); $t->string('reference',100)->nullable(); $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->index(['organization_id','stock_item_id','created_at'], 'proc_stock_moves_org_item_created_idx');
        });
        Schema::create('procurement_assets', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->cascadeOnDelete(); $t->string('asset_tag',60); $t->string('name',180); $t->string('category',100)->nullable(); $t->string('serial_number',120)->nullable(); $t->date('acquired_on')->nullable(); $t->decimal('purchase_cost',14,2)->nullable(); $t->string('currency',3)->default('GHS'); $t->string('location',160)->nullable(); $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $t->string('status',24)->default('available'); $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->unique(['organization_id','asset_tag']); $t->index(['organization_id','status']);
        });
        Schema::create('procurement_asset_maintenance', function (Blueprint $t) {
            $t->id(); $t->foreignId('asset_id')->constrained('procurement_assets')->cascadeOnDelete(); $t->string('description',240); $t->date('scheduled_on')->nullable(); $t->date('completed_on')->nullable(); $t->decimal('cost',14,2)->nullable(); $t->string('status',20)->default('scheduled'); $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('procurement_asset_disposals', function (Blueprint $t) {
            $t->id(); $t->foreignId('asset_id')->constrained('procurement_assets')->cascadeOnDelete(); $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete(); $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('status',20)->default('pending_approval'); $t->text('reason'); $t->text('decision_note')->nullable(); $t->timestamp('decided_at')->nullable(); $t->timestamps();
        });
        foreach (['purchase_request'=>'PR-','purchase_order'=>'PO-','goods_receipt'=>'GRN-','supplier'=>'SUP-','asset'=>'AST-'] as $key=>$prefix) DB::table('number_sequences')->updateOrInsert(['key'=>$key],['prefix'=>$prefix,'padding'=>5,'next_number'=>1,'created_at'=>now(),'updated_at'=>now()]);
        if (Schema::hasTable('module_settings')) DB::table('module_settings')->updateOrInsert(['key'=>'procurement'],['enabled'=>true,'updated_at'=>now(),'created_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('number_sequences')->whereIn('key',['purchase_request','purchase_order','goods_receipt','supplier','asset'])->delete();
        if (Schema::hasTable('module_settings')) DB::table('module_settings')->where('key','procurement')->update(['enabled'=>false,'updated_at'=>now()]);
        foreach (['procurement_asset_disposals','procurement_asset_maintenance','procurement_assets','procurement_stock_movements','procurement_stock_balances','procurement_goods_receipt_lines','procurement_goods_receipts','procurement_purchase_order_lines','procurement_purchase_orders','procurement_stock_items','procurement_stock_locations','procurement_request_approvals','procurement_requests','procurement_suppliers'] as $table) Schema::dropIfExists($table);
    }
};
