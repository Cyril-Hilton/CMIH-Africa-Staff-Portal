<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Attendance;
use App\Models\PerformanceAward;
use App\Mail\AwardCertificateMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PerformanceAwardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_authorized_roles_can_lock_awards(): void
    {
        $executive = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Regular Employee'
        ]);

        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'name' => 'Manager User',
            'department' => 'hr_admin'
        ]);

        $period = Carbon::now()->format('Y-m');

        // Staff should get 403
        $response = $this->actingAs($executive)->post('/portal/awards/lock', [
            'award_type' => 'employee_of_the_month',
            'period' => $period,
            'winner_id' => $executive->id,
            'winner_score' => 95.0,
        ]);
        $response->assertStatus(403);

        // Manager should succeed
        $response2 = $this->actingAs($manager)->post('/portal/awards/lock', [
            'award_type' => 'employee_of_the_month',
            'period' => $period,
            'winner_id' => $executive->id,
            'winner_score' => 95.0,
        ]);
        $response2->assertSessionHasNoErrors();
        $response2->assertRedirect();
        
        $this->assertDatabaseHas('performance_awards', [
            'award_type' => 'employee_of_the_month',
            'period' => $period,
            'winner_id' => $executive->id,
            'winner_score' => '95.00',
        ]);
    }

    public function test_get_standings_retrieves_correct_data(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin'
        ]);

        $employee = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'creatives'
        ]);

        // Create tasks and attendances in current month
        $period = Carbon::now()->format('Y-m');

        Task::create([
            'title' => 'Sample Task',
            'assigned_to' => $employee->id,
            'assigned_by' => $admin->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
        ]);

        Attendance::create([
            'user_id' => $employee->id,
            'clock_in_at' => Carbon::now(),
            'daily_objective' => 'Work tasks',
            'status' => 'On Time',
        ]);

        $response = $this->actingAs($admin)->getJson("/portal/awards/standings?period={$period}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'calculated' => [
                    'employees' => [
                        '*' => [
                            'name',
                            'score',
                            'task_rate',
                            'punctuality',
                            'attendance_rate',
                            'task_weighted',
                            'punctuality_weighted',
                            'attendance_weighted',
                        ],
                    ],
                    'departments' => [
                        '*' => [
                            'key',
                            'label',
                            'score',
                            'task_rate',
                            'punctuality',
                            'attendance_rate',
                            'member_count',
                            'members',
                        ],
                    ],
                ],
                'locked'
            ]);

        $data = $response->json();
        $this->assertEquals($period, $data['period']);
        $this->assertNotEmpty($data['calculated']['employees']);
        $this->assertEquals($employee->name, $data['calculated']['employees'][0]['name']);
    }

    public function test_award_score_penalizes_missed_workday_clock_ins_even_with_full_task_completion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Awards Admin',
        ]);

        $consistentStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Consistent Clock In Staff',
            'department' => 'brands_marketing',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);

        $missedClockInsStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Walker Nana Kwame Asare',
            'department' => 'brands_marketing',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);

        foreach ([$consistentStaff, $missedClockInsStaff] as $staff) {
            $task = Task::create([
                'title' => 'Completed field work for ' . $staff->name,
                'assigned_to' => $staff->id,
                'assigned_by' => $admin->id,
                'department' => 'brands_marketing',
                'status' => 'Completed',
                'priority' => 'Medium',
            ]);
            $task->forceFill(['created_at' => Carbon::parse('2026-07-01 09:00:00')])->save();
        }

        foreach (['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'] as $date) {
            Attendance::create([
                'user_id' => $consistentStaff->id,
                'clock_in_at' => Carbon::parse($date . ' 08:30:00'),
                'daily_objective' => 'Daily task update',
                'status' => 'On Time',
            ]);
        }

        Attendance::create([
            'user_id' => $missedClockInsStaff->id,
            'clock_in_at' => Carbon::parse('2026-07-01 08:30:00'),
            'daily_objective' => 'Daily task update',
            'status' => 'On Time',
        ]);

        $response = $this->actingAs($admin)->getJson('/portal/awards/standings?period=2026-07');
        $response->assertOk();

        $employees = collect($response->json('calculated.employees'))->keyBy('name');
        $consistent = $employees['Consistent Clock In Staff'];
        $missedClockIns = $employees['Walker Nana Kwame Asare'];

        $this->assertSame(100, (int) $consistent['task_rate']);
        $this->assertSame(100, (int) $consistent['punctuality']);
        $this->assertSame(100, (int) $consistent['score']);
        $this->assertSame(8, (int) $missedClockIns['expected_workdays']);
        $this->assertSame(1, (int) $missedClockIns['attendance_days']);
        $this->assertEquals(12.5, $missedClockIns['punctuality']);
        $this->assertEquals(30.0, $missedClockIns['score']);
        $this->assertLessThan($consistent['score'], $missedClockIns['score']);
    }

    public function test_locking_award_saves_to_database_and_sends_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin'
        ]);

        $winner = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'email' => 'winner@cmih.africa'
        ]);

        $period = '2026-06';

        $response = $this->actingAs($admin)->post('/portal/awards/lock', [
            'award_type' => 'employee_of_the_month',
            'period' => $period,
            'winner_id' => $winner->id,
            'winner_score' => 98.50,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('performance_awards', [
            'award_type' => 'employee_of_the_month',
            'period' => $period,
            'winner_id' => $winner->id,
        ]);

        Mail::assertSent(AwardCertificateMail::class, function ($mail) use ($winner) {
            return $mail->hasTo('winner@cmih.africa') && 
                   $mail->awardType === 'Employee of the Month' &&
                   $mail->periodLabel === 'June 2026';
        });
    }

    public function test_creative_task_custom_other_specification(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'creatives'
        ]);

        // Submit task with Job Description "Other" and specification "Packaging Design"
        $response = $this->actingAs($user)->post('/portal/creative/briefs', [
            'title' => 'New Christmas Box',
            'job_description' => 'Other',
            'job_description_custom' => 'Packaging Design',
            'details' => 'Draft the template layouts for the box.',
            'priority' => 'medium',
            'due_on' => Carbon::now()->addDays(5)->format('Y-m-d')
        ]);

        $response->assertRedirect();
        
        // Assert task is created with custom category bracketed in title
        $this->assertDatabaseHas('tasks', [
            'title' => '[Packaging Design] New Christmas Box',
            'department' => 'creatives',
            'assigned_to' => $user->id,
        ]);
    }

    public function test_standings_includes_cyril_but_excludes_other_super_admins(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'General Super Admin'
        ]);

        $cyril = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Cyril Hilton'
        ]);

        $period = Carbon::now()->format('Y-m');

        // Create tasks and attendance for both to be eligible
        foreach ([$admin, $cyril] as $u) {
            Task::create([
                'title' => 'Task for ' . $u->name,
                'assigned_to' => $u->id,
                'assigned_by' => $admin->id,
                'department' => 'finance',
                'status' => 'Completed',
                'priority' => 'Medium',
            ]);

            Attendance::create([
                'user_id' => $u->id,
                'clock_in_at' => Carbon::now(),
                'daily_objective' => 'Objective ' . $u->name,
                'status' => 'On Time',
            ]);
        }

        $response = $this->actingAs($cyril)->getJson("/portal/awards/standings?period={$period}");
        $response->assertStatus(200);

        $employees = $response->json('calculated.employees');
        $employeeNames = collect($employees)->pluck('name')->toArray();

        // Cyril Hilton should be in the list, but General Super Admin should be excluded
        $this->assertContains('Cyril Hilton', $employeeNames);
        $this->assertNotContains('General Super Admin', $employeeNames);
    }

    public function test_award_backfill_command_keeps_auto_clock_users_at_full_punctuality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Awards Admin',
        ]);

        $cyril = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Cyril Hilton',
            'department' => 'creatives',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);

        $curtis = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'name' => 'Curtis Barnor',
            'department' => 'creatives',
            'start_date' => Carbon::parse('2026-07-01'),
        ]);

        Attendance::create([
            'user_id' => $cyril->id,
            'clock_in_at' => Carbon::parse('2026-07-01 10:45:00'),
            'clock_out_at' => Carbon::parse('2026-07-01 17:30:00'),
            'daily_objective' => 'Old late auto row',
            'status' => 'Late',
        ]);

        $this->artisan('awards:backfill-autoclock 2026-07')
            ->assertSuccessful();

        $response = $this->actingAs($admin)->getJson('/portal/awards/standings?period=2026-07');
        $response->assertOk();

        $employees = collect($response->json('calculated.employees'))->keyBy('name');

        foreach ([$cyril->name, $curtis->name] as $name) {
            $this->assertSame(100, (int) $employees[$name]['punctuality']);
            $this->assertSame(100, (int) $employees[$name]['attendance_rate']);
            $this->assertSame(8, (int) $employees[$name]['expected_workdays']);
            $this->assertSame(8, (int) $employees[$name]['attendance_days']);
        }
    }

    public function test_department_award_score_uses_average_of_all_department_staff_not_only_top_performer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Awards Admin',
        ]);

        $cyril = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Cyril Hilton',
            'department' => 'creatives',
        ]);

        User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Creative Teammate With No Activity',
            'department' => 'creatives',
        ]);

        $brandOne = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Brand Staff One',
            'department' => 'brands_marketing',
        ]);

        $brandTwo = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Brand Staff Two',
            'department' => 'brands_marketing',
        ]);

        $period = Carbon::now()->format('Y-m');

        Task::create([
            'title' => 'Perfect creative task',
            'assigned_to' => $cyril->id,
            'assigned_by' => $admin->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
        ]);

        Attendance::create([
            'user_id' => $cyril->id,
            'clock_in_at' => Carbon::now()->setTime(8, 30),
            'daily_objective' => 'Creative leadership',
            'status' => 'On Time',
        ]);

        foreach ([$brandOne, $brandTwo] as $brandStaff) {
            Task::create([
                'title' => 'Strong brands task for ' . $brandStaff->name,
                'assigned_to' => $brandStaff->id,
                'assigned_by' => $admin->id,
                'department' => 'brands_marketing',
                'status' => 'Completed',
                'priority' => 'Medium',
            ]);

            Attendance::create([
                'user_id' => $brandStaff->id,
                'clock_in_at' => Carbon::now()->setTime(8, 30),
                'daily_objective' => 'Brands execution',
                'status' => 'On Time',
            ]);

            Attendance::create([
                'user_id' => $brandStaff->id,
                'clock_in_at' => Carbon::now()->setTime(10, 15),
                'daily_objective' => 'Brands execution',
                'status' => 'Late',
            ]);
        }

        $this->artisan("awards:backfill-autoclock {$period}")
            ->assertSuccessful();

        $response = $this->actingAs($admin)->getJson("/portal/awards/standings?period={$period}");
        $response->assertOk();

        $employees = collect($response->json('calculated.employees'));
        $departments = collect($response->json('calculated.departments'))->keyBy('key');

        $this->assertSame('Cyril Hilton', $employees->first()['name']);
        $this->assertSame('creatives', $response->json('calculated.departments.0.key'));
        $this->assertEquals(30.0, $departments['brands_marketing']['score']);
        $this->assertEquals(50.0, $departments['creatives']['score']);
        $this->assertEquals(2, $departments['creatives']['member_count']);
        $this->assertCount(2, $departments['brands_marketing']['members']);
        $this->assertArrayHasKey('score_contribution', $departments['brands_marketing']['members'][0]);
        $this->assertArrayHasKey('task_contribution', $departments['brands_marketing']['members'][0]);
        $this->assertArrayHasKey('punctuality_contribution', $departments['brands_marketing']['members'][0]);
        $this->assertArrayHasKey('attendance_contribution', $departments['brands_marketing']['members'][0]);
    }

    public function test_dashboard_department_medal_uses_locked_department_award_winner(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'name' => 'Cyril Hilton',
        ]);

        $brandsStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'brands_marketing',
        ]);

        Task::create([
            'title' => 'Brands task that would otherwise win operational completion',
            'assigned_to' => $brandsStaff->id,
            'assigned_by' => $admin->id,
            'department' => 'brands_marketing',
            'status' => 'Completed',
            'priority' => 'Medium',
        ]);

        $period = Carbon::now()->format('Y-m');

        PerformanceAward::create([
            'award_type' => 'department_of_the_month',
            'period' => $period,
            'winner_val' => 'creatives',
            'winner_score' => 85,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id="tab-btn-creatives"[^>]*data-award-winner="true"/', $html);
        $this->assertMatchesRegularExpression('/id="tab-btn-brands_marketing"[^>]*data-award-winner="false"/', $html);
    }
}
