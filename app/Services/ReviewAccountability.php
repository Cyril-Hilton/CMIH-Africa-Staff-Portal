<?php
namespace App\Services;

use App\Models\{User,Task,WeeklyConsolidatedItem,PerformanceEvent};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Review deadlines are frozen on submission; decisions never rewrite a late response. */
class ReviewAccountability
{
    private const SOURCES = [
        'task'=>[Task::class,'creatives',['submit_completion']],
        'commitment'=>[WeeklyConsolidatedItem::class,null,['submit','complete']],
        'brands_submission'=>[\App\Models\BrandsReportSubmission::class,'brands_marketing',['submitted']],
        'crm_submission'=>[\App\Models\CrmReportSubmission::class,'client_relations',['submit']],
        'operations_submission'=>[\App\Models\OperationsReportSubmission::class,'operations_projects',['submit']],
        'register'=>[\App\Models\PerformanceRegister::class,null,['create','finance_submit','finance_update']],
        'hr_report'=>[\App\Models\HrPeriodicReport::class,'hr_admin',['submit']],
        'admin_weekly'=>[\App\Models\AdminWeeklyReport::class,'hr_admin',['submit']],
        'observation'=>[\App\Models\PerformanceObservation::class,null,['submit']],
        'target_plan'=>[\App\Models\PerformanceTargetPlan::class,null,['propose','propose_amendment']],
        'warehouse_asset_suggestion'=>[\App\Models\WarehouseAssetSuggestion::class,'operations_projects',['submitted']],
        'operations_handover'=>[\App\Models\OperationsHandover::class,'operations_projects',['submitted','submit','accept','signoff_cr','signoff_ops']],
    ];

    public static function deadline(Carbon $submitted, ?User $reviewer = null): Carbon
    {
        $day=$submitted->copy()->startOfDay();
        do {
            $day->addDay();
            $leave=$reviewer && \App\Models\LeaveApplication::where('user_id',$reviewer->id)->where('status','approved')->whereDate('start_date','<=',$day)->whereDate('end_date','>=',$day)->exists();
        } while ($day->isWeekend() || in_array($day->toDateString(),CompanyCalendar::nonWorkingDates(),true) || $leave);
        return $day->endOfDay();
    }

    public static function snapshot(string $type,int $id,?int $actor,string $action,array $after, ?Carbon $submitted = null): ?array
    {
        if (!isset(self::SOURCES[$type]) || !in_array($action,self::SOURCES[$type][2],true) || !$actor) return null;
        if($type==='operations_handover' && in_array($action,['signoff_cr','signoff_ops'],true) && (empty($after['cr_signoff_at']) || empty($after['ops_signoff_at']) || !empty($after['handover_approved_at'])))return null;
        $class=self::SOURCES[$type][0];
        if(!class_exists($class) || !($record=$class::find($id))) return null;
        if($type==='register' && !empty($after['approved_at'])) return null;
        $submitter=User::find($record->lead_staff_id ?? $record->owner_id ?? $record->payload['owner_id'] ?? $record->prepared_by ?? $record->submitted_by ?? $actor);
        $ownerId=$submitter?->id;
        if(!in_array($type,['task','commitment','operations_handover'],true))$submitter=User::find($record->submitted_by??$record->prepared_by??$actor);
        if(!$submitter) return null;
        $department=self::SOURCES[$type][1] ?? $record->department ?? $record->kpi?->department ?? PerformanceAccess::department($submitter);
        if($type==='task')$department=$record->department;
        $reviewerId=$after['manager_id'] ?? $record->reviewer_id ?? $record->kpi?->reviewer_id;
        if($type==='task')$reviewerId=$record->completionReviewTask?->assigned_to ?? ($record->custom_fields['completion_manager_id']??null);
        if($department==='finance' && $type==='register')$reviewerId=config('finance_reporting.manager_user_id');
        if($type==='operations_handover' && $action==='accept')$submitter=User::find($record->lead_id)??$submitter;
        $permitted=fn($u)=>match($type){'register'=>$department==='finance'?FinanceDashboard::manager($u):($u->isLineManager()||$u->isCvoOrSuperAdmin()),'hr_report'=>$u->hasFullHrAccess(),'warehouse_asset_suggestion'=>app(WarehouseAssetIntake::class)->canReview($u),default=>$u->isLineManager()||$u->isCvoOrSuperAdmin()};
        $eligible=fn($u)=>$u && $u->isActive() && !$u->isServiceAccount() && $u->id!==$submitter->id && $permitted($u);
        $reviewer=User::find($reviewerId??0);
        if(!$eligible($reviewer))$reviewer=User::find($submitter->line_manager_id??0);
        if(!$eligible($reviewer))$reviewer=User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases($department))->orderBy('id')->get()->first($eligible);
        if(!$eligible($reviewer))$reviewer=null;
        // Managers publishing their own entries do not owe themselves an independent review.
        if(in_array($type,['brands_submission','crm_submission','operations_submission'],true) && PerformanceAccess::manages($submitter,$department))return null;
        $submitted=$submitted??now();
        $policyStart=Carbon::parse('2026-11-23 00:00:00');
        $deadline=self::deadline($submitted->copy()->max($policyStart),$reviewer);
        return ['reviewer_id'=>$reviewer?->id,'submitter_id'=>$submitter->id,'owner_id'=>$ownerId,'department'=>$department,'submitted_at'=>$submitted->toDateTimeString(),'due_at'=>$deadline->toDateTimeString(),'period_start'=>$record->period_start?->toDateString()??$record->payload['period_start']??$submitted->toDateString(),'period_end'=>$record->period_end?->toDateString()??$record->payload['period_end']??$submitted->toDateString(),'kind'=>$record->category??$record->kind??$record->payload['kind']??$record->entity_type??null,'title'=>$record->title??$record->payload['title']??$record->campaign_name??$record->reference??ucwords(str_replace('_',' ',$type)).' #'.$id];
    }

    public function rows(): Collection
    {
        $key='manager_review_accountability';
        if(request()->attributes->has($key))return request()->attributes->get($key);
        $start=PerformanceScoringService::resetStartsOn() ?? Carbon::parse('2026-11-23');
        $events=PerformanceEvent::whereIn('entity_type',array_keys(self::SOURCES))->where('created_at','>=',$start)->orderBy('id')->get();
        $rows=collect();
        foreach($events->groupBy(fn($e)=>$e->entity_type.':'.$e->entity_id) as $history){
            $open=null;
            foreach($history as $event){
                $snapshot=$event->after['_review_accountability']??self::snapshot($event->entity_type,$event->entity_id,$event->actor_id,$event->action,$event->after??[],Carbon::parse($event->created_at));
                if($open!==null && in_array($event->action,['approve','return','revert','complete','verify','withdraw','reject','acknowledge','accept','approve_handover','publish'],true)){
                    $rows[$open]=array_merge($rows[$open],['decided_at'=>Carbon::parse($event->created_at),'decision'=>$event->action,'decided_by'=>$event->actor_id]);$open=null;
                }
                if($snapshot && $open!==null)continue;
                if($snapshot){
                    // A new submission closes only the prior episode; it never erases its deadline.
                    $rows->push($snapshot+['event_id'=>$event->id,'type'=>$event->entity_type,'record_id'=>$event->entity_id,'decided_at'=>null,'decision'=>null,'decided_by'=>null]);$open=$rows->count()-1;
                }
            }
        }
        $rows=$rows->map(function($r){$r['submitted_at']=Carbon::parse($r['submitted_at']);$r['due_at']=Carbon::parse($r['due_at']);$r['overdue']=!$r['decided_at']&&$r['due_at']->isPast();$r['timely']=$r['decided_at']&&$r['decided_at']->lte($r['due_at']);return $r;});
        request()->attributes->set($key,$rows);return $rows;
    }

    public function measure(User $user,Carbon $start,Carbon $end): ?array
    {
        $due=$this->rows()->where('reviewer_id',$user->id)->filter(fn($r)=>$r['due_at']->betweenIncluded($start->copy()->startOfDay(),$end->copy()->endOfDay())&&$r['due_at']->lte(now()));
        if($due->isEmpty())return null;
        return ['id'=>'manager-review-timeliness','name'=>'Manager review turnaround','weight'=>10,'achievement'=>round(100*$due->where('timely',true)->count()/$due->count(),2),'result_reported'=>true,'source_ids'=>$due->pluck('event_id')->all(),'total'=>$due->count(),'on_time'=>$due->where('timely',true)->count()];
    }

    public function pendingAreas(int $owner, string $department): array
    {
        $areas=[];
        foreach($this->rows()->filter(fn($r)=>(int)($r['owner_id']??$r['submitter_id'])===$owner)->whereNull('decided_at')->where('department',$department) as $row){
            $kind=$row['kind']??null;
            $area=match($department){
                'brands_marketing'=>match($kind){'people'=>'People & team coverage','perfect_store','campaign'=>'Merchandising performance','activation','visibility'=>'Activations & visibility','workstream'=>'Weekly workstreams',default=>null},
                'finance'=>match($kind){'revenue','revenue_target','profitability'=>'Revenue','invoice','payable'=>'Invoices','cash','receipt','payment'=>'Cash Flow','expense'=>'Expenses','commercial_risk'=>'Commercial Risks','payroll'=>'Payroll','tax'=>'Taxes & SSNIT',default=>null},
                'operations_projects'=>match($kind){'activation','weekly_update','team_allocation'=>'Projects & activations','posm_deployment'=>'Assets & POSM','fabrication','procurement'=>'Procurement & execution',default=>null},
                'client_relations'=>match($kind){'project','weekly'=>'Projects & delivery','target'=>'Revenue','client'=>'Growth & pipeline',default=>null},
                default=>null,
            };
            if($area)$areas[]=$area;
        }
        return array_unique($areas);
    }

    public static function taskSubmittedOnTime(Task $task): bool
    {
        $key='task_first_submission_'.$task->id;
        if(!request()->attributes->has($key))request()->attributes->set($key,PerformanceEvent::where('entity_type','task')->where('entity_id',$task->id)->where('action','submit_completion')->orderBy('id')->first());
        $first=request()->attributes->get($key);
        $submitted=$first?Carbon::parse($first->created_at):$task->completion_review_requested_at;
        $due=!empty($first?->after['due_on'])?Carbon::parse($first->after['due_on'])->endOfDay():$task->due_on?->copy()->endOfDay();
        return $submitted && $due && $submitted->lte($due);
    }

    public static function timelyTaskSubmission(Task $task): bool
    {
        return in_array($task->completion_review_status,['pending','submitted','pending_review'],true) && $task->completion_review_requested_at && $task->due_on && self::taskSubmittedOnTime($task);
    }

    public static function timelyCommitmentSubmission(WeeklyConsolidatedItem $item): bool
    {
        return in_array($item->workflow_state,['review','completed'],true) && $item->submitted_at && self::commitmentSubmittedOnTime($item);
    }

    public static function commitmentSubmittedOnTime(WeeklyConsolidatedItem $item): bool
    {
        $first=$item->exists ? PerformanceEvent::where('entity_type','commitment')->where('entity_id',$item->id)->where('action','submit')->orderBy('id')->first() : null;
        $submitted=$first?Carbon::parse($first->created_at):($item->submitted_at??$item->completed_at);
        $originalDue=$first?->after['project_ends_on']??$first?->after['week_end']??null;
        $due=$originalDue?Carbon::parse($originalDue)->endOfDay():$item->dueDate();
        return $submitted && $due && $submitted->lte($due);
    }

    public static function url(array $row): string
    {
        return route('portal.performance.index',['view'=>'reviews','review_event'=>$row['event_id']]).'#review-'.$row['event_id'];
    }
}
