<?php
namespace App\Services\Creative;
use App\Models\{CreativeRequest,User,Task};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
class CreativeAccountability {
 public static function neutralTaskIds():array {return Schema::hasTable('creative_requests')?CreativeRequest::whereNotNull('planned_by')->whereIn('stage',['in_review','delivered'])->whereNotNull('submitted_at')->whereNotNull('task_id')->pluck('task_id')->all():[];}
 public static function independentSignoff(Task $task,User $owner):bool {
  if(!Schema::hasTable('creative_requests'))return false;
  $r=CreativeRequest::where('task_id',$task->id)->whereIn('stage',['completed','archived'])->whereNotNull('completed_at')->where('assignee_id',$owner->id)->where('verified_by',$task->completion_reviewed_by)->first();$verifier=$r?User::find($r->verified_by):null;
  return $r&&$verifier&&$verifier->id!==$owner->id&&CreativeAccess::lead($verifier);
 }
 public static function reviewerMeasure(User $u,Carbon $start,Carbon $end):?array {
  if(!Schema::hasTable('creative_requests'))return null;
  // Review obligations belong to each submitted version, not the latest mutable row.
  $requests=CreativeRequest::whereNotNull('planned_by')->where(fn($q)=>$q->where('reviewer_id',$u->id)->orWhereHas('updates',fn($q)=>$q->where('kind','in_review')->where('metadata->reviewer_id',$u->id)))->with(['updates'=>fn($q)=>$q->whereIn('kind',['in_review','changes_requested','delivered'])->orderBy('id')])->get();
  $obligations=[];
  foreach($requests as $request){
   foreach($request->updates->where('kind','in_review') as $submission){
    $metadata=$submission->metadata??[];
    if((int)($metadata['reviewer_id']??$request->reviewer_id)!==$u->id||empty($metadata['review_due_at']))continue;
    $due=Carbon::parse($metadata['review_due_at']);
    if(!$due->betweenIncluded($start->copy()->startOfDay(),$end->copy()->endOfDay())||$due->isFuture())continue;
    $decision=$request->updates->first(fn($update)=>$update->id>$submission->id&&in_array($update->kind,['changes_requested','delivered'],true)&&(int)($update->metadata['iteration']??-1)===(int)($metadata['iteration']??0));
    $obligations[]=['request'=>$request->id,'timely'=>$decision&&$decision->created_at->lte($due)];
   }
   // Preserve pre-upgrade records that have no versioned submission history.
   if($request->updates->where('kind','in_review')->isEmpty()&&$request->reviewer_id===$u->id&&$request->review_due_at&&$request->review_due_at->betweenIncluded($start->copy()->startOfDay(),$end->copy()->endOfDay())&&$request->review_due_at->lte(now()))
    $obligations[]=['request'=>$request->id,'timely'=>$request->reviewed_at&&$request->reviewed_at->lte($request->review_due_at)];
  }
  if(!$obligations)return null;$timely=count(array_filter($obligations,fn($o)=>$o['timely']));
  return ['id'=>'creative-review-timeliness','name'=>'Manager-assigned Creative Review Timeliness','weight'=>(int)config('creative.review_kpi_weight',10),'achievement'=>round(100*$timely/count($obligations),2),'source_ids'=>array_values(array_unique(array_column($obligations,'request'))),'total'=>count($obligations),'on_time'=>$timely];
 }
 public function reminders():int {
  if(!Schema::hasTable('creative_requests'))return 0;$count=0;$workflow=app(CreativeWorkflow::class);
  CreativeRequest::whereNotIn('stage',['completed','archived'])->chunkById(100,function($rows)use(&$count,$workflow){foreach($rows as $r){
   if($r->stage==='in_review'&&$r->review_due_at?->isPast()){$ids=array_merge([$r->reviewer_id],$workflow->leads());$key='creative-review-overdue:'.$r->id.':'.$r->iteration;$title='Creative review overdue';$message=$r->reference.' is awaiting reviewer action; the creative submission time is retained.';}
   elseif($r->requested_deadline&&$r->requested_deadline->isFuture()&&$r->requested_deadline->lte(now()->addDays(2))&&!$r->first_submitted_at){$ids=array_merge([$r->assignee_id],$workflow->leads());$key='creative-due:'.$r->id.':'.$r->requested_deadline->timestamp;$title='Creative deadline approaching';$message=$r->reference.' has a requested deadline within 48 hours.';}
   else continue;
   $workflow->notify($r,$ids,$title,$message,$key);$count++;
  }});return $count;
 }
}
