<?php

namespace Tests\Feature;

use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\User;
use App\Services\RevenueDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueReviewTest extends TestCase
{
    use RefreshDatabase;

    private function staff($department = 'client_relations', $role = 'staff')
    {
        return User::factory()->create(['department' => $department, 'access_role' => $role, 'status' => 'active']);
    }

    private function project($owner, $extra = [])
    {
        return CrmPipelineProject::create($extra + ['client_name' => 'Dove', 'brand' => 'Dove Care', 'project_name' => 'Campus Activation', 'week_start' => today()->startOfWeek(), 'stage' => 'project_completed', 'source_period_confirmed' => true, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'actual_amount' => 100, 'target_amount' => 100]);
    }

    private function act($who, $row, $action, $extra = [])
    {
        return $this->actingAs($who)->from(route('dashboard', ['weekly_department' => $who->department, 'revenue_view' => 1]))->post(route('portal.crm.revenue.review', ['type' => $row instanceof CrmPipelineProject ? 'project' : 'target', 'id' => $row->id]), $extra + ['revision' => $row->fresh()->revision, 'action' => $action, 'reason' => 'Checked against the client revenue record']);
    }

    public function test_revenue_waits_for_two_independent_confirmations_and_correction_preserves_original(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $row = $this->project($cr);
        $this->assertNull($row->confirmedRevenue());
        $this->assertEquals($cr->id, $row->revenue_submitted_by);
        $this->actingAs($fin)->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonPath('totals.actual_amount.value', null)->assertJsonPath('clients.0.projects.0.reported_revenue', '100.00')->assertJsonPath('clients.0.projects.0.revenue_status', 'Awaiting Finance Confirmation');
        $this->act($fin, $row, 'correct', ['amount' => 90])->assertRedirect();
        $row->refresh();
        $this->assertEquals(100, $row->actual_amount);
        $this->assertEquals(90, $row->revenue_proposed_amount);
        $this->assertNull($row->confirmedRevenue());
        $this->act($cr, $row, 'accept')->assertRedirect();
        $row->refresh();
        $this->assertSame(90.0, $row->confirmedRevenue());
        $this->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonPath('totals.actual_amount.value', 90)->assertJsonPath('summary.achievement', 90);
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'crm_revenue_project', 'entity_id' => $row->id, 'action' => 'accept']);
        $event = \App\Models\PerformanceEvent::where('entity_type', 'crm_revenue_project')->where('action', 'accept')->first();
        $this->assertEquals(100, $event->before['actual_amount']);
        $this->assertEquals(90, $event->after['actual_amount']);
    }

    public function test_legacy_values_are_not_auto_approved_and_finance_first_requires_cr_acceptance(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $row = $this->project($cr);
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $this->assertNull($row->fresh()->confirmedRevenue());
        $this->assertSame('awaiting_cr', $row->fresh()->revenue_state);
        $this->act($cr, $row, 'accept')->assertRedirect();
        $this->assertSame(100.0, $row->fresh()->confirmedRevenue());
    }

    public function test_unauthorised_staff_self_approval_stale_replay_and_suspended_reviewers_are_blocked(): void
    {
        $cr = $this->staff();
        $other = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $row = $this->project($cr);
        $this->act($cr, $row, 'confirm')->assertForbidden();
        $this->act($other, $row, 'accept')->assertForbidden();
        $stale = $row->revision;
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $this->actingAs($fin)->post(route('portal.crm.revenue.review', ['type' => 'project', 'id' => $row->id]), ['revision' => $stale, 'action' => 'confirm', 'reason' => 'Attempting stale confirmation'])->assertStatus(409);
        $row->actual_amount = 110;
        $row->save();
        $this->assertNull($row->confirmedRevenue());
        $this->assertNull($row->revenue_finance_by);
        $fin->update(['status' => 'suspended']);
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $this->assertNull($row->fresh()->confirmedRevenue());
    }

    public function test_resubmission_resets_approval_even_if_an_old_amount_is_restored(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $row = $this->project($cr);
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $this->actingAs($cr);
        $row->refresh();
        $row->actual_amount = 80;
        $row->save();
        $row->actual_amount = 100;
        $row->save();
        $this->assertNull($row->confirmedRevenue());
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $row->refresh();
        $row->week_start = today()->subWeek()->startOfWeek();
        $row->save();
        $this->assertNull($row->confirmedRevenue());
    }

    public function test_rejection_returns_to_finance_and_does_not_replace_cr_amount(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $row = $this->project($cr);
        $this->act($fin, $row, 'correct', ['amount' => 90])->assertRedirect();
        $this->act($cr, $row, 'reject')->assertRedirect();
        $row->refresh();
        $this->assertEquals(100, $row->actual_amount);
        $this->assertNull($row->confirmedRevenue());
        $this->assertSame('awaiting_finance', $row->revenue_state);
        $this->act($fin, $row, 'confirm')->assertRedirect();
        $this->assertSame(100.0, $row->fresh()->confirmedRevenue());
    }

    public function test_period_target_results_are_reviewed_but_not_double_counted_in_revenue(): void
    {
        $cr = $this->staff('client_relations', 'manager');
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $project = $this->project($cr);
        $target = CrmAccountTarget::create(['owner_id' => $cr->id, 'client_name' => 'Dove', 'reference' => 'dove-oct', 'frequency' => 'monthly', 'period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(), 'target_amount' => 100, 'actual_amount' => 100]);
        $this->act($fin, $project, 'confirm')->assertRedirect();
        $this->act($fin, $target, 'confirm')->assertRedirect();
        $data = app(RevenueDashboard::class)->build(['period' => 'monthly']);
        $this->assertSame(100.0, $data['confirmed']);
        $this->assertSame(1, $data['targets_count']);
        $this->actingAs($cr)->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonPath('targets.0.actual', 100)->assertJsonPath('targets.0.achievement', 100);
    }

    public function test_year_month_and_week_use_latest_snapshot_and_filters_preserve_owner_and_brand(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $old = $this->project($cr, ['week_start' => today()->subWeek()->startOfWeek(), 'actual_amount' => 100]);
        $new = $this->project($cr, ['actual_amount' => 90]);
        $this->act($fin, $old, 'confirm')->assertRedirect();
        $this->act($fin, $new, 'confirm')->assertRedirect();
        foreach (['weekly', 'monthly', 'yearly'] as $period) {
            $data = app(RevenueDashboard::class)->build(['period' => $period, 'q' => 'Dove Care']);
            $this->assertSame(90.0, $data['confirmed']);
            $this->assertSame($cr->name, $data['accounts'][0]['owner']);
        }
        foreach (['finance', 'client_relations'] as $dept) {
            $this->actingAs($fin)->get(route('dashboard', ['weekly_department' => $dept, 'revenue_view' => 1]))->assertOk()->assertSee('Revenue, Confirmed Together')->assertSee('GH₵ 90.00')->assertSee('Dove Care');
        }
    }

    public function test_executive_in_finance_cannot_confirm_own_submission(): void
    {
        $owner=$this->staff();$executive=$this->staff('finance','super_admin');$this->actingAs($executive);$row=$this->project($owner);
        $this->assertEquals($executive->id,$row->revenue_submitted_by);
        $this->act($executive,$row,'confirm')->assertForbidden();$this->assertNull($row->fresh()->confirmedRevenue());
    }

    public function test_zero_revenue_is_a_valid_confirmed_value_and_unconfirmed_dates_cannot_be_reviewed(): void
    {
        $cr = $this->staff();
        $fin = $this->staff('finance');
        $this->actingAs($cr);
        $row = $this->project($cr, ['actual_amount' => 0]);
        $this->act($fin,$row,'confirm')->assertRedirect();
        $this->assertSame(0.0,$row->fresh()->confirmedRevenue());
        $row = $this->project($cr,['project_name' => 'Undated', 'source_period_confirmed' => false]);
        $this->act($fin,$row,'confirm')->assertStatus(422);
    }
}
