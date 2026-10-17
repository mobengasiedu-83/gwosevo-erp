<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDelegation extends Model
{
    protected $fillable = ['organization_id', 'delegator_id', 'delegate_id', 'permission', 'starts_at', 'ends_at', 'revoked_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
