<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrLifecycleTask extends Model
{
    protected $fillable = ['organization_id','employee_id','phase','title','notes','due_on','assigned_to','completed_at','created_by'];
    protected function casts(): array { return ['due_on' => 'date','completed_at' => 'datetime']; }
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
