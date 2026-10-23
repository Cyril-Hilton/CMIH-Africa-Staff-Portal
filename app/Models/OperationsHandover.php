<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OperationsHandover extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['resources'=>'array','attendees'=>'array',...array_fill_keys(['event_at','setup_due_at','acknowledged_at','meeting_scheduled_at','meeting_held_at','cr_signoff_at','ops_signoff_at','submitted_at','accepted_at','verified_at'],'datetime')];}
 public function requester(){return $this->belongsTo(User::class,'requester_id');}
 public function lead(){return $this->belongsTo(User::class,'lead_id');}
 public function project(){return $this->belongsTo(CrmPipelineProject::class,'project_id');}
 public function task(){return $this->belongsTo(Task::class,'task_id');}
}
