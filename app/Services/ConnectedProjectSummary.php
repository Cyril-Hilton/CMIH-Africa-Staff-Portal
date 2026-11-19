<?php

namespace App\Services;

use App\Models\{CreativeRequest, CrmPipelineProject, OperationsHandover, PerformanceEvent, User};
use Illuminate\Support\Carbon;

/** Shared accountability projection: no commercial figures, private contacts or HR data. */
class ConnectedProjectSummary
{
    public function build(CrmPipelineProject $project, User $viewer, int $historyPage=1): array
    {
        $state=$project->team_workflow??[];
        $briefs=CreativeRequest::visibleTo($viewer)->where('crm_project_id',$project->id)->whereNull('parent_request_id')
            ->with(['requester:id,name','assignee:id,name','reviewer:id,name','parts.assignee:id,name','parts.reviewer:id,name'])->get();
        $handovers=OperationsHandover::where('project_id',$project->id)->with('lead:id,name','requester:id,name')->get();
        $names=User::whereIn('id',array_filter([$project->owner_id,$state['creative_manager_id']??null,$state['operations_manager_id']??null,$state['creative_requested_by_id']??null,$state['operations_requested_by_id']??null]))->pluck('name','id');
        $supportIds=$briefs->flatMap(fn($b)=>collect([$b])->merge($b->parts)->flatMap(fn($p)=>$p->supportingStaffIds()))->merge($handovers->flatMap(fn($h)=>$h->task?->supporting_staff_ids??[]))->unique();
        $supportNames=User::whereIn('id',$supportIds)->pluck('name','id');
        $timelineEvents=PerformanceEvent::with('actor:id,name')->where(fn($q)=>$q
            ->where(fn($q)=>$q->where('entity_type','creative_request')->whereIn('entity_id',$briefs->pluck('id')->merge($briefs->flatMap(fn($b)=>$b->parts->pluck('id')))))
            ->orWhere(fn($q)=>$q->where('entity_type','operations_handover')->whereIn('entity_id',$handovers->pluck('id'))))->orderBy('id')->get();
        $recorder=function(string $type,int $id,string $field,$at)use($timelineEvents){
            $event=$timelineEvents->filter(fn($e)=>$e->entity_type===$type&&(int)$e->entity_id===$id&&!empty($e->after[$field])&&($e->before[$field]??null)!==$e->after[$field]&&Carbon::parse($e->after[$field])->equalTo($at))->last();
            return $event?->actor?->name??'Recorder unavailable · see retained history';
        };
        $dates=[['label'=>'Project entered in portal','at'=>$project->created_at,'owner'=>$project->created_by?User::find($project->created_by)?->name??'Recorder unavailable':'Recorder unavailable']];
        if(!empty($state['client_brief_received_at']))$dates[]=['label'=>'Client brief received · staff-reported','at'=>Carbon::parse($state['client_brief_received_at']),'owner'=>User::find($state['client_brief_received_entered_by_id']??$project->owner_id)?->name??'Account owner'];
        if(!empty($state['client_brief_received_entered_at']))$dates[]=['label'=>'Reported client receipt entered in portal','at'=>Carbon::parse($state['client_brief_received_entered_at']),'owner'=>User::find($state['client_brief_received_entered_by_id']??$project->owner_id)?->name??'Account owner'];
        $lanes=[];
        foreach(['creative'=>'Creative','operations'=>'Operations'] as $key=>$label) {
            $required=$state[$key.'_required']??null;
            $linked=$key==='creative'?$briefs->isNotEmpty():$handovers->isNotEmpty();
            $lanes[$key]=['required'=>$linked?true:$required,'manager'=>$names[$state[$key.'_manager_id']??0]??null];
            if(isset($state[$key.'_requested_at']))$dates[]=['label'=>$label.' request submitted','at'=>Carbon::parse($state[$key.'_requested_at']),'owner'=>$names[$state[$key.'_requested_by_id']??$project->owner_id]??'Client Relations'];
            if(isset($state[$key.'_routed_at']))$dates[]=['label'=>$label.' request routed after publication','at'=>Carbon::parse($state[$key.'_routed_at']),'owner'=>!empty($state[$key.'_routed_by_id'])?User::find($state[$key.'_routed_by_id'])?->name??'Publisher unavailable':'Publisher unavailable · see retained history'];
        }
        $work=[];
        foreach($briefs as $brief) {
            foreach($brief->is_group?$brief->parts:[$brief] as $part) {
                $deadline=\App\Services\Creative\CreativeDeadlines::scoringDue($part);
                $creativeTimeline=app(\App\Services\Creative\CreativeTimeline::class)->build($part);
                $requested=!empty($creativeTimeline['client_relations_deadline'])?Carbon::parse($creativeTimeline['client_relations_deadline']):null;
                $completed=$part->first_submitted_at;
                $work[]=['team'=>'Creative','title'=>$part->title,'owner'=>$part->assignee?->name??$lanes['creative']['manager']??'Creative HOD · assignment needed',
                    'support'=>collect($part->supportingStaffIds())->map(fn($id)=>$supportNames[$id]??'Retained staff history')->values()->all(),
                    'from'=>$brief->requester?->name??$names[$project->owner_id]??'Client Relations','manager'=>$part->reviewer?->name,
                    'requested'=>$requested,'agreed'=>$part->internal_eta,'start'=>$part->planned_start,'delivered'=>$completed,
                    'state'=>config('creative.stages.'.$part->stage,$part->stage),'late'=>$deadline && ($completed?$completed->gt($deadline):($deadline->isPast()&&!in_array($part->stage,['completed','archived'],true))),
                    'conflict'=>$part->internal_eta&&$requested&&$part->internal_eta->gt($requested),
                    'waiting'=>$part->assignee_id?($part->internal_eta?'':'Creative HOD schedule needed'):'Creative HOD assignment needed',
                    'url'=>route('portal.creative',['request'=>$part->id])];
                foreach(['received_at'=>'Brief received','acknowledged_at'=>'Creative acknowledged','first_submitted_at'=>'First creative submission','delivered_at'=>'Creative delivered','completed_at'=>'Creative completion confirmed'] as $field=>$title) if($part->$field)$dates[]=['label'=>$title.' · '.$part->title,'at'=>$part->$field,'owner'=>$recorder('creative_request',$part->id,$field,$part->$field)];
            }
        }
        foreach($handovers as $h) {
            $executionDue=$h->agreed_execution_due_at??$h->execution_due_at;
            $executionAt=$h->execution_completed_at??$h->submitted_at;

            $waiting=match(true) {
                $h->stage==='awaiting_acceptance'=>'Client Relations acceptance needed · '.($names[$project->owner_id]??'Account owner'),
                $h->stage==='accepted'=>'Independent Operations manager verification needed',
                in_array($h->stage,['completed','cancelled'],true)=>'',
                !$h->lead_id=>'Operations HOD assignment needed',
                !$h->meeting_held_at=>'Handover meeting needed',
                !$h->cr_signoff_at && !$h->ops_signoff_at=>'Client Relations and Operations sign-offs needed',
                !$h->cr_signoff_at=>'Client Relations sign-off needed',
                !$h->ops_signoff_at=>'Operations sign-off needed',
                !$h->handover_approved_at=>'Operations HOD handover approval needed',
                default=>'',
            };
            $work[]=['team'=>'Operations','title'=>$h->title,'owner'=>$h->lead?->name??$lanes['operations']['manager']??'Operations HOD · assignment needed',
                'support'=>collect($h->task?->supporting_staff_ids??[])->map(fn($id)=>$supportNames[$id]??'Retained staff history')->values()->all(),
                'from'=>$h->requester?->name??$names[$project->owner_id]??'Client Relations','manager'=>$lanes['operations']['manager'],
                'requested'=>$h->setup_due_at,'agreed'=>$h->agreed_setup_due_at,'requested_execution'=>$h->execution_due_at,'agreed_execution'=>$h->agreed_execution_due_at,'activation'=>$h->event_at,'start'=>$h->meeting_held_at,'delivered'=>$h->submitted_at,
                'state'=>ucwords(str_replace('_',' ',$h->stage)),'late'=>$executionDue&&($executionAt?$executionAt->gt($executionDue):($executionDue->isPast()&&!in_array($h->stage,['completed','cancelled'],true))),
                'conflict'=>($h->agreed_setup_due_at&&$h->setup_due_at&&$h->agreed_setup_due_at->gt($h->setup_due_at))||($h->agreed_execution_due_at&&$h->execution_due_at&&$h->agreed_execution_due_at->gt($h->execution_due_at)),'waiting'=>$waiting,'url'=>route('portal.operations.handovers.index',['handover'=>$h->id])];
            foreach(['created_at'=>'Execution plan routed','acknowledged_at'=>'Operations acknowledged','meeting_held_at'=>'Handover meeting held','cr_signoff_at'=>'Client Relations signed handover','ops_signoff_at'=>'Operations signed handover','submitted_at'=>'Execution delivery submitted','accepted_at'=>'Client Relations accepted execution','execution_due_at'=>'Requested execution completion deadline','agreed_setup_due_at'=>'Operations HOD setup commitment','agreed_execution_due_at'=>'Operations HOD execution commitment','execution_completed_at'=>'Actual execution completed · staff-reported','handover_approved_at'=>'Operations HOD approved handover','verified_at'=>'Operations completion verified'] as $field=>$label) if($h->$field)$dates[]=['label'=>$label,'at'=>$h->$field,'owner'=>$recorder('operations_handover',$h->id,$field,$h->$field)];
        }
        $historyPageRecords=PerformanceEvent::with('actor:id,name')->where(fn($q)=>$q->where(fn($q)=>$q->where('entity_type','crm_project')->where('entity_id',$project->id))
            ->orWhere(fn($q)=>$q->where('entity_type','creative_request')->whereIn('entity_id',$briefs->pluck('id')->merge($briefs->flatMap(fn($b)=>$b->parts->pluck('id')))))
            ->orWhere(fn($q)=>$q->where('entity_type','operations_handover')->whereIn('entity_id',$handovers->pluck('id'))))
            ->orderByDesc('id')->paginate(30,['*'],'history_page',$historyPage);
        $history=$historyPageRecords->getCollection()->map(function($event) use($viewer) {
                $changes=[];
                foreach(['requested_deadline','planned_start','internal_eta','setup_due_at','event_at','execution_due_at','agreed_setup_due_at','agreed_execution_due_at','execution_completed_at','client_relations_deadline','client_brief_received_at','client_brief_received_entered_at','client_brief_received_entered_by_id','handover_approved_at','handover_approved_by','assignee_id','lead_id','reviewer_id','stage'] as $field) {
                    if(($event->before[$field]??null)!==($event->after[$field]??null))$changes[]=['field'=>ucwords(str_replace('_',' ',$field)),'before'=>$event->before[$field]??null,'after'=>$event->after[$field]??null];
                }
                return ['action'=>ucwords(str_replace('_',' ',$event->action)),'actor'=>$event->actor?->name??'System','at'=>$event->created_at,'reason'=>$event->entity_type==='crm_project' && $event->action!=='team_requirements_updated' && !ClientRelationsPipeline::member($viewer) && !$viewer->canViewExecutiveActivity() ? 'Project record updated; commercial details remain in the authorised Client Relations workspace.' : $event->reason,'changes'=>$changes];
            });
        $sharedUpdates=\App\Models\CreativeUpdate::with('author:id,name')->whereIn('creative_request_id',$briefs->pluck('id')->merge($briefs->flatMap(fn($b)=>$b->parts->pluck('id'))))
            ->whereIn('kind',['comment','project_request_changed','project_reference_changed','changes_requested','needs_info'])->latest('id')->limit(30)->get()->map(fn($update)=>[
                'team'=>'Creative','actor'=>$update->author?->name??'Recorder unavailable','at'=>$update->created_at,'kind'=>ucwords(str_replace('_',' ',$update->kind)),'body'=>$update->body,
                'url'=>route('portal.creative',['request'=>$update->creative_request_id])]);
        $sharedUpdates=$sharedUpdates->concat($timelineEvents->filter(fn($e)=>$e->entity_type==='operations_handover'&&in_array($e->action,['comment','assign','meeting','return','project_request_changed'],true)&&filled($e->reason))->map(fn($e)=>[
            'team'=>'Operations','actor'=>$e->actor?->name??'Recorder unavailable','at'=>Carbon::parse($e->created_at),'kind'=>ucwords(str_replace('_',' ',$e->action)),'body'=>$e->reason,
            'url'=>route('portal.operations.handovers.index',['handover'=>$e->entity_id])]))->sortByDesc('at')->take(30)->values()->all();
        return ['project_id'=>$project->id,'title'=>$project->project_name,'client'=>$project->client_name,'account_owner'=>$names[$project->owner_id]??'Account owner assignment needed',
            'shared_updates'=>$sharedUpdates,'lanes'=>$lanes,'work'=>$work,'timeline'=>collect($dates)->sortBy('at')->values()->all(),'history'=>$history->all(),
            'original_deadlines'=>array_intersect_key($state,array_flip(['creative_original_deadline','operations_original_setup_due','operations_original_execution'])),
            'history_pagination'=>['page'=>$historyPageRecords->currentPage(),'last'=>$historyPageRecords->lastPage(),'total'=>$historyPageRecords->total(),'url'=>route('portal.project-journey.accountability',$project)],
            'url'=>route('portal.project-journey',['project'=>$project->id])];
    }
}
