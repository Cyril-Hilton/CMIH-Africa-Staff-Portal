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
            'delivery'=>'array:creative_required,operations_required,creative_manager_id,operations_manager_id,creative,operations,change_note,client_brief_received_at',
            'delivery.client_brief_received_at'=>'nullable|date|before_or_equal:now',
            'delivery.creative'=>'nullable|array:brief,creative_type,requested_deadline,reference_url',
            'delivery.operations'=>'nullable|array:plan,acceptance_criteria,event_at,setup_due_at,execution_due_at,location,plan_url',
            'delivery.creative_required'=>'nullable|boolean',
            'delivery.operations_required'=>'nullable|boolean',
            'delivery.creative_manager_id'=>'nullable|integer|exists:users,id',
            'delivery.operations_manager_id'=>'nullable|integer|exists:users,id',
            'delivery.creative.brief'=>'nullable|string|min:3|max:10000',
            'delivery.creative.creative_type'=>['nullable',Rule::in(config('creative.types'))],
            'delivery.creative.requested_deadline'=>'nullable|date',
            'delivery.creative.reference_url'=>'nullable|url:https|max:2000',
            'delivery.operations.plan'=>'nullable|string|min:20|max:12000',
            'delivery.operations.plan_url'=>'nullable|url:https|max:2000',
            'delivery.operations.acceptance_criteria'=>'nullable|string|min:10|max:8000',
            'delivery.operations.event_at'=>'nullable|date',
            'delivery.operations.execution_due_at'=>'nullable|date|after_or_equal:delivery.operations.event_at',
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
        if(array_key_exists('client_brief_received_at',$delivery)){
            $received=filled($delivery['client_brief_received_at'])?Carbon::parse($delivery['client_brief_received_at'],config('creative.timezone'))->toIso8601String():null;
            if(($before['client_brief_received_at']??null)!==$received){
                $state['client_brief_received_at']=$received;
                $state['client_brief_received_entered_by_id']=$originator->id;
                $state['client_brief_received_entered_at']=($submittedAt??now())->toIso8601String();
                $state['client_brief_received_published_by_id']=$actor->id;
                $state['client_brief_received_published_at']=now()->toIso8601String();
            }
        }
        if($state['creative_required']??false) {
            $brief=CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')->lockForUpdate()
                ->when($state['creative_request_id']??null,fn($q,$id)=>$q->whereKey($id))->first();
            $input=collect($delivery['creative']??[])->only(['brief','creative_type','requested_deadline','reference_url'])->all();
            if(!$brief) {
                $brief=app(CreativeWorkflow::class)->create($originator,$input+['title'=>$project->project_name,'client'=>$project->client_name,'priority'=>'Medium','visibility'=>'shared']);
                $brief->forceFill(['crm_project_id'=>$project->id,'reviewer_id'=>$state['creative_manager_id']])->save();
                $state['creative_request_id']=$brief->id;
                $state['creative_requested_by_id']=$originator->id;
                $state['creative_requested_at']=($submittedAt??now())->toIso8601String();
                $state['creative_routed_at']=now()->toIso8601String();$state['creative_routed_by_id']=$actor->id;
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
                        app(CreativeWorkflow::class)->notify($brief,[$originator->id,$state['creative_manager_id']],'Client Relations brief or deadline changed',$project->project_name.' has a changed request. Compare the Client Relations deadline with the Creative HOD commitment.');
                    }
                }
            }
            if(array_key_exists('reference_url',$input)) {
                $previous=$brief->updates()->whereIn('kind',['reference','project_reference_changed'])->reorder('id','desc')->first()?->reference_url;
                if(($previous?:null)!==($input['reference_url']?:null)) {
                    if(in_array($brief->stage,['completed','archived'],true))throw ValidationException::withMessages(['delivery.creative.reference_url'=>'This brief is complete. Add a new work package for additional references.']);
                    $brief->updates()->create(['author_id'=>$originator->id,'kind'=>'project_reference_changed','body'=>$delivery['change_note']??'Client Relations updated the brief reference.','reference_url'=>$input['reference_url']?:null]);
                    $brief->increment('revision');
                    PerformanceWorkflow::record('creative_request',$brief->id,$originator->id,'project_reference_changed',['reference_url'=>$previous],['reference_url'=>$input['reference_url']?:null],$delivery['change_note']??'Brief reference updated.');
                    app(CreativeWorkflow::class)->notify($brief,[$state['creative_manager_id']],'Creative brief reference updated',$project->project_name.' has an updated reference. Review the linked brief.');
                }
            }
            if(array_key_exists('creative_manager_id',$delivery)&&(int)($before['creative_manager_id']??0)!==(int)$state['creative_manager_id']&&(int)$brief->reviewer_id!==(int)$state['creative_manager_id']) {
                if(in_array($brief->stage,['completed','archived'],true))throw ValidationException::withMessages(['delivery.creative_manager_id'=>'This brief is complete. Its reviewer remains in the completion history.']);
                $prior=$brief->only(['reviewer_id','revision']);$previousManager=$brief->reviewer_id;
                $brief->update(['reviewer_id'=>$state['creative_manager_id'],'revision'=>$brief->revision+1]);
                PerformanceWorkflow::record('creative_request',$brief->id,$actor->id,'project_manager_changed',$prior,$brief->only(['reviewer_id','revision']),'Client Relations updated the tagged Creative HOD; the existing assignee and agreed schedule are retained.');
                app(CreativeWorkflow::class)->notify($brief,[$previousManager,$state['creative_manager_id']],'Creative brief manager updated',$project->project_name.' has an updated responsible Creative HOD.');
            }
            $state['creative_request_id']=$brief->id;
            PerformanceWorkflow::notify($state['creative_manager_id'],'project-creative:'.$project->id.':'.$project->revision,'Project brief · '.$project->project_name,'Client Relations: '.$originator->name.'. Review the linked brief and assign its owner and work schedule.',route('portal.creative',['request'=>$brief->id]));
        }
        if($state['operations_required']??false) {
            $handover=OperationsHandover::where('project_id',$project->id)->lockForUpdate()
                ->when($state['operations_handover_id']??null,fn($q,$id)=>$q->whereKey($id))->where('stage','!=','cancelled')->first();
            $input=collect($delivery['operations']??[])->only(['plan','acceptance_criteria','event_at','setup_due_at','execution_due_at','location','plan_url'])->all();
            if(!$handover) {
                $handover=OperationsHandover::create($input+['project_id'=>$project->id,'requester_id'=>$originator->id,'title'=>$project->project_name,'priority'=>'Medium','stage'=>'submitted','resources'=>[]]);
                $state['operations_handover_id']=$handover->id;
                $state['operations_requested_by_id']=$originator->id;
                $state['operations_requested_at']=($submittedAt??now())->toIso8601String();
                $state['operations_routed_at']=now()->toIso8601String();$state['operations_routed_by_id']=$actor->id;
                $state['operations_original_setup_due']=$handover->setup_due_at?->toIso8601String();
                $state['operations_original_execution']=$handover->event_at?->toIso8601String();
                PerformanceWorkflow::record('operations_handover',$handover->id,$actor->id,'submitted',[],$handover->getAttributes()+['original_request_at'=>$state['operations_requested_at'],'manager_id'=>$state['operations_manager_id']],'Routed from the published Client Relations weekly entry.');
            } elseif($input) {
                $prior=$handover->getAttributes();$handover->fill(collect($input)->only(['plan','acceptance_criteria','event_at','setup_due_at','execution_due_at','location','plan_url'])->filter(fn($v,$key)=>$v!==null||$key==='plan_url')->all());
                if($handover->isDirty()) {
                    if(in_array($handover->stage,['completed','cancelled'],true))throw ValidationException::withMessages(['delivery.operations'=>'This handover is closed. Start a new handover for additional work.']);
                    $handover->cr_signoff_at=null;$handover->ops_signoff_at=null;$handover->handover_approved_at=null;$handover->handover_approved_by=null;$handover->submitted_at=null;$handover->accepted_at=null;$handover->execution_completed_at=null;$handover->submission_evidence=null;$handover->stage=$handover->meeting_held_at?'handover_meeting':($handover->acknowledged_at?'acknowledged':'submitted');$handover->revision++;$handover->save();
                    PerformanceWorkflow::record('operations_handover',$handover->id,$actor->id,'project_request_changed',$prior,$handover->getAttributes(),$delivery['change_note']??'Client Relations changed the execution request; both teams must reconfirm handover sign-off.');
                    if($handover->task_id)$handover->task->update(['details'=>$handover->plan,'due_on'=>($handover->agreed_setup_due_at??$handover->setup_due_at)->toDateString()]);
                }
            }
            $state['operations_handover_id']=$handover->id;
            app(OperationsHandoverPublication::class)->sync($handover,$actor);
            app(OperationsHandoverWorkflow::class)->notify($handover,'Project handover · '.$project->project_name);
            PerformanceWorkflow::notify($state['operations_manager_id'],'project-operations:'.$project->id.':'.$project->revision,'Project handover · '.$project->project_name,'Client Relations: '.$originator->name.'. Review the plan, assign the lead and agree the handover.',route('portal.operations.handovers.index',['handover'=>$handover->id]));
        }
        $project->team_workflow=$state;$project->save();
        if($before!==$state){foreach(OperationsHandover::where('project_id',$project->id)->where('stage','!=','cancelled')->get() as $linked)app(OperationsHandoverWorkflow::class)->notify($linked,'Connected project timeline updated','project-operations-timeline:'.$project->id.':'.$project->revision.':'.hash('sha256',json_encode($state)));}
        if($before!==$state){foreach(CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')->get() as $linked)app(CreativeWorkflow::class)->notify($linked,[$project->owner_id,...app(CreativeWorkflow::class)->leads()],'Connected project timeline updated',$project->project_name.' has updated routing, receipt dates or deadlines. Open the brief to review the recorded timeline.','project-timeline:'.$project->id.':'.$project->revision.':'.hash('sha256',json_encode($state)));}
        if($before!==$state)PerformanceWorkflow::record('crm_project',$project->id,$actor->id,'team_requirements_updated',$before,$state,$delivery['change_note']??'Project department requirements and routing updated.');
    }

    public function syncProjectIdentity(CrmPipelineProject $project, User $actor): void
    {
        foreach(\App\Models\PerformanceRegister::whereIn('department',User::departmentAliases('operations_projects'))->where('data->crm_project_id',$project->id)->lockForUpdate()->get() as $record){
            $before=$record->only(['project','client']);$record->fill(['project'=>$project->project_name,'client'=>$project->client_name]);
            if($record->isDirty()){$record->save();PerformanceWorkflow::record('register',$record->id,$actor->id,'project_identity_updated',$before,$record->only(['project','client']),'Connected Client Relations project name or client updated. Operational detail retained.');}
        }
    }

    public function formValues(?CrmPipelineProject $project): array
    {
        $state=$project?->team_workflow??[];
        if(!$project)return $state;
        $brief=CreativeRequest::where('crm_project_id',$project->id)->whereNull('parent_request_id')->first();
        $handover=OperationsHandover::where('project_id',$project->id)->where('stage','!=','cancelled')->first();
        if($brief){$state['creative_required']=true;if(empty($state['creative_manager_id'])&&$brief->reviewer&&CreativeAccess::lead($brief->reviewer))$state['creative_manager_id']=$brief->reviewer_id;}
        if($handover){$state['operations_required']=true;if(empty($state['operations_manager_id'])&&$handover->lead&&OperationsHandoverWorkflow::lead($handover->lead))$state['operations_manager_id']=$handover->lead_id;}
        if($brief){
            $timeline=app(\App\Services\Creative\CreativeTimeline::class)->build($brief);
            $state['creative']=collect($brief->toArray())->only(['brief','creative_type'])->all()+[
                'requested_deadline'=>!empty($timeline['client_relations_deadline'])?Carbon::parse($timeline['client_relations_deadline'])->timezone(config('creative.timezone'))->format('Y-m-d\\TH:i'):null,
                'reference_url'=>$brief->updates()->whereIn('kind',['reference','project_reference_changed'])->reorder('id','desc')->first()?->reference_url];
        }
        if($handover)$state['operations']=collect($handover->toArray())->only(['plan','acceptance_criteria','location','plan_url'])->all()+['event_at'=>$handover->event_at?->format('Y-m-d\\TH:i'),'setup_due_at'=>$handover->setup_due_at?->format('Y-m-d\\TH:i'),'execution_due_at'=>$handover->execution_due_at?->format('Y-m-d\\TH:i')];
        return $state;
    }
}
