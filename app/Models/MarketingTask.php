<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingTask extends Model
{
    protected $fillable = ['organization_id', 'campaign_id', 'subject', 'description', 'due_at', 'assigned_to_id', 'created_by', 'completed_at'];
    protected function casts(): array { return ['due_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_id'); }
}
