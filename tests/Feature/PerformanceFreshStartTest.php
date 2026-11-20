<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceRegister;
use App\Models\PerformanceTargetPlan;
use App\Models\User;
use App\Services\PerformanceScoringService;
use App\Services\ReportingReset;
use App\Services\StaffPerformanceScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceFreshStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_only_archive_preserves_fresh_work_and_templates_and_clears_old_credit_without_absence_penalties(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 19:00:00'));
        $admin = User::factory()->create(['status' => 'active', 'access_role' => 'super_admin']);
        $staff = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'start_date' => '2026-01-01']);
        Attendance::create(['user_id' => $staff->id, 'clock_in_at' => '2026-11-20 08:00:00', 'status' => 'On Time', 'daily_objective' => 'Old attendance']);
        $objective = PerformanceObjective::create(['title' => 'Original goal', 'outcome' => 'Reliable delivery', 'owner_id' => $admin->id, 'department' => 'creatives', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $base = ['objective_id' => $objective->id, 'department' => 'creatives', 'name' => 'Original KPI', 'definition' => 'Verified output', 'formula' => 'actual/target', 'unit' => 'items', 'frequency' => 'weekly', 'source' => 'Original ledger', 'reviewer_id' => $admin->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 2, 'required_action' => 'Review outcome', 'dimension' => 'target_achievement', 'direction' => 'higher', 'aggregation' => 'sum', 'weight' => 100, 'version' => 1];
        $assigned = PerformanceKpi::create($base + ['owner_id' => $staff->id]);
        PerformanceTargetPlan::create(['kpi_id' => $assigned->id, 'scope_type' => 'individual', 'owner_id' => $staff->id, 'department' => 'creatives', 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'target' => 10, 'weight' => 100, 'approved_at' => now(), 'approved_by' => $admin->id, 'submitted_by' => $admin->id, 'reviewer_id' => $admin->id, 'scope_key' => 'fresh-start-test', 'rationale' => 'Original agreed target']);
        PerformanceRegister::create(['department' => 'operations_projects', 'kind' => 'activation', 'title' => 'Fresh report after first reset', 'reference' => 'FRESH-KEEP', 'status' => 'planned', 'data' => [], 'owner_id' => $staff->id, 'submitted_by' => $staff->id, 'period_start' => '2026-11-16', 'period_end' => '2026-11-22']);
        $selection = ['attendance', 'performance_target_plans', 'performance_kpis', 'performance_observations', 'performance_awards', 'performance_cases', 'performance_report_packs'];
        $service = app(ReportingReset::class);
        $protected = $service->protectedState($selection);
        $batch = $service->archive($admin, 'Authorized performance-only reset', 'verified-backup.sqlite', $selection);
        $this->assertSame($protected, $service->protectedState($selection));
        $this->assertSame('2026-11-23', $batch['performance_tracking_starts_on']);
        $this->assertSame(0, Attendance::count());
        $this->assertSame(1, DB::table('attendance')->count());
        $this->assertSame(0, PerformanceTargetPlan::count());
        $this->assertSame(0, PerformanceKpi::count());
        $this->assertArrayHasKey('creatives', config('performance.departments'));
        $this->assertSame(1, PerformanceRegister::count());
        $a = PerformanceScoringService::attendanceSummary($staff, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-22'));
        $this->assertSame(0, $a['expected_workdays']);
        $this->assertSame(0, $a['attendance_days']);
        $score = app(StaffPerformanceScore::class)->user($staff, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-22'));
        $this->assertEquals(0, $score['subtotal']);
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00'));
        Attendance::create(['user_id' => $staff->id, 'clock_in_at' => '2026-11-23 08:30:00', 'status' => 'On Time', 'daily_objective' => 'New cycle attendance']);
        $a = PerformanceScoringService::attendanceSummary($staff, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(1, $a['expected_workdays']);
        $this->assertSame(1, $a['attendance_days']);
        $this->assertSame(100.0, $a['punctuality_score']);
    }
}
