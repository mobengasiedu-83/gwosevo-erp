<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSlaEvent extends Model
{
    protected $fillable = ['ticket_id', 'type', 'occurred_at', 'notified_user_id'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    public function ticket() { return $this->belongsTo(Ticket::class); }
}
