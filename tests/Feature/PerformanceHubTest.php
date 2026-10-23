<?php

namespace Tests\Feature;

use App\Models\PerformanceEvent;
use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceObservation;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceAutomation;
use App\Services\PerformanceHubReport;
use App\Services\PerformanceWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerformanceHubTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $department = 'finance'): array
    {
        $manager = User::factory()->create(['access_role' => 'manager', 'department' => $department, 'status' => 'active']);
        $owner = User::factory()->create(['access_role' => 'staff', 'department' => $department, 'status' => 'active']);
        $reviewer = User::factory()->create(['access_role' => 'manager', 'department' => $department, 'status' => 'active']);
        $objective = PerformanceObjective::create(['department' => $department, 'title' => 'Controlled delivery', 'outcome' => 'Measured result', 'owner_id' => $manager->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $kpi = PerformanceKpi::create(['department' => $department, 'objective_id' => $objective->id, 'name' => 'Approved output', 'definition' => 'Verified outputs delivered', 'formula' => 'Count verified outputs', 'unit' => 'jobs', 'direction' => 'higher', 'aggregation' => 'sum', 'frequency' => 'monthly', 'source' => 'Delivery register', 'owner_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 2, 'required_action' => 'Resolve shortfall', 'dimension' => 'target_achievement', 'weight' => 1, 'evidence_required' => true]);
        $item = WeeklyConsolidatedItem::create(['department' => $department, 'performance_kpi_id' => $kpi->id, 'workflow_state' => 'assigned', 'week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'campaign_name' => 'Delivery for client', 'deliverables' => 'Provide output and proof', 'lead_staff_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'actual_value' => 1, 'status' => 'Planned', 'created_by' => $manager->id, 'updated_by' => $manager->id]);

        return compact('manager', 'owner', 'reviewer', 'objective', 'kpi', 'item');
    }

    public function test_team_filter_scopes_results_and_keeps_other_teams_selectable(): void
    {
        $s = $this->scenario();
        $otherLead = $s['reviewer'];
        $s['owner']->update(['line_manager_id' => $s['manager']->id]);
        $other = User::factory()->create(['access_role' => 'staff', 'department' => 'finance', 'status' => 'active', 'line_manager_id' => $otherLead->id]);
        foreach ([$s['owner'], $other] as $person) {
            PerformanceObservation::create(['kpi_id' => $s['kpi']->id, 'owner_id' => $person->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => 10, 'actual' => 5, 'source_reference' => 'Filter QA', 'scope_key' => 'observation-'.$person->id]);
            \App\Models\PerformanceTargetPlan::create(['kpi_id' => $s['kpi']->id, 'owner_id' => $person->id, 'reviewer_id' => $s['manager']->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => 10, 'scope_key' => 'plan-'.$person->id, 'department' => 'finance', 'scope_type' => 'individual', 'weight' => 1, 'rationale' => 'Filter QA', 'submitted_by' => $s['manager']->id]);
            \App\Models\PerformanceRegister::create(['department' => 'finance', 'kind' => 'invoice', 'reference' => 'FILTER-'.$person->id, 'title' => 'Filter QA', 'owner_id' => $person->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'submitted', 'data' => []]);
        }
        $response = $this->actingAs($s['manager'])->get(route('portal.performance.index', ['team_lead_id' => $s['manager']->id, 'from' => '2026-10-01', 'to' => '2026-10-31']));
        $response->assertOk();
        foreach (['observations', 'targetPlans', 'registers'] as $key) {
            $this->assertSame([$s['owner']->id], $response->viewData($key)->pluck('owner_id')->all());
        }
        $this->assertContains($otherLead->id, $response->viewData('teamLeads')->pluck('id')->all());
        $this->assertNotContains($other->id, $response->viewData('staff')->pluck('id')->all());
    }

    public function test_deadline_filter_is_distinct_from_reporting_period_and_validates_range(): void
    {
        $s = $this->scenario();
        $other = $s['item']->replicate();
        $other->week_end = '2026-10-30';
        $other->save();
        $params = ['from' => '2026-10-01', 'to' => '2026-10-31', 'due_from' => '2026-10-24', 'due_to' => '2026-10-26'];
        $response = $this->actingAs($s['manager'])->get(route('portal.performance.index', $params))->assertOk();
        $this->assertSame([$s['item']->id], $response->viewData('items')->pluck('id')->all());
        $params['due_to'] = '2026-10-20';
        $this->getJson(route('portal.performance.index', $params))->assertUnprocessable()->assertJsonValidationErrors('due_to');
    }

    public function test_all_views_render_for_every_function(): void
    {
        foreach (array_keys(config('performance.departments')) as $department) {
            $s = $this->scenario($department === 'admin_drivers' ? 'hr_admin' : $department);
            foreach (['summary', 'commitments', 'kpis', 'exceptions', 'trends', 'dependencies', 'meeting', 'dictionary', 'registers'] as $view) {
                $this->actingAs($s['manager'])->get(route('portal.performance.index', ['department' => $department, 'view' => $view, 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
            }
        }
    }

    public function test_owner_review_verification_lifecycle_and_stale_writes(): void
    {
        $s = $this->scenario();
        $workflow = app(PerformanceWorkflow::class);
        $i = $s['item'];
        foreach (['accept', 'execute'] as $action) {
            $i = $workflow->transition($i, $s['owner'], $action, $i->revision, 'Owner action');
        }
        $this->actingAs($s['owner'])->post(route('portal.performance.transition', $i), ['action' => 'submit', 'revision' => $i->revision, 'reason' => 'Ready'])->assertSessionHasErrors('evidence');
        $i->update(['evidence_url' => 'https://example.com/proof']);
        $i = $workflow->transition($i, $s['owner'], 'submit', $i->revision, 'Evidence ready');
        $this->actingAs($s['owner'])->post(route('portal.performance.transition', $i), ['action' => 'complete', 'revision' => $i->revision, 'reason' => 'Self approval'])->assertForbidden();
        $i = $workflow->transition($i, $s['reviewer'], 'complete', $i->revision, 'Reviewed');
        $i = $workflow->transition($i, $s['reviewer'], 'verify', $i->revision, 'Quality checked', 95);
        $i = $workflow->transition($i, $s['reviewer'], 'close', $i->revision, 'Accepted result');
        $this->assertSame('closed', $i->workflow_state);
        $this->assertNotNull($i->verified_at);
        $this->assertSame(95.0, $i->quality_score);
        $this->actingAs($s['manager'])->post(route('portal.performance.update', $i), ['revision' => $i->revision, 'progress_percent' => 50, 'reason' => 'Edit closed'])->assertUnprocessable();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.transition', $i), ['action' => 'reopen', 'revision' => 1, 'reason' => 'Stale'])->assertStatus(409);
        $i = $workflow->transition($i, $s['reviewer'], 'reopen', $i->revision, 'Rework required');
        $this->assertNull($i->verified_at);
        $this->assertGreaterThanOrEqual(7, PerformanceEvent::where('entity_type', 'commitment')->where('entity_id', $i->id)->count());
    }

    public function test_private_scope_and_legacy_bypass_are_blocked(): void
    {
        $s = $this->scenario();
        $outsider = User::factory()->create(['access_role' => 'staff', 'department' => 'creatives', 'status' => 'active']);
        $this->actingAs($outsider)->get(route('portal.performance.index', ['department' => 'finance']))->assertForbidden();
        $this->get(route('portal.performance.history', $s['item']))->assertForbidden();
        $this->actingAs($s['manager'])->patch(route('portal.dashboard.weekly-consolidated.update', $s['item']), ['status' => 'Done'])->assertUnprocessable();
        $this->delete(route('portal.dashboard.weekly-consolidated.destroy', $s['item']))->assertUnprocessable();
    }

    public function test_dependency_cycles_are_rejected_and_upstream_blocks_execution(): void
    {
        $s = $this->scenario();
        $other = $s['item']->replicate();
        $other->save();
        $this->actingAs($s['manager'])->post(route('portal.performance.dependency'), ['upstream_id' => $s['item']->id, 'downstream_id' => $other->id, 'impact' => 'Await upstream'])->assertSessionHasNoErrors();
        $this->post(route('portal.performance.dependency'), ['upstream_id' => $other->id, 'downstream_id' => $s['item']->id, 'impact' => 'Cycle'])->assertSessionHasErrors('upstream_id');
        $other = app(PerformanceWorkflow::class)->transition($other, $s['owner'], 'accept', $other->revision, 'Accept');
        $this->actingAs($s['owner'])->post(route('portal.performance.transition', $other), ['action' => 'execute', 'revision' => $other->revision, 'reason' => 'Start'])->assertSessionHasErrors('action');
    }

    public function test_weighted_rates_and_balance_snapshots_are_not_summed(): void
    {
        $s = $this->scenario();
        $base = ['kpi_id' => $s['kpi']->id, 'owner_id' => $s['owner']->id, 'target' => 80, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'source_reference' => 'Test'];
        $a = new PerformanceObservation($base + ['actual' => 50, 'numerator' => 1, 'denominator' => 2]);
        $b = new PerformanceObservation($base + ['actual' => 90, 'numerator' => 9, 'denominator' => 10]);
        $report = app(PerformanceHubReport::class);
        $this->assertSame(83.3333, $report->aggregate(collect([$a, $b]), 'ratio'));
        $b->period_end = '2026-11-30';
        $this->assertEquals(90, $report->aggregate(collect([$a, $b]), 'balance'));
    }

    public function test_private_file_versions_and_notification_deduplication(): void
    {
        Storage::fake('local');
        $s = $this->scenario();
        $this->actingAs($s['owner'])->post(route('portal.performance.evidence', $s['item']), ['file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('performance_evidence', 1);
        $this->travelTo(Carbon::parse('2026-10-29 10:00:00'));
        $auto = app(PerformanceAutomation::class);
        $auto->sweep();
        $auto->deliver();
        $before = \App\Models\Notification::count();
        $auto->sweep();
        $auto->deliver();
        $this->assertSame($before, \App\Models\Notification::count());
        $this->assertSame(2, (int) $s['item']->fresh()->escalation_level);
    }

    public function test_submitters_cannot_approve_results_recorded_on_behalf_of_another_owner(): void
    {
        $s = $this->scenario();
        $observation = PerformanceObservation::create(['kpi_id' => $s['kpi']->id, 'owner_id' => $s['owner']->id, 'submitted_by' => $s['reviewer']->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => 10, 'actual' => 8, 'source_reference' => 'Source', 'scope_key' => 'independent-submitter']);
        $this->actingAs($s['reviewer'])->post(route('portal.performance.approveObservation', $observation), ['reason' => 'Self-submitted result'])->assertForbidden();
        $this->assertNull($observation->fresh()->approved_at);
        $register = \App\Models\PerformanceRegister::create(['department' => 'finance', 'kind' => 'invoice', 'reference' => 'INV-SELF', 'title' => 'Invoice', 'owner_id' => $s['owner']->id, 'submitted_by' => $s['manager']->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'submitted', 'data' => ['value' => 100], 'evidence_url' => 'https://example.com/invoice']);
        $this->actingAs($s['manager'])->post(route('portal.performance.approveRegister', $register), ['reason' => 'Self-submitted source'])->assertForbidden();
        $this->actingAs($s['reviewer'])->post(route('portal.performance.approveRegister', $register), ['reason' => 'Independent source check'])->assertSessionHasNoErrors();
        $this->assertNotNull($register->fresh()->approved_at);
    }
}
