<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->boolean('is_reconciled')->default(false)->after('status');
            $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
            $table->string('reconciliation_reference', 100)->nullable()->after('reconciled_at');
        });
    }

    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->dropColumn(['is_reconciled', 'reconciled_at', 'reconciliation_reference']);
        });
    }
};
