<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    protected $fillable = ['organization_id', 'name', 'body', 'status', 'scheduled_at', 'started_at', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function messages(): HasMany { return $this->hasMany(SmsMessage::class, 'campaign_id'); }
}
