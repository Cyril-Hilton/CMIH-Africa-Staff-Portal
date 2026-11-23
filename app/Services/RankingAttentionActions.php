<?php
namespace App\Services;

use App\Models\{CreativeRequest,Task,User,WeeklyConsolidatedItem};
use App\Services\Creative\CreativeAccess;
use Illuminate\Support\Carbon;

/** Navigation only. Flags and score calculations remain in their source services. */
class RankingAttentionActions
{
    public function forPerson(User $viewer, array $row, Carbon $from, Carbon $to): array
    {
        $person=$row['person'];$score=$row['score'];$actions=[];
        $add=function($code,$label,$url)use(&$actions){$actions[$code][$url]=['label'=>$label,'url'=>$url];};
        $department=PerformanceAccess::department($person);
        $context=['owner'=>$person->id,'department'=>$department,'from'=>$from->toDateString(),'to'=>$to->toDateString()];
        $hub=PerformanceAccess::executive($viewer)||PerformanceAccess::department($viewer)===$department;
        if($hub){
            $add('kpi_setup','Open targets & KPI setup',route('portal.performance.index',$context+['view'=>'kpis']).'#kpi-targets');
            foreach($score['kpis']??[] as $kpi){
                if(($kpi['result_reported']??false)&&!in_array('recorded_kpi_shortfall',array_column($row['issues'],'code')))continue;
                if(!empty($kpi['id']) && !is_numeric($kpi['id'])){
                    if($kpi['id']==='manager-review-timeliness')$add('recorded_kpi_shortfall','Review manager turnaround',route('portal.performance.index',$context+['view'=>'reviews']).'#manager-reviews');
                    elseif($kpi['id']==='staff-submission-timeliness'){foreach(Task::whereIn('id',$kpi['source_ids']??[])->get() as $sourceTask)if($sourceTask->canBeViewedBy($viewer))$add('recorded_kpi_shortfall','Review submission: '.$sourceTask->title,route($sourceTask->canBeEditedBy($viewer)?'portal.tasks.edit':'portal.tasks.show',$sourceTask));}
                    elseif($kpi['id']==='creative-review-timeliness'){foreach(CreativeRequest::whereIn('id',$kpi['source_ids']??[])->get() as $sourceBrief)if(CreativeAccess::view($viewer,$sourceBrief))$add('recorded_kpi_shortfall','Review brief: '.$sourceBrief->title,route('portal.creative',['request'=>$sourceBrief->id]));}
                    continue;
                }
                if(!empty($kpi['id'])){
                    $anchor=!empty($kpi['target_plan_id'])?'target-plan-'.$kpi['target_plan_id']:'kpi-targets';
                    $url=route('portal.performance.index',$context+['view'=>'kpis','kpi'=>$kpi['id']]).'#'.$anchor;
                    $add('kpi_evidence','Open '.$kpi['name'],$url);$add('recorded_kpi_shortfall','Review '.$kpi['name'],$url);
                }
            }
        }
        // Dashboard-derived KPIs are corrected in their original reporting module.
        foreach($score['kpis']??[] as $kpi)if(empty($kpi['id'])&&!($kpi['result_reported']??false)){
            $url=$department==='creatives' ? route('portal.performance.index',$context+['view'=>'kpis','source_gap'=>$kpi['name']]).'#source-gap' : $this->reportingArea($person,$from,$to,$kpi['name']);
            $add('kpi_evidence','Open '.$kpi['name'],$url);
        }
        $add('delivery_setup','Open department reporting',$this->reportingArea($person,$from,$to));
        $add('recorded_kpi_shortfall','Open source reports',$this->reportingArea($person,$from,$to));
        $tasks=Task::with(['creativeRequest','assignee'])->whereIn('id',array_unique(array_merge($score['source_tasks']??[],$score['unverified_task_ids']??[],$row['attention_task_ids']??[])))->get();
        foreach($tasks as $task){
            if(!$task->canBeViewedBy($viewer))continue;
            $brief=$task->creativeRequest;
            $url=$brief&&CreativeAccess::view($viewer,$brief)?route('portal.creative',['request'=>$brief->id]):route($task->canBeEditedBy($viewer)?'portal.tasks.edit':'portal.tasks.show',$task);
            $label=($brief?'Open brief: ':'Open task: ').($brief?->title?:$task->title);
            if(in_array($task->id,$score['unverified_task_ids']??[]))$add('unverified_delivery',$label,$url);
            if(in_array($task->completion_review_status,['pending','submitted','pending_review'],true))$add('awaiting_verification',$label,$url);
        }
        foreach(WeeklyConsolidatedItem::whereIn('id',$score['source_commitments']??[])->get() as $item){
            if(!PerformanceAccess::view($viewer,$item))continue;
            $url=route('portal.performance.index',$context+['view'=>'commitments','item'=>$item->id]).'#commitment-'.$item->id;
            if(in_array($item->id,$score['unverified_commitment_ids']??[]))$add('unverified_delivery','Open report: '.($item->campaign_name?:'WCT-'.$item->id),$url);
            if(in_array($item->workflow_state,['submitted','in_review','review','completed'],true))$add('awaiting_verification','Open report: '.($item->campaign_name?:'WCT-'.$item->id),$url);
        }
        foreach(Task::realWork()->with(['creativeRequest','assignee'])->where('assigned_to',$person->id)->whereNull('due_on')->whereNotIn('status',['Completed','Done','Cancelled','cancelled'])->get() as $task){
            if(!$task->canBeViewedBy($viewer))continue;
            $url=$task->creativeRequest?route('portal.creative',['request'=>$task->creativeRequest->id]):route($task->canBeEditedBy($viewer)?'portal.tasks.edit':'portal.tasks.show',$task);
            $add('missing_deadlines','Set deadline: '.$task->title,$url);
        }
        if($department==='creatives')foreach(CreativeRequest::studioWork()->where('assignee_id',$person->id)->whereNotIn('stage',['completed','cancelled'])->whereNull('internal_eta')->get() as $brief)if(CreativeAccess::view($viewer,$brief))$add('creative_schedule','Open planning: '.$brief->title,route('portal.creative',['request'=>$brief->id]));
        if($viewer->hasFullHrAccess()||$viewer->canAuditHrAndFinance()){
            foreach(['missing_clockins'=>'missing','recorded_lateness'=>'late'] as $code=>$state)$add($code,'Review attendance records',route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>$from->toDateString(),'q'=>$person->name,'punctuality'=>$state]));
        }else{
            if($viewer->id===$person->id){
                if(!\App\Models\Attendance::where('user_id',$person->id)->whereDate('clock_in_at',today())->exists())$add('missing_clockins','Open clock-in form',route('dashboard').'#clock-in-form');
                $add('missing_clockins','Review your attendance',route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>$from->toDateString()]));
            }
            $add('recorded_lateness','Review your attendance',route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>$from->toDateString(),'punctuality'=>'late']));
        }
        foreach($row['own_reviews']??[] as $review)$add(empty($review['reviewer_id'])?'reviewer_assignment_missing':'awaiting_manager_review','View review deadline: '.$review['title'],ReviewAccountability::url($review));
        foreach($row['manager_reviews']??[] as $review)if($review['overdue'])$add('manager_review_overdue','Review: '.$review['title'],ReviewAccountability::url($review));
        return array_map('array_values',$actions);
    }

    public function reportingArea(User $person, Carbon $from, Carbon $to, ?string $area=null): string
    {
        $dept=PerformanceAccess::department($person);$period=['report_period'=>'month','report_date'=>$from->toDateString(),'weekly_from'=>$from->toDateString(),'weekly_to'=>$to->toDateString()];
        $tab=match($dept){
            'brands_marketing'=>['brands_tab'=>match($area){'People & team coverage'=>'people','Merchandising performance'=>'perfect_store','Activations & visibility'=>'activation',default=>'workstream'},'brands_owner'=>$person->id,'brands_period'=>'month','brands_date'=>$from->toDateString()],
            'creatives'=>['creative_view'=>'board'],
            'finance'=>['finance_tab'=>array_search($area,FinanceDashboard::TABS,true)?:'revenue','finance_owner'=>$person->id,'finance_period'=>'custom','finance_from'=>$from->toDateString(),'finance_to'=>$to->toDateString()],
            'operations_projects'=>['ops_tab'=>match($area){'Assets & POSM'=>'assets','Procurement & execution'=>'execution','Reports, approvals & risks'=>'follow-up',default=>'projects'}],
            'hr_admin'=>['hr_tab'=>'overview','hr_period'=>'month','hr_from'=>$from->toDateString(),'hr_to'=>$to->toDateString()],
            default=>[],
        };
        return route('dashboard',$period+['weekly_department'=>$dept]+$tab).'#performance-panel-'.$dept;
    }
}
