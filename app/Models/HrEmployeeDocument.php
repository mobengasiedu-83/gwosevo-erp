<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrEmployeeDocument extends Model
{
    protected $fillable = ['organization_id','employee_id','contract_id','category','original_name','mime_type','size_bytes','storage_path','uploaded_by'];
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function contract() { return $this->belongsTo(HrEmploymentContract::class, 'contract_id'); }
}
