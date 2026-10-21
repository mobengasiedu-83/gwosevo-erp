<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 24)->default('pending_approval');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'effective_from', 'effective_until'], 'commercial_price_org_effective_idx');
        });
        Schema::create('commercial_price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('commercial_price_lists')->cascadeOnDelete();
            $table->foreignId('service_line_id')->nullable()->constrained('sales_service_lines')->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained('sales_service_packages')->nullOnDelete();
            $table->string('description', 255);
            $table->string('pricing_type', 20);
            $table->decimal('unit_price', 15, 2);
            $table->string('billing_period', 30)->nullable();
            $table->timestamps();
            $table->index(['price_list_id', 'service_line_id', 'service_package_id'], 'commercial_price_items_lookup_idx');
        });
        Schema::table('commercial_quote_lines', function (Blueprint $table) {
            $table->foreignId('price_list_item_id')->nullable()->after('service_package_id')->constrained('commercial_price_list_items')->nullOnDelete();
        });
        DB::table('module_settings')->updateOrInsert(['key' => 'commercial'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'commercial')->delete();
        Schema::table('commercial_quote_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('price_list_item_id'));
        Schema::dropIfExists('commercial_price_list_items');
        Schema::dropIfExists('commercial_price_lists');
    }
};
