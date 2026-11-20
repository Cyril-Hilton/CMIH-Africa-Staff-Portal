<?php

namespace Tests\Feature;

use App\Models\HrRegisterRecord;
use App\Models\PerformanceOutbox;
use App\Models\User;
use App\Services\ExecutiveReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExecutiveReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_hr_digest_is_private_deduplicated_and_delivered_without_an_hour_delay(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 15:00:00'));
        $outsider = User::factory()->create(['status' => 'active', 'department' => 'operations_projects', 'access_role' => 'manager', 'job_level' => 'manager']);
        $executive = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'super_admin']);
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin', 'access_role' => 'manager', 'position_title' => 'HR Manager', 'line_manager_id' => $outsider->id]);
        HrRegisterRecord::create(['kind' => 'employee_case', 'label' => 'Confidential case marker', 'created_by' => $hr->id, 'data' => ['staff_id' => $outsider->id, 'responsible_id' => $hr->id, 'opened_on' => '2026-11-16', 'due_on' => '2026-11-19', 'state' => 'Open']]);
        config(['executive_tracker.reminders.mode' => 'live', 'performance.email_enabled' => false, 'creative.email_enabled' => false]);
        $service = app(ExecutiveReminders::class);
        $this->assertSame(1, $service->run());
        $this->assertSame(0, $service->run());
        $this->assertSame(0, PerformanceOutbox::where('user_id', $outsider->id)->count());
        $this->assertStringContainsString('Confidential case marker', PerformanceOutbox::where('user_id', $hr->id)->sole()->message);
        $this->artisan('executive:reminders')->assertExitCode(0);
        $this->assertNotNull(PerformanceOutbox::where('user_id', $hr->id)->sole()->fresh()->dashboard_at);
        $this->assertDatabaseCount('executive_reminder_logs', 1);
    }

    public function test_confidential_hr_record_does_not_notify_an_unauthorized_responsible_person(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 15:00:00'));
        $executive = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'super_admin']);
        $person = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'access_role' => 'staff']);
        HrRegisterRecord::create(['kind' => 'employee_case', 'label' => 'Private grievance', 'created_by' => $executive->id, 'data' => ['staff_id' => $person->id, 'responsible_id' => $person->id, 'opened_on' => '2026-11-16', 'due_on' => '2026-11-19', 'state' => 'Open']]);
        config(['executive_tracker.reminders.mode' => 'live']);
        $this->assertSame(0, app(ExecutiveReminders::class)->run());
        $this->assertDatabaseCount('performance_outbox', 0);
    }
}
