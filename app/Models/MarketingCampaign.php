<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    protected $fillable = ['organization_id', 'name', 'objective', 'channel', 'status', 'starts_on', 'ends_on', 'budget', 'actual_spend', 'audience_rules', 'owner_id', 'created_by'];

    protected function casts(): array { return ['starts_on' => 'date', 'ends_on' => 'date', 'budget' => 'decimal:2', 'actual_spend' => 'decimal:2', 'audience_rules' => 'array']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks(): HasMany { return $this->hasMany(MarketingTask::class, 'campaign_id'); }
    public function landingPages(): HasMany { return $this->hasMany(MarketingLandingPage::class, 'campaign_id'); }
}
