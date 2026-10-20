<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    protected $fillable = ['message_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];
    public function message() { return $this->belongsTo(TicketMessage::class, 'message_id'); }
}
