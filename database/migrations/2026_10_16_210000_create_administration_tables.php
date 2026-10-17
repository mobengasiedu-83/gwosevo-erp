<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
        Schema::create('module_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
        Schema::create('reference_values', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('code', 40);
            $table->string('label', 150);
            $table->string('parent_code', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'code']);
        });
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 40);
            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['record_type', 'key']);
        });
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('prefix', 20);
            $table->unsignedSmallInteger('padding')->default(5);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
        foreach (['lead' => 'LD-', 'customer' => 'CUS-', 'ticket' => 'TKT-', 'quotation' => 'QUO-', 'invoice' => 'INV-'] as $key => $prefix) {
            DB::table('number_sequences')->insert(['key' => $key, 'prefix' => $prefix, 'padding' => 5, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('leads', fn (Blueprint $table) => $table->json('custom_values')->nullable());
        Schema::table('accounts', fn (Blueprint $table) => $table->string('reference')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('accounts', fn (Blueprint $table) => $table->dropColumn('reference'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('custom_values'));
        foreach (['number_sequences', 'custom_fields', 'reference_values', 'module_settings', 'system_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
