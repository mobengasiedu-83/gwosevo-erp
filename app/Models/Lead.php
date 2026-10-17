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
        'custom_values', 'organization_id', 'reference', 'first_name', 'last_name', 'email', 'phone', 'company_name',
        'industry', 'interests', 'source', 'status', 'owner_id', 'assigned_at', 'qualified_at',
        'budget', 'authority', 'need', 'timeline', 'score', 'next_follow_up_at', 'converted_at',
        'account_id', 'contact_id', 'opportunity_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'custom_values' => 'array', 'assigned_at' => 'datetime', 'qualified_at' => 'datetime', 'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime', 'budget' => 'decimal:2',
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

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
