<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentitySession extends Model
{
    protected $hidden = ['session_hash'];

    protected $fillable = ['user_id', 'session_hash', 'ip_address', 'user_agent', 'method', 'last_seen_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
