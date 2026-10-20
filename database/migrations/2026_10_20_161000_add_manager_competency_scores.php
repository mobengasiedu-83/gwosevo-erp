<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_performance_reviews', function (Blueprint $table) {
            $table->longText('manager_competency_scores')->nullable()->after('competency_scores');
        });
    }

    public function down(): void
    {
        Schema::table('hr_performance_reviews', function (Blueprint $table) {
            $table->dropColumn('manager_competency_scores');
        });
    }
};
