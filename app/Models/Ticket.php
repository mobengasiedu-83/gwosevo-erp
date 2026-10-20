<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'organization_id', 'reference', 'subject', 'status', 'priority', 'channel', 'category_id', 'contact_id', 'account_id', 'call_record_id',
        'team_id', 'assigned_to_id', 'escalation_owner_id', 'requester_name', 'requester_email', 'requester_phone', 'portal_token_hash', 'portal_token_encrypted',
        'first_response_due_at', 'resolution_due_at', 'first_response_at', 'resolved_at', 'closed_at', 'response_breached_at',
        'resolution_breached_at', 'reopen_count', 'satisfaction_score', 'satisfaction_comment', 'satisfaction_received_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['portal_token_encrypted' => 'encrypted', 'first_response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'response_breached_at' => 'datetime', 'resolution_breached_at' => 'datetime', 'satisfaction_received_at' => 'datetime'];
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function category() { return $this->belongsTo(TicketCategory::class, 'category_id'); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function account() { return $this->belongsTo(Account::class); }
    public function callRecord() { return $this->belongsTo(CallRecord::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function escalationOwner() { return $this->belongsTo(User::class, 'escalation_owner_id'); }
    public function messages() { return $this->hasMany(TicketMessage::class); }
    public function slaEvents() { return $this->hasMany(TicketSlaEvent::class); }
}
