<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePerformanceExportTest extends TestCase
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

    public function test_super_admin_hr_and_cvo_can_export_attendance_performance_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 12:00:00'));

        $superAdmin = User::factory()->create([
            'name' => 'Export Super Admin',
            'status' => 'active',
            'access_role' => 'super_admin',
            ...$this->identityDocumentAttributes(),
        ]);
        $hrUser = User::factory()->create([
            'name' => 'HR Export User',
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_title' => 'HR Executive',
            'position_title' => 'Executive',
            ...$this->identityDocumentAttributes(),
        ]);
        $cvo = User::factory()->create([
            'name' => 'CVO Export User',
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'client_relations',
            'position_title' => 'CVO',
            ...$this->identityDocumentAttributes(),
        ]);
        $staff = User::factory()->create([
            'name' => 'Target Staff',
            'email' => 'target.staff@cmih.africa',
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'creatives',
            'position_title' => 'Designer',
            'start_date' => Carbon::parse('2026-07-01'),
            'last_login_at' => Carbon::parse('2026-07-14 08:01:00'),
            'previous_login_at' => Carbon::parse('2026-07-13 08:05:00'),
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => Carbon::parse('2026-07-01 08:30:00'),
            'clock_out_at' => Carbon::parse('2026-07-01 18:15:00'),
            'daily_objective' => 'Creative delivery',
            'status' => 'On Time',
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => Carbon::parse('2026-07-02 09:30:00'),
            'daily_objective' => 'Creative delivery follow-up',
            'status' => 'Late',
        ]);

        $completedTask = Task::create([
            'title' => 'Completed export task',
            'assigned_to' => $staff->id,
            'assigned_by' => $superAdmin->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'due_on' => Carbon::parse('2026-07-05'),
        ]);
        $completedTask->forceFill(['created_at' => Carbon::parse('2026-07-03 10:00:00')])->save();

        $pendingTask = Task::create([
            'title' => 'Pending export task',
            'assigned_to' => $staff->id,
            'assigned_by' => $superAdmin->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'High',
            'due_on' => Carbon::parse('2026-07-04'),
        ]);
        $pendingTask->forceFill(['created_at' => Carbon::parse('2026-07-04 10:00:00')])->save();

        foreach ([$superAdmin, $hrUser, $cvo] as $viewer) {
            $response = $this->actingAs($viewer)
                ->withHeaders(['X-Test-Enforce-ClockIn' => 'true'])
                ->get(route('portal.attendance-performance.export', [
                    'start' => '2026-07-01',
                    'end' => '2026-07-14',
                ]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
            $this->assertStringContainsString(
                'attachment; filename="attendance_punctuality_performance_2026-07-01_to_2026-07-14.csv"',
                $response->headers->get('Content-Disposition')
            );

            $csv = $response->streamedContent();

            $this->assertStringContainsString('Login Log Storage', $csv);
            $this->assertStringContainsString('Tasks In Range', $csv);
            $this->assertStringContainsString('Target Staff', $csv);
            $this->assertStringContainsString('Missed 8 expected workday(s)', $csv);
            $this->assertStringContainsString('2026-07-01 08:30; 2026-07-02 09:30', $csv);
            $this->assertStringContainsString('2026-07-01: On Time; 2026-07-02: Late', $csv);
        }
    }

    public function test_regular_staff_cannot_export_attendance_performance_data(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'operations_projects',
        ]);

        $this->actingAs($staff)
            ->withHeaders(['X-Test-Enforce-ClockIn' => 'true'])
            ->get(route('portal.attendance-performance.export'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_clock_in_export_button_only_for_authorized_users(): void
    {
        $hrUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_title' => 'HR Executive',
            'position_title' => 'Executive',
        ]);
        $regularStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'creatives',
        ]);

        $this->actingAs($hrUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Export Clock-In CSV');

        $this->actingAs($regularStaff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Export Clock-In CSV');
    }
}
