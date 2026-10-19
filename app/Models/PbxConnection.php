<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PbxConnection extends Model
{
    protected $fillable = ['organization_id','name','driver','base_url','api_token','webhook_secret','settings','is_active','last_connected_at','created_by'];
    protected function casts(): array { return ['api_token'=>'encrypted','webhook_secret'=>'encrypted','settings'=>'array','is_active'=>'boolean','last_connected_at'=>'datetime']; }
    public function extensions() { return $this->hasMany(PbxExtension::class, 'connection_id'); }
}
