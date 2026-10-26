<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\HrAttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrAttendanceCoverageTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ReadsExcelExports;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 14:00:00');
        config(['company_calendar.non_working_dates' => []]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff($name, $extra = [])
    {
        return User::factory()->create($extra + ['name' => $name, 'department' => 'finance', 'status' => 'active', 'access_role' => 'staff', 'start_date' => '2026-10-19']);
    }

    private function log($user, $date)
    {
        return Attendance::create(['user_id' => $user->id, 'clock_in_at' => $date, 'daily_objective' => 'Attendance coverage test', 'status' => 'On Time']);
    }

    public function test_roster_includes_non_clockers_and_excludes_executives_and_field_accounts(): void
    {
        $cvo = $this->staff('Executive CVO', ['access_role' => 'cvo', 'position_title' => 'CVO', 'job_level' => 'CVO']);
        $admin = $this->staff('Executive Super Admin', ['access_role' => 'super_admin']);
        $regular = $this->staff('Regular Staff');
        $missing = $this->staff('No Check-in Staff');
        $field = $this->staff('Merchandiser', ['access_role' => 'merchandiser']);
        foreach ([$cvo, $admin, $regular, $field] as $u) {
            $this->log($u, '2026-10-19 08:30:00');
        }
        $data = app(HrAttendanceReport::class)->build(['date' => '2026-10-23']);
        $rows = $data['summary']->keyBy('id');
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->has($missing->id));
        $this->assertNull($rows[$missing->id]['punctuality']);
        $this->assertCount(5, $rows[$missing->id]['missing']);
        $this->assertSame('Not Clocked In', $rows[$missing->id]['today_status']);
        $this->assertSame(1, $data['logs']->total());
        $this->assertSame(1, $data['coverage']['no_clockins']);
        foreach (['weekly', 'monthly', 'yearly'] as $period) {
            $this->assertTrue(app(HrAttendanceReport::class)->build(['period' => $period, 'date' => '2026-10-23'])['summary']->keyBy('id')->has($missing->id));
        }
    }

    public function test_attendance_export_keeps_non_clockers_but_excludes_executives(): void
    {
        $cvo = $this->staff('Executive CVO', ['access_role' => 'cvo', 'position_title' => 'CVO']);
        $admin = $this->staff('Executive Super Admin', ['access_role' => 'super_admin']);
        $staff = $this->staff('Zero Attendance Staff');
        $response = $this->actingAs($admin)->get(route('portal.attendance-performance.export'))->assertOk();
        $csv = $this->excelText($response->getContent());
        $this->assertStringContainsString('Zero Attendance Staff', $csv);
        $this->assertStringNotContainsString('Executive CVO', $csv);
        $this->assertStringNotContainsString('Executive Super Admin', $csv);
    }

    public function test_missing_dates_exclude_leave_holidays_future_days_and_pre_employment(): void
    {
        $anchor = $this->staff('Tracking anchor');
        $this->log($anchor, '2026-10-19 08:00:00');
        $staff = $this->staff('New Joiner', ['start_date' => '2026-10-21']);
        config(['company_calendar.non_working_dates' => ['2026-10-22']]);
        LeaveApplication::create(['user_id' => $staff->id, 'start_date' => '2026-10-23', 'end_date' => '2026-10-23', 'leave_type' => 'Annual', 'status' => 'approved']);
        $row = app(HrAttendanceReport::class)->build(['date' => '2026-10-23', 'q' => 'New Joiner'])['summary']->first();
        $this->assertSame(['2026-10-21'], $row['missing']);
        $this->assertSame('Approved Leave', $row['today_status']);
        $future = app(HrAttendanceReport::class)->build(['date' => '2026-11-10', 'q' => 'New Joiner'])['summary']->first();
        $this->assertSame([], $future['missing']);
    }

    public function test_first_arrival_counts_once_and_today_status_is_correct_when_browsing_history(): void
    {
        $u = $this->staff('Repeat Staff');
        $this->log($u, '2026-10-19 08:45:00');
        $this->log($u, '2026-10-19 09:40:00');
        $this->log($u, '2026-10-23 09:15:00');
        $row = app(HrAttendanceReport::class)->build(['date' => '2026-10-23'])['summary']->first();
        $this->assertSame(2, $row['days']);
        $this->assertSame(50.0, $row['punctuality']);
        $historic = app(HrAttendanceReport::class)->build(['date' => '2026-10-12'])['summary']->first();
        $this->assertSame('Clocked In · Late', $historic['today_status']);
        $this->assertSame(0, $historic['days']);
    }

    public function test_filters_find_non_clockers_and_late_filter_does_not_turn_their_period_rate_into_zero(): void
    {
        $u = $this->staff('Mixed Staff');
        $missing = $this->staff('Missing Staff');
        $this->log($u, '2026-10-19 08:45:00');
        $this->log($u, '2026-10-20 09:30:00');
        $data = app(HrAttendanceReport::class)->build(['date' => '2026-10-23', 'punctuality' => 'no_clockins']);
        $this->assertSame([$missing->id], $data['summary']->pluck('id')->values()->all());
        $late = app(HrAttendanceReport::class)->build(['date' => '2026-10-23', 'punctuality' => 'late']);
        $this->assertSame(50.0, $late['summary']->first()['punctuality']);
        $this->assertSame(1, $late['logs']->total());
    }
}
