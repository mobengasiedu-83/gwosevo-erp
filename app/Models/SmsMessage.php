<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = ['organization_id', 'campaign_id', 'contact_id', 'ticket_id', 'to', 'sender', 'body', 'status', 'idempotency_key', 'provider_message_id', 'attempts', 'provider_response', 'failure_reason', 'scheduled_at', 'submitted_at', 'delivered_at', 'created_by'];

    protected function casts(): array
    {
        return ['provider_response' => 'array', 'scheduled_at' => 'datetime', 'submitted_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
