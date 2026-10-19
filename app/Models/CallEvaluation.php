<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CallEvaluation extends Model
{
    protected $fillable = ['call_record_id','form_id','evaluator_id','scores','score_percent','notes'];
    protected function casts(): array { return ['scores'=>'array']; }
}
