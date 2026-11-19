<?php
namespace App\Services;

use App\Models\{OperationsHandover, PerformanceRegister, User};

/** One approved handover owns one activation; operational additions remain editable. */
class OperationsHandoverPublication
{
 public function sync(OperationsHandover $handover, User $actor): void
 {
  $ready=$handover->cr_signoff_at&&$handover->ops_signoff_at&&$handover->handover_approved_at&&$handover->lead_id;
  $row=$handover->operations_register_id?PerformanceRegister::lockForUpdate()->find($handover->operations_register_id):null;
  if(!$ready&&!$row)return;
  if(!$ready){$data=$row->data??[];$data['handover_review_required']=true;$row->update(['data'=>$data]);return;}
  $project=$handover->project;$new=!$row;$row??=new PerformanceRegister(['reference'=>'OPS-HANDOVER-'.$handover->id,'department'=>'operations_projects','kind'=>'activation','submitted_by'=>$handover->requester_id]);
  $before=$row->getAttributes();$data=$row->data??[];$sync=$data['handover_synced_fields']??[];
  $source=['brand'=>$project->client_name,'location'=>$handover->location,'starts_on'=>$handover->event_at?->toDateString(),'ends_on'=>($handover->agreed_execution_due_at??$handover->execution_due_at)?->toDateString(),'client_service'=>$project->owner?->name,'remarks'=>$handover->plan];
  foreach($source as $key=>$value)if(!array_key_exists($key,$data)||($sync[$key]??null)===$data[$key])$data[$key]=$value;
  $data['handover_synced_fields']=$source;$data['crm_project_id']=$project->id;$data['operations_handover_id']=$handover->id;$data['handover_review_required']=false;
  $data['collaborator_ids']=array_values(array_unique(array_merge($data['collaborator_ids']??[],$handover->task?->supporting_staff_ids??[])));
  if($new)$data['activation_status']='Planned';
  $status=match($handover->stage){'completed'=>'completed','cancelled'=>'cancelled','sourcing','setup'=>'in_progress',default=>$row->status??'planned'};
  if($handover->stage==='completed')$data['activation_status']='Completed';
  if($handover->stage==='cancelled')$data['activation_status']='Cancelled';
  $row->fill(['title'=>$handover->title,'client'=>$project->client_name,'project'=>$project->project_name,'owner_id'=>$handover->lead_id,'period_start'=>$handover->event_at->toDateString(),'period_end'=>($handover->execution_due_at??$handover->event_at)->toDateString(),'status'=>$status,'data'=>$data,'approved_by'=>$handover->handover_approved_by,'approved_at'=>$handover->handover_approved_at]);
  if($row->isDirty()){$row->save();PerformanceWorkflow::record('register',$row->id,$actor->id,$new?'create':'update',$before,$row->getAttributes(),'Synced from the signed, Operations HOD-approved handover. Operational fields remain editable.');}
  if(!$handover->operations_register_id){$handover->operations_register_id=$row->id;$handover->save();}
 }
}
