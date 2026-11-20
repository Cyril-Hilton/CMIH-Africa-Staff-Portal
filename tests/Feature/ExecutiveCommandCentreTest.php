<?php

namespace Tests\Feature;

use App\Models\CrmPipelineProject;
use App\Models\ProjectExecutionControl;
use App\Models\User;
use App\Services\ExecutiveCommandCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExecutiveCommandCentreTest extends TestCase
{
    use RefreshDatabase;

    private function executive(): User
    {
        return User::factory()->create(['department' => 'executive', 'access_role' => 'super_admin', 'status' => 'active']);
    }

    private function project(User $u): CrmPipelineProject
    {
        return CrmPipelineProject::create(['owner_id' => $u->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'client_name' => 'Real client', 'project_name' => 'Connected launch', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22']);
    }

    public function test_empty_projection_and_dashboard_render(): void
    {
        $u = $this->executive();
        $r = Request::create('/dashboard', 'GET', ['exec_date' => '2026-11-19']);
        $r->setUserResolver(fn () => $u);
        $data = app(ExecutiveCommandCentre::class)->build($r)['command'];
        $this->assertEquals(0, $data['summary']['projects']);
        $this->actingAs($u)->get(route('dashboard', ['exec_date' => '2026-11-19']))->assertOk();
    }

    public function test_controls_require_revision_and_evidence_and_preserve_history(): void
    {
        $u = $this->executive();
        $p = $this->project($u);
        $url = route('portal.executive.controls.save', $p);
        $this->actingAs($u)->post($url, ['section' => 'compliance', 'revision' => 0, 'reason' => 'Review source', 'data' => ['checks' => ['contract' => ['response' => 'yes']]]])->assertSessionHasErrors();
        $this->post($url, ['section' => 'schedule', 'revision' => 0, 'reason' => 'Confirmed execution dates', 'data' => ['starts_at' => '2026-11-20 10:00', 'ends_at' => '2026-11-21 18:00', 'staff_required' => 10, 'staff_confirmed' => 9]])->assertSessionHasNoErrors();
        $this->assertSame(1, ProjectExecutionControl::sole()->revision);
        $this->post($url, ['section' => 'schedule', 'revision' => 0, 'reason' => 'Stale edit', 'data' => ['staff_required' => 20]])->assertStatus(409);
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'project_control', 'action' => 'update_schedule']);
    }

    public function test_no_checks_or_excluding_every_check_does_not_award_full_credit(): void
    {
        $service = app(ExecutiveCommandCentre::class);
        $this->assertEquals(0, $service->compliance([])['score']);
        $checks = [];
        foreach (config('executive_tracker.checks') as $key => $label) {
            $checks[$key] = ['response' => 'not_required', 'reason' => 'Not applicable to this project'];
        }$this->assertFalse($service->compliance(['checks' => $checks])['ready']);
    }

    public function test_all_executive_tabs_render_connected_project_and_editors(): void
    {
        $u = $this->executive();
        $p = $this->project($u);
        foreach (array_keys(config('executive_tracker.tabs')) as $view) {
            $response = $this->actingAs($u)->get(route('dashboard', ['exec_view' => $view, 'exec_project' => $p->id, 'exec_date' => '2026-11-19']))->assertOk();
            $response->assertSee('data-executive-top-chart="'.$view.'"', false)->assertSee('View chart data');
            $this->assertSame(1, substr_count($response->getContent(), 'data-executive-top-chart='));
        }
    }

    private function data(User $u, array $params = []): array
    {
        $r = Request::create('/dashboard', 'GET', $params + ['exec_date' => '2026-11-19']);
        $r->setUserResolver(fn () => $u);

        return app(ExecutiveCommandCentre::class)->build($r)['command'];
    }

    public function test_snapshot_identity_staff_shortfall_and_deadline_evidence_are_not_inflated(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        $u = $this->executive();
        $p = $this->project($u);
        $old = $p->replicate();
        $old->week_start = '2026-11-09';
        $old->week_end = '2026-11-15';
        $old->save();
        ProjectExecutionControl::create(['crm_project_id' => $p->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'revision' => 1, 'data' => ['schedule' => ['starts_at' => '2026-11-20 09:00', 'ends_at' => '2026-11-21 18:00', 'staff_required' => 10, 'staff_confirmed' => 9], 'process' => ['proposal' => ['owner_id' => $u->id, 'due_at' => '2026-11-18 17:00', 'completed_at' => '2026-11-19 09:00', 'evidence' => 'Proposal email reference', 'status' => 'submitted']]]]);
        $d = $this->data($u);
        $this->assertSame(1, $d['summary']['projects']);
        $this->assertEquals(1, $d['summary']['staff_gaps']);
        $this->assertEquals(1, $d['summary']['late_completed']);
        $this->assertEquals(0, $d['summary']['overdue']);
        $this->assertSame(1, $d['activeProjects']->first()['process_complete']);
        $this->actingAs($u)->get(route('dashboard', ['exec_view' => 'projects', 'exec_project' => $p->id, 'exec_date' => '2026-11-19']))->assertOk()->assertSee('9 / 10');
    }

    public function test_resource_overlap_and_leave_clash_use_real_bookings_without_changing_stock(): void
    {
        $u = $this->executive();
        $person = User::factory()->create(['status' => 'active', 'department' => 'operations']);
        $p = $this->project($u);
        $other = $p->replicate();
        $other->project_name = 'Different launch';
        $other->save();
        foreach ([$p, $other] as $project) {
            ProjectExecutionControl::create(['crm_project_id' => $project->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'revision' => 1, 'data' => ['resources' => ['rows' => [['id' => 'resource-'.$project->id, 'description' => 'Exclusive driver', 'owner_id' => $u->id, 'staff_id' => $person->id, 'exclusive' => true, 'status' => 'confirmed', 'required' => 1, 'confirmed' => 1, 'start_at' => '2026-11-20 10:00', 'end_at' => '2026-11-20 18:00']]]]]);
        }
        \App\Models\LeaveApplication::create(['user_id' => $person->id, 'leave_type' => 'annual', 'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'days' => 1, 'reason' => 'Private medical family narrative', 'status' => 'approved']);
        $d = $this->data($u);
        $this->assertCount(1, $d['clashes']);
        $this->assertSame(2, $d['summary']['leave_clashes']);
        $this->assertDatabaseCount('asset_warehouse_requests', 0);
        foreach (['calendar', 'resources', 'accountability'] as $tab) {
            $this->actingAs($u)->get(route('dashboard', ['exec_view' => $tab, 'exec_date' => '2026-11-19']))->assertOk()->assertDontSee('Private medical family narrative');
        }
    }

    public function test_real_task_assignment_preserves_review_and_reminders_preview_then_deduplicate(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        $u = $this->executive();
        $p = $this->project($u);
        $person = User::factory()->create(['status' => 'active', 'department' => 'operations']);
        $this->actingAs($u)->post(route('portal.executive.controls.save', $p), ['section' => 'tasks', 'revision' => 0, 'reason' => 'Assign execution work', 'data' => ['rows' => [['description' => 'Deliver original task', 'owner_id' => $person->id, 'due_at' => '2026-11-22 17:00']]]])->assertSessionHasNoErrors();
        $task = \App\Models\Task::where('title', 'Deliver original task')->sole();
        $this->assertSame('open', $task->status);
        $this->assertFalse($task->isApprovedForPerformance());
        $this->assertSame($p->id, $task->custom_fields['executive_control_project_id']);
        $control = ProjectExecutionControl::sole();
        $this->assertSame($task->id, $control->data['tasks']['rows'][0]['task_id']);
        $this->post(route('portal.executive.controls.save', $p), ['section' => 'tasks', 'revision' => 1, 'reason' => 'Keep original assignment', 'data' => $control->data['tasks']])->assertSessionHasNoErrors();
        $this->assertEquals(1, \App\Models\Task::where('title', 'Deliver original task')->count());
        $reminders = app(\App\Services\ExecutiveReminders::class);
        $this->assertSame(0, $reminders->run());
        $this->assertDatabaseCount('executive_reminder_logs', 0);
        $this->assertCount(1, $reminders->preview($u));
        config(['executive_tracker.reminders.mode' => 'live']);
        $this->assertSame(1, $reminders->run());
        $this->assertSame(0, $reminders->run());
        $this->assertDatabaseCount('executive_reminder_logs', 1);
        $this->actingAs($u)->get(route('dashboard', ['exec_view' => 'accountability', 'exec_date' => '2026-11-19']))->assertOk()->assertSee('Deliver original task');
    }

    public function test_regular_staff_cannot_change_executive_controls(): void
    {
        $u = $this->executive();
        $p = $this->project($u);
        $staff = User::factory()->create(['department' => 'operations', 'access_role' => 'staff', 'status' => 'active']);
        $this->actingAs($staff)->post(route('portal.executive.controls.save', $p), ['section' => 'schedule', 'revision' => 0, 'reason' => 'No executive authority', 'data' => ['staff_required' => 1]])->assertForbidden();
    }

    public function test_standalone_activation_has_controls_and_consistent_deadline_counts_without_creating_a_crm_project(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        $u = $this->executive();
        $owner = User::factory()->create(['department' => 'operations_projects', 'status' => 'active']);
        $a = \App\Models\PerformanceRegister::create(['department' => 'operations_projects', 'kind' => 'activation', 'reference' => 'NATIVE-EXEC-1', 'title' => 'Independent execution', 'client' => 'Direct client', 'owner_id' => $owner->id, 'submitted_by' => $owner->id, 'approved_by' => $u->id, 'approved_at' => now(), 'status' => 'in_progress', 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'data' => ['starts_on' => '2026-11-17', 'ends_on' => '2026-11-18']]);
        $d = $this->data($u);
        $this->assertSame(1, $d['summary']['projects']);
        $this->assertSame(1, $d['summary']['due']);
        $this->assertSame(1, $d['summary']['overdue']);
        $this->assertSame(1, $d['summary']['staff_setup']);
        $this->assertSame(1, $d['summary']['compliance_pending']);
        $this->assertSame(1, $d['events']->where('kind', 'activation')->count());
        $this->actingAs($u)->get(route('dashboard', ['exec_view' => 'projects', 'exec_activation' => $a->id, 'exec_date' => '2026-11-19']))->assertOk()->assertSee('Independent execution')->assertSee('Compliance checklist &amp; evidence', false);
        $this->post(route('portal.executive.activations.save', $a), ['section' => 'schedule', 'revision' => 0, 'reason' => 'Confirm direct operational ownership', 'data' => ['starts_at' => '2026-11-20 10:00', 'ends_at' => '2026-11-21 18:00', 'staff_required' => 10, 'staff_confirmed' => 9, 'operations_owner_id' => $owner->id]])->assertSessionHasNoErrors();
        $control = ProjectExecutionControl::sole();
        $this->assertNull($control->crm_project_id);
        $this->assertSame($a->id, $control->operations_activation_id);
        $this->assertDatabaseCount('crm_pipeline_projects', 0);
        $this->actingAs($owner)->get(route('portal.executive.controls.show', $control))->assertOk()->assertSee('Independent execution');
        $other = User::factory()->create(['department' => 'operations_projects', 'status' => 'active', 'access_role' => 'staff']);
        $this->actingAs($other)->get(route('portal.executive.controls.show', $control))->assertForbidden();
        $d = $this->data($u, ['exec_activation' => $a->id]);
        $this->assertSame(1, $d['summary']['staff_gaps']);
        $this->assertSame(0, $d['summary']['overdue']);
    }
}
