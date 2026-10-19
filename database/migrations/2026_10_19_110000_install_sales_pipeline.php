<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $createTable = fn (string $name, Closure $definition) => Schema::hasTable($name) ? null : Schema::create($name, $definition);
        $createTable('sales_service_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        $createTable('sales_service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_line_id')->nullable()->constrained('sales_service_lines')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        if (! Schema::hasColumn('opportunities', 'reference')) Schema::table('opportunities', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('service_line_id')->nullable()->constrained('sales_service_lines')->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained('sales_service_packages')->nullOnDelete();
            $table->string('loss_reason')->nullable();
            $table->text('nonstandard_terms')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'stage', 'expected_close_date']);
            $table->index(['organization_id', 'owner_id', 'stage']);
        });
        $createTable('sales_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('name', 100);
            $table->unsignedTinyInteger('probability')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });
        $createTable('opportunity_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['opportunity_id', 'changed_at']);
        });
        $createTable('opportunity_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['opportunity_id', 'due_at', 'completed_at']);
        });
        $createTable('opportunity_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $createTable('opportunity_discount_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->text('terms')->nullable();
            $table->text('justification');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['opportunity_id', 'status']);
        });
        $createTable('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('revenue_target', 15, 2)->default(0);
            $table->decimal('pipeline_target', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'period_start', 'period_end']);
        });
        $createTable('opportunity_forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->date('forecast_month');
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('probability');
            $table->decimal('weighted_amount', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'snapshot_date'], 'opp_forecast_snap_uniq');
            $table->index(['organization_id', 'forecast_month', 'snapshot_date'], 'opp_forecast_month_idx');
        });

        // The opportunity table predates its owner/reference workflow. Assign stable references and
        // record initial stage entries without changing any existing commercial values.
        foreach (DB::table('opportunities')->whereNull('reference')->orderBy('id')->cursor() as $opportunity) {
            DB::table('opportunities')->where('id', $opportunity->id)->update(['reference' => 'OPP-'.str_pad((string) $opportunity->id, 6, '0', STR_PAD_LEFT)]);
            DB::table('opportunity_stage_histories')->insert(['opportunity_id' => $opportunity->id, 'from_stage' => null, 'to_stage' => $opportunity->stage ?: 'new', 'changed_by' => $opportunity->created_by, 'changed_at' => $opportunity->created_at ?: now()]);
        }
        $now = now();
        DB::table('module_settings')->updateOrInsert(['key' => 'sales'], ['enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('module_settings')->where('key', 'sales')->delete();
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'stage', 'expected_close_date']);
            $table->dropIndex(['organization_id', 'owner_id', 'stage']);
            $table->dropForeign(['service_line_id']);
            $table->dropForeign(['service_package_id']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['reference', 'description', 'service_line_id', 'service_package_id', 'loss_reason', 'nonstandard_terms', 'closed_at', 'updated_by']);
        });
        Schema::dropIfExists('sales_targets');
        Schema::dropIfExists('opportunity_forecast_snapshots');
        Schema::dropIfExists('opportunity_discount_approvals');
        Schema::dropIfExists('opportunity_competitors');
        Schema::dropIfExists('opportunity_activities');
        Schema::dropIfExists('opportunity_stage_histories');
        Schema::dropIfExists('sales_pipeline_stages');
        Schema::dropIfExists('sales_service_packages');
        Schema::dropIfExists('sales_service_lines');
    }
};
