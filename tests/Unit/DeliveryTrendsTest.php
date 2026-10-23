<?php

namespace Tests\Unit;

use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceHubReport;
use Tests\TestCase;

class DeliveryTrendsTest extends TestCase
{
    public function test_only_verified_work_counts_and_undated_completions_do_not_distort_timeliness(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $rows = collect([
            new WeeklyConsolidatedItem(['week_start' => '2026-10-01', 'week_end' => '2026-10-09', 'workflow_state' => 'verified', 'completed_at' => '2026-10-08']),
            new WeeklyConsolidatedItem(['week_start' => '2026-10-01', 'week_end' => '2026-10-09', 'workflow_state' => 'closed']),
            new WeeklyConsolidatedItem(['week_start' => '2026-10-01', 'week_end' => '2026-10-09', 'workflow_state' => 'review']),
            new WeeklyConsolidatedItem(['week_start' => '2026-10-01', 'week_end' => '2026-10-09', 'workflow_state' => 'assigned']),
        ]);
        $trend = app(PerformanceHubReport::class)->deliveryTrends($rows)->first();
        $this->assertSame(4, $trend['assigned']);
        $this->assertSame(2, $trend['verified']);
        $this->assertEquals(50, $trend['completion']);
        $this->assertEquals(100, $trend['on_time']);
        $this->assertSame(1, $trend['on_time_coverage']);
        $this->assertSame(2, $trend['overdue']);
    }
}
