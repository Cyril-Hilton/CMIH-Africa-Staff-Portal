<?php

namespace Tests\Unit;

use App\Models\PerformanceKpi;
use App\Models\PerformanceObservation;
use App\Services\RepeatedPerformanceFailures;
use Tests\TestCase;

class RepeatedPerformanceFailuresTest extends TestCase
{
    public function test_three_distinct_approved_red_periods_trigger_but_recovery_or_drafts_do_not(): void
    {
        $kpi = new PerformanceKpi(['id' => 1, 'name' => 'Delivery', 'department' => 'creatives', 'direction' => 'higher', 'green_threshold' => 100, 'amber_threshold' => 80, 'reviewer_id' => 4]);
        $rows = collect();
        foreach ([1, 2, 3] as $month) {
            $o = new PerformanceObservation(['id' => $month, 'kpi_id' => 1, 'owner_id' => 2, 'target' => 100, 'actual' => 50, 'period_start' => "2026-0$month-01", 'period_end' => "2026-0$month-28", 'approved_at' => now()]);
            $o->setRelation('kpi', $kpi)->setRelation('targetPlan', null);
            $rows->push($o);
        }
        $service = new RepeatedPerformanceFailures;
        $this->assertCount(1, $service->detect($rows));
        $rows[2]->actual = 100;
        $this->assertCount(0, $service->detect($rows));
        $rows[2]->actual = 50;
        $rows[2]->approved_at = null;
        $this->assertCount(0, $service->detect($rows));
        $rows[2]->approved_at = now();
        $rows[2]->period_start = $rows[1]->period_start;
        $rows[2]->period_end = $rows[1]->period_end;
        $this->assertCount(0, $service->detect($rows));
    }
}
