<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUSES = ['new', 'assigned', 'qualified', 'converted', 'disqualified'];

    protected $fillable = [
        'custom_values', 'organization_id', 'reference', 'first_name', 'last_name', 'email', 'phone', 'phone_normalized', 'marketing_campaign_id', 'marketing_affiliate_id', 'company_name',
        'industry', 'interests', 'source', 'source_detail', 'status', 'owner_id', 'assigned_at', 'qualified_at',
        'budget', 'authority', 'need', 'timeline', 'score', 'next_follow_up_at', 'converted_at',
        'account_id', 'contact_id', 'opportunity_id', 'duplicate_of_id', 'duplicate_reviewed_by', 'duplicate_reviewed_at',
        'fit_score', 'engagement_score', 'disqualified_at', 'disqualification_reason', 'archived_at', 'follow_up_reminder_sent_at', 'follow_up_escalated_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'custom_values' => 'array', 'assigned_at' => 'datetime', 'qualified_at' => 'datetime', 'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime', 'duplicate_reviewed_at' => 'datetime', 'disqualified_at' => 'datetime',
            'archived_at' => 'datetime', 'follow_up_reminder_sent_at' => 'datetime', 'follow_up_escalated_at' => 'datetime', 'budget' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function possibleDuplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function marketingAffiliate(): BelongsTo
    {
        return $this->belongsTo(MarketingAffiliate::class, 'marketing_affiliate_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
