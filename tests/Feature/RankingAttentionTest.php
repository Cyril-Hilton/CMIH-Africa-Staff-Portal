<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\PerformanceOutbox;
use App\Models\User;
use App\Services\RankingAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RankingAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
    }

    private function staff(string $name): User
    {
        return User::factory()->create(['name' => $name, 'status' => 'active', 'access_role' => 'staff', 'department' => 'creatives', 'start_date' => '2026-11-23']);
    }

    private function codes(User $u): array
    {
        return array_column(app(RankingAttention::class)->forPerson($u, today()->startOfMonth(), today()->endOfMonth())['issues'], 'code');
    }

    public function test_flags_persist_and_clear_from_live_records_and_staff_cannot_read_other_people_flags(): void
    {
        $person = $this->staff('Own ranking fixture');
        $other = $this->staff('Other ranking private fixture');
        $this->assertContains('missing_clockins', $this->codes($person));
        $this->assertContains('missing_clockins', $this->codes($person));
        $visible = app(RankingAttention::class)->visible($person, today()->startOfMonth(), today()->endOfMonth());
        $this->assertSame([$person->id], $visible->pluck('person.id')->all());
        $this->assertCount(0, app(RankingAttention::class)->visible($person, today()->startOfMonth(), today()->endOfMonth(), $other->id));
        Attendance::create(['user_id' => $person->id, 'clock_in_at' => '2026-11-23 08:30:00', 'daily_objective' => 'New cycle work', 'status' => 'On Time']);
        $this->assertNotContains('missing_clockins', $this->codes($person));
        $this->assertNotContains('recorded_lateness', $this->codes($person));
    }

    public function test_approved_leave_does_not_create_absence_or_setup_failure_flags(): void
    {
        $person = $this->staff('Approved leave fixture');
        LeaveApplication::create(['user_id' => $person->id, 'leave_type' => 'annual', 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'reason' => 'Private absence reason', 'status' => 'approved']);
        $this->assertSame([], $this->codes($person));
    }

    public function test_staff_and_executive_daily_reminders_deduplicate_but_recur_while_unresolved(): void
    {
        $exec = User::factory()->create(['status' => 'active', 'access_role' => 'super_admin', 'department' => 'executive']);
        $person = $this->staff('Unresolved ranking fixture');
        $service = app(RankingAttention::class);
        $service->notify();
        $count = PerformanceOutbox::count();
        $this->assertSame(2, $count);
        $service->notify();
        $this->assertSame($count, PerformanceOutbox::count());
        $this->assertTrue(PerformanceOutbox::where('user_id', $person->id)->exists());
        $this->assertTrue(PerformanceOutbox::where('user_id', $exec->id)->exists());
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00'));
        $service->notify();
        $this->assertSame(4, PerformanceOutbox::count());
    }

    public function test_ranking_flags_are_visible_without_expanding_old_dashboard_sections(): void
    {
        $person = $this->staff('Visible ranking fixture');
        $this->actingAs($person)->get(route('dashboard'))->assertOk()->assertSee('Your ranking attention')->assertSee('eligible working day(s) have no recorded clock-in');
        $exec = User::factory()->create(['status' => 'active', 'access_role' => 'super_admin', 'department' => 'executive']);
        $this->actingAs($exec)->get(route('dashboard', ['exec_view' => 'accountability']))->assertOk()->assertSee('Staff ranking attention')->assertSee('Visible ranking fixture')->assertSee('unresolved setup, review or outcome flags');
    }

    public function test_new_cycle_does_not_publish_premature_ranking_positions_before_its_start(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 19:00:00'));
        $person = $this->staff('Fresh cycle fixture');
        \Illuminate\Support\Facades\DB::table('reporting_archive_batches')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Fresh performance cycle', 'actor_id' => $person->id, 'manifest' => json_encode(['performance_tracking_starts_on' => '2026-11-23']), 'created_at' => now()]);
        \App\Models\Task::create(['title' => 'Planned new-cycle work', 'assigned_to' => $person->id, 'assigned_by' => $person->id, 'department' => 'creatives', 'due_on' => '2026-11-23', 'status' => 'In Progress']);
        \App\Models\Task::create(['title' => 'Undated preparation work', 'assigned_to' => $person->id, 'assigned_by' => $person->id, 'department' => 'creatives', 'status' => 'In Progress']);
        $data = app(\App\Http\Controllers\Portal\PerformanceAwardController::class)->calculateStandingsData('2026-11');
        $this->assertSame([], $data['measured_employees']);
        $this->assertSame([], $data['measured_departments']);
        $this->assertSame('2026-11-23', $data['cycle_starts_on']);
        $this->assertContains('missing_deadlines', $this->codes($person));
        $this->assertNotContains('missing_clockins', $this->codes($person));
    }
}
