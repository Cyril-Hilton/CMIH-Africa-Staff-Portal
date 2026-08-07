<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AutoClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function identityDocumentAttributes(): array
    {
        return [
            'ghana_card_number' => 'GHA-TEST-0001',
            'ghana_card_front_path' => 'identity-documents/tests/front.jpg',
            'ghana_card_back_path' => 'identity-documents/tests/back.jpg',
        ];
    }

    public function test_admin_can_update_user_permissions_matrix_and_job_level(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'permissions_matrix' => [],
        ]);

        $response = $this->actingAs($admin)->post("/admin/users/{$staff->id}/permissions", [
            'job_level' => 'manager',
            'permissions' => ['manage_tasks', 'manage_payroll'],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'job_level' => 'manager',
        ]);

        $staff->refresh();
        $this->assertEquals(['manage_tasks', 'manage_payroll'], $staff->permissions_matrix);
    }

    public function test_full_hr_staff_only_see_staff_management_admin_link(): void
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'Manager',
        ]);

        $response = $this->actingAs($hrManager)->get('/profile');

        $response->assertOk();
        $response->assertSee('href="' . route('admin.users') . '"', false);
        $response->assertDontSee('href="' . route('admin.dashboard') . '"', false);
        $response->assertDontSee('href="' . route('admin.content') . '"', false);
        $response->assertDontSee('href="' . route('admin.tasks') . '"', false);
        $response->assertDontSee('href="' . route('admin.announcements') . '"', false);
        $response->assertDontSee('href="' . route('admin.events') . '"', false);
        $response->assertDontSee('href="' . route('admin.brands') . '"', false);
        $response->assertDontSee('href="' . route('admin.portfolio') . '"', false);
        $response->assertDontSee('href="' . route('admin.settings') . '"', false);
    }

    public function test_cyril_does_not_see_admin_sidebar_links_from_developer_bypass(): void
    {
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'creatives',
            'job_level' => 'manager',
            'position_title' => 'Manager',
        ]);

        $response = $this->actingAs($cyril)->get('/profile');

        $response->assertOk();
        $response->assertDontSee('href="' . route('admin.dashboard') . '"', false);
        $response->assertDontSee('href="' . route('admin.users') . '"', false);
        $response->assertDontSee('href="' . route('admin.content') . '"', false);
        $response->assertDontSee('href="' . route('admin.tasks') . '"', false);
        $response->assertDontSee('href="' . route('admin.announcements') . '"', false);
        $response->assertDontSee('href="' . route('admin.events') . '"', false);
        $response->assertDontSee('href="' . route('admin.brands') . '"', false);
        $response->assertDontSee('href="' . route('admin.portfolio') . '"', false);
        $response->assertDontSee('href="' . route('admin.settings') . '"', false);

        $this->actingAs($cyril)
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_full_hr_staff_can_open_staff_management_but_not_site_admin_pages(): void
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'Manager',
        ]);

        $this->actingAs($hrManager)
            ->get(route('admin.users'))
            ->assertOk();

        $this->actingAs($hrManager)
            ->get(route('admin.content'))
            ->assertForbidden();
    }

    public function test_only_admins_can_archive_or_restore_user_accounts(): void
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'Manager',
        ]);

        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $this->actingAs($hrManager)
            ->delete(route('admin.users.destroy', $staff))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $staff))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'status' => 'archived',
        ]);

        $this->actingAs($hrManager)
            ->post(route('admin.users.restore', $staff))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'status' => 'archived',
        ]);
    }

    public function test_developer_and_super_admin_are_autoclocked_in_upon_access(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));

        $dev = User::factory()->create([
            'name' => 'Cyril Hilton Wemegah',
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'department' => 'creatives',
            ...$this->identityDocumentAttributes(),
        ]);

        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'department' => 'creatives',
        ]);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'status' => 'active',
            'access_role' => 'super_admin',
            'job_level' => 'super_admin',
            ...$this->identityDocumentAttributes(),
        ]);

        // Access portal as developer with the clock-in enforcement header
        $responseDev = $this->actingAs($dev)
            ->withHeader('X-Test-Enforce-ClockIn', 'true')
            ->get('/portal/payroll'); // payroll is a protected route subject to clock-in check

        $responseDev->assertStatus(200);

        // Verify task and attendance logs automatically created for the developer
        $devTask = \App\Models\Task::where('assigned_to', $dev->id)
            ->where('title', 'CMIH Portal Maintainance and Feature Upgrade')
            ->firstOrFail();
        $this->assertSame('Completed', $devTask->status);
        $this->assertSame(100, $devTask->progress);
        $this->assertNull($devTask->completion_review_status);
        $this->assertNotContains($curtis->id, array_map('intval', $devTask->copied_manager_ids ?? []));
        $this->assertNull($devTask->completion_review_task_id);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $dev->id,
            'status' => 'On Time',
            'daily_objective' => 'CMIH Portal Maintainance and Feature Upgrade',
        ]);

        $devAttendance = Attendance::where('user_id', $dev->id)->first();
        $this->assertNotNull($devAttendance->clock_out_at);
        $this->assertGreaterThanOrEqual(19, (int) $devAttendance->clock_out_at->format('H'));
        $this->assertLessThanOrEqual(21, (int) $devAttendance->clock_out_at->format('H'));
        $this->assertGreaterThanOrEqual(60, $devAttendance->overtime_minutes);

        // Access portal as super admin with enforcement header
        $responseAdmin = $this->actingAs($superAdmin)
            ->withHeader('X-Test-Enforce-ClockIn', 'true')
            ->get('/portal/payroll');

        $responseAdmin->assertStatus(200);

        // Verify task and attendance logs created for super admin
        $this->assertDatabaseHas('tasks', [
            'assigned_to' => $superAdmin->id,
            'title' => 'Overall App Supervision & Staff Management',
            'status' => 'Completed',
            'progress' => 100,
        ]);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $superAdmin->id,
            'status' => 'On Time',
            'daily_objective' => 'Overall App Supervision & Staff Management',
        ]);

        $adminAttendance = Attendance::where('user_id', $superAdmin->id)->first();
        $this->assertNotNull($adminAttendance->clock_out_at);
        $this->assertGreaterThanOrEqual(19, (int) $adminAttendance->clock_out_at->format('H'));
        $this->assertLessThanOrEqual(21, (int) $adminAttendance->clock_out_at->format('H'));
        $this->assertGreaterThanOrEqual(60, $adminAttendance->overtime_minutes);
    }

    public function test_auto_clock_corrects_existing_early_privileged_clock_out(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'status' => 'active',
            'access_role' => 'super_admin',
            'job_level' => 'super_admin',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));

        $date = Carbon::today();
        $attendance = Attendance::create([
            'user_id' => $superAdmin->id,
            'clock_in_at' => $date->copy()->setTime(8, 12, 0),
            'clock_out_at' => $date->copy()->setTime(17, 45, 0),
            'daily_objective' => 'Overall App Supervision & Staff Management',
            'status' => 'On Time',
            'overtime_minutes' => 0,
            'created_at' => $date->copy()->setTime(8, 12, 0),
        ]);

        AutoClockService::handleForUser($superAdmin, $date);

        $attendance->refresh();

        $this->assertGreaterThanOrEqual(19, (int) $attendance->clock_out_at->format('H'));
        $this->assertLessThanOrEqual(21, (int) $attendance->clock_out_at->format('H'));
        $this->assertGreaterThanOrEqual(60, $attendance->overtime_minutes);
    }

    public function test_developer_and_super_admin_can_edit_autoclocked_task(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00'));

        $dev = User::factory()->create([
            'name' => 'Cyril Hilton Wemegah',
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'department' => 'creatives',
            ...$this->identityDocumentAttributes(),
        ]);

        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'department' => 'creatives',
        ]);

        // Access a protected route to trigger the auto-clock and auto-task generation
        $this->actingAs($dev)
            ->withHeader('X-Test-Enforce-ClockIn', 'true')
            ->get('/portal/payroll')
            ->assertStatus(200);

        // Retrieve the generated task
        $task = \App\Models\Task::where('assigned_to', $dev->id)->first();
        $this->assertNotNull($task);

        // Edit the task using portal task update endpoint
        $response = $this->actingAs($dev)->patch("/portal/tasks/{$task->id}", [
            'title' => 'CMIH Portal Maintenance - Custom Feature Upgrades',
            'details' => 'Modified description by Developer.',
            'priority' => 'High',
            'status' => 'Completed',
            'progress' => 100,
            'due_on' => now()->toDateString(),
            'completion_manager_id' => $curtis->id,
            'copied_manager_ids' => [$curtis->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Check if task database entry was updated
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'CMIH Portal Maintenance - Custom Feature Upgrades',
            'details' => 'Modified description by Developer.',
        ]);

        // Check if today's attendance record's daily objective was updated as well
        $this->assertDatabaseHas('attendance', [
            'user_id' => $dev->id,
            'daily_objective' => 'CMIH Portal Maintenance - Custom Feature Upgrades',
        ]);
    }

    public function test_super_admin_can_view_confidential_payroll_and_dob(): void
    {
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'job_level' => 'super_admin',
            'date_of_birth' => '1990-01-01',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'date_of_birth' => '1995-05-15',
            'bank_name' => 'GCB',
            'bank_account_number' => '10400030029',
        ]);

        $otherStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'date_of_birth' => '1996-06-20',
            'bank_name' => 'Ecobank',
            'bank_account_number' => '99999999999',
        ]);

        // Access payroll page as staff
        $responseStaffPayroll = $this->actingAs($staff)->get('/portal/payroll');
        $responseStaffPayroll->assertStatus(200);
        $responseStaffPayroll->assertDontSee('Staff Payroll & Banking Ledger');
        $responseStaffPayroll->assertDontSee($otherStaff->bank_account_number);

        // Access payroll page as super admin
        $responseAdminPayroll = $this->actingAs($superAdmin)->get('/portal/payroll');
        $responseAdminPayroll->assertStatus(200);
        $responseAdminPayroll->assertSee('Staff Payroll & Banking Ledger', false);
        $responseAdminPayroll->assertSee($staff->name);
        $responseAdminPayroll->assertSee($staff->bank_account_number);
        $responseAdminPayroll->assertSee($otherStaff->bank_account_number);

        // Access directory page as staff
        $responseStaffDirectory = $this->actingAs($staff)->get('/portal/directory');
        $responseStaffDirectory->assertStatus(200);
        $responseStaffDirectory->assertDontSee('Upcoming Birthdays');

        // Access directory page as super admin
        $responseAdminDirectory = $this->actingAs($superAdmin)->get('/portal/directory');
        $responseAdminDirectory->assertStatus(200);
        $responseAdminDirectory->assertSee('Upcoming Birthdays');
    }

    public function test_visitor_portal_access_and_tickets(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'creatives',
        ]);

        $otherStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'finance',
        ]);

        // Accessing HR Command Center as Creative staff is forbidden
        $this->actingAs($staff)->get('/portal/hr')->assertStatus(403);

        // Accessing Visitor Portal is allowed
        $response = $this->actingAs($staff)->get('/portal/visitors');
        $response->assertStatus(200);
        $response->assertSee('Visitor Pre-Ticketing');

        // Submit a pre-ticket successfully
        $responsePost = $this->actingAs($staff)->post('/portal/hr/pre-tickets', [
            'visitor_name' => 'Alice Doe',
            'visitor_company' => 'Initech Corp',
            'visitor_email' => 'alice@example.com',
            'visitor_phone' => '0244123987',
            'purpose' => 'Design Sync',
            'host_id' => $staff->id,
            'expected_arrival' => '2026-06-18 10:00:00',
        ]);

        $responsePost->assertSessionHasNoErrors();
        $responsePost->assertRedirect();

        $this->assertDatabaseHas('visitor_pre_tickets', [
            'visitor_name' => 'Alice Doe',
            'host_id' => $staff->id,
            'created_by' => $staff->id,
        ]);
    }
}
