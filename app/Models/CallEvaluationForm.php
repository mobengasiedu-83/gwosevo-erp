<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CallEvaluationForm extends Model
{
    protected $fillable = ['organization_id','name','criteria','is_active','created_by'];
    protected function casts(): array { return ['criteria'=>'array','is_active'=>'boolean']; }
}
