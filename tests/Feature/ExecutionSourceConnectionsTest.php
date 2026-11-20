<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\CrmPipelineProject;
use App\Models\LeaveApplication;
use App\Models\OperationsReportSubmission;
use App\Models\PassOutRequest;
use App\Models\PerformanceRegister;
use App\Models\ProjectExecutionControl;
use App\Models\Task;
use App\Models\User;
use App\Services\ExecutiveCommandCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExecutionSourceConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
    }

    private function person(string $department = 'operations_projects', string $role = 'staff'): User
    {
        return User::factory()->create(['department' => $department, 'access_role' => $role, 'status' => 'active']);
    }

    private function projection(User $user, array $params = []): array
    {
        $r = Request::create('/dashboard', 'GET', $params + ['exec_date' => '2026-11-19', 'exec_period' => 'week']);
        $r->setUserResolver(fn () => $user);

        return app(ExecutiveCommandCentre::class)->build($r)['command'];
    }

    public function test_operations_entry_review_warehouse_and_tracker_share_one_publication(): void
    {
        $staff = $this->person();
        $manager = $this->person('operations_projects', 'manager');
        $crew = $this->person();
        $executive = $this->person('executive', 'super_admin');
        $asset = Asset::create(['name' => 'Original warehouse stand', 'type' => 'Equipment', 'status' => 'available', 'is_warehouse_tracked' => true, 'warehouse_quantity' => 5]);
        LeaveApplication::create(['user_id' => $crew->id, 'leave_type' => 'annual', 'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'days' => 1, 'reason' => 'PRIVATE REASON FOR ABSENCE', 'status' => 'approved']);
        $payload = ['kind' => 'activation', 'module_entry' => 1, 'title' => 'Direct activation with one source', 'client' => 'Direct client', 'week' => '2026-11-16', 'status' => 'in_progress', 'warehouse_assets_present' => 1, 'warehouse_assets' => [['selected' => 1, 'asset_id' => $asset->id, 'quantity' => 2]], 'execution_revision' => 0, 'execution_sections' => ['schedule', 'resources', 'assets', 'compliance', 'tasks'], 'execution' => [
            'schedule' => ['starts_at' => '2026-11-20 09:00', 'ends_at' => '2026-11-21 18:00', 'venue' => 'Original venue', 'city' => 'Accra', 'country' => 'Ghana', 'staff_required' => 10, 'staff_confirmed' => 9],
            'resources' => ['rows' => [['description' => 'Assigned crew', 'owner_id' => $staff->id, 'staff_id' => $crew->id, 'required' => 1, 'confirmed' => 1, 'start_at' => '2026-11-20 10:00', 'end_at' => '2026-11-20 12:00', 'status' => 'confirmed', 'exclusive' => 1]]],
            'assets' => ['rows' => [['description' => 'Hired marquee', 'owner_id' => $staff->id, 'required' => 2, 'confirmed' => 1, 'due_at' => '2026-11-20 09:00', 'status' => 'sourcing', 'source' => 'External vendor']]],
            'compliance' => ['owner_id' => $staff->id, 'due_at' => '2026-11-20 08:00', 'checks' => ['contract' => ['response' => 'yes', 'evidence' => 'Signed contract reference']]],
            'tasks' => ['rows' => [['description' => 'Install original stand', 'owner_id' => $staff->id, 'due_at' => '2026-11-20 12:00']]],
        ]];
        $this->actingAs($staff)->get(route('portal.operations', ['ops_add' => 'activation']))->assertOk()->assertSee('name="execution[schedule][staff_required]"', false)->assertSee('name="execution[compliance][checks][contract][response]"', false)->assertDontSee('name="data[planned_assets]"', false)->assertDontSee('name="data[starts_on]"', false);
        $this->post(route('portal.operations.reporting.save'), $payload)->assertSessionHasNoErrors();
        $submission = OperationsReportSubmission::sole();
        $this->assertDatabaseCount('performance_registers', 0);
        $this->assertDatabaseCount('project_execution_controls', 0);
        $this->assertDatabaseCount('asset_warehouse_requests', 0);
        $this->assertSame(0, Task::where('title', 'Install original stand')->count());
        $this->actingAs($manager)->post(route('portal.operations.reporting.review', $submission), ['decision' => 'approve', 'revision' => 1])->assertSessionHasNoErrors();
        $record = PerformanceRegister::sole();
        $control = ProjectExecutionControl::sole();
        $this->assertEquals($record->id, $control->operations_activation_id);
        $this->assertSame($staff->id, $control->data['_source_entry']['entered_by']);
        $this->assertSame('2026-11-20', $record->data['starts_on']);
        $this->assertEquals(9, $control->data['schedule']['staff_confirmed']);
        $this->assertEquals($staff->id, $control->data['schedule']['operations_owner_id']);
        $this->assertSame(5, $asset->fresh()->warehouse_quantity);
        $this->assertDatabaseCount('assets', 1);
        $this->assertEquals($staff->id, AssetWarehouseRequest::sole()->custodian_id);
        $this->assertEquals(2, AssetWarehouseRequest::sole()->requested_quantity);
        $this->assertSame(1, Task::where('title', 'Install original stand')->count());
        $source = app(\App\Services\DepartmentSourcePerformance::class)->build('operations_projects', '2026-11-16', '2026-11-22');
        $staffing = collect($source['metrics'])->firstWhere('name', 'Activation staffing coverage');
        $this->assertEquals(90, $staffing['achievement']);
        $this->assertEquals(10, $staffing['target']);
        $this->assertEquals(9, $staffing['actual']);
        $d = $this->projection($executive);
        $this->assertSame(1, $d['summary']['staff_gaps']);
        $this->assertSame(1, $d['summary']['leave_clashes']);
        $this->assertCount(1, $d['assetRows']);
        $this->assertCount(1, $d['warehousing']);
        $this->assertLessThan(100, $d['activeProjects']->first()['check']['score']);
        $this->actingAs($executive)->get(route('dashboard', ['exec_view' => 'calendar', 'exec_date' => '2026-11-19']))->assertOk()->assertSee('Approved leave')->assertDontSee('PRIVATE REASON FOR ABSENCE');
        $this->actingAs($staff)->get(route('portal.executive.controls.show', $control))->assertOk()->assertSee('Original venue');
        $this->get(route('portal.operations', ['ops_edit' => $record->id]))->assertOk()->assertSee('name="execution_revision" value="1"', false);
    }

    public function test_approved_absence_is_context_and_afternoon_passout_does_not_block_the_morning(): void
    {
        $person = $this->person();
        $executive = $this->person('executive', 'super_admin');
        LeaveApplication::create(['user_id' => $person->id, 'leave_type' => 'annual', 'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'days' => 1, 'reason' => 'PRIVATE MEDICAL DATA', 'status' => 'approved']);
        $coverageTask = Task::create(['title' => 'Deadline needs coverage', 'assigned_to' => $person->id, 'assigned_by' => $executive->id, 'department' => 'operations_projects', 'status' => 'open', 'due_on' => '2026-11-18']);
        $coverageProject = CrmPipelineProject::create(['owner_id' => $person->id, 'created_by' => $person->id, 'updated_by' => $person->id, 'client_name' => 'Client', 'project_name' => 'Coverage project', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22']);
        ProjectExecutionControl::create(['crm_project_id' => $coverageProject->id, 'created_by' => $executive->id, 'updated_by' => $executive->id, 'data' => ['tasks' => ['rows' => [['task_id' => $coverageTask->id]]]], 'revision' => 1]);
        $d = $this->projection($executive);
        $task = $d['events']->firstWhere('title', 'Deadline needs coverage');
        $this->assertTrue($task['leave_deadline']);
        $this->assertSame(0, $d['summary']['overdue']);
        $this->assertSame(1, $d['summary']['leave_review']);
        $this->assertCount(0, app(\App\Services\ExecutiveReminders::class)->preview($executive));
        PassOutRequest::create(['user_id' => $person->id, 'supervisor_id' => $executive->id, 'hod_id' => $executive->id, 'request_date' => '2026-11-20', 'departure_time' => '13:00', 'reason' => 'PRIVATE HALF DAY REASON', 'status' => 'approved', 'history' => []]);
        $windows = app(\App\Services\ApprovedAvailability::class)->windows(today()->startOfWeek(), today()->endOfWeek());
        $pass = $windows->first(fn ($w) => str_starts_with($w['key'], 'passout:'));
        $this->assertSame('13:00', $pass['start']->format('H:i'));
        $this->assertFalse($pass['start']->lte(\Carbon\Carbon::parse('2026-11-20 10:00')));
        $this->actingAs($executive)->get(route('dashboard', ['exec_view' => 'calendar', 'exec_date' => '2026-11-19']))->assertOk()->assertSee('Reassignment / deadline review needed')->assertSee('Approved afternoon pass-out')->assertDontSee('PRIVATE MEDICAL DATA')->assertDontSee('PRIVATE HALF DAY REASON');
    }

    public function test_client_relations_process_is_entered_once_reviewed_and_returned_to_the_shared_project(): void
    {
        $staff = $this->person('client_relations');
        $manager = $this->person('client_relations', 'manager');
        $executive = $this->person('executive', 'super_admin');
        $payload = ['owner_id' => $staff->id, 'client_name' => 'One source client', 'project_name' => 'One connected project', 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'stage' => 'proposal_sent', 'execution_process_present' => 1, 'execution_revision' => 0, 'execution' => ['process' => ['proposal' => ['owner_id' => $staff->id, 'due_at' => '2026-11-20 16:00', 'submitted_at' => '2026-11-19 10:00', 'status' => 'submitted', 'evidence' => 'Original proposal email']]]];
        $this->actingAs($staff)->get(route('portal.crm.index'))->assertOk()->assertSee('Process milestones');
        $id = $this->postJson(route('portal.crm.store'), $payload)->assertStatus(202)->json('submission_id');
        $this->assertDatabaseCount('project_execution_controls', 0);
        $this->actingAs($manager)->postJson(route('portal.crm.submissions.review', $id), ['revision' => 1, 'decision' => 'approve'])->assertOk();
        $p = CrmPipelineProject::sole();
        $control = ProjectExecutionControl::sole();
        $this->assertSame($p->id, $control->crm_project_id);
        $this->assertSame('2026-11-19 10:00', $control->data['process']['proposal']['completed_at']);
        $this->assertSame($staff->id, $control->data['_source_entry']['entered_by']);
        $this->assertCount(1, $this->projection($executive)['events']->where('kind', 'process'));
        $this->actingAs($staff)->get(route('portal.executive.controls.show', $control))->assertOk()->assertSee('Original proposal email');
        $this->actingAs($executive)->post(route('portal.executive.controls.save', $p), ['section' => 'schedule', 'revision' => 1, 'reason' => 'Operations HOD commitment', 'data' => ['starts_at' => '2026-11-21 09:00', 'ends_at' => '2026-11-21 18:00']])->assertSessionHasNoErrors();
        $this->actingAs($staff)->get(route('portal.operations', ['ops_add' => 'activation', 'ops_project' => $p->id]))->assertForbidden();
        $ops = $this->person();
        $this->actingAs($ops)->get(route('portal.operations', ['ops_add' => 'activation', 'ops_project' => $p->id]))->assertOk()->assertSee('name="execution_revision" value="2"', false)->assertSee('One connected project')->assertSee('2026-11-21T09:00');
    }

    public function test_exact_task_links_reuse_finance_statuses_without_making_revenue(): void
    {
        $viewer = $this->person('executive', 'super_admin');
        $owner = $this->person();
        $project = CrmPipelineProject::create(['owner_id' => $owner->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'client_name' => 'Original client', 'project_name' => 'Linked job', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22']);
        $task = Task::create(['title' => 'Linked delivery', 'assigned_to' => $owner->id, 'assigned_by' => $viewer->id, 'department' => 'operations_projects', 'status' => 'open', 'due_on' => '2026-11-20', 'custom_fields' => ['executive_control_project_id' => $project->id]]);
        \App\Models\ProjectBudget::create(['created_by' => $owner->id, 'task_id' => $task->id, 'title' => 'Original budget', 'total_amount' => 500, 'currency' => 'GHS', 'status' => 'CVO Approved']);
        \App\Models\SupplierInvoice::create(['submitted_by' => $owner->id, 'task_id' => $task->id, 'description' => 'Original supplier cost', 'invoice_number' => 'INV-ORIGINAL', 'supplier_name' => 'Original vendor', 'amount' => 100, 'currency' => 'GHS', 'status' => 'Submitted']);
        $data = $this->projection($viewer);
        $this->assertCount(2, $data['linkedFinance']);
        $this->assertSame([$project->id], $data['linkedFinance']->pluck('project_id')->unique()->values()->all());
        $this->assertDatabaseCount('performance_registers', 0);
        $this->assertDatabaseCount('project_budgets', 1);
        $this->assertDatabaseCount('supplier_invoices', 1);
        $task->update(['custom_fields' => []]);
        $this->assertCount(0, $this->projection($viewer)['linkedFinance']);
    }

    public function test_original_upstream_verification_releases_downstream_dependency(): void
    {
        $viewer = $this->person('executive', 'super_admin');
        $lead = $this->person();
        $base = ['department' => 'operations_projects', 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'lead_staff_id' => $lead->id, 'created_by' => $lead->id, 'deliverables' => 'Original work', 'report_status' => 'published', 'workflow_state' => 'approved'];
        $up = \App\Models\WeeklyConsolidatedItem::create($base + ['campaign_name' => 'Upstream sign off']);
        $down = \App\Models\WeeklyConsolidatedItem::create($base + ['campaign_name' => 'Dependent installation']);
        \App\Models\PerformanceDependency::create(['upstream_id' => $up->id, 'downstream_id' => $down->id, 'impact' => 'Installation waits for source sign off']);
        $events = collect([['key' => 'weekly:'.$down->id]]);
        $service = app(\App\Services\LinkedProjectDependencies::class);
        $this->assertTrue($service->work($viewer, $events)->sole()['blocked']);
        $up->update(['workflow_state' => 'verified']);
        $this->assertFalse($service->work($viewer, $events)->sole()['blocked']);
        $this->assertDatabaseCount('performance_dependencies', 1);
    }
}
