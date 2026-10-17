<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessReview extends Model
{
    protected $fillable = ['name', 'reviewer_id', 'created_by', 'due_at', 'completed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
