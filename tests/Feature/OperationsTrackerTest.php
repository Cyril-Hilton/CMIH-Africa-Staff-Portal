<?php

namespace Tests\Feature;

use App\Models\PerformanceRegister;
use App\Services\OperationsTracker;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationsTrackerTest extends TestCase
{
    public function test_ended_activation_is_not_assumed_completed_and_days_are_inclusive(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $result = app(OperationsTracker::class)->derived('activation', ['starts_on' => '2026-10-10', 'ends_on' => '2026-10-12']);
        $this->assertSame(3, $result['duration_days']);
        $this->assertSame('Period ended — completion not verified', $result['calendar_state']);
        $this->assertFalse($result['report_shared']);
    }

    public function test_approved_deployment_and_report_sources_drive_operations_exceptions(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $base = ['department' => 'operations_projects', 'status' => 'in_progress', 'approved_at' => now()];
        $deployment = new PerformanceRegister($base + ['kind' => 'posm_deployment', 'data' => ['quantity' => 5, 'deployment_status' => 'Deployed', 'deployed_on' => '2026-10-10', 'expected_return_on' => '2026-10-15', 'return_status' => 'Pending']]);
        $report = new PerformanceRegister($base + ['kind' => 'project_report', 'data' => ['due_on' => '2026-10-18']]);
        $unapproved = new PerformanceRegister(['department' => 'operations_projects', 'kind' => 'project_report', 'status' => 'in_progress', 'data' => ['due_on' => '2026-10-01']]);
        $s = app(OperationsTracker::class)->summary(collect([$deployment, $report, $unapproved]));
        $this->assertEquals(5, $s['deployed_quantity']);
        $this->assertSame(1, $s['overdue_returns']);
        $this->assertSame(1, $s['reports_overdue']);
        $this->assertSame(2, $s['approved_sources']);
        $deployment->data = $deployment->data + ['returned_on' => '2026-10-19'];
        $this->assertFalse(app(OperationsTracker::class)->derived($deployment->kind, $deployment->data)['return_overdue']);
    }

    public function test_partial_returns_remain_overdue_and_over_returns_are_rejected(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $data = ['asset_code' => 'A1', 'quantity' => 10, 'returned_quantity' => 4, 'deployed_on' => '2026-10-01', 'expected_return_on' => '2026-10-10', 'returned_on' => '2026-10-19', 'return_status' => 'Partially Returned'];
        $service = app(OperationsTracker::class);
        $service->validate('posm_deployment', $data);
        $derived = $service->derived('posm_deployment', $data);
        $this->assertEquals(6, $derived['outstanding_quantity']);
        $this->assertTrue($derived['return_overdue']);
        $data['returned_quantity'] = 10;
        $data['return_status'] = 'Returned';
        $service->validate('posm_deployment', $data);
        $this->assertFalse($service->derived('posm_deployment', $data)['return_overdue']);
        $data['returned_quantity'] = 11;
        $this->expectException(ValidationException::class);
        $service->validate('posm_deployment', $data);
    }

    public function test_invalid_deployment_dates_and_unknown_operational_states_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationsTracker::class)->validate('posm_deployment', ['asset_code' => 'A1', 'quantity' => 1, 'deployed_on' => '2026-10-20', 'expected_return_on' => '2026-10-19']);
    }
}
