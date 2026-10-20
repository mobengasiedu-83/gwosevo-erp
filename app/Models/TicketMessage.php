<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_id', 'author_name', 'author_email', 'visibility', 'channel', 'body'];
    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function attachments() { return $this->hasMany(TicketAttachment::class, 'message_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
