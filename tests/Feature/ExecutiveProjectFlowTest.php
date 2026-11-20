<?php

namespace Tests\Feature;

use App\Models\CreativeRequest;
use App\Models\CrmPipelineProject;
use App\Models\PerformanceRegister;
use App\Models\ProjectExecutionControl;
use App\Models\Task;
use App\Models\User;
use App\Services\ExecutiveCommandCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutiveProjectFlowTest extends TestCase
{
    use RefreshDatabase;

    private function executive(): User
    {
        return User::factory()->create(['department' => 'executive', 'access_role' => 'super_admin', 'status' => 'active']);
    }

    private function data(User $u): array
    {
        $r = Request::create('/dashboard', 'GET', ['exec_view' => 'projects', 'exec_period' => 'year', 'exec_date' => '2026-11-20']);
        $r->setUserResolver(fn () => $u);

        return app(ExecutiveCommandCentre::class)->build($r)['command'];
    }

    public function test_connected_departments_show_native_substages_and_daily_tasks_stay_out(): void
    {
        $u = $this->executive();
        $p = CrmPipelineProject::create(['owner_id' => $u->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'project_name' => 'Connected launch', 'client_name' => 'Original client', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'team_workflow' => ['operations_required' => false]]);
        $brief = CreativeRequest::create(['uuid' => (string) Str::uuid(), 'reference' => 'CR-FLOW', 'title' => 'Linked design', 'crm_project_id' => $p->id, 'requester_id' => $u->id, 'assignee_id' => $u->id, 'reviewer_id' => $u->id, 'stage' => 'in_progress', 'visibility' => 'shared', 'received_at' => '2026-11-17 10:00', 'planned_start' => '2026-11-18 10:00', 'internal_eta' => '2026-11-25 18:00']);
        $daily = Task::create(['title' => 'IRM daily task', 'assigned_to' => $u->id, 'assigned_by' => $u->id, 'department' => 'creatives', 'status' => 'Open', 'due_on' => '2026-11-20']);
        $d = $this->data($u);
        $this->assertFalse($d['events']->contains('key', 'task:'.$daily->id));
        $flow = $d['projectRows'][$p->id]['flow'];
        $this->assertCount(6, $flow['sections']);
        $this->assertSame('current', $flow['sections']['creative']['state']);
        $this->assertSame('skipped', $flow['sections']['execution']['state']);
        $this->assertSame('current', collect($flow['sections']['creative']['records'][0]['steps'])->firstWhere('label', 'In progress')['state']);
        $brief->update(['stage' => 'in_review', 'first_submitted_at' => now(), 'submitted_at' => now()]);
        $flow = $this->data($u)['projectRows'][$p->id]['flow'];
        $this->assertSame('current', $flow['sections']['approval']['state']);
        $brief->update(['stage' => 'completed', 'completed_at' => now(), 'delivered_at' => now()]);
        $flow = $this->data($u)['projectRows'][$p->id]['flow'];
        $this->assertSame('done', $flow['sections']['approval']['state']);
        $this->actingAs($u)->get(route('dashboard', ['exec_view' => 'projects', 'exec_period' => 'year', 'exec_date' => '2026-11-20']))->assertOk()->assertSee('Live project journey')->assertSee('data-flow-refresh', false)->assertSee('Linked design')->assertSee('HOD delivery approval', false);
    }

    public function test_only_explicit_project_assignments_can_add_task_deadlines(): void
    {
        $u = $this->executive();
        $p = CrmPipelineProject::create(['owner_id' => $u->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'project_name' => 'Project assignment', 'client_name' => 'Client', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16']);
        $t = Task::create(['title' => 'Weekly execution assignment', 'assigned_to' => $u->id, 'assigned_by' => $u->id, 'department' => 'operations_projects', 'status' => 'Open', 'due_on' => '2026-11-20', 'custom_fields' => ['executive_control_project_id' => $p->id]]);
        ProjectExecutionControl::create(['crm_project_id' => $p->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'revision' => 1, 'data' => ['tasks' => ['rows' => [['task_id' => $t->id]]]]]);
        $this->assertTrue($this->data($u)['events']->contains('key', 'task:'.$t->id));
        $return = '/dashboard?exec_view=calendar&exec_date=2026-11-20#executive-command';
        $this->actingAs($u)->withHeader('X-Test-Enforce-ClockIn', '1')->get(route('portal.tasks.show', ['task' => $t, 'return_to' => $return]))->assertOk()->assertSee('Weekly execution assignment')->assertSee('Back to activity tracker');
    }

    public function test_traffic_can_open_approved_original_without_edit_or_private_cost_fields(): void
    {
        $u = User::factory()->create(['department' => 'executive', 'access_role' => 'staff', 'status' => 'active', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        $r = PerformanceRegister::create(['department' => 'operations_projects', 'kind' => 'activation', 'reference' => 'OPS-FLOW', 'title' => 'Published field activation', 'owner_id' => $u->id, 'submitted_by' => $u->id, 'approved_by' => $u->id, 'approved_at' => now(), 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'status' => 'in_progress', 'data' => ['location' => 'Accra', 'cost' => 9876543, 'budget' => 9876543, 'execution_controls' => ['private' => 'not for raw display']]]);
        $this->actingAs($u)->get(route('portal.executive.registers.show', $r))->assertOk()->assertSee('Accra')->assertDontSee('9876543')->assertDontSee('Open Operations entry')->assertSee('Back to executive tracker');
        $r->update(['approved_at' => null]);
        $this->get(route('portal.executive.registers.show',$r))->assertForbidden();
    }
}
