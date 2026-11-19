<?php
namespace App\Services\Creative;

use App\Models\{CreativeRequest, User};
use Illuminate\Support\Carbon;

/** Deadline provenance is factual: a project owner is never assumed to have set a date. */
class CreativeTimeline
{
    public function build(CreativeRequest $brief): array
    {
        $stamp=fn($event)=>$event?->created_at?Carbon::parse($event->created_at)->toIso8601String():null;
        $root=$brief->parentRequest ?? $brief;
        $project=$root->crmProject;
        $events=($root->id===$brief->id?$root->deadlineHistory:$root->deadlineHistory->merge($brief->deadlineHistory))->sortBy('id');
        $submitted=$events->firstWhere('action','submitted');
        $cr=$events->filter(function($event) use($project){
            if($event->action==='client_relations_deadline_recorded')return (int)($event->after['crm_project_id']??0)===(int)$project?->id;
            return in_array($event->action,['submitted','project_request_changed'],true)
                && User::normalizeDepartmentKey($event->actor?->department)==='client_relations'
                && !empty($event->after['requested_deadline']) && ($event->action==='submitted'||($event->before['requested_deadline']??null)!==$event->after['requested_deadline']);
        })->last();
        $crDate=$cr?->action==='client_relations_deadline_recorded'?($cr->after['client_relations_deadline']??null):($cr?->after['requested_deadline']??null);
        $state=$project?->team_workflow??[];
        if(!$crDate && (int)($state['creative_request_id']??0)===$root->id)$crDate=$state['creative']['requested_deadline']??$state['creative_original_deadline']??null;
        $hod=$brief->planChanges->filter(fn($e)=>($e->before['internal_eta']??null)!==($e->after['internal_eta']??null))->last();
        if(!$hod&&$submitted&&!empty($submitted->after['internal_eta']))$hod=$submitted;
        $hodActor=$hod?->actor ?? ($brief->planned_by?User::find($brief->planned_by):null);
        $crActor=$cr?->actor;
        $original=$submitted?->after['requested_deadline']??null;
        $history=$events->filter(fn($e)=>$e->action==='client_relations_deadline_recorded'||$e->action==='submitted'||($e->before['requested_deadline']??null)!==($e->after['requested_deadline']??null)||($e->before['internal_eta']??null)!==($e->after['internal_eta']??null))->map(fn($e)=>[
            'actor'=>$e->actor?->name??'Recorder unavailable','at'=>$stamp($e),'action'=>$e->action,
            'before'=>collect($e->before??[])->only(['requested_deadline','internal_eta','planned_start','client_relations_deadline'])->all(),
            'after'=>collect($e->after??[])->only(['requested_deadline','internal_eta','planned_start','client_relations_deadline'])->all(),
        ])->values()->all();
        $received=$state['client_brief_received_at']??null;
        $enteredBy=!empty($state['client_brief_received_entered_by_id'])?User::find($state['client_brief_received_entered_by_id']):null;
        $handed=$state['creative_routed_at']??$root->received_at?->toIso8601String();
        $gap=$received&&$handed?round(Carbon::parse($received)->diffInHours(Carbon::parse($handed),false),1):null;
        return ['client_brief_received_at'=>$received,'client_brief_received_recorded_by'=>$enteredBy?->name,'client_brief_received_recorded_at'=>$state['client_brief_received_entered_at']??null,
            'project_created_at'=>$project?->created_at?->toIso8601String(),'creative_created_at'=>$root->created_at?->toIso8601String(),'creative_submitted_at'=>$root->received_at?->toIso8601String(),'creative_routed_at'=>$handed,'receipt_to_creative_hours'=>$gap,
            'client_relations_deadline'=>$crDate?Carbon::parse($crDate,config('creative.timezone'))->toIso8601String():null,
            'client_relations_recorded_by'=>$crActor?->name,'client_relations_recorded_at'=>$stamp($cr),
            'client_relations_source'=>$cr?->action==='client_relations_deadline_recorded'?'Reported deadline entered in portal':($crDate?'Recorded Client Relations brief':'Client Relations deadline needs recording'),
            'original_requester_deadline'=>$original,'original_recorded_by'=>$submitted?->actor?->name,'original_recorded_at'=>$stamp($submitted),
            'hod_recorded_by'=>$hodActor?->name,'hod_recorded_at'=>$stamp($hod),
            'history'=>$history];
    }
}
