<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCategory extends Model
{
    protected $fillable = ['organization_id', 'name', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function tickets() { return $this->hasMany(Ticket::class, 'category_id'); }
}
