<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreativeRequest extends Model {
 protected $guarded=['id'];
 protected $attributes=['revision'=>1,'iteration'=>0,'stage'=>'brief_received','legacy'=>false,'priority'=>'Medium','visibility'=>'shared'];
 protected function casts():array {return array_merge(['requester_snapshot'=>'array','assignee_snapshot'=>'array','legacy'=>'boolean','revision'=>'integer','iteration'=>'integer','estimated_hours'=>'float','quality_score'=>'float'],array_fill_keys(['received_at','acknowledged_at','reported_external_at','source_recorded_at','requested_deadline','planned_start','internal_eta','first_submitted_at','submitted_at','review_due_at','reviewed_at','delivered_at','completed_at','archived_at'],'datetime'));}
 public function requester(){return $this->belongsTo(User::class,'requester_id');} public function assignee(){return $this->belongsTo(User::class,'assignee_id');} public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');} public function task(){return $this->belongsTo(Task::class);}
 public function firstSubmission(){return $this->hasOne(CreativeUpdate::class)->ofMany(['id'=>'min'],fn($q)=>$q->where('kind','in_review'));}
 public function updates(){return $this->hasMany(CreativeUpdate::class)->orderBy('id');} public function attachments(){return $this->hasMany(CreativeAttachment::class)->latest('id');}
 public function scopeVisibleTo($query,User $user){if(\App\Services\Creative\CreativeAccess::lead($user))return $query;return $query->where(fn($q)=>$q->where('visibility','shared')->orWhere('requester_id',$user->id)->orWhere('reviewer_id',$user->id)->orWhere('assignee_id',$user->id));}
}
