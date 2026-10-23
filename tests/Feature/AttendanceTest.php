<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_clock_in(): void
    {
        $response = $this->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Code all day long.',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_is_blocked_from_portal_until_clocked_in(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        // Accessing portal/messages should redirect to dashboard with errors
        $response = $this->actingAs($user)->withHeaders(['X-Test-Enforce-ClockIn' => 'true'])->get('/portal/messages');

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors(['attendance']);
    }

    public function test_read_only_dashboard_support_routes_are_available_before_clock_in(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $headers = ['X-Test-Enforce-ClockIn' => 'true'];

        $this->actingAs($user)
            ->withHeaders($headers)
            ->getJson(route('portal.awards.standings', ['period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertJsonStructure(['calculated', 'locked']);

        $this->actingAs($user)
            ->withHeaders($headers)
            ->get(route('portal.announcements'))
            ->assertOk()
            ->assertSee('Company Announcements');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->get(route('portal.dashboard.live'))
            ->assertOk()
            ->assertSee('mega-operational-table-live-region');
    }

    public function test_user_can_clock_in_and_then_access_portal(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        \App\Models\Task::create([
            'title' => 'Daily Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(8, 30, 0));

        $response = $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Finish the attendance widget implementation.',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'remote_notes' => 'Headquarters',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Successfully clocked in for today!');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'daily_objective' => 'Finish the attendance widget implementation.',
            'status' => 'On Time',
            'latitude' => '5.60370000',
            'longitude' => '-0.18700000',
            'remote_notes' => 'Headquarters',
        ]);

        // User should now be able to access portal/messages
        $portalResponse = $this->actingAs($user)->get('/portal/messages');
        $portalResponse->assertStatus(200);
    }

    public function test_user_cannot_clock_in_twice_after_adding_another_task_today(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        \App\Models\Task::create([
            'title' => 'First Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(8, 45, 0));

        $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Start the day properly.',
        ])->assertSessionHasNoErrors();

        \App\Models\Task::create([
            'title' => 'Second Task After Clock In',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(10, 5, 0));

        $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Trying to clock in twice.',
        ])->assertSessionHasErrors(['attendance']);

        $this->assertSame(1, Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', Carbon::today())
            ->count());
    }

    public function test_user_can_clock_in_without_typing_objective_after_creating_task_from_tasks_page(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'operations_projects',
        ]);
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'operations_projects',
            'position_title' => 'Line Manager',
        ]);

        $this->actingAs($user)->post(route('portal.tasks.store'), [
            'title' => 'Sidebar First Task',
            'details' => 'Created from the tasks page before clock-in.',
            'priority' => 'medium',
            'completion_manager_id' => $manager->id,
        ])->assertRedirect();

        Carbon::setTestNow(Carbon::today()->setTime(8, 30, 0));

        $response = $this->actingAs($user)->post('/portal/attendance/clock-in');

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Successfully clocked in for today!');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'daily_objective' => 'Sidebar First Task',
            'status' => 'On Time',
        ]);
    }

    public function test_dashboard_and_header_offer_clock_in_after_today_task_exists(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'operations_projects',
        ]);

        \App\Models\Task::create([
            'title' => 'Today Sidebar Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('action="' . route('portal.attendance.clock-in') . '"', false);
        $response->assertSee('Optional: add a focus note, or leave blank to use your first task for today.', false);
        $response->assertSee('Clocking In...', false);
        $response->assertDontSee('Add Task First');

        $html = $response->getContent();
        preg_match_all('/<form[^>]+action="' . preg_quote(route('portal.attendance.clock-in'), '/') . '"[^>]+data-clock-in-form[^>]*>/', $html, $clockInForms);
        preg_match_all('/<button[^>]+type="submit"[^>]+data-clock-in-submit[^>]*>/', $html, $clockInButtons);

        $this->assertCount(2, $clockInForms[0]);
        $this->assertCount(2, $clockInButtons[0]);
        $this->assertSame(2, substr_count($html, 'action="' . route('portal.attendance.clock-in') . '"'));
    }

    public function test_clock_in_lateness_after_nine_am(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        \App\Models\Task::create([
            'title' => 'Late Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(9, 15, 0));

        $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Slept in late.',
        ]);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'status' => 'Late',
        ]);
    }

    public function test_user_can_clock_out_with_overtime_calculation(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        // Clock In at 8:00 AM
        Carbon::setTestNow(Carbon::today()->setTime(8, 0, 0));
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'clock_in_at' => Carbon::now(),
            'daily_objective' => 'Work hard all day.',
            'status' => 'On Time',
        ]);

        // Clock Out at 7:30 PM (19:30) which is 90 minutes past 6 PM (18:00)
        Carbon::setTestNow(Carbon::today()->setTime(19, 30, 0));

        $response = $this->actingAs($user)->post('/portal/attendance/clock-out');

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Successfully clocked out. Have a great evening!');

        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'overtime_minutes' => 90,
        ]);
    }

    public function test_user_cannot_clock_out_before_six_pm(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(8, 0, 0));
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'clock_in_at' => Carbon::now(),
            'daily_objective' => 'Work hard all day.',
            'status' => 'On Time',
        ]);

        Carbon::setTestNow(Carbon::today()->setTime(17, 59, 0));

        $response = $this->actingAs($user)->post('/portal/attendance/clock-out');

        $response->assertSessionHasErrors(['attendance']);
        $this->assertDatabaseHas('attendance', [
            'id' => $attendance->id,
            'clock_out_at' => null,
        ]);
    }

    public function test_clock_in_fails_if_no_task_added_today(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $response = $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'My focus for today.',
        ]);

        $response->assertSessionHasErrors(['attendance']);
        $this->assertDatabaseMissing('attendance', [
            'user_id' => $user->id,
        ]);
    }

    public function test_legacy_admin_attendance_dashboard_redirects_to_main_dashboard(): void
    {
        $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('dashboard',['weekly_department'=>'all']));
        // Attendance tools retain their own permissions after the overview is consolidated.
        $this->actingAs($admin)->get(route('portal.hr-reporting.index'))->assertOk();
    }
}
