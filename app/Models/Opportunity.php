<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    protected $fillable = ['organization_id', 'reference', 'account_id', 'contact_id', 'lead_id', 'name', 'description', 'stage', 'amount', 'probability', 'expected_close_date', 'owner_id', 'service_line_id', 'service_package_id', 'loss_reason', 'nonstandard_terms', 'closed_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expected_close_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function serviceLine(): BelongsTo { return $this->belongsTo(SalesServiceLine::class, 'service_line_id'); }
    public function servicePackage(): BelongsTo { return $this->belongsTo(SalesServicePackage::class, 'service_package_id'); }
    public function activities(): HasMany { return $this->hasMany(OpportunityActivity::class); }
    public function stageHistory(): HasMany { return $this->hasMany(OpportunityStageHistory::class); }
    public function competitors(): HasMany { return $this->hasMany(OpportunityCompetitor::class); }
    public function discountApprovals(): HasMany { return $this->hasMany(OpportunityDiscountApproval::class); }
    public function forecastSnapshots(): HasMany { return $this->hasMany(OpportunityForecastSnapshot::class); }
}
