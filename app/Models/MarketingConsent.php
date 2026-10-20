<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingConsent extends Model
{
    protected $fillable = ['organization_id', 'lead_id', 'channel', 'destination', 'status', 'source', 'consented_at', 'withdrawn_at', 'updated_by'];
    protected function casts(): array { return ['consented_at' => 'datetime', 'withdrawn_at' => 'datetime']; }
}
