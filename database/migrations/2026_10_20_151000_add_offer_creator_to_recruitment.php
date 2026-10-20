<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hr_offers', 'created_by')) {
            Schema::table('hr_offers', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hr_offers', 'created_by')) {
            Schema::table('hr_offers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
