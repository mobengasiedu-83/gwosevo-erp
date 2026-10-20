<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrJobHistory extends Model
{
    protected $fillable = ['organization_id','employee_id','effective_on','change_type','previous_assignment','new_assignment','reason','recorded_by'];
    protected function casts(): array { return ['effective_on' => 'date','previous_assignment' => 'encrypted:array','new_assignment' => 'encrypted:array','reason' => 'encrypted']; }
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
