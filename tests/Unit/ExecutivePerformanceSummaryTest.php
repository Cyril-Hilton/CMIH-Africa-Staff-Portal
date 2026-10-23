<?php

namespace Tests\Unit;

use App\Models\PerformanceRegister;
use App\Services\ExecutivePerformanceSummary;
use Tests\TestCase;

class ExecutivePerformanceSummaryTest extends TestCase
{
    public function test_approved_financial_sources_are_not_double_counted_and_cash_requires_a_balance(): void
    {
        $row = fn ($id, $kind, $data, $department = 'finance', $period = '2026-10-31') => (new PerformanceRegister)->forceFill(['id' => $id, 'department' => $department, 'kind' => $kind, 'data' => $data, 'period_end' => $period, 'status' => 'approved', 'approved_at' => now()]);
        $rows = collect([
            $row(1, 'revenue', ['actual' => 1000]), $row(2, 'project', ['revenue' => 1000, 'actual_cost' => 600], 'operations_projects'),
            $row(3, 'invoice', ['value' => 1000, 'paid_amount' => 200, 'credited_amount' => 100]),
            $row(4, 'cash', ['inflow' => 1000, 'outflow' => 200]),
            $row(5, 'cash', ['account_ref' => 'BANK-1', 'closing_balance' => 300], 'finance', '2026-10-15'),
            $row(6, 'cash', ['account_ref' => 'BANK-1', 'closing_balance' => 500]),
        ]);
        $summary = collect(app(ExecutivePerformanceSummary::class)->build($rows, collect()))->keyBy('label');
        $this->assertEquals(1000, $summary['Recognised Revenue']['value']);
        $this->assertEquals(400, $summary['Project Gross Profit']['value']);
        $this->assertEquals(700, $summary['Receivables']['value']);
        $this->assertEquals(500, $summary['Cash Position']['value']);
        $this->assertSame([6], $summary['Cash Position']['sources']);
        $this->assertNull($summary['Client Satisfaction']['value']);
        $rows->each(fn ($record) => $record->approved_at = null);
        $empty = collect(app(ExecutivePerformanceSummary::class)->build($rows, collect()))->keyBy('label');
        $this->assertNull($empty['Recognised Revenue']['value']);
    }
}
