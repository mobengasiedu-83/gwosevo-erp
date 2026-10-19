<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommercialContractTemplate extends Model
{
    protected $fillable = ['organization_id','name','document_path','is_active','created_by'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
}
