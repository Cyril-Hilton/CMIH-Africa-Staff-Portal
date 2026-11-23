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
        if (empty($score['kpi_expected'])) {
            $add('kpi_setup', 'Required KPI targets or reporting setup are missing.', 'Staff member and reporting manager');
        } elseif (! empty($score['kpi_gaps'])) {
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
        if ($eligible && ($score['completion_total'] ?? 0) === 0) {
            $add('delivery_setup', 'No measurable work is linked to this period for verified delivery. Record the work and confirm its owner and dates.', 'Staff member and department manager');
        }
        $tasks = Task::realWork()->where('assigned_to', $person->id)->where(function ($q) use ($start, $end) {
            $q->whereBetween('due_on', [$start->toDateString(), $end->toDateString()])->orWhereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->orWhere(fn ($open) => $open->whereNull('due_on')->whereNotIn('status', ['Completed', 'Done', 'Cancelled', 'cancelled']));
        })->get();
        $undated = $tasks->whereNull('due_on')->count();
        if ($undated) {
            $add('missing_deadlines', $undated.' work item(s) need a deadline before timely delivery can be measured.', 'Assigning manager');
        }
        $pending = $tasks->filter(fn ($t) => in_array($t->completion_review_status, ['pending', 'submitted', 'pending_review'], true))->count();
        $pending += WeeklyConsolidatedItem::where('lead_staff_id', $person->id)->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])->whereIn('workflow_state', ['submitted', 'in_review'])->count();
        if ($pending) {
            $add('awaiting_verification', $pending.' submitted work item(s) await manager review; submission does not yet count as verified completion.', $reviewer);
        }
        if (User::normalizeDepartmentKey($person->department) === 'creatives') {
            $briefs = CreativeRequest::studioWork()->where('assignee_id', $person->id)->whereNotIn('stage', ['completed', 'cancelled'])->whereNull('internal_eta')->count();
            if ($briefs) {
                $add('creative_schedule', $briefs.' assigned brief(s) need the Creative HOD delivery commitment.', 'Creative HOD');
            }
        }

        if ($eligible && ! $pending && ($score['completion_total'] ?? 0) > ($score['completion_verified'] ?? 0)) {
            $add('unverified_delivery', (($score['completion_total'] ?? 0) - ($score['completion_verified'] ?? 0)).' linked work item(s) are not yet verified. These remain provisional, rather than completed performance credit.', 'Staff member and '.$reviewer);
        }
        if (empty($score['kpi_gaps']) && is_numeric($score['kpi_rate'] ?? null) && $score['kpi_rate'] < 100) {
            $add('recorded_kpi_shortfall', 'Recorded KPI achievement is '.round($score['kpi_rate'], 1).'%. Check the underlying targets and reviewed results.', 'Staff member and KPI reviewer');
        }

        return ['person' => $person, 'issues' => $issues, 'score' => $score, 'attention_task_ids' => $tasks->pluck('id')->all(), 'preparing' => (bool) $preparing, 'starts_on' => $reset];
    }

    public function visible(User $viewer, Carbon $from, Carbon $to, ?int $person = null, ?string $department = null): Collection
    {
        $executive = $viewer->canViewExecutiveActivity();
        $people = User::internalStaff()->where('status', 'active')->when(! $executive, fn ($q) => $q->whereKey($viewer->id))->when($person, fn ($q) => $q->whereKey($person))->orderBy('name')->get();

        return $people->reject(fn ($u) => $u->isServiceAccount() || ($u->access_role === 'super_admin' && ! in_array(strtolower(trim($u->name)), ['cyril hilton', 'cyril hilton wemegah'], true)))
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
