<?php

namespace Tests\Unit;

use App\Models\PerformanceRegister;
use App\Services\PerformanceExceptions;
use Tests\TestCase;

class SourceExceptionTest extends TestCase
{
    public function test_approved_unpaid_invoice_still_raises_an_overdue_exception(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21));
        $invoice = new PerformanceRegister(['kind' => 'invoice', 'status' => 'approved', 'approved_at' => now(), 'data' => ['value' => 100, 'paid_amount' => 25, 'due_on' => '2026-10-01']]);
        $alerts = app(PerformanceExceptions::class)->build(collect([$invoice]));
        $this->assertCount(1, $alerts);
        $this->assertEquals(75, $alerts[0]['exposure']);
        $invoice->data = ['value' => 100, 'paid_amount' => 25, 'credited_amount' => 75, 'due_on' => '2026-10-01'];
        $this->assertCount(0, app(PerformanceExceptions::class)->build(collect([$invoice])));
    }

    public function test_shared_reports_do_not_remain_overdue(): void
    {
        $report = new PerformanceRegister(['kind' => 'project_report', 'status' => 'in_progress', 'approved_at' => now(), 'data' => ['due_on' => '2026-01-01', 'shared_on' => '2026-01-01']]);
        $this->assertCount(0, app(PerformanceExceptions::class)->build(collect([$report])));
    }
}
