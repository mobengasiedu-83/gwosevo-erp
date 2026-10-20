<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingLandingPage extends Model
{
    protected $fillable = ['organization_id', 'campaign_id', 'slug', 'title', 'description', 'is_published', 'created_by'];
    protected function casts(): array { return ['is_published' => 'boolean']; }
    public function campaign() { return $this->belongsTo(MarketingCampaign::class, 'campaign_id'); }
}
