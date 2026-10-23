<?php

namespace Tests\Unit;

use App\Models\PerformanceRegister;
use App\Services\PerformanceHubReport;
use Tests\TestCase;

class FinancialReportingMathTest extends TestCase
{
    public function test_profitability_variance_and_contribution_use_complete_approved_sources(): void
    {
        $approved = new PerformanceRegister(['id' => 1, 'department' => 'finance', 'kind' => 'profitability', 'status' => 'approved', 'approved_at' => now(), 'data' => ['revenue' => 1000, 'direct_cost' => 600, 'variable_cost' => 300, 'gross_profit_target' => 450]]);
        $draft = new PerformanceRegister(['department' => 'finance', 'kind' => 'profitability', 'status' => 'draft', 'data' => ['revenue' => 9000, 'direct_cost' => 0, 'variable_cost' => 0, 'gross_profit_target' => 1]]);
        $results = collect(app(\App\Services\DepartmentMetricCalculator::class)->calculate(collect([$approved, $draft])))->where('department', 'finance')->keyBy('name');
        $this->assertEquals(400, $results['Gross profit']['value']);
        $this->assertEquals(40, $results['Gross margin']['value']);
        $this->assertEquals(700, $results['Contribution']['value']);
        $this->assertEquals(-50, $results['GP variance']['value']);
        $approved->data = ['revenue' => 1000, 'direct_cost' => 600];
        $results = collect(app(\App\Services\DepartmentMetricCalculator::class)->calculate(collect([$approved])))->where('department', 'finance')->keyBy('name');
        $this->assertNull($results['GP variance']['value']);
        $this->assertNull($results['Contribution']['value']);
    }

    public function test_credits_reduce_outstanding_and_ageing_uses_report_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $record = new PerformanceRegister(['department' => 'finance', 'kind' => 'invoice', 'status' => 'approved', 'approved_at' => now(), 'data' => ['value' => 1000, 'paid_amount' => 200, 'credited_amount' => 100, 'due_on' => '2026-09-15']]);
        $metrics = app(PerformanceHubReport::class)->operational(collect([$record]), '2026-09-30');
        $this->assertEquals(700, $metrics['Receivables outstanding']['value']);
        $this->assertEquals(700, $metrics['Receivables / 1–30 days']['value']);
        $this->assertEquals(0, $metrics['Receivables / 31–60 days']['value']);
    }
}
