<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('commercial_contracts','client_contract_visible')) Schema::table('commercial_contracts',fn(Blueprint $table)=>$table->boolean('client_contract_visible')->default(false)->after('document_path'));
    }
    public function down(): void
    {
        if (Schema::hasColumn('commercial_contracts','client_contract_visible')) Schema::table('commercial_contracts',fn(Blueprint $table)=>$table->dropColumn('client_contract_visible'));
    }
};
