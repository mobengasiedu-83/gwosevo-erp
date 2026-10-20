<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSlaPolicy extends Model
{
    protected $fillable = ['organization_id', 'priority', 'response_minutes', 'resolution_minutes', 'escalation_owner_id'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function escalationOwner() { return $this->belongsTo(User::class, 'escalation_owner_id'); }
}
