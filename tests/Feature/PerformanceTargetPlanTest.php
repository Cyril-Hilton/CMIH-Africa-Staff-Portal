<?php

namespace Tests\Feature;

use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceObservation;
use App\Models\PerformanceTargetPlan;
use App\Models\User;
use App\Services\PerformanceHubReport;
use App\Services\StaffPerformanceScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceTargetPlanTest extends TestCase
{
    use RefreshDatabase;

    private function setupPlan(): array
    {
        $manager = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $reviewer = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'staff', 'status' => 'active']);
        $objective = PerformanceObjective::create(['department' => 'client_relations', 'title' => 'Commercial delivery', 'outcome' => 'Revenue and client growth', 'owner_id' => $manager->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $kpi = PerformanceKpi::create(['department' => 'client_relations', 'objective_id' => $objective->id, 'name' => 'Revenue delivered', 'definition' => 'Recognized revenue', 'formula' => 'Recognized actual / agreed target', 'unit' => 'GHS', 'direction' => 'higher', 'aggregation' => 'sum', 'frequency' => 'monthly', 'source' => 'Approved ledger', 'owner_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 2, 'required_action' => 'Close revenue gap', 'dimension' => 'financial_performance', 'weight' => 1, 'evidence_required' => true]);

        return compact('manager', 'reviewer', 'owner', 'kpi');
    }

    private function payload(array $s): array
    {
        return ['kpi_id' => $s['kpi']->id, 'scope_type' => 'individual', 'owner_id' => $s['owner']->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => 100, 'weight' => 40, 'rationale' => 'Agreed monthly commercial target'];
    }

    public function test_history_keeps_department_and_individual_results_separate_and_compares_calendar_months(): void
    {
        $s = $this->setupPlan();
        foreach ([['2026-08-31', '2026-08-31', 7], ['2026-09-01', '2026-09-30', 50], ['2026-10-01', '2026-10-31', 40]] as [$from, $to, $actual]) {
            foreach (['department', 'individual'] as $scope) {
                $key = $scope.$from;
                $plan = PerformanceTargetPlan::create(['kpi_id' => $s['kpi']->id, 'department' => 'client_relations', 'scope_type' => $scope, 'owner_id' => $s['owner']->id, 'reviewer_id' => $s['reviewer']->id, 'submitted_by' => $s['manager']->id, 'period_start' => $from, 'period_end' => $to, 'target' => 100, 'weight' => 1, 'rationale' => 'History regression', 'scope_key' => $key, 'approved_at' => now()]);
                PerformanceObservation::create(['kpi_id' => $s['kpi']->id, 'target_plan_id' => $plan->id, 'owner_id' => $s['owner']->id, 'period_start' => $from, 'period_end' => $to, 'target' => 100, 'actual' => $actual, 'scope_key' => $key, 'source_reference' => 'Ledger regression', 'approved_at' => now()]);
            }
        }
        $observations = PerformanceObservation::with('kpi')->get();
        $plans = PerformanceTargetPlan::whereDate('period_start', '2026-10-01')->get();
        $row = app(PerformanceHubReport::class)->build(collect(), $observations, collect(), '2026-10-01', '2026-10-31', collect([$s['kpi']]), $plans)['kpis']->first();
        $this->assertEquals(40, $row['actual']);
        $this->assertEquals(50, $row['previous']);
        $this->assertEquals(97, $row['ytd']);
    }

    public function test_target_and_actual_have_separate_independent_approvals_and_actual_cannot_rewrite_baseline(): void
    {
        $s = $this->setupPlan();
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $this->payload($s))->assertRedirect()->assertSessionHasNoErrors();
        $plan = PerformanceTargetPlan::firstOrFail();
        $this->post(route('portal.performance.observation'), ['target_plan_id' => $plan->id, 'actual' => 50, 'source_reference' => 'Ledger'])->assertUnprocessable();
        $this->post(route('portal.performance.targets.approve', $plan), ['reason' => 'Self approval'])->assertForbidden();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.approve', $plan), ['reason' => 'Target agreed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s['manager'])->post(route('portal.performance.observation'), ['target_plan_id' => $plan->id, 'target' => 1, 'owner_id' => $s['manager']->id, 'actual' => 50, 'source_reference' => 'Reviewed ledger', 'evidence_url' => 'https://example.com/ledger'])->assertRedirect()->assertSessionHasNoErrors();
        $result = PerformanceObservation::firstOrFail();
        $this->assertEquals(100, $result->target);
        $this->assertEquals($s['owner']->id, $result->owner_id);
        $this->assertNull($result->approved_at);
        $this->actingAs($s['reviewer'])->post(route('portal.performance.approveObservation', $result), ['reason' => 'Ledger reconciled'])->assertRedirect()->assertSessionHasNoErrors();
        $score = app(StaffPerformanceScore::class)->user($s['owner'], Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertEquals(50, $score['kpi_rate']);
        $this->assertEquals(12.5, $score['components']['kpi']['points']);
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'target_plan', 'entity_id' => $plan->id, 'action' => 'approve']);
        $response = $this->actingAs($s['manager'])->get(route('portal.performance.index', ['department' => 'client_relations', 'from' => '2026-10-01', 'to' => '2026-10-31']));
        $response->assertOk();
        $this->assertEquals(50, $response->viewData('summary')['departments']['client_relations']['kpi_score']);
    }

    public function test_amendment_keeps_baseline_until_independent_approval_and_rejects_late_changes(): void
    {
        $s = $this->setupPlan();
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $this->payload($s))->assertSessionHasNoErrors();
        $original = PerformanceTargetPlan::firstOrFail();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.approve', $original), ['reason' => 'Agreed'])->assertRedirect();
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.amend', $original), ['target' => 200, 'weight' => 50, 'reason' => 'Scope increased'])->assertRedirect()->assertSessionHasNoErrors();
        $amendment = PerformanceTargetPlan::where('amends_plan_id', $original->id)->firstOrFail();
        $this->assertNull($original->fresh()->withdrawn_at);
        $this->assertEquals(100, $original->fresh()->target);
        $this->assertNull($amendment->approved_at);
        $this->post(route('portal.performance.targets.amend', $original), ['target' => 300, 'weight' => 50, 'reason' => 'Duplicate'])->assertConflict();
        $this->post(route('portal.performance.targets.approve', $amendment), ['reason' => 'Self approve'])->assertForbidden();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.approve', $amendment), ['reason' => 'Scope verified'])->assertRedirect();
        $this->assertNotNull($original->fresh()->withdrawn_at);
        $this->assertNotNull($amendment->fresh()->approved_at);
        $this->assertEquals(100, $original->fresh()->target);
        $this->actingAs($s['manager'])->post(route('portal.performance.observation'), ['target_plan_id' => $amendment->id, 'actual' => 100, 'source_reference' => 'Ledger', 'evidence_url' => 'https://example.com/evidence'])->assertSessionHasNoErrors();
        $this->post(route('portal.performance.targets.amend', $amendment), ['target' => 400, 'weight' => 50, 'reason' => 'After results'])->assertConflict();
    }

    public function test_kpi_revision_is_independently_approved_dated_and_preserves_original_definition(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20'));
        $s = $this->setupPlan();
        $changes = ['effective_on' => '2026-11-01', 'reason' => 'Updated source definition', 'definition' => 'Reconciled recognized revenue', 'formula' => 'Actual over approved target', 'source' => 'Approved monthly ledger', 'green_threshold' => 100, 'amber_threshold' => 85, 'required_action' => 'Review variance', 'escalation_days' => 3];
        $this->actingAs($s['manager'])->post(route('portal.performance.kpi.revisions.store', $s['kpi']), $changes)->assertRedirect()->assertSessionHasNoErrors();
        $revision = \App\Models\PerformanceKpiRevision::firstOrFail();
        $this->assertDatabaseCount('performance_kpis', 1);
        $this->assertSame('Recognized revenue', $s['kpi']->fresh()->definition);
        $this->post(route('portal.performance.kpi.revisions.review', $revision), ['decision' => 'approve', 'reason' => 'Self approve'])->assertForbidden();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.kpi.revisions.review', $revision), ['decision' => 'approve', 'reason' => 'Source agreed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('performance_kpis', 2);
        $new = PerformanceKpi::findOrFail($revision->fresh()->new_kpi_id);
        $this->assertEquals(2, $new->version);
        $this->assertSame('2026-11-01', $new->effective_from->toDateString());
        $this->assertSame('2026-10-31', $s['kpi']->fresh()->effective_until->toDateString());
        $this->assertSame('Recognized revenue', $s['kpi']->fresh()->definition);
        $this->post(route('portal.performance.kpi.revisions.review', $revision), ['decision' => 'approve', 'reason' => 'Again'])->assertConflict();
        $payload = $this->payload($s);
        $payload['period_start'] = '2026-11-01';
        $payload['period_end'] = '2026-11-30';
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $payload)->assertSessionHasErrors('kpi_id');
        $payload['kpi_id'] = $new->id;
        $this->post(route('portal.performance.targets.store'), $payload)->assertSessionHasNoErrors();
    }

    public function test_same_definition_can_have_different_personal_targets_and_department_scope_is_separate(): void
    {
        $s = $this->setupPlan();
        $other = User::factory()->create(['department' => 'client_relations', 'access_role' => 'staff', 'status' => 'active']);
        $plans = [];
        $results = [];
        foreach ([[$s['owner'], 'individual', 100, 50], [$other, 'individual', 200, 200], [$s['manager'], 'department', 500, 300]] as [$owner,$scope,$target,$actual]) {
            $plan = PerformanceTargetPlan::create(['kpi_id' => $s['kpi']->id, 'department' => 'client_relations', 'scope_type' => $scope, 'owner_id' => $owner->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => $target, 'weight' => 40, 'rationale' => 'Agreed', 'submitted_by' => $s['manager']->id, 'reviewer_id' => $s['reviewer']->id, 'approved_by' => $s['reviewer']->id, 'approved_at' => now(), 'scope_key' => $scope.'-'.$owner->id]);
            $result = PerformanceObservation::create(['target_plan_id' => $plan->id, 'kpi_id' => $s['kpi']->id, 'owner_id' => $owner->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => $target, 'actual' => $actual, 'source_reference' => 'Ledger', 'approved_at' => now(), 'approved_by' => $s['reviewer']->id, 'scope_key' => 'result-'.$plan->id]);
            $plans[] = $plan;
            $results[] = $result;
        }
        $service = app(StaffPerformanceScore::class);
        $start = Carbon::parse('2026-10-01');
        $end = Carbon::parse('2026-10-31');
        $this->assertEquals(50, $service->user($s['owner'], $start, $end)['kpi_rate']);
        $this->assertEquals(100, $service->user($other, $start, $end)['kpi_rate']);
        $this->assertNull($service->user($s['manager'], $start, $end)['kpi_rate']);
        $report = app(PerformanceHubReport::class)->build(collect(), collect($results)->each(fn ($r) => $r->setRelation('kpi', $s['kpi'])), collect(), '2026-10-01', '2026-10-31', collect([$s['kpi']]), collect($plans));
        $this->assertEquals(60, $report['departments']['client_relations']['kpi_score']);
        $this->assertEquals(300, $report['kpis'][0]['actual']);
        $this->assertEquals(500, $report['kpis'][0]['target']);
    }

    public function test_missing_planned_result_keeps_person_and_department_scores_incomplete(): void
    {
        $s = $this->setupPlan();
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $this->payload($s))->assertRedirect()->assertSessionHasNoErrors();
        $plan = PerformanceTargetPlan::firstOrFail();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.approve', $plan), ['reason' => 'Agreed'])->assertRedirect()->assertSessionHasNoErrors();
        $score = app(StaffPerformanceScore::class)->user($s['owner'], Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertNull($score['kpi_rate']);
        $this->assertSame(1, $score['kpi_expected']);
        $this->assertSame(0, $score['kpi_measured']);
        $report = app(PerformanceHubReport::class)->build(collect(), collect(), collect(), '2026-10-01', '2026-10-31', collect([$s['kpi']]), collect([$plan->fresh()]));
        $this->assertEquals(100, $report['kpis'][0]['target']);
        $this->assertNull($report['departments']['client_relations']['kpi_score']);
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $this->payload($s))->assertSessionHasErrors('period_start');
    }

    public function test_unapproved_target_can_be_returned_for_correction_but_approved_baseline_is_protected(): void
    {
        $s = $this->setupPlan();
        $payload = $this->payload($s);
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan = PerformanceTargetPlan::firstOrFail();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.withdraw', $plan), ['reason' => 'Target needs correction'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($plan->fresh()->withdrawn_at);
        $this->post(route('portal.performance.targets.approve', $plan), ['reason' => 'Stale review'])->assertStatus(409);
        $this->actingAs($s['manager'])->post(route('portal.performance.targets.store'), array_replace($payload, ['target' => 120]))->assertRedirect()->assertSessionHasNoErrors();
        $corrected = PerformanceTargetPlan::latest('id')->firstOrFail();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.targets.approve', $corrected), ['reason' => 'Corrected agreement'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('portal.performance.targets.withdraw', $corrected), ['reason' => 'Remove approved baseline'])->assertStatus(409);
        $this->assertEquals(120, $corrected->fresh()->target);
        $this->assertEquals(100, $plan->fresh()->target);
    }
}
