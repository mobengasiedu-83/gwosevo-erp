<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CallEvaluation extends Model
{
    protected $fillable = ['call_record_id','form_id','evaluator_id','scores','score_percent','notes'];
    protected function casts(): array { return ['scores'=>'array']; }
    public function callRecord() { return $this->belongsTo(CallRecord::class, 'call_record_id'); }
    public function form() { return $this->belongsTo(CallEvaluationForm::class, 'form_id'); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluator_id'); }
}
