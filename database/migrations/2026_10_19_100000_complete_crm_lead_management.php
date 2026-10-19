<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_detail')->nullable()->after('source');
            $table->string('phone_normalized', 32)->nullable()->after('phone');
            $table->unsignedTinyInteger('fit_score')->default(0)->after('score');
            $table->unsignedTinyInteger('engagement_score')->default(0)->after('fit_score');
            $table->foreignId('duplicate_of_id')->nullable()->after('opportunity_id')->constrained('leads')->nullOnDelete();
            $table->foreignId('duplicate_reviewed_by')->nullable()->after('duplicate_of_id')->constrained('users')->nullOnDelete();
            $table->timestamp('duplicate_reviewed_at')->nullable()->after('duplicate_reviewed_by');
            $table->timestamp('disqualified_at')->nullable()->after('converted_at');
            $table->string('disqualification_reason')->nullable()->after('disqualified_at');
            $table->timestamp('archived_at')->nullable()->after('disqualification_reason');
            $table->timestamp('follow_up_reminder_sent_at')->nullable()->after('archived_at');
            $table->timestamp('follow_up_escalated_at')->nullable()->after('follow_up_reminder_sent_at');
            $table->index(['organization_id', 'phone_normalized']);
            $table->index(['organization_id', 'next_follow_up_at', 'status']);
            $table->index(['organization_id', 'archived_at']);
        });

        DB::table('leads')->select('id', 'phone')->whereNotNull('phone')->orderBy('id')->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                DB::table('leads')->where('id', $lead->id)->update(['phone_normalized' => preg_replace('/\D+/', '', $lead->phone) ?: null]);
            }
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('completed_at');
            $table->timestamp('escalated_at')->nullable()->after('reminder_sent_at');
            $table->index(['due_at', 'completed_at', 'reminder_sent_at']);
        });

        Schema::create('lead_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source')->nullable();
            $table->string('industry')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'priority']);
        });

        Schema::create('lead_assignment_cursors', function (Blueprint $table) {
            $table->foreignId('organization_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('last_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_capture_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 128);
            $table->char('payload_hash', 64);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_capture_keys');
        Schema::dropIfExists('lead_assignment_cursors');
        Schema::dropIfExists('lead_assignment_rules');
        // Keep notifications: it may have pre-existed and is shared by Laravel features.
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex(['due_at', 'completed_at', 'reminder_sent_at']);
            $table->dropColumn(['reminder_sent_at', 'escalated_at']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'next_follow_up_at', 'status']);
            $table->dropIndex(['organization_id', 'phone_normalized']);
            $table->dropIndex(['organization_id', 'archived_at']);
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropConstrainedForeignId('duplicate_reviewed_by');
            $table->dropColumn(['source_detail', 'phone_normalized', 'fit_score', 'engagement_score', 'duplicate_reviewed_at', 'disqualified_at', 'disqualification_reason', 'archived_at', 'follow_up_reminder_sent_at', 'follow_up_escalated_at']);
        });
    }
};
