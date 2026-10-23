<?php
namespace App\Services\Creative;
use App\Models\{Task,User,WeeklyConsolidatedItem,CreativeRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CreativeLegacyImport {
 public function run():int {
  $count=0;
  Task::whereIn('department',User::departmentAliases('creatives'))->realWork()->with('assignee','assigner')->chunkById(100,function($tasks)use(&$count){foreach($tasks as $task){if(CreativeRequest::where('task_id',$task->id)->exists())continue;
   DB::transaction(function()use($task,&$count){$r=CreativeRequest::firstOrCreate(['task_id'=>$task->id],['uuid'=>(string)Str::uuid(),'reference'=>'CR-T'.$task->id,'requester_id'=>$task->assigned_by,'assignee_id'=>$task->assigned_to,'requester_snapshot'=>CreativeAccess::snapshot($task->assigner),'assignee_snapshot'=>CreativeAccess::snapshot($task->assignee),'department'=>$task->assigner?User::normalizeDepartmentKey($task->assigner->department):null,'title'=>$task->title,'brief'=>strip_tags($task->details??''),'client'=>$task->client_name,'stage'=>$task->isApprovedForPerformance()?'completed':(str_contains(strtolower($task->status??''),'progress')?'in_progress':'backlog'),'priority'=>$task->priority?:'Medium','source_recorded_at'=>$task->created_at,'requested_deadline'=>$task->due_on,'completed_at'=>$task->completion_reviewed_at,'legacy'=>true]);$count+=(int)$r->wasRecentlyCreated;});
  }});
  WeeklyConsolidatedItem::whereIn('department',User::departmentAliases('creatives'))->with('creator','leadStaff')->chunkById(100,function($rows)use(&$count){foreach($rows as $row){if(DB::table('creative_sources')->where('source_type','weekly')->where('source_id',$row->id)->exists())continue;
   DB::transaction(function()use($row,&$count){$r=$row->source_task_id?CreativeRequest::where('task_id',$row->source_task_id)->first():null;
    if(!$r){$r=CreativeRequest::create(['uuid'=>(string)Str::uuid(),'reference'=>'CR-W'.$row->id,'requester_id'=>$row->created_by,'assignee_id'=>$row->lead_staff_id,'requester_snapshot'=>CreativeAccess::snapshot($row->creator),'assignee_snapshot'=>CreativeAccess::snapshot($row->leadStaff),'department'=>$row->creator?User::normalizeDepartmentKey($row->creator->department):null,'title'=>$row->campaign_name?:($row->deliverables?:'Weekly creative record'),'brief'=>$row->deliverables,'client'=>$row->client_name,'stage'=>in_array($row->workflow_state,['verified','closed'])?'completed':($row->status==='Done'?'delivered':($row->status==='In Progress'?'in_progress':'backlog')),'priority'=>$row->priority?:'Medium','received_at'=>$row->brief_received_on,'source_recorded_at'=>$row->created_at,'completed_at'=>$row->verified_at,'legacy'=>true]);$count++;}
    elseif(!$r->received_at && $row->brief_received_on)$r->update(['received_at'=>$row->brief_received_on]);
    DB::table('creative_sources')->insertOrIgnore(['creative_request_id'=>$r->id,'source_type'=>'weekly','source_id'=>$row->id]);
   });
  }});return $count;
 }
}
