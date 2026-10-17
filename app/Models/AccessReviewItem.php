<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessReviewItem extends Model
{
    protected $fillable = ['access_review_id', 'user_id', 'snapshot', 'decision', 'notes', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'decided_at' => 'datetime'];
    }
}
