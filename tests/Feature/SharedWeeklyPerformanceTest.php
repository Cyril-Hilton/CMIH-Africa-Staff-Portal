<?php

namespace Tests\Feature;

use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceObservation;
use App\Models\PerformanceTargetPlan;
use App\Models\User;
use App\Services\SharedWeeklyPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedWeeklyPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_department_sees_client_relations_financial_kpis_without_private_source_notes(): void
    {
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $reviewer = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $objective = PerformanceObjective::create(['department' => 'client_relations', 'title' => 'Commercial objective', 'outcome' => 'Deliver revenue', 'owner_id' => $owner->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $k = PerformanceKpi::create(['objective_id' => $objective->id, 'department' => 'client_relations', 'name' => 'Private revenue KPI', 'definition' => 'Revenue', 'formula' => 'actual/target', 'unit' => 'GHS', 'direction' => 'higher', 'aggregation' => 'sum', 'frequency' => 'weekly', 'source' => 'Private ledger', 'owner_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 3, 'required_action' => 'Follow up', 'dimension' => 'financial', 'weight' => 1, 'evidence_required' => false]);
        $plan = PerformanceTargetPlan::create(['scope_key' => 'private-target', 'kpi_id' => $k->id, 'department' => 'client_relations', 'scope_type' => 'department', 'owner_id' => $owner->id, 'period_start' => today()->startOfWeek(), 'period_end' => today()->endOfWeek(), 'target' => 246913.78, 'weight' => 1, 'rationale' => 'PRIVATE-BASELINE-NOTE', 'submitted_by' => $owner->id, 'reviewer_id' => $reviewer->id, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'client' => 'Shared client', 'project' => 'Shared project']);
        PerformanceObservation::create(['scope_key' => 'private-actual', 'target_plan_id' => $plan->id, 'kpi_id' => $k->id, 'owner_id' => $owner->id, 'period_start' => today()->startOfWeek(), 'period_end' => today()->endOfWeek(), 'target' => 246913.78, 'actual' => 123456.89, 'source_reference' => 'PRIVATE-LEDGER-REFERENCE', 'approved_at' => now(), 'approved_by' => $reviewer->id]);
        foreach (array_keys(config('performance.departments')) as $department) {
            $staff = User::factory()->create(['department' => $department, 'access_role' => 'staff', 'status' => 'active']);
            $page = $this->actingAs($staff)->get(route('dashboard', ['weekly_department' => 'all']))->assertOk()->assertSee('Shared client')->assertSee('Shared project')->assertSee('GH₵ 123,456.89')->assertSee('GH₵ 246,913.78')->assertDontSee('PRIVATE-BASELINE-NOTE')->assertDontSee('PRIVATE-LEDGER-REFERENCE');
            $public = json_encode($page->viewData('sharedWeeklyPerformance'));
            $this->assertStringContainsString('123,456.89', $public);
            if ($department !== 'client_relations') {
                $this->get(route('portal.crm.index'))->assertForbidden();
            }
        }
        $projection = app(SharedWeeklyPerformance::class)->build('client_relations', today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString());
        $this->assertSame('GH₵ 246,913.78', $projection['client_relations']['kpis'][0]['target']);
        $this->assertSame('red', $projection['client_relations']['kpis'][0]['rag']);

        // Currency units must protect Finance amounts even if the KPI dimension is misclassified.
        $k->update(['department' => 'finance', 'dimension' => 'delivery', 'unit' => 'GH₵']);
        $plan->update(['department' => 'finance']);
        $finance = app(SharedWeeklyPerformance::class)->build('finance', today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString());
        $this->assertSame('100% of agreed target', $finance['finance']['kpis'][0]['target']);
        $this->assertSame('50% of target', $finance['finance']['kpis'][0]['actual']);
        $this->assertStringNotContainsString('123456', json_encode($finance));
        $this->assertStringNotContainsString('PRIVATE-LEDGER-REFERENCE', json_encode($finance));

    }
}
