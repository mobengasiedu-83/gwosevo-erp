<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrDisciplinaryCase extends Model
{
    protected $fillable = ['organization_id','employee_id','opened_on','category','status','details','resolution','created_by'];
    protected function casts(): array { return ['opened_on' => 'date','details' => 'encrypted','resolution' => 'encrypted']; }
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
}
