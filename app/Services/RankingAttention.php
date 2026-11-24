<?php

namespace App\Services;

use App\Models\CreativeRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RankingAttention
{
    public function forPerson(User $person, Carbon $from, Carbon $to): array
    {
        $reset = PerformanceScoringService::resetStartsOn();
        $preparing = $reset && today()->lt($reset);
        $start = $reset && $from->lt($reset) ? $reset->copy() : $from->copy();
        $end = $to->lt($start) ? $start->copy()->endOfWeek() : $to->copy();
        $score = app(StaffPerformanceScore::class)->user($person, $start, $end);
        $issues = [];
        if($person->isSupervisionOnly() || $person->isServiceAccount())return ['person'=>$person,'issues'=>[],'score'=>$score,'preparing'=>false,'starts_on'=>$reset];
        $manager = $person->lineManager?->name;
        $reviewer = $manager ? 'Reviewing manager: '.$manager : 'Department reviewing manager';
        $add = function ($code, $message, $responsible) use (&$issues) {
            $issues[] = ['code' => $code, 'message' => $message, 'responsible' => $responsible];
        };
        // No missing-attendance or delivery accusation during approved absence/non-working windows.
        $eligible = ($score['expected_workdays'] ?? 0) > 0 || ($score['has_activity'] ?? false);
        if (! $eligible && ! $preparing) {
            return ['person' => $person, 'issues' => [], 'score' => $score, 'preparing' => false, 'starts_on' => $reset];
        }
        if (! empty($score['kpi_gaps'])) {
            $add('kpi_evidence', 'KPI reporting or review is incomplete: '.implode('; ', $score['kpi_gaps']).'.', 'Staff member and KPI reviewer');
        }
        $missing = max(0, ($score['expected_workdays'] ?? 0) - ($score['attendance_days'] ?? 0));
        if ($missing) {
            $add('missing_clockins', $missing.' eligible working day(s) have no recorded clock-in. Approved leave and non-working days are excluded.', 'Staff member and attendance reviewer');
        }
        $late = max(0, ($score['attendance_days'] ?? 0) - ($score['punctual_days'] ?? 0));
        if ($late) {
            $add('recorded_lateness', $late.' day(s) have recorded late clock-ins affecting punctuality. Review the original record if disputed.', 'Staff member and attendance reviewer');
        }
        $tasks = Task::realWork()->where('assigned_to', $person->id)->where(function ($q) use ($start, $end) {
            $q->whereBetween('due_on', [$start->toDateString(), $end->toDateString()])->orWhereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->orWhere(fn ($open) => $open->whereNull('due_on')->whereNotIn('status', ['Completed', 'Done', 'Cancelled', 'cancelled']));
        })->get();
        $undated = $tasks->whereNull('due_on')->whereNotIn('id',OperationsHandoverWorkflow::unconfirmedTaskIds())->count();
        if ($undated) {
            $add('missing_deadlines', $undated.' work item(s) need a deadline before timely delivery can be measured.', 'Assigning manager');
        }
        $pending = $tasks->filter(fn ($t) => in_array($t->completion_review_status, ['pending', 'submitted', 'pending_review'], true))->count();
        $pending += WeeklyConsolidatedItem::where('lead_staff_id', $person->id)->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])->whereIn('workflow_state', ['submitted', 'in_review', 'review', 'completed'])->count();
        if (User::normalizeDepartmentKey($person->department) === 'creatives') {
            $briefs = CreativeRequest::studioWork()->where('assignee_id', $person->id)->whereNotIn('stage', ['completed', 'cancelled'])->whereNull('internal_eta')->count();
            if ($briefs) {
                $add('creative_schedule', $briefs.' assigned brief(s) need the Creative HOD delivery commitment.', 'Creative HOD');
            }
        }

        $actionableTasks = $tasks->whereIn('id',$score['unverified_task_ids']??[])->filter(fn($task)=>!in_array(strtolower($task->status),['cancelled','canceled'],true) && (in_array(strtolower($task->status),['completed','done'],true) || ($task->due_on && $task->due_on->copy()->endOfDay()->lte(now()))));
        $actionableCommitments = WeeklyConsolidatedItem::whereIn('id',$score['unverified_commitment_ids']??[])->get()->filter(fn($item)=>in_array($item->workflow_state,['completed','verified','closed'],true) || ($item->week_end && $item->week_end->copy()->endOfDay()->lte(now())));
        if ($eligible && !$pending && $actionableTasks->count()+$actionableCommitments->count()>0) {
            $add('unverified_delivery', ($actionableTasks->count()+$actionableCommitments->count()).' due or reported-complete work item(s) need verification or completion. Open the original record for the required action.', $reviewer);
        }
        $dueResults=collect($score['kpis']??[])->filter(fn($measure)=>($measure['attention_due']??true) && ($measure['result_reported']??false));
        if ($dueResults->contains(fn($measure)=>is_numeric($measure['achievement']??null) && $measure['achievement']<100)) {
            $add('recorded_kpi_shortfall', 'Recorded due KPI achievement is '.round(PerformanceMath::creditedWeightedScore($dueResults), 1).'%. Check the underlying targets and reviewed results.', 'Staff member and KPI reviewer');
        }

        $ownReviews=app(ReviewAccountability::class)->rows()->filter(fn($r)=>(int)$r['submitter_id']===$person->id||(int)($r['owner_id']??0)===$person->id)->whereNull('decided_at');
        $managerReviews=app(ReviewAccountability::class)->rows()->where('reviewer_id',$person->id)->filter(fn($r)=>$r['due_at']->betweenIncluded($start->copy()->startOfDay(),$end->copy()->endOfDay()));
        $overdueReviews=$managerReviews->where('overdue',true);
        if($overdueReviews->isNotEmpty())$add('manager_review_overdue',$overdueReviews->count().' submitted item(s) passed the manager review deadline. Approve or return with correction notes.','Reviewing manager');
        return ['person' => $person, 'issues' => $issues, 'score' => $score, 'manager_reviews'=>$managerReviews->values()->all(), 'own_reviews'=>$ownReviews->values()->all(), 'actionable_unverified_task_ids'=>$actionableTasks->pluck('id')->all(),'actionable_unverified_commitment_ids'=>$actionableCommitments->pluck('id')->all(),'attention_task_ids' => $tasks->pluck('id')->all(), 'preparing' => (bool) $preparing, 'starts_on' => $reset];
    }

    public function visible(User $viewer, Carbon $from, Carbon $to, ?int $person = null, ?string $department = null): Collection
    {
        $executive = $viewer->canViewExecutiveActivity();
        $people = User::internalStaff()->where('status', 'active')->when(! $executive, fn ($q) => $q->whereKey($viewer->id))->when($person, fn ($q) => $q->whereKey($person))->orderBy('name')->get();

        return $people->reject(fn ($u) => $u->isServiceAccount() || $u->isSupervisionOnly())
            ->filter(fn ($u) => ! $department || User::normalizeDepartmentKey($u->department) === User::normalizeDepartmentKey($department))
            ->map(fn ($u) => $this->forPerson($u, $from, $to))->filter(fn ($row) => count($row['issues']) > 0)->values();
    }

    public function forRequest(User $viewer, Carbon $from, Carbon $to, ?int $person = null, ?string $department = null): Collection
    {
        $key = 'ranking_attention.'.hash('sha256', $viewer->id.'|'.$from->toDateString().'|'.$to->toDateString().'|'.$person.'|'.$department);
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, $this->visible($viewer, $from, $to, $person, $department));
        }

        return request()->attributes->get($key);
    }

    public function notify(): int
    {
        $executives = User::internalStaff()->where('status', 'active')->get()->filter(fn ($u) => $u->canViewExecutiveActivity());
        $viewer = $executives->first();
        if (! $viewer) {
            return 0;
        }
        $from = today()->startOfMonth();
        $to = today()->endOfMonth();
        $rows = $this->visible($viewer, $from, $to);
        $queued = 0;
        foreach ($rows as $row) {
            $signature = hash('sha256', json_encode($row['issues']));
            $person = $row['person'];
            $key = 'ranking-attention:'.today()->toDateString().':'.$person->id.':'.$signature;
            $body = ($row['preparing'] ? 'Prepare for the new performance cycle starting '.$row['starts_on']->format('d M Y').'.' : 'Your ranking has data, review or recorded-outcome flags.')."\n".collect($row['issues'])->map(fn ($i) => $i['message'].' Responsible: '.$i['responsible'])->implode("\n")."\nFlags remain visible until the underlying records change. Open the dashboard for current status.";
            if (! $person->canViewExecutiveActivity()) {
                PerformanceWorkflow::notify($person->id, $key, 'Ranking attention · action or review needed', $body, route('dashboard').'#ranking-attention');
            }
            $queued++;
        }
        if ($rows->isNotEmpty()) {
            $signature = hash('sha256', $rows->map(fn ($r) => $r['person']->id.':'.hash('sha256', json_encode($r['issues'])))->implode('|'));
            $body = $rows->count()." staff member(s) have ranking readiness or recorded-outcome flags.\n".$rows->map(fn ($r) => $r['person']->name.' · '.count($r['issues']).' flag(s)')->implode("\n")."\nOpen Accountability for current reasons and responsibilities. Corrected records automatically clear the corresponding live flags.";
            foreach ($executives as $recipient) {
                PerformanceWorkflow::notify($recipient->id, 'ranking-attention-executive:'.today()->toDateString().':'.$signature, 'Staff rankings · persistent attention flags', $body, route('dashboard', ['exec_view' => 'accountability']).'#ranking-attention');
            }
        }

        return $queued;
    }
}
