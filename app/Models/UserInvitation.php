<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInvitation extends Model
{
    protected $hidden = ['token_hash'];

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'invited_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
