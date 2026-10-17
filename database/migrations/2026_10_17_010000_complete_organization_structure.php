<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->unique();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->string('timezone')->default('Africa/Accra');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->text('address')->nullable();
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->text('description')->nullable();
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country_code', 2)->nullable();
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('title', 150);
            $table->string('grade', 50)->nullable();
            $table->text('job_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        Schema::create('cost_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('kind', 20)->default('both');
            $table->string('currency', 3)->default('GHS');
            $table->decimal('budget', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        Schema::table('user_organization_memberships', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_manager_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('user_cost_centre_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('user_organization_memberships')->cascadeOnDelete();
            $table->foreignId('cost_centre_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();
            $table->unique(['membership_id', 'cost_centre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cost_centre_allocations');
        Schema::table('user_organization_memberships', function (Blueprint $table) {
            foreach (['branch_id', 'position_id', 'manager_id', 'approval_manager_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropColumn(['branch_id', 'position_id', 'manager_id', 'approval_manager_id']);
        });
        Schema::dropIfExists('cost_centres');
        Schema::dropIfExists('positions');
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['manager_id', 'email', 'city', 'country_code']);
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'description']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['legal_entity_id', 'parent_id', 'address']);
        });
        Schema::dropIfExists('legal_entities');
    }
};
