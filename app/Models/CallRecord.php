<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CallRecord extends Model
{
    protected $fillable = ['organization_id','connection_id','external_id','direction','status','from_number','to_number','queue','agent_id','contact_id','account_id','started_at','answered_at','ended_at','wait_seconds','duration_seconds','disposition','notes','follow_up_at','follow_up_owner_id','recording_reference','recording_expires_at','created_by'];
    protected function casts(): array { return ['started_at'=>'datetime','answered_at'=>'datetime','ended_at'=>'datetime','follow_up_at'=>'datetime','recording_expires_at'=>'datetime']; }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
    public function account() { return $this->belongsTo(Account::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function evaluations() { return $this->hasMany(CallEvaluation::class); }
}
