<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['lead_id', 'from_status', 'to_status', 'notes', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
