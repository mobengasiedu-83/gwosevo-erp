<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrEmploymentContract extends Model
{
    protected $fillable = ['organization_id','employee_id','reference','contract_type','start_date','end_date','renewal_date','status','terms','created_by'];
    protected function casts(): array { return ['start_date' => 'date','end_date' => 'date','renewal_date' => 'date','terms' => 'encrypted']; }
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function documents() { return $this->hasMany(HrEmployeeDocument::class, 'contract_id'); }
}
