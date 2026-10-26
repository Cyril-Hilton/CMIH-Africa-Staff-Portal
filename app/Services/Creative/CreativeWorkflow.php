<?php
namespace App\Services\Creative;
use App\Models\{CreativeRequest,CreativeUpdate,Task,User,PerformanceEvent};
use App\Services\{PerformanceWorkflow,TaskStatsService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CreativeWorkflow {
 public function create(User $actor,array $d):CreativeRequest {
  abort_unless(CreativeAccess::staff($actor),403);
  return DB::transaction(function()use($actor,$d){$id=(string)Str::uuid();$r=CreativeRequest::create(['uuid'=>$id,'reference'=>'CR-'.strtoupper(substr(str_replace('-','',$id),0,12)),'requester_id'=>$actor->id,'reviewer_id'=>$actor->id,'requester_snapshot'=>CreativeAccess::snapshot($actor),'department'=>User::normalizeDepartmentKey($actor->department),'title'=>$d['title'],'brief'=>$d['brief'],'creative_type'=>$d['creative_type'],'client'=>$d['client']??null,'requested_deadline'=>$d['requested_deadline'],'priority'=>$d['priority'],'visibility'=>$d['visibility']??'shared','received_at'=>now(),'reported_external_at'=>$d['reported_external_at']??null,'stage'=>'brief_received']);
   $this->event($r,$actor,'submitted',[],'Brief submitted in the portal.');if(!empty($d['reference_url']))$r->updates()->create(['author_id'=>$actor->id,'kind'=>'reference','body'=>'Brief reference','reference_url'=>$d['reference_url']]);
   $this->notify($r,$this->leads(),'New creative brief',$r->reference.' has been submitted.');return $r;
  });
 }
 public function split(CreativeRequest $record,User $actor,array $d):CreativeRequest {
  abort_unless(CreativeAccess::lead($actor),403);
  return DB::transaction(function()use($record,$actor,$d){
   $r=$this->lock($record,$d['revision']);abort_if($r->task_id||$r->assignee_id||$r->is_group||$r->parent_request_id||!in_array($r->stage,['backlog','brief_received','needs_info']),422,'Split an unassigned original brief before planning individual tasks.');
   $r->forceFill(['is_group'=>true,'stage'=>'brief_received','revision'=>$r->revision+1])->save();
   foreach($d['parts'] as $part){$uuid=(string)Str::uuid();$child=CreativeRequest::create(['uuid'=>$uuid,'reference'=>'CR-'.strtoupper(substr(str_replace('-','',$uuid),0,12)),'parent_request_id'=>$r->id,'requester_id'=>$r->requester_id,'reviewer_id'=>$r->reviewer_id,'requester_snapshot'=>$r->requester_snapshot,'department'=>$r->department,'title'=>$part['title'],'brief'=>$part['brief'],'creative_type'=>$r->creative_type,'client'=>$r->client,'priority'=>$part['priority']??$r->priority,'visibility'=>$r->visibility,'requested_deadline'=>$part['requested_deadline']??$r->requested_deadline,'received_at'=>$r->received_at,'stage'=>'brief_received']);
    $this->event($child,$actor,'brief_part_created',[],'Work package created from '.$r->reference.'. Original requester and receipt are retained.');
   }
   $this->event($r,$actor,'brief_split',[],$d['reason']);$this->notify($r,[$r->requester_id,...$this->leads()],'Creative brief divided into work packages',$r->reference.' has individual parts for Creative HOD assignment.');return $r;
  });
 }
 public function plan(CreativeRequest $record,User $actor,array $d):CreativeRequest {
  abort_unless(CreativeAccess::lead($actor),403);
  return DB::transaction(function()use($record,$actor,$d){$r=$this->lock($record,$d['revision']);abort_if($r->is_group,422,'Assign the individual brief parts, not the parent brief.');abort_if(in_array($r->stage,['completed','archived','in_review','delivered']),409,'Return work to production or reopen it before replanning.');$before=$r->getAttributes();$assignee=User::humanAccounts()->whereKey($d['assignee_id'])->where('status','active')->firstOrFail();abort_unless(User::normalizeDepartmentKey($assignee->department)==='creatives',422,'Choose an active creative executive.');
   $reviewer=User::findOrFail($d['reviewer_id']);abort_unless(CreativeAccess::lead($reviewer)&&$reviewer->id!==$assignee->id,422,'Choose an active Creative HOD or line manager other than the assignee.');
   $r->fill(collect($d)->only(['assignee_id','reviewer_id','planned_start','internal_eta','estimated_hours','requested_deadline','priority','blocker','blocker_owner'])->all());$r->planned_by=$actor->id;if((int)($before['assignee_id']??0)!==$assignee->id)$r->acknowledged_at=null;$r->assignee_snapshot=CreativeAccess::snapshot($assignee);
   if(!app(CreativeCapacity::class)->workingDates($r->planned_start,$r->internal_eta,$assignee->id))throw ValidationException::withMessages(['planned_start'=>'Choose a work window with an available working day.']);
   $warnings=app(CreativeCapacity::class)->warnings($r);if($warnings&&empty($d['override_reason']))throw ValidationException::withMessages(['override_reason'=>implode(' ',$warnings).' Record an override reason to keep this plan.']);
   if(in_array($r->stage,['backlog','brief_received','needs_info']))$r->stage='scheduled';
   if(!$r->task_id){$task=Task::create(['title'=>$r->title,'details'=>$r->brief,'client_name'=>$r->client,'department'=>'creatives','assigned_to'=>$assignee->id,'assigned_by'=>$actor->id,'due_on'=>$r->requested_deadline,'priority'=>$r->priority,'status'=>'Open','progress'=>10,'completion_review_status'=>null]);$r->task_id=$task->id;}
   else Task::whereKey($r->task_id)->update(['assigned_to'=>$assignee->id,'due_on'=>$r->requested_deadline]);
   if(isset($d['performance_weight'])){
    abort_unless(\App\Services\DeliveryImpact::mayRate($actor,$assignee),403,'Impact weighting requires an independent manager.');
    $weightedTask=Task::findOrFail($r->task_id);$oldWeight=$weightedTask->only(['performance_weight','weight_approved_by','weight_approved_at']);
    $weightedTask->forceFill(['performance_weight'=>$d['performance_weight'],'weight_approved_by'=>$actor->id,'weight_approved_at'=>now()])->save();
    PerformanceWorkflow::record('task',$weightedTask->id,$actor->id,'impact_weighted',$oldWeight,$weightedTask->only(['performance_weight','weight_approved_by','weight_approved_at']),$d['reason']);
   }
   $r->revision++;$r->save();$this->event($r,$actor,'plan_changed',$before,$d['reason'].(!empty($d['override_reason'])?' Override: '.$d['override_reason']:''));$this->notify($r,[$r->assignee_id,$r->requester_id,$r->reviewer_id],'Creative plan updated',$r->reference.' is assigned with a recorded delivery plan.');return $r;
  });
 }
 public function allowed(CreativeRequest $r,User $u):array {
  if(!CreativeAccess::view($u,$r)||$r->is_group)return [];
  $lead=CreativeAccess::lead($u);$owner=CreativeAccess::owner($u,$r);$independent=$lead&&$u->id!==$r->assignee_id;
  if($r->stage==='archived')return $lead?['brief_received']:[];
  $actions=[];
  if($owner&&($lead||!in_array($r->stage,['in_review','delivered','completed'],true))){
   $actions=['brief_received','scheduled','in_progress'];
   if($r->stage==='in_progress'&&$r->assignee_id&&$r->planned_start&&$r->internal_eta)$actions[]='in_review';
  }
  if($lead)$actions[]='needs_info';
  if($r->stage==='needs_info'&&$u->id===$r->requester_id)$actions[]='brief_received';
  if($independent&&$r->stage==='in_review'&&$r->submitted_at)$actions[]='delivered';
  if(($lead&&in_array($r->stage,['in_review','delivered'],true))||($u->id===$r->requester_id&&$u->id!==$r->assignee_id&&$r->stage==='delivered'))$actions[]='changes_requested';
  if($independent&&$r->stage==='delivered')$actions[]='completed';
  if($lead&&$r->stage!=='in_review')$actions[]='archived';
  return array_values(array_unique(array_filter($actions,fn($stage)=>$stage!==$r->stage)));
 }
 public function transition(CreativeRequest $record,User $actor,array $d):CreativeRequest {
  return DB::transaction(function()use($record,$actor,$d){$r=$this->lock($record,$d['revision']);abort_unless(in_array($d['stage'],$this->allowed($r,$actor),true),403,'This move is not available to your role from the current stage.');$before=$r->getAttributes();$next=$d['stage'];if($next==='in_progress'&&in_array($r->stage,['in_review','delivered'],true))$next='changes_requested';if(in_array($next,['scheduled','in_progress','in_review'])&&(!$r->assignee_id||!$r->planned_start||!$r->internal_eta))throw ValidationException::withMessages(['assignee_id'=>'The Creative HOD must assign a creative and plan the work before production.']);
   if(in_array($next,['needs_info','changes_requested','archived'])||($next==='in_progress'&&$r->stage==='completed')){if(empty($d['reason']))throw ValidationException::withMessages(['reason'=>'Explain this change so the team can act on it.']);}
   if($next==='in_review'){
    $reviewLead=$r->reviewer;
    if(!$reviewLead||!CreativeAccess::lead($reviewLead)||$reviewLead->id===$r->assignee_id)throw ValidationException::withMessages(['reviewer_id'=>'The Creative HOD must assign an independent HOD or line manager reviewer before submission.']);
    $evidence=$r->attachments()->where('kind','deliverable')->where('iteration','>=',$r->iteration)->exists()||!empty($d['reference_url'])||(in_array($r->stage,['delivered','completed'])&&$r->attachments()->where('kind','deliverable')->exists());
    if(!$evidence)throw ValidationException::withMessages(['reference_url'=>'Attach a deliverable or add its delivery link. A status change alone is not evidence.']);
    $r->iteration++;$r->submitted_at=now();$r->first_submitted_at??=now();$r->review_due_at=app(CreativeCapacity::class)->reviewDue(now());$r->reviewed_at=null;
   }
   if($next==='changes_requested'){
    if(empty($d['feedback_type'])||!in_array($d['feedback_type'],['correction','scope_change','clarification']))throw ValidationException::withMessages(['feedback_type'=>'Identify whether this is a correction, new scope or clarification.']);$r->reviewed_at=now();
   }
   if($next==='delivered'){$r->reviewed_at=now();$r->delivered_at=now();}
   if($next==='completed'){
    if(empty($d['client_approved'])||empty(trim($d['reason']??'')))throw ValidationException::withMessages(['client_approved'=>'Confirm client approval and record the approval details before completion.']);
    if($d['quality_score']===null)throw ValidationException::withMessages(['quality_score'=>'Record a quality score before final verification.']);
    $r->quality_score=$d['quality_score'];$r->completed_at=now();$r->verified_by=$actor->id;
   }
   if($r->stage==='completed'&&!in_array($next,['completed','archived'])){$r->completed_at=null;$r->verified_by=null;$r->quality_score=null;}
   if($next==='archived')$r->archived_at=now();if($r->stage==='archived')$r->archived_at=null;
   $action=$next;$r->stage=$next==='changes_requested'?'in_progress':$next;$r->revision++;$r->save();$r->updates()->create(['author_id'=>$actor->id,'kind'=>$action,'body'=>$d['reason']?:config('creative.stages.'.$next),'reference_url'=>$d['reference_url']??null,'metadata'=>['client_approved'=>$next==='completed'?(bool)($d['client_approved']??false):null,'client_approval_recorded_by'=>$next==='completed'?$actor->id:null,'iteration'=>$r->iteration,'requested_deadline'=>$r->requested_deadline?->toIso8601String(),'reviewer_id'=>$r->reviewer_id,'submitted_at'=>$r->submitted_at?->toIso8601String(),'feedback_type'=>$d['feedback_type']??null,'review_due_at'=>$r->review_due_at?->toIso8601String()]]);
   $this->syncTask($r,$actor);if($r->parent_request_id){$parent=CreativeRequest::lockForUpdate()->findOrFail($r->parent_request_id);$allDone=$parent->parts()->whereNotIn('stage',['completed'])->doesntExist();$parent->stage=$allDone?'completed':'in_progress';$parent->completed_at=$allDone?now():null;$parent->revision++;$parent->save();$this->event($parent,$actor,'parts_progress_updated',[],$allDone?'All work packages independently verified.':'Work package progress updated.');}$this->event($r,$actor,'stage_changed',$before,$d['reason']?:'Workflow updated.');$this->notify($r,array_merge([$r->requester_id,$r->assignee_id,$r->reviewer_id],$this->leads()),'Creative work: '.config('creative.stages.'.$action),$r->reference.' needs attention. Open the shared timeline for details.');return $r;
  });
 }
 private function syncTask(CreativeRequest $r,User $actor):void {
  if(!$r->task_id||$r->stage==='archived')return;
  $verified=$r->stage==='completed';$review=in_array($r->stage,['in_review','delivered']);
  $task=Task::findOrFail($r->task_id);$task->fill(['status'=>$verified?'Completed':($review?'Awaiting Approval':($r->stage==='backlog'?'Deferred':(in_array($r->stage,['brief_received','scheduled','needs_info'])?'Open':'In Progress'))),'progress'=>$verified?100:($review?95:($r->stage==='in_progress'?50:10)),'completion_review_status'=>$verified?'approved':($review?'pending':null),'completion_review_requested_at'=>$r->submitted_at,'completion_reviewed_at'=>$verified?now():null,'completion_reviewed_by'=>$verified?$actor->id:null,'completion_review_note'=>$verified?'Creative Studio independent verification.':null]);
  $custom=$task->custom_fields??[];$custom['creative_studio_id']=$r->id;if($verified)$custom['completion_manager_id']=$actor->id;$task->custom_fields=$custom;$task->save();
 }
 private function lock(CreativeRequest $r,int $revision):CreativeRequest {$r=CreativeRequest::lockForUpdate()->findOrFail($r->id);abort_if($r->revision!==$revision,409,'This work changed in another session. Refresh before saving.');return $r;}
 public function event(CreativeRequest $r,User $actor,string $action,array $before,string $reason):void {PerformanceWorkflow::record('creative_request',$r->id,$actor->id,$action,$before,$r->getAttributes(),$reason);}
 public function leads():array{return User::humanAccounts()->where('status','active')->get()->filter(fn($u)=>CreativeAccess::lead($u))->pluck('id')->all();}
 public function notify(CreativeRequest $r,array $ids,string $title,string $message,?string $key=null):void {
  foreach(array_unique(array_filter($ids)) as $id){
   $dedupe=$key??'creative:'.$r->id.':'.$r->revision.':'.$title;
   PerformanceWorkflow::notify((int)$id,$dedupe,$title,$message,route('portal.creative',['request'=>$r->id]));
   $outbox=\App\Models\PerformanceOutbox::where('dedupe_key',hash('sha256',$dedupe.':'.$id))->lockForUpdate()->firstOrFail();
   if(!$outbox->dashboard_at){\App\Services\NotificationService::send((int)$id,$title,$message,$outbox->url);$outbox->update(['dashboard_at'=>now()]);}
  }
  if(config('creative.email_enabled')) \App\Jobs\DeliverCreativeNotifications::dispatch($r->id)->onConnection('database')->afterCommit();
 }

}
