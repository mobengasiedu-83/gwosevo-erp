<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PbxExtension extends Model
{
    protected $fillable = ['connection_id','user_id','extension','queue','availability','activity_at'];
    protected function casts(): array { return ['activity_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function connection() { return $this->belongsTo(PbxConnection::class, 'connection_id'); }
}
