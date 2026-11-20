<?php

namespace Tests\Feature;

use App\Models\HrDashboardActivity;
use App\Models\User;
use App\Services\HrWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HrRequirementsCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_period_includes_actual_completion_of_older_work_and_summary_matches_filters(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 15:00:00'));
        $hr = User::factory()->create(['status' => 'active', 'access_role' => 'manager', 'department' => 'hr_admin', 'position_title' => 'HR Manager', 'job_level' => 'manager']);
        $base = ['tab' => 'fleet', 'category' => 'Vehicle servicing', 'assigned_to' => $hr->id, 'created_by' => $hr->id, 'opened_on' => '2026-10-01', 'deadline' => '2026-10-02', 'priority' => 'High', 'status' => 'Completed', 'completed_at' => now()];
        HrDashboardActivity::create($base + ['activity' => 'Late service completed this week']);
        HrDashboardActivity::create(array_replace($base, ['activity' => 'Low priority completed this week', 'priority' => 'Low']));
        HrDashboardActivity::create(array_replace($base, ['activity' => 'Previously completed service', 'completed_at' => '2026-10-03 12:00:00']));
        $r = Request::create('/portal/hr-workspace', 'GET', ['hr_tab' => 'fleet', 'hr_period' => 'week', 'hr_from' => '2026-11-16', 'hr_status' => 'Completed', 'hr_priority' => 'High']);
        $r->setUserResolver(fn () => $hr);
        $data = app(HrWorkspace::class)->build($r);
        $this->assertSame(1, $data['activities']->total());
        $this->assertSame('Late service completed this week', $data['activities']->first()->activity);
        $this->assertSame(1, $data['summary']['Completed in period']);
        $this->assertSame(0, $data['summary']['Open activities']);
        $this->assertSame(1, $data['allExportActivities']->count());
    }

    public function test_pending_approval_cannot_be_bypassed_and_completion_keeps_cvo_provenance(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
        $hr = User::factory()->create(['status' => 'active', 'access_role' => 'manager', 'department' => 'hr_admin', 'position_title' => 'HR Manager', 'job_level' => 'manager']);
        $cvo = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'staff', 'position_title' => 'CVO', 'job_level' => 'CVO']);
        $input = ['tab' => 'fleet', 'category' => 'Vehicle servicing', 'assigned_to' => $hr->id, 'opened_on' => today()->toDateString(), 'deadline' => today()->addWeek()->toDateString(), 'priority' => 'High', 'status' => 'Pending Approval', 'activity' => 'Approval-controlled servicing'];
        $this->actingAs($hr)->post(route('portal.hr-dashboard.activities.store'), $input)->assertSessionHasNoErrors();
        $activity = HrDashboardActivity::sole();
        $this->patch(route('portal.hr-dashboard.activities.update', $activity), array_replace($input, ['revision' => 1, 'status' => 'Completed']))->assertSessionHasErrors('status');
        $this->assertSame('Pending Approval', $activity->fresh()->status);
        $this->actingAs($cvo)->post(route('portal.hr-dashboard.activities.approve', $activity), ['revision' => 1])->assertSessionHasNoErrors();
        $approvedAt = $activity->fresh()->approved_at->toDateTimeString();
        $this->actingAs($hr)->patch(route('portal.hr-dashboard.activities.update', $activity), array_replace($input, ['revision' => 2, 'status' => 'Completed']))->assertSessionHasNoErrors();
        $this->assertSame($cvo->id, $activity->fresh()->approved_by);
        $this->assertSame($approvedAt, $activity->fresh()->approved_at->toDateTimeString());
        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_existing_ghana_card_and_damaged_facility_are_reused_without_reentry(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 15:00:00'));
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('hr-documents/existing-id.pdf', 'Existing ID');
        $hr = User::factory()->create(['status' => 'active', 'access_role' => 'manager', 'department' => 'hr_admin', 'position_title' => 'HR Manager']);
        $staff = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'ghana_card_front_path' => 'hr-documents/existing-id.pdf']);
        $service = app(\App\Services\HrLiveRegisters::class);
        $gap = collect($service->documentGaps($hr))->firstWhere('id', $staff->id);
        $this->assertNotContains('ID', $gap['missing']);
        $coverage = app(\App\Services\HrRegisterKpis::class)->build('2026-11-16', '2026-11-22');
        $this->assertSame(1, collect($coverage['metrics'])->firstWhere('name', 'Staff document completeness')['actual']);
        \App\Models\Asset::create(['name' => 'Existing damaged office AC', 'asset_tag' => 'DAMAGE-AC', 'type' => 'Equipment', 'category' => 'AC', 'quantity_procured' => 2, 'condition' => 'Damaged', 'status' => 'available', 'is_warehouse_tracked' => false]);
        $stats = $service->stats('facilities', $hr, '2026-11-16', '2026-11-22');
        $this->assertSame(2, $stats['AC']);
        $this->assertSame(2, $stats['Faulty / damaged / under repair']);
        $this->assertDatabaseCount('hr_register_records',0);
    }
}
