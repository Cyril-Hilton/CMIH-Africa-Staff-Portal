<?php
namespace App\Services;
use App\Models\{OperationsHandover,User,Task,PerformanceOutbox};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class OperationsHandoverWorkflow {
 public static function ops(User $u):bool{return $u->isActive()&&User::normalizeDepartmentKey($u->department)==='operations_projects';}
 public static function lead(User $u):bool{return $u->isActive()&&PerformanceAccess::manages($u,'operations_projects');}
 public static function sender(User $u,OperationsHandover $h):bool{return ClientRelationsPipeline::member($u)&&($u->isLineManager()||(int)$h->requester_id===$u->id||(int)$h->project?->owner_id===$u->id);}
 public function allowed(User $u,OperationsHandover $h):array {
  $actions=[];$ops=self::lead($u)||(self::ops($u)&&(int)$h->lead_id===$u->id);$sender=self::sender($u,$h);$resourceOwner=self::ops($u)&&collect($h->resources??[])->contains(fn($r)=>(int)$r['owner_id']===$u->id);
  if(self::lead($u)&&!in_array($h->stage,['completed','cancelled']))$actions[]='assign';
  if($ops&&$h->stage==='submitted')$actions[]='acknowledge';
  if(($ops||$sender)&&!in_array($h->stage,['completed','cancelled'])){$actions[]='meeting';$actions[]='comment';}
  if($resourceOwner&&$h->cr_signoff_at&&$h->ops_signoff_at&&!in_array($h->stage,['completed','cancelled','accepted','awaiting_acceptance']))$actions[]='resource';
  if($h->meeting_held_at&&!in_array($h->stage,['completed','cancelled','accepted','awaiting_acceptance'])){if($sender)$actions[]='signoff_cr';if($ops)$actions[]='signoff_ops';}
  if($ops&&$h->cr_signoff_at&&$h->ops_signoff_at&&!in_array($h->stage,['completed','cancelled','accepted'])){$actions[]='resource';foreach(['planning','sourcing','setup'] as $stage)if($h->stage!==$stage)$actions[]=$stage;$actions[]='submit';}
  if($sender&&$h->stage==='awaiting_acceptance'&&(int)$h->lead_id!==$u->id){$actions[]='accept';$actions[]='return';}
  if(self::lead($u)&&$h->stage==='accepted'&&(int)$h->lead_id!==$u->id)$actions[]='verify';
  if($sender&&!in_array($h->stage,['completed','cancelled']))$actions[]='cancel';
  return array_values(array_unique($actions));
 }
 public function transition(OperationsHandover $original,User $actor,array $d):OperationsHandover {
  return DB::transaction(function()use($original,$actor,$d){$h=OperationsHandover::with('project')->lockForUpdate()->findOrFail($original->id);abort_if((int)$d['revision']!==(int)$h->revision,409,'This handover changed. Refresh before saving.');abort_unless(in_array($d['action'],$this->allowed($actor,$h)),403,'This action belongs to the responsible department or reviewer.');$before=$h->getAttributes();$action=$d['action'];
   switch($action){
    case 'assign':$owner=User::findOrFail($d['lead_id']??0);abort_unless(self::ops($owner),422,'Choose an active Operations team member.');$h->lead_id=$owner->id;if(!$h->task_id){$t=Task::create(['title'=>'OPS-'.$h->id.' · '.$h->title,'details'=>$h->plan,'client_name'=>$h->project->client_name,'assigned_to'=>$owner->id,'assigned_by'=>$actor->id,'department'=>'operations_projects','priority'=>$h->priority,'status'=>'Open','progress'=>0,'due_on'=>$h->setup_due_at->toDateString()]);$t->forceFill(['performance_weight'=>$d['performance_weight']??1,'weight_approved_by'=>$actor->id,'weight_approved_at'=>now()])->save();$h->task_id=$t->id;}else{$h->task->update(['assigned_to'=>$owner->id]);}break;
    case 'acknowledge':$h->acknowledged_at??=now();$h->stage='acknowledged';break;
    case 'meeting':
     if(empty($d['meeting_scheduled_at'])||empty($d['meeting_location']))throw ValidationException::withMessages(['meeting_scheduled_at'=>'Record the handover meeting time and location or meeting link.']);
     $h->meeting_scheduled_at=$d['meeting_scheduled_at'];$h->meeting_location=$d['meeting_location'];$h->stage='handover_meeting';
     if(!empty($d['meeting_held_at'])){if(Carbon::parse($d['meeting_held_at'])->isFuture())throw ValidationException::withMessages(['meeting_held_at'=>'Actual meeting time cannot be in the future.']);
      $people=User::whereIn('id',$d['attendees']??[])->get();if(!$people->contains(fn($p)=>ClientRelationsPipeline::staffMember($p))||!$people->contains(fn($p)=>self::ops($p))||empty($d['meeting_minutes']))throw ValidationException::withMessages(['attendees'=>'Minutes and attendees from both Client Relations and Operations are required.']);
      $h->meeting_held_at=$d['meeting_held_at'];$h->meeting_minutes=$d['meeting_minutes'];$h->attendees=$people->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'department'=>User::normalizeDepartmentKey($u->department)])->all();
     }else{$h->meeting_held_at=null;$h->meeting_minutes=null;$h->attendees=null;}
     // Revised meeting decisions require both sides to acknowledge again.
     $h->cr_signoff_at=null;$h->ops_signoff_at=null;break;
    case 'signoff_cr':$h->cr_signoff_at=now();if($h->ops_signoff_at)$h->stage='planning';break;
    case 'signoff_ops':$h->ops_signoff_at=now();if($h->cr_signoff_at)$h->stage='planning';break;
    case 'resource':
     $resource=$d['resource']??[];if(!self::lead($actor)&&(int)$h->lead_id!==$actor->id){$existing=collect($h->resources??[])->firstWhere('id',$resource['id']??null);abort_unless($existing&&(int)$existing['owner_id']===$actor->id,403,'Update only requirements assigned to you.');$resource=array_replace($existing,collect($resource)->only(['status','note'])->all());}
     if(empty($resource['name'])||empty($resource['category'])||empty($resource['owner_id'])||empty($resource['due_on'])||empty($resource['status']))throw ValidationException::withMessages(['resource'=>'Each requirement needs its category, name, responsible Operations person and due date.']);
     $responsible=User::findOrFail($resource['owner_id']);abort_unless(self::ops($responsible),422,'The resource owner must be an active Operations team member.');
     $resources=$h->resources??[];$resource['owner']=$responsible->name;$resource['id']=$resource['id']??(string)\Illuminate\Support\Str::uuid();$found=false;foreach($resources as &$row)if($row['id']===$resource['id']){$row=$resource;$found=true;}unset($row);if(!$found)$resources[]=$resource;$h->resources=$resources;break;
    case 'planning':case 'sourcing':case 'setup':$h->stage=$action;break;
    case 'submit':
     if(!$h->lead_id)throw ValidationException::withMessages(['lead_id'=>'An Operations lead must be assigned.']);
     if(empty($h->resources)||collect($h->resources)->contains(fn($r)=>!in_array($r['status'],['ready','not_required'])))throw ValidationException::withMessages(['resources'=>'Record the resource checklist and resolve every requirement before submitting.']);
     if(empty($d['evidence_url']))throw ValidationException::withMessages(['evidence_url'=>'Provide a setup report, photograph or delivery evidence link.']);$h->submission_evidence=$d['evidence_url'];$h->submitted_at=now();$h->stage='awaiting_acceptance';break;
    case 'return':$h->stage='planning';$h->accepted_at=null;$h->verified_at=null;break;
    case 'accept':$h->accepted_at=now();$h->stage='accepted';break;
    case 'verify':if(!isset($d['quality_score']))throw ValidationException::withMessages(['quality_score'=>'Independent quality verification is required.']);$h->stage='completed';$h->verified_at=now();$h->verified_by=$actor->id;$h->quality_score=$d['quality_score'];break;
    case 'cancel':$h->stage='cancelled';break;
   }
   $h->revision++;$h->save();
   if($h->task_id&&$action!=='assign'){
    $task=$h->task;$complete=$h->stage==='completed';$pending=in_array($h->stage,['awaiting_acceptance','accepted']);$task->forceFill(['status'=>$complete?'Completed':($pending?'AwaitingApproval':($h->stage==='cancelled'?'Deferred':'In Progress')),'progress'=>$complete?100:($pending?95:($h->stage==='setup'?75:25)),'completion_review_status'=>$complete?'approved':($pending?'pending':null),'completion_review_requested_at'=>$pending?$h->submitted_at:null,'completion_reviewed_at'=>$complete?now():null,'completion_reviewed_by'=>$complete?$actor->id:null,'completion_review_note'=>$complete?$d['reason']:null])->save();
   }
   PerformanceWorkflow::record('operations_handover',$h->id,$actor->id,$action,$before,$h->getAttributes(),$d['reason']);$this->notify($h,'Operations Handover · '.ucwords(str_replace('_',' ',$h->stage)));return $h;
  });
 }
 public function notify(OperationsHandover $h,string $title):void {
  $ids=User::internalStaff()->where('status','active')->get()->filter(fn($u)=>self::lead($u))->pluck('id')->merge([$h->requester_id,$h->lead_id,$h->project?->owner_id])->merge(collect($h->resources??[])->pluck('owner_id'))->filter()->unique();
  foreach($ids as $id){$key='ops-handover:'.$h->id.':'.$h->revision.':'.$title;PerformanceWorkflow::notify((int)$id,$key,$title,$h->title.' has a recorded update. Open the handover for the next action.',route('portal.operations.handovers.index',['handover'=>$h->id]));$outbox=PerformanceOutbox::where('dedupe_key',hash('sha256',$key.':'.$id))->firstOrFail();if(!$outbox->dashboard_at){NotificationService::send((int)$id,$outbox->title,$outbox->message,$outbox->url);$outbox->update(['dashboard_at'=>now()]);}}
  if(config('performance.email_enabled'))DB::afterCommit(fn()=>defer(fn()=>app(PerformanceAutomation::class)->deliver()));
 }
}
