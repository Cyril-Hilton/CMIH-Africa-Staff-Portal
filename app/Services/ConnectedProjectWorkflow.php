<?php

namespace App\Services;

use App\Models\{CreativeRequest, CrmPipelineProject, OperationsHandover, PerformanceEvent, User};
use App\Services\Creative\{CreativeAccess, CreativeWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\{Rule, ValidationException};

/** Route published project requirements into the existing departmental workflows. */
class ConnectedProjectWorkflow
{
    public function validate(Request $request, ?CrmPipelineProject $project): array
    {
        if (!$request->has('delivery')) return [];
        $data=$request->validate([
            'delivery'=>'array:creative_required,operations_required,creative_manager_id,operations_manager_id,creative,operations,change_note',
            'delivery.creative'=>'nullable|array:brief,creative_type,requested_deadline,reference_url',
            'delivery.operations'=>'nullable|array:plan,acceptance_criteria,event_at,setup_due_at,location',
            'delivery.creative_required'=>'nullable|boolean',
            'delivery.operations_required'=>'nullable|boolean',
            'delivery.creative_manager_id'=>'nullable|integer|exists:users,id',
            'delivery.operations_manager_id'=>'nullable|integer|exists:users,id',
            'delivery.creative.brief'=>'nullable|string|min:3|max:10000',
            'delivery.creative.creative_type'=>['nullable',Rule::in(config('creative.types'))],
            'delivery.creative.requested_deadline'=>'nullable|date',
            'delivery.creative.reference_url'=>'nullable|url:https|max:2000',
            'delivery.operations.plan'=>'nullable|string|min:20|max:12000',
            'delivery.operations.acceptance_criteria'=>'nullable|string|min:10|max:8000',
            'delivery.operations.event_at'=>'nullable|date',
            'delivery.operations.setup_due_at'=>'nullable|date|before_or_equal:delivery.operations.event_at',
            'delivery.operations.location'=>'nullable|string|max:255',
            'delivery.change_note'=>'nullable|string|max:2000',
        ])['delivery'];
        $old=$project?->team_workflow??[];
        foreach(['creative','operations'] as $team) {
            $required=$team.'_required';
            if(!array_key_exists($required,$data)) continue;
            $data[$required]=$data[$required]===null?null:(bool)$data[$required];
            $linked=$old[$team.'_'.($team==='creative'?'request':'handover').'_id']??null;
            if(!$linked && $project) $linked=$team==='creative'?CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')->exists():OperationsHandover::where('project_id',$project->id)->where('stage','!=','cancelled')->exists();
            if($linked && $data[$required]!==true) throw ValidationException::withMessages(['delivery.'.$required=>'Work has already been routed for this project. Keep the requirement and use its departmental workflow to cancel or close the work; its history remains visible.']);
            if(!$data[$required])continue;
            $manager=User::find($data[$team.'_manager_id']??$old[$team.'_manager_id']??null);
            $valid=$manager && $manager->isActive() && User::normalizeDepartmentKey($manager->department)===($team==='creative'?'creatives':'operations_projects')
                && ($team==='creative'?CreativeAccess::lead($manager):OperationsHandoverWorkflow::lead($manager));
            if(!$valid)throw ValidationException::withMessages(['delivery.'.$team.'_manager_id'=>'Tag an active '.$team.' line manager.']);
            $data[$team.'_manager_id']=$manager->id;
            if(!$linked) {
                $fields=$team==='creative'?['brief','creative_type','requested_deadline']:['plan','acceptance_criteria','event_at','setup_due_at','location'];
                foreach($fields as $field) if(!filled($data[$team][$field]??null)) throw ValidationException::withMessages(['delivery.'.$team.'.'.$field=>'Complete this field to send the '.$team.' request.']);
                $original=$request->attributes->get('crm_approved_submission')?->created_at??now();
                foreach($team==='creative'?['requested_deadline']:['event_at','setup_due_at'] as $field) {
                    if(Carbon::parse($data[$team][$field])->lte($original))throw ValidationException::withMessages(['delivery.'.$team.'.'.$field=>'Choose a deadline after the original request submission.']);
                }
            }
        }
        return $data;
    }

    /** Called inside the project's locked publication transaction, never for unreviewed drafts. */
    public function sync(CrmPipelineProject $project,User $actor,array $delivery,?Carbon $submittedAt=null,?User $requester=null): void
    {
        if(!$delivery)return;
        $before=$project->team_workflow??[];
        $state=$before;
        foreach(['creative_required','operations_required','creative_manager_id','operations_manager_id'] as $key) {
            if(array_key_exists($key,$delivery))$state[$key]=$delivery[$key];
        }
        $originator=$requester??$actor;
        if($state['creative_required']??false) {
            $brief=CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')
                ->when($state['creative_request_id']??null,fn($q,$id)=>$q->whereKey($id))->first();
            $input=collect($delivery['creative']??[])->only(['brief','creative_type','requested_deadline','reference_url'])->all();
            if(!$brief) {
                $brief=app(CreativeWorkflow::class)->create($originator,$input+['title'=>$project->project_name,'client'=>$project->client_name,'priority'=>'Medium','visibility'=>'shared']);
                $brief->forceFill(['crm_project_id'=>$project->id,'reviewer_id'=>$state['creative_manager_id']])->save();
                $state['creative_request_id']=$brief->id;
                $state['creative_requested_by_id']=$originator->id;
                $state['creative_requested_at']=($submittedAt??now())->toIso8601String();
                $state['creative_routed_at']=now()->toIso8601String();
                $state['creative_original_deadline']=$brief->requested_deadline?->toIso8601String();
                PerformanceWorkflow::record('creative_request',$brief->id,$actor->id,'project_linked',[],['crm_project_id'=>$project->id,'requested_at'=>$state['creative_requested_at'],'manager_id'=>$state['creative_manager_id']],'Routed from the published Client Relations weekly entry.');
            } elseif($input) {
                $changed=collect($input)->only(['brief','creative_type','requested_deadline'])->filter(fn($v)=>$v!==null)->all();
                if($changed) {
                    $prior=$brief->getAttributes();$brief->fill($changed);
                    if($brief->isDirty()) {
                        if(in_array($brief->stage,['completed','archived'],true))throw ValidationException::withMessages(['delivery.creative'=>'This brief is complete. Use a new work package for additional work.']);
                        $brief->revision++;$brief->save();
                        PerformanceWorkflow::record('creative_request',$brief->id,$actor->id,'project_request_changed',$prior,$brief->getAttributes(),$delivery['change_note']??'Client Relations updated the requested brief. The Creative HOD schedule remains separately recorded.');
                        $brief->updates()->create(['author_id'=>$actor->id,'kind'=>'project_request_changed','body'=>$delivery['change_note']??'Client Relations updated the requested brief. Review the changed request and agreed timeline.','metadata'=>['crm_project_id'=>$project->id,'requested_deadline'=>$brief->requested_deadline?->toIso8601String()]]);
                    }
                }
            }
            $state['creative_request_id']=$brief->id;
            PerformanceWorkflow::notify($state['creative_manager_id'],'project-creative:'.$project->id.':'.$project->revision,'Project brief · '.$project->project_name,'Client Relations: '.$originator->name.'. Review the linked brief and assign its owner and work schedule.',route('portal.creative',['request'=>$brief->id]));
        }
        if($state['operations_required']??false) {
            $handover=OperationsHandover::where('project_id',$project->id)
                ->when($state['operations_handover_id']??null,fn($q,$id)=>$q->whereKey($id))->where('stage','!=','cancelled')->first();
            $input=collect($delivery['operations']??[])->only(['plan','acceptance_criteria','event_at','setup_due_at','location'])->all();
            if(!$handover) {
                $handover=OperationsHandover::create($input+['project_id'=>$project->id,'requester_id'=>$originator->id,'title'=>$project->project_name,'priority'=>'Medium','stage'=>'submitted','resources'=>[]]);
                $state['operations_handover_id']=$handover->id;
                $state['operations_requested_by_id']=$originator->id;
                $state['operations_requested_at']=($submittedAt??now())->toIso8601String();
                $state['operations_routed_at']=now()->toIso8601String();
                $state['operations_original_setup_due']=$handover->setup_due_at?->toIso8601String();
                $state['operations_original_execution']=$handover->event_at?->toIso8601String();
                PerformanceWorkflow::record('operations_handover',$handover->id,$actor->id,'submitted',[],$handover->getAttributes()+['original_request_at'=>$state['operations_requested_at'],'manager_id'=>$state['operations_manager_id']],'Routed from the published Client Relations weekly entry.');
            } elseif($input) {
                $prior=$handover->getAttributes();$handover->fill(collect($input)->only(['plan','acceptance_criteria','event_at','setup_due_at','location'])->filter(fn($v)=>$v!==null)->all());
                if($handover->isDirty()) {
                    if(in_array($handover->stage,['completed','cancelled'],true))throw ValidationException::withMessages(['delivery.operations'=>'This handover is closed. Start a new handover for additional work.']);
                    $handover->cr_signoff_at=null;$handover->ops_signoff_at=null;$handover->submitted_at=null;$handover->accepted_at=null;$handover->stage=$handover->meeting_held_at?'handover_meeting':($handover->acknowledged_at?'acknowledged':'submitted');$handover->revision++;$handover->save();
                    PerformanceWorkflow::record('operations_handover',$handover->id,$actor->id,'project_request_changed',$prior,$handover->getAttributes(),$delivery['change_note']??'Client Relations changed the execution request; both teams must reconfirm handover sign-off.');
                    if($handover->task_id)$handover->task->update(['details'=>$handover->plan,'due_on'=>$handover->setup_due_at->toDateString()]);
                }
            }
            $state['operations_handover_id']=$handover->id;
            app(OperationsHandoverWorkflow::class)->notify($handover,'Project handover · '.$project->project_name);
            PerformanceWorkflow::notify($state['operations_manager_id'],'project-operations:'.$project->id.':'.$project->revision,'Project handover · '.$project->project_name,'Client Relations: '.$originator->name.'. Review the plan, assign the lead and agree the handover.',route('portal.operations.handovers.index',['handover'=>$handover->id]));
        }
        $project->team_workflow=$state;$project->save();
        if($before!==$state)PerformanceWorkflow::record('crm_project',$project->id,$actor->id,'team_requirements_updated',$before,$state,$delivery['change_note']??'Project department requirements and routing updated.');
    }

    public function formValues(?CrmPipelineProject $project): array
    {
        $state=$project?->team_workflow??[];
        if(!$project)return $state;
        $brief=CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')->first();
        $handover=OperationsHandover::where('project_id',$project->id)->where('stage','!=','cancelled')->first();
        if($brief){$state['creative_required']=true;if(empty($state['creative_manager_id'])&&$brief->reviewer&&CreativeAccess::lead($brief->reviewer))$state['creative_manager_id']=$brief->reviewer_id;}
        if($handover){$state['operations_required']=true;if(empty($state['operations_manager_id'])&&$handover->lead&&OperationsHandoverWorkflow::lead($handover->lead))$state['operations_manager_id']=$handover->lead_id;}
        if($brief)$state['creative']=collect($brief->toArray())->only(['brief','creative_type','requested_deadline'])->all()+['requested_deadline'=>$brief->requested_deadline?->format('Y-m-d\\TH:i')];
        if($brief)$state['creative']['requested_deadline']=$brief->requested_deadline?->format('Y-m-d\\TH:i');
        if($handover)$state['operations']=collect($handover->toArray())->only(['plan','acceptance_criteria','location'])->all()+['event_at'=>$handover->event_at?->format('Y-m-d\\TH:i'),'setup_due_at'=>$handover->setup_due_at?->format('Y-m-d\\TH:i')];
        return $state;
    }
}
