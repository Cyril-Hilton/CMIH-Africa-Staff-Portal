<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use App\Services\AutoClockService;
use App\Services\PerformanceScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutoClockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_curtis_barnor_receives_auto_clock_and_creative_task(): void
    {
        $date = Carbon::parse('2026-07-10');
        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'staff',
            'department' => 'creatives',
        ]);

        AutoClockService::handleForUser($curtis, $date);

        $task = Task::where('assigned_to', $curtis->id)->firstOrFail();
        $this->assertSame('3D/4D Product Mockups and Creative Design Development', $task->title);
        $this->assertSame(
            'Daily creative production covering 3D/4D mockups, visual refinements, campaign design assets, and creative concept upgrades.',
            $task->details
        );
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);

        $attendance = Attendance::where('user_id', $curtis->id)->firstOrFail();
        $this->assertSame('On Time', $attendance->status);
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertNotNull($attendance->clock_out_at);
        $this->assertTrue($attendance->clock_out_at->gte($date->copy()->setTime(19, 0)));
        $this->assertGreaterThanOrEqual(60, $attendance->overtime_minutes);
    }

    public function test_run_all_includes_curtis_barnor(): void
    {
        $date = Carbon::parse('2026-07-10');
        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'staff',
            'department' => 'creatives',
        ]);

        AutoClockService::runAll($date);

        $this->assertDatabaseHas('tasks', [
            'assigned_to' => $curtis->id,
            'title' => '3D/4D Product Mockups and Creative Design Development',
            'department' => 'creatives',
        ]);
        $this->assertDatabaseHas('attendance', [
            'user_id' => $curtis->id,
            'daily_objective' => '3D/4D Product Mockups and Creative Design Development',
            'status' => 'On Time',
        ]);
    }

    public function test_cyril_auto_clock_task_completes_without_curtis_approval(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 12:00:00'));

        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        AutoClockService::handleForUser($cyril, Carbon::today());

        $task = Task::where('assigned_to', $cyril->id)
            ->where('title', 'CMIH Portal Maintainance and Feature Upgrade')
            ->firstOrFail();

        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNull($task->completion_review_status);
        $this->assertNotContains($curtis->id, array_map('intval', $task->copied_manager_ids ?? []));
        $this->assertSame(0, (int) ($task->custom_fields['completion_manager_id'] ?? 0));
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $curtis->id,
            'title' => 'Task Completion Audit Needed',
        ]);
    }

    public function test_cyril_historical_auto_clock_task_stays_completed_without_curtis_routing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 12:00:00'));

        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);
        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $legacyTask = Task::create([
            'title' => 'CMIH Portal Maintainance and Feature Upgrade',
            'details' => 'Daily app maintenance work activity, bug fixes, and systems upgrades.',
            'assigned_to' => $cyril->id,
            'assigned_by' => $cyril->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'High',
            'progress' => 100,
            'due_on' => Carbon::parse('2026-07-10'),
        ]);
        $legacyTask->forceFill([
            'created_at' => Carbon::parse('2026-07-10 08:15:00'),
            'updated_at' => Carbon::parse('2026-07-10 17:00:00'),
        ])->save();

        AutoClockService::backfillForUser($cyril, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-10'));

        $legacyTask->refresh();

        $this->assertSame('Completed', $legacyTask->status);
        $this->assertSame(100, $legacyTask->progress);
        $this->assertNull($legacyTask->completion_review_status);
        $this->assertNotContains($curtis->id, array_map('intval', $legacyTask->copied_manager_ids ?? []));
        $this->assertSame(0, (int) ($legacyTask->custom_fields['completion_manager_id'] ?? 0));
        $this->assertSame(1, Task::where('assigned_to', $cyril->id)->realWork()->count());
        $this->assertSame(1, Task::where('assigned_to', $cyril->id)->approvedForPerformance()->count());
        $this->assertSame(0, Task::where('assigned_to', $cyril->id)->pendingFinalSignOff()->count());
    }

    public function test_backfill_repairs_missing_and_late_auto_clock_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);

        Attendance::create([
            'user_id' => $curtis->id,
            'clock_in_at' => Carbon::parse('2026-07-01 10:30:00'),
            'clock_out_at' => Carbon::parse('2026-07-01 17:20:00'),
            'daily_objective' => 'Old late row',
            'status' => 'Late',
            'overtime_minutes' => 0,
        ]);

        AutoClockService::backfillForUser($curtis, Carbon::parse('2026-07-01'), Carbon::today());

        $summary = PerformanceScoringService::attendanceSummary(
            $curtis,
            Carbon::parse('2026-07-01'),
            Carbon::today()
        );

        $repairedAttendance = Attendance::where('user_id', $curtis->id)
            ->whereDate('clock_in_at', '2026-07-01')
            ->firstOrFail();

        $this->assertSame(8, $summary['expected_workdays']);
        $this->assertSame(8, $summary['attendance_days']);
        $this->assertSame(100.0, $summary['punctuality_score']);
        $this->assertSame(100.0, $summary['attendance_rate']);
        $this->assertSame('On Time', $repairedAttendance->status);
        $this->assertTrue($repairedAttendance->clock_in_at->lte(Carbon::parse('2026-07-01 09:00:00')));
        $this->assertTrue($repairedAttendance->clock_out_at->gte(Carbon::parse('2026-07-01 19:00:00')));
        $this->assertSame(8, Attendance::where('user_id', $curtis->id)->count());
    }
}
