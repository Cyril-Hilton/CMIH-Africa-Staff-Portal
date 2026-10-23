<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PerformanceHubReport
{
    public function aggregate(Collection $rows, string $aggregation, string $field = 'actual'): ?float
    {
        $measured = $rows->filter(fn ($r) => $r->{$field} !== null);
        if ($measured->isEmpty()) {
            return null;
        }
        if ($aggregation === 'ratio' || $aggregation === 'mean') {
            if ($field === 'target') {
                return $measured->pluck($field)->unique()->count() === 1 ? (float) $measured->first()->{$field} : null;
            }
            $complete = $measured->filter(fn ($r) => $r->numerator !== null && $r->denominator !== null && $r->denominator > 0);

            return $complete->isEmpty() ? null : PerformanceMath::ratio($complete->sum('numerator'), $complete->sum('denominator'), $aggregation === 'ratio');
        }
        if ($aggregation === 'balance') {
            return $measured->groupBy(fn ($r) => $r->owner_id.'|'.$r->client.'|'.$r->project)->sum(fn ($scope) => (float) $scope->sortByDesc('period_end')->first()->{$field});
        }

        return (float) $measured->sum($field);
    }

    public function build(Collection $items, Collection $observations, Collection $registers, string $from, string $to, ?Collection $definitions = null, ?Collection $targetPlans = null): array
    {
        $approved = $observations->whereNotNull('approved_at');
        $historyPlans = \App\Models\PerformanceTargetPlan::whereIn('id', $approved->pluck('target_plan_id')->filter())->get()->keyBy('id');
        $qualifiedHistory = $approved->filter(fn ($o) => ! $o->target_plan_id || ($historyPlans->has($o->target_plan_id) && $historyPlans[$o->target_plan_id]->approved_at && ! $historyPlans[$o->target_plan_id]->withdrawn_at));
        $historicalScope = function (Collection $rows) use ($historyPlans): Collection {
            $departmentIds = $historyPlans->only($rows->pluck('target_plan_id')->filter()->all())->where('scope_type', 'department')->pluck('id');

            return $departmentIds->isNotEmpty() ? $rows->whereIn('target_plan_id', $departmentIds) : $rows;
        };
        $current = $approved->filter(fn ($o) => $o->period_start->toDateString() >= $from && $o->period_end->toDateString() <= $to);
        $done = $items->whereIn('workflow_state', ['verified', 'closed']);
        $overdue = $items->filter(fn ($i) => ! in_array($i->workflow_state, ['verified', 'closed']) && $i->dueDate()?->lt(now()));
        $dated = $done->filter(fn ($i) => $i->completed_at && $i->dueDate());
        $targetPlans = ($targetPlans ?? collect())->whereNull('withdrawn_at')->filter(fn ($plan) => ! $plan->amends_plan_id || $plan->approved_at);
        $definitions = $definitions ?? $current->map(fn ($row) => $row->kpi)->unique('id');
        $kpiRows = $definitions->map(function ($k) use ($current, $qualifiedHistory, $historicalScope, $from, $to, $targetPlans) {
            $rows = $current->where('kpi_id', $k->id);
            $plans = $targetPlans->where('kpi_id', $k->id);
            if ($plans->isNotEmpty()) {
                $scope = $plans->where('scope_type', 'department')->isNotEmpty() ? $plans->where('scope_type', 'department') : $plans->where('scope_type', 'individual');
                $approvedPlans = $scope->whereNotNull('approved_at');
                $rows = $rows->whereIn('target_plan_id', $approvedPlans->pluck('id'));
                $target = $approvedPlans->count() === $scope->count() ? $this->aggregate($approvedPlans, $k->aggregation, 'target') : null;
            } else {
                $target = $this->aggregate($rows, $k->aggregation, 'target');
            }
            $actual = $this->aggregate($rows, $k->aggregation);
            $start = Carbon::parse($from);
            $days = $start->diffInDays(Carbon::parse($to)) + 1;
            $priorStart = $start->copy()->subDays($days);
            $priorEnd = $start->copy()->subDay();
            $end = Carbon::parse($to);
            if ($start->day === 1 && $end->day === $end->daysInMonth) {
                $months = ($end->year - $start->year) * 12 + $end->month - $start->month + 1;
                $priorStart = $start->copy()->subMonthsNoOverflow($months);
            }
            $previous = $historicalScope($qualifiedHistory->where('kpi_id', $k->id)->filter(fn ($o) => $o->period_start->gte($priorStart) && $o->period_end->lte($priorEnd)));
            $ytd = $historicalScope($qualifiedHistory->where('kpi_id', $k->id)->filter(fn ($o) => $o->period_start->year === $start->year && $o->period_end->toDateString() <= $to));
            $completePlans = $plans->isEmpty() || ($rows->whereNotNull('actual')->count() === $scope->count());
            $achievement = $completePlans ? PerformanceMath::achievement($target, $actual, $k->direction) : null;

            return ['kpi' => $k, 'target' => $target, 'actual' => $actual, 'achievement' => $achievement, 'variance' => $target !== null && $actual !== null ? round($actual - $target, 4) : null, 'rag' => PerformanceMath::rag($achievement, $k->green_threshold, $k->amber_threshold), 'previous' => $this->aggregate($previous, $k->aggregation), 'ytd' => $this->aggregate($ytd, $k->aggregation), 'scoring_weight' => $plans->isNotEmpty() ? $scope->sum('weight') : $k->weight, 'expected_sources' => $plans->isNotEmpty() ? $scope->count() : null, 'coverage' => $rows->whereNotNull('actual')->count(), 'observations' => $rows];
        })->values();
        $dimensions = $kpiRows->filter(fn ($r) => $r['achievement'] !== null)->groupBy(fn ($r) => $r['kpi']->dimension)->map(function ($rows) {
            $weight = $rows->sum(fn ($r) => $r['scoring_weight']);

            return $weight > 0 ? round($rows->sum(fn ($r) => min(100, max(0, $r['achievement'])) * $r['scoring_weight']) / $weight, 1) : null;
        });
        $trends = $approved->groupBy(fn ($o) => $o->period_start->format('Y-m'))->map(function ($month) {
            return ['observations' => $month->count(), 'approved_measured' => $month->whereNotNull('actual')->count()];
        });
        $trendPlans = \App\Models\PerformanceTargetPlan::whereIn('id', $approved->pluck('target_plan_id')->filter())->get()->keyBy('id');
        $trendRows = $approved->filter(fn ($o) => ! $o->target_plan_id || ($trendPlans->has($o->target_plan_id) && ! $trendPlans[$o->target_plan_id]->withdrawn_at));
        $performanceTrends = $trendRows->groupBy('kpi_id')->map(function ($history) use ($trendPlans) {
            $kpi = $history->first()->kpi;
            $planIds = $history->pluck('target_plan_id')->filter();
            $plans = $trendPlans->only($planIds->all());
            if ($plans->where('scope_type', 'department')->isNotEmpty()) {
                $history = $history->whereIn('target_plan_id', $plans->where('scope_type', 'department')->pluck('id'));
            }
            $series = $history->groupBy(fn ($o) => $o->period_start->format('Y-m'))->sortKeys()->map(function ($month, $period) use ($kpi) {
                $actual = $this->aggregate($month, $kpi->aggregation);
                $target = $this->aggregate($month, $kpi->aggregation, 'target');

                return ['period' => $period, 'actual' => $actual, 'achievement' => PerformanceMath::achievement($target, $actual, $kpi->direction), 'sources' => $month->pluck('id')->all()];
            })->values();
            $measured = $series->filter(fn ($point) => $point['achievement'] !== null)->values();
            $projected = null;
            if ($measured->count() >= 6) {
                $last = $measured->take(-6)->values();
                $continuous = true;
                for ($i = 1; $i < 6; $i++) {
                    if (Carbon::parse($last[$i - 1]['period'].'-01')->addMonth()->format('Y-m') !== $last[$i]['period']) {
                        $continuous = false;
                    }
                }if ($continuous) {
                    $projected = round($last[5]['achievement'] + ($last[5]['achievement'] - $last[0]['achievement']) / 5, 2);
                }
            }

            $quarterly = $history->groupBy(fn ($o) => $o->period_start->year.' Q'.$o->period_start->quarter)->sortKeys()->map(function ($rows, $period) use ($kpi) {
                $actual = $this->aggregate($rows, $kpi->aggregation);
                $target = $this->aggregate($rows, $kpi->aggregation, 'target');

                return ['period' => $period, 'actual' => $actual, 'achievement' => PerformanceMath::achievement($target, $actual, $kpi->direction), 'sources' => $rows->pluck('id')->all()];
            })->values();

            return ['kpi' => $kpi, 'quarterly' => $quarterly, 'series' => $series, 'projected_achievement' => $projected, 'emerging_risk' => $projected !== null && $projected < $kpi->amber_threshold];
        })->values();
        $staff = $items->whereNotNull('lead_staff_id')->groupBy('lead_staff_id')->map(function ($owned) {
            return ['name' => $owned->first()->leadStaff?->name ?? 'Former staff', 'assigned' => $owned->count(), 'verified' => $owned->whereIn('workflow_state', ['verified', 'closed'])->count(), 'overdue' => $owned->filter(fn ($i) => ! in_array($i->workflow_state, ['verified', 'closed']) && $i->dueDate()?->lt(now()))->count(), 'quality' => $owned->whereNotNull('verified_at')->whereNotNull('quality_score')->avg('quality_score')];
        });

        $departmentKeys = $items->pluck('department')->merge($definitions->pluck('department'))->unique();
        $departments = $departmentKeys->mapWithKeys(function ($department) use ($items, $kpiRows) {
            $rows = $items->where('department', $department);
            $measures = $kpiRows->filter(fn ($r) => $r['kpi']->department === $department);
            $measured = $measures->whereNotNull('achievement');
            $weight = $measured->sum(fn ($r) => $r['scoring_weight']);
            $score = $weight > 0 && $measured->count() === $measures->count()
                ? round($measured->sum(fn ($r) => min(100, max(0, $r['achievement'])) * $r['scoring_weight']) / $weight, 2) : null;
            $verified = $rows->whereIn('workflow_state', ['verified', 'closed']);
            $dated = $verified->filter(fn ($i) => $i->completed_at && $i->dueDate());

            return [$department => ['total' => $rows->count(), 'verified' => $verified->count(),
                'overdue' => $rows->filter(fn ($i) => ! in_array($i->workflow_state, ['verified', 'closed']) && $i->dueDate()?->lt(now()))->count(),
                'kpi_score' => $score, 'kpi_measured' => $measured->count(), 'kpi_expected' => $measures->count(),
                'on_time' => PerformanceMath::percentage($dated->filter(fn ($i) => $i->completed_at->lte($i->dueDate()))->count(), $dated->count()),
                'critical' => $rows->where('risk_level', 'critical')->whereNotIn('workflow_state', ['verified', 'closed'])->count()]];
        });

        return ['delivery_trends' => $this->deliveryTrends($items), 'total' => $items->count(), 'verified' => $done->count(), 'overdue' => $overdue->count(), 'completion' => PerformanceMath::percentage($done->count(), $items->count()), 'on_time' => PerformanceMath::percentage($dated->filter(fn ($i) => $i->completed_at->lte($i->dueDate()))->count(), $dated->count()), 'on_time_coverage' => $dated->count(), 'quality' => $done->whereNotNull('quality_score')->avg('quality_score'), 'kpis' => $kpiRows, 'dimensions' => $dimensions, 'departments' => $departments, 'staff' => $staff, 'root_causes' => $items->whereNotNull('root_cause')->countBy('root_cause'), 'trends' => $trends, 'performance_trends' => $performanceTrends, 'exceptions' => $items->filter(fn ($i) => ! in_array($i->workflow_state, ['verified', 'closed']) && ($i->isOverdue() || in_array($i->risk_level, ['high', 'critical']) || $i->escalation_level > 0)), 'operational' => $this->operational($registers, $to), 'pending_observations' => $observations->whereNull('approved_at')->count()];
    }

    public function deliveryTrends(Collection $items): Collection
    {
        return $items->groupBy(fn ($item) => $item->week_start->format('Y-m'))->sortKeys()->map(function ($rows, $period) {
            $verified = $rows->whereIn('workflow_state', ['verified', 'closed']);
            $dated = $verified->filter(fn ($item) => $item->completed_at && $item->dueDate());
            $onTime = $dated->filter(fn ($item) => $item->completed_at->lte($item->dueDate()));

            return ['period' => $period, 'assigned' => $rows->count(), 'verified' => $verified->count(), 'completion' => PerformanceMath::percentage($verified->count(), $rows->count()), 'on_time' => PerformanceMath::percentage($onTime->count(), $dated->count()), 'on_time_coverage' => $dated->count(), 'overdue' => $rows->filter(fn ($item) => ! in_array($item->workflow_state, ['verified', 'closed']) && $item->dueDate()?->lt(now()))->count()];
        })->values();
    }

    public function operational(Collection $registers, ?string $asOf = null): array
    {
        $ageDate = $asOf ? Carbon::parse($asOf)->min(today())->startOfDay() : today();
        $rows = $registers->whereNotNull('approved_at')->where('status', '!=', 'cancelled');
        $metrics = [];
        foreach ($rows->groupBy(fn ($r) => $r->department.' / '.$r->kind) as $kind => $group) {
            foreach ($group->flatMap(fn ($r) => array_keys($r->data))->unique() as $field) {
                $values = $group->map(fn ($r) => $r->data[$field] ?? null)->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v));
                if ($values->isNotEmpty()) {
                    $metrics[$kind.' / '.$field] = ['value' => (in_array($field, ['satisfaction_score', 'quality_score', 'probability']) ? $values->avg() : $values->sum()), 'coverage' => $values->count(), 'records' => $group->count(), 'note' => 'Sum of approved records; compare like-for-like periods.'];
                }
            }
        }
        // Snapshot headcounts must use the last snapshot for each owner, never sum snapshots.
        $headcounts = $rows->where('kind', 'workforce')->groupBy('owner_id')->map(fn ($g) => $g->sortByDesc('period_end')->first());
        foreach (['approved_headcount', 'actual_headcount'] as $field) {
            $vals = $headcounts->map(fn ($r) => $r->data[$field] ?? null)->filter(fn ($v) => $v !== null);
            if ($vals->isNotEmpty()) {
                $metrics['hr_admin / workforce / '.$field] = ['value' => $vals->sum(), 'coverage' => $vals->count(), 'records' => $headcounts->count(), 'note' => 'Latest approved snapshot per accountable scope.'];
            }
        }
        foreach (['invoice' => 'Receivables', 'payable' => 'Payables'] as $kind => $label) {
            $entries = $rows->where('kind', $kind)->filter(fn ($r) => isset($r->data['value'],$r->data['paid_amount']));
            if ($entries->isNotEmpty()) {
                $metrics[$label.' outstanding'] = ['value' => $entries->sum(fn ($r) => max(0, $r->data['value'] - $r->data['paid_amount'] - ($r->data['credited_amount'] ?? 0))), 'coverage' => $entries->count(), 'records' => $rows->where('kind', $kind)->count(), 'note' => 'Value minus recorded payments and credits; records without payment values are excluded.'];
                foreach (['Current' => [null, 0], '1–30 days' => [1, 30], '31–60 days' => [31, 60], '61–90 days' => [61, 90], '90+ days' => [91, null]] as $bucket => [$min,$max]) {
                    $subset = $entries->filter(function ($r) use ($min, $max, $ageDate) {
                        if (empty($r->data['due_on'])) {
                            return false;
                        }$days = (int) Carbon::parse($r->data['due_on'])->startOfDay()->diffInDays($ageDate, false);

                        return ($min === null || $days >= $min) && ($max === null || $days <= $max);
                    });
                    $metrics[$label.' / '.$bucket] = ['value' => $subset->sum(fn ($r) => max(0, $r->data['value'] - $r->data['paid_amount'] - ($r->data['credited_amount'] ?? 0))), 'coverage' => $subset->count(), 'records' => $entries->count(), 'note' => 'Age at '.$ageDate->toDateString().'; only supplied due dates.'];
                }
            }
        }
        $projects = $rows->where('kind', 'project')->filter(fn ($r) => isset($r->data['revenue'],$r->data['actual_cost']));
        if ($projects->isNotEmpty()) {
            $revenue = $projects->sum(fn ($r) => $r->data['revenue']);
            $gp = $revenue - $projects->sum(fn ($r) => $r->data['actual_cost']);
            $metrics['Project gross profit'] = ['value' => $gp, 'coverage' => $projects->count(), 'records' => $rows->where('kind', 'project')->count(), 'note' => 'Recognized project revenue minus approved actual cost.'];
            $metrics['Project gross margin %'] = ['value' => PerformanceMath::ratio($gp, $revenue), 'coverage' => $projects->count(), 'records' => $rows->where('kind', 'project')->count(), 'note' => 'Gross profit / recognized revenue × 100.'];
        }

        return $metrics;
    }
}
