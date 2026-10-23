<?php

namespace App\Services;

use App\Models\PerformanceKpi;
use App\Models\PerformanceObservation;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffPerformanceScore
{
    public const WEIGHTS = ['punctuality' => 10, 'completion' => 65, 'kpi' => 25];

    public function user(User $user, Carbon $start, Carbon $end): array
    {
        $departments = User::departmentAliases($user->department);
        if (PerformanceAccess::department($user) === 'hr_admin') {
            $departments[] = 'admin_drivers';
        }
        $attendance = PerformanceScoringService::attendanceSummary($user, $start, $end);
        $tasks = Task::query()->realWork()->where('assigned_to', $user->id)->whereRaw("CASE WHEN completion_review_status = 'approved' AND completion_reviewed_at IS NOT NULL THEN completion_reviewed_at ELSE COALESCE(due_on, created_at) END BETWEEN ? AND ?", [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->get();
        $neutralTaskIds=\App\Services\Creative\CreativeAccountability::neutralTaskIds();
        $awaitingCreativeReview=$tasks->whereIn('id',$neutralTaskIds)->count();
        $tasks=$tasks->whereNotIn('id',$neutralTaskIds);
        $linked = WeeklyConsolidatedItem::where('lead_staff_id', $user->id)->where('workflow_state', '!=', 'legacy')->whereIn('source_task_id', $tasks->pluck('id'))->get();
        $verifiedLinkedTaskIds = $this->verifiedCommitments($linked, $user)->pluck('source_task_id');
        $reviewers = User::whereIn('id', $tasks->pluck('completion_reviewed_by')->filter()->unique())->get()->keyBy('id');
        $verifiedTaskRows = $tasks->filter(fn ($task) => ($task->completion_review_status === 'approved' && $task->completion_reviewed_at && $this->managerSignedOff($reviewers->get($task->completion_reviewed_by), $user) && TaskStatsService::wasApprovedByExpectedManager($task, $user)) || $verifiedLinkedTaskIds->contains($task->id));
        $verifiedTasks = $verifiedTaskRows->count();
        $commitments = WeeklyConsolidatedItem::where('lead_staff_id', $user->id)->whereNull('source_task_id')->where('workflow_state', '!=', 'legacy')->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])->get();
        $verifiedCommitmentRows = $this->verifiedCommitments($commitments, $user);
        $done = $verifiedTasks + $verifiedCommitmentRows->count();
        $weightFor = fn ($work) => DeliveryImpact::points($work,$user);
        $totalPoints = $tasks->sum($weightFor) + $commitments->sum($weightFor);
        $verifiedPoints = $verifiedTaskRows->sum($weightFor) + $verifiedCommitmentRows->sum($weightFor);
        $total = $tasks->count() + $commitments->count();
        $definitions = PerformanceKpi::whereIn('department', $departments)->where('owner_id', $user->id)->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $end))->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start))->whereHas('objective', fn ($q) => $q->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start))->get();
        $observations = PerformanceObservation::with('kpi')->whereIn('kpi_id', $definitions->pluck('id'))->where('owner_id', $user->id)->where('approved_by', '!=', $user->id)->whereNull('target_plan_id')->whereNotNull('approved_at')->whereDate('period_start', '>=', $start)->whereDate('period_end', '<=', $end)->get();
        $report = app(PerformanceHubReport::class);
        $measures = [];
        foreach ($definitions as $definition) {
            $rows = $observations->where('kpi_id', $definition->id);
            $target = $report->aggregate($rows, $definition->aggregation, 'target');
            $actual = $report->aggregate($rows, $definition->aggregation);
            $achievement = PerformanceMath::achievement($target, $actual, $definition->direction);
            $measures[] = ['id' => $definition->id, 'name' => $definition->name, 'weight' => $definition->weight, 'achievement' => $achievement === null ? null : min(100, max(0, $achievement)), 'source_ids' => $rows->pluck('id')->all()];
        }
        $plans = \App\Models\PerformanceTargetPlan::with(['kpi', 'observation'])->whereNull('withdrawn_at')->where(fn ($q) => $q->whereNull('amends_plan_id')->orWhereNotNull('approved_at'))->whereHas('kpi', fn ($q) => $q->whereIn('department', $departments))->where('scope_type', 'individual')->where('owner_id', $user->id)->whereDate('period_start', '>=', $start)->whereDate('period_end', '<=', $end)->get();
        if ($plans->isNotEmpty()) {
            $measures = $plans->map(function ($plan) {
                $observation = $plan->observation;
                $achievement = $plan->approved_at && $observation?->approved_at && (int) $observation->approved_by !== (int) $plan->owner_id ? PerformanceMath::achievement($plan->target, $observation->actual, $plan->kpi->direction) : null;

                return ['id' => $plan->kpi_id, 'target_plan_id' => $plan->id, 'name' => $plan->kpi->name, 'weight' => $plan->weight, 'achievement' => $achievement === null ? null : min(100, max(0, $achievement)), 'source_ids' => $observation?->approved_at ? [$observation->id] : []];
            })->all();
        }
        $reviewMeasure=\App\Services\Creative\CreativeAccountability::reviewerMeasure($user,$start,$end);
        if($reviewMeasure)$measures[]=$reviewMeasure;
        $expectedMeasures = count($measures);
        $measured = collect($measures)->whereNotNull('achievement');
        $kpiWeight = $measured->sum('weight');
        $kpi = $kpiWeight > 0 && $measured->count() === $expectedMeasures ? round($measured->sum(fn ($m) => $m['achievement'] * $m['weight']) / $kpiWeight, 2) : null;
        $punctuality = $attendance['expected_workdays'] > 0 ? $attendance['punctuality_score'] : null;
        $completion = $totalPoints > 0 ? round(100 * $verifiedPoints / $totalPoints, 2) : null;
        $result = $this->combine($punctuality, $completion, $kpi);
        $result['complete'] = $result['complete'] && $measured->count() === $expectedMeasures;
        if (! $result['complete']) {
            $result['score'] = null;
        }

        return $result + ['awaiting_creative_review'=>$awaitingCreativeReview,'task_rate' => $completion, 'punctuality' => $punctuality, 'kpi_rate' => $kpi, 'attendance_rate' => $attendance['expected_workdays'] > 0 ? $attendance['attendance_rate'] : null, 'task_weighted' => $result['components']['completion']['points'], 'punctuality_weighted' => $result['components']['punctuality']['points'], 'kpi_weighted' => $result['components']['kpi']['points'], 'attendance_weighted' => 0.0, 'expected_workdays' => $attendance['expected_workdays'], 'attendance_days' => $attendance['attendance_days'], 'punctual_days' => $attendance['punctual_days'], 'completion_points_total' => $totalPoints, 'completion_points_verified' => $verifiedPoints, 'completion_total' => $total, 'completion_verified' => $done, 'kpi_expected' => $expectedMeasures, 'kpi_measured' => $measured->count(), 'kpis' => $measures, 'has_activity' => $total > 0 || $attendance['attendance_days'] > 0 || $observations->isNotEmpty() || $reviewMeasure !== null, 'source_tasks' => $tasks->pluck('id')->all(), 'source_commitments' => $commitments->pluck('id')->merge($linked->pluck('id'))->unique()->values()->all()];
    }

    private function managerSignedOff(?User $reviewer, User $owner): bool
    {
        return $reviewer && $reviewer->id !== $owner->id
            && ((int) $owner->line_manager_id === $reviewer->id || PerformanceAccess::manages($reviewer, PerformanceAccess::department($owner)));
    }

    private function verifiedCommitments(Collection $items, User $owner): Collection
    {
        $eligible = $items->whereIn('workflow_state', ['verified', 'closed'])->whereNotNull('verified_at');
        $events = \App\Models\PerformanceEvent::where('entity_type', 'commitment')->whereIn('entity_id', $eligible->pluck('id'))->where('action', 'verify')->orderByDesc('id')->get()->unique('entity_id')->keyBy('entity_id');
        $reviewers = User::whereIn('id', $events->pluck('actor_id')->filter()->unique())->get()->keyBy('id');

        return $eligible->filter(fn ($item) => $this->managerSignedOff($reviewers->get($events->get($item->id)?->actor_id), $owner));
    }

    public function combine(?float $punctuality, ?float $completion, ?float $kpi): array
    {
        $components = [];
        $subtotal = 0;
        $coverage = 0;
        foreach (['punctuality' => $punctuality, 'completion' => $completion, 'kpi' => $kpi] as $key => $raw) {
            $weight = self::WEIGHTS[$key];
            $points = $raw === null ? null : round(min(100, max(0, $raw)) * $weight / 100, 2);
            $components[$key] = ['raw' => $raw, 'weight' => $weight, 'points' => $points];
            if ($points !== null) {
                $subtotal += $points;
                $coverage += $weight;
            }
        }

        return ['score' => $coverage === 100 ? round($subtotal, 2) : null, 'subtotal' => round($subtotal, 2), 'coverage_weight' => $coverage, 'complete' => $coverage === 100, 'components' => $components, 'formula_version' => '10-65-25-v3-impact'];
    }

    public function team(Collection $users, Carbon $start, Carbon $end): array
    {
        $members = $users->map(fn ($u) => $this->user($u, $start, $end));
        $expected = $members->sum('expected_workdays');
        $punctual = $members->sum('punctual_days');
        $total = $members->sum('completion_points_total');
        $verified = $members->sum('completion_points_verified');
        $kpis = $members->flatMap(fn ($m) => $m['kpis']);
        $measured = $kpis->whereNotNull('achievement');
        $weight = $measured->sum('weight');
        $kpi = $weight > 0 ? $measured->sum(fn ($m) => $m['achievement'] * $m['weight']) / $weight : null;
        $result = $this->combine($expected > 0 ? 100 * $punctual / $expected : null, $total > 0 ? 100 * $verified / $total : null, $kpi);
        if ($measured->count() !== $kpis->count()) {
            $result = $this->combine($expected > 0 ? 100 * $punctual / $expected : null, $total > 0 ? 100 * $verified / $total : null, null);
        }

        return $result + ['member_count' => $users->count(), 'complete_members' => $members->where('complete', true)->count(), 'expected_workdays' => $expected, 'punctual_days' => $punctual, 'completion_points_total' => $total, 'completion_points_verified' => $verified, 'completion_total' => $members->sum('completion_total'), 'completion_verified' => $members->sum('completion_verified'), 'kpi_measured' => $measured->count(), 'kpi_expected' => $kpis->count()];
    }
}
