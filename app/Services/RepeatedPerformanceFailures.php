<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RepeatedPerformanceFailures
{
    /** Independent approved periods only; individual and department scopes never mix. */
    public function detect(Collection $observations, int $periods = 3): array
    {
        $failures = [];
        $periods = max(2, $periods);
        $groups = $observations->whereNotNull('approved_at')->whereNotNull('actual')
            ->filter(fn ($o) => $o->kpi && (! $o->targetPlan || ! $o->targetPlan->withdrawn_at))
            ->groupBy(fn ($o) => implode('|', [$o->kpi_id, $o->owner_id, $o->targetPlan?->scope_type ?? 'legacy', $o->client, $o->project]));
        foreach ($groups as $rows) {
            $recent = $rows->sortByDesc('period_end')->unique(fn ($o) => $o->period_start->toDateString().'|'.$o->period_end->toDateString())->take($periods);
            if ($recent->count() < $periods) {
                continue;
            }
            $missed = $recent->every(fn ($o) => PerformanceMath::rag(PerformanceMath::achievement($o->target, $o->actual, $o->kpi->direction), $o->kpi->green_threshold, $o->kpi->amber_threshold) === 'red');
            if (! $missed) {
                continue;
            }
            $latest = $recent->first();
            $failures[] = ['kpi_id' => $latest->kpi_id, 'owner_id' => $latest->owner_id, 'reviewer_id' => $latest->kpi->reviewer_id, 'department' => $latest->kpi->department, 'name' => $latest->kpi->name, 'latest_id' => $latest->id, 'periods' => $periods];
        }

        return $failures;
    }
}
