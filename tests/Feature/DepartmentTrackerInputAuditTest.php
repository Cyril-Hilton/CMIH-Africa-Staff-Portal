<?php

namespace Tests\Feature;

use App\Models\HrRegisterRecord;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\ExecutiveCommandCentre;
use App\Services\ExecutiveSourceMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DepartmentTrackerInputAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20 12:00:00'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
    }

    private function person(string $dept, string $role = 'manager'): User
    {
        return User::factory()->create(['department' => $dept, 'access_role' => $role, 'status' => 'active']);
    }

    private function executive(User $u): array
    {
        $r = Request::create('/dashboard', 'GET', ['exec_date' => '2026-11-20', 'exec_period' => 'month']);
        $r->setUserResolver(fn () => $u);

        return app(ExecutiveCommandCentre::class)->build($r)['command'];
    }

    private function register(User $u, string $dept, string $kind, array $data): PerformanceRegister
    {
        return PerformanceRegister::create(['department' => $dept, 'kind' => $kind, 'title' => 'Original '.$kind, 'reference' => fake()->uuid(), 'owner_id' => $u->id, 'submitted_by' => $u->id, 'approved_by' => $u->id, 'approved_at' => now(), 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'status' => 'in_progress', 'data' => $data]);
    }

    public function test_every_tracked_register_deadline_and_outcome_has_a_department_input(): void
    {
        foreach (config('executive_source_milestones.registers') as $dept => $kinds) {
            foreach (config('department_reporting.'.$dept, []) as $kind => $schema) {
                foreach (array_replace($kinds['*'] ?? [], $kinds[$kind] ?? []) as $due => $rule) {
                    if (! isset($schema[$due])) {
                        continue;
                    }$this->assertSame('date', $schema[$due], "$dept/$kind/$due");
                    if (! empty($rule['actual']) && isset($schema[$rule['actual']])) {
                        $this->assertSame('date', $schema[$rule['actual']]);
                    }
                }
            }
        }
        foreach (config('executive_source_milestones.hr_live') as $kind => $fields) {
            $schema = app(\App\Services\HrLiveRegisters::class)->schema($kind)['fields'];
            foreach ($fields as $due => $rule) {
                $this->assertSame('date', $schema[$due]['type']);
                if (! empty($rule['actual'])) {
                    $this->assertSame('date', $schema[$rule['actual']]['type']);
                }
            }
        }
    }

    public function test_hr_source_form_accepts_responsibility_due_and_resolution_and_tracker_uses_them(): void
    {
        $hr = $this->person('hr_admin');
        $staff = $this->person('creatives', 'staff');
        $exec = $this->person('executive', 'super_admin');
        $this->actingAs($hr)->get(route('portal.hr-workspace', ['hr_tab' => 'relations']))->assertOk()->assertSee('Actual resolution date')->assertSee('Follow-up deadline');
        $this->post(route('portal.hr-registers.store', 'employee_case'), ['data' => ['staff_id' => $staff->id, 'case_type' => 'Grievance', 'opened_on' => '2026-11-10', 'state' => 'Closed', 'summary' => 'PRIVATE CASE CONTENT', 'responsible_id' => $hr->id, 'due_on' => '2026-11-15', 'resolved_on' => '2026-11-18']])->assertSessionHasNoErrors();
        $row = HrRegisterRecord::sole();
        $event = $this->executive($exec)['events']->firstWhere('key', 'hrregister:'.$row->id.':due_on');
        $this->assertNotNull($event);
        $this->assertTrue($event['done']);
        $this->assertTrue($event['late']);
        $this->assertSame($hr->id, $event['owner']);
        $traffic = $this->person('executive', 'staff');
        $traffic->update(['permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        $this->assertFalse($this->executive($traffic)['events']->contains('key', $event['key']));
        $traffic->update(['permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION, User::HR_FINANCE_AUDIT_PERMISSION]]);
        $this->actingAs($traffic->fresh())->get(route('portal.hr-workspace', ['hr_tab' => 'relations']))->assertOk()->assertSee('2026-11-18')->assertSee('PRIVATE CASE CONTENT')->assertDontSee('name="data[resolved_on]"', false);
    }

    public function test_finance_paid_late_is_a_dated_outcome_and_part_payment_remains_open(): void
    {
        $finance = $this->person('finance');
        $exec = $this->person('executive', 'super_admin');
        $row = $this->register($finance, 'finance', 'invoice', ['value' => 100, 'paid_amount' => 100, 'paid_on' => '2026-11-18', 'due_on' => '2026-11-15']);
        $event = $this->executive($exec)['events']->firstWhere('key', 'finance:'.$row->id.':due_on');
        $this->assertTrue($event['done']);
        $this->assertTrue($event['late']);
        $row->update(['data' => array_replace($row->data, ['paid_amount' => 50])]);
        $event = $this->executive($exec)['events']->firstWhere('key', 'finance:'.$row->id.':due_on');
        $this->assertFalse($event['done']);
        $row->update(['data' => array_replace($row->data, ['credited_amount' => 50, 'credited_on' => '2026-11-19'])]);
        $m = app(ExecutiveSourceMilestones::class)->register($row)['due_on'];
        $this->assertSame('2026-11-19', $m['actual']);
    }

    public function test_operations_drivers_and_brands_use_entered_target_and_outcome_dates(): void
    {
        $exec = $this->person('executive', 'super_admin');
        foreach ([['operations_projects', 'procurement', 'required_on', 'delivered_on'], ['admin_drivers', 'maintenance', 'scheduled_on', 'completed_on'], ['brands_marketing', 'campaign', 'report_due_on', 'report_shared_on']] as [$dept,$kind,$due,$actual]) {
            $owner = $this->person($dept);
            $r = $this->register($owner, $dept, $kind, [$due => '2026-11-15', $actual => '2026-11-18']);
            $event = $this->executive($exec)['events']->firstWhere('key', 'register:'.$r->id.':'.$due);
            $this->assertNotNull($event, "$dept deadline missing");
            $this->assertTrue($event['done']);
            $this->assertTrue($event['late']);
        }
    }

    public function test_weekly_project_window_is_used_but_reporting_week_and_daily_tasks_are_not_deadlines(): void
    {
        $owner = $this->person('brands_marketing');
        $exec = $this->person('executive', 'super_admin');
        $w = \App\Models\WeeklyConsolidatedItem::create(['department' => 'brands_marketing', 'lead_staff_id' => $owner->id, 'created_by' => $owner->id, 'campaign_name' => 'Weekly major project', 'deliverables' => 'Weekly work', 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'project_starts_on' => '2026-11-10', 'project_ends_on' => '2026-11-18', 'workflow_state' => 'approved', 'report_status' => 'reviewed', 'status' => 'In Progress']);
        $events = $this->executive($exec)['events'];
        $this->assertSame('activity', $events->firstWhere('key', 'weekly:'.$w->id)['kind']);
        $this->assertTrue($events->firstWhere('key', 'weekly:'.$w->id.':project')['late']);
    }

    public function test_operations_actual_delivery_is_entered_by_staff_reviewed_and_then_shared(): void
    {
        $staff = $this->person('operations_projects', 'staff');
        $hod = $this->person('operations_projects');
        $exec = $this->person('executive', 'super_admin');
        $this->actingAs($staff)->get(route('portal.operations', ['ops_add' => 'procurement']))->assertOk()->assertSee('name="data[delivered_on]"', false);
        $payload = ['kind' => 'procurement', 'module_entry' => 1, 'title' => 'Reviewed supplier delivery', 'owner_id' => $staff->id, 'week' => '2026-11-16', 'status' => 'completed', 'data' => ['item_service' => 'Stands', 'required_on' => '2026-11-17', 'delivered_on' => '2026-11-19']];
        $this->post(route('portal.operations.reporting.save'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('performance_registers', 0);
        $submission = \App\Models\OperationsReportSubmission::sole();
        $this->actingAs($hod)->post(route('portal.operations.reporting.review', $submission), ['revision' => 1, 'decision' => 'approve'])->assertSessionHasNoErrors();
        $record = PerformanceRegister::sole();
        $event = $this->executive($exec)['events']->firstWhere('key', 'register:'.$record->id.':required_on');
        $this->assertTrue($event['done']);
        $this->assertTrue($event['late']);
        $this->actingAs($staff)->get(route('portal.operations', ['ops_tab' => 'execution']))->assertOk()->assertSee('Delivered On')->assertSee('2026-11-19');
    }

    public function test_unrelated_edits_do_not_move_a_recorded_sales_stage_date(): void
    {
        $u = $this->person('executive', 'super_admin');
        $p = \App\Models\CrmPipelineProject::create(['owner_id' => $u->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'project_name' => 'Stage history', 'client_name' => 'Client', 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => '2026-11-16']);
        \App\Models\PerformanceEvent::create(['entity_type' => 'crm_project', 'entity_id' => $p->id, 'actor_id' => $u->id, 'action' => 'update', 'before' => ['stage' => 'verbal_yes'], 'after' => ['stage' => 'project_ongoing'], 'reason' => 'Real stage change', 'created_at' => '2026-11-17 10:00:00']);
        \App\Models\PerformanceEvent::create(['entity_type' => 'crm_project', 'entity_id' => $p->id, 'actor_id' => $u->id, 'action' => 'update', 'before' => ['stage' => 'project_ongoing'], 'after' => ['stage' => 'project_ongoing'], 'reason' => 'Notes edited later', 'created_at' => '2026-11-20 10:00:00']);
        $r = \Illuminate\Http\Request::create('/dashboard', 'GET', ['exec_view' => 'projects', 'exec_period' => 'month', 'exec_date' => '2026-11-20']);
        $r->setUserResolver(fn () => $u);
        $d = app(ExecutiveCommandCentre::class)->build($r)['command'];
        $step = collect($d['projectRows'][$p->id]['flow']['sections']['client']['records'][0]['steps'])->firstWhere('label', 'Project ongoing');
        $this->assertSame('2026-11-17 10:00:00', $step['at']);
        $this->assertSame('Recorded in portal', $step['date_kind']);
    }

    public function test_finance_entered_credit_date_displays_and_risk_resolution_is_not_a_payment(): void
    {
        $u = $this->person('finance');
        config(['finance_reporting.manager_user_id' => $u->id]);
        $exec = $this->person('executive', 'super_admin');
        $payload = ['kind' => 'invoice', 'title' => 'Display submitted credit date', 'reference' => 'DISPLAY-INV', 'owner_id' => $u->id, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'status' => 'paid', 'reason' => 'Record actual settlement', 'data' => ['value' => 100, 'paid_amount' => 50, 'paid_on' => '2026-11-18', 'credited_amount' => 50, 'credited_on' => '2026-11-19', 'due_on' => '2026-11-15']];
        $this->actingAs($u)->post(route('portal.finance.reporting.save'), $payload)->assertSessionHasNoErrors();
        $this->get(route('portal.finance', ['finance_tab' => 'invoices', 'finance_period' => 'month', 'finance_date' => '2026-11-20']))->assertOk()->assertSee('Display submitted credit date')->assertSee('Credited On')->assertSee('19 Nov 2026');
        $risk = $this->register($u, 'finance', 'commercial_risk', ['risk_type' => 'Other', 'due_on' => '2026-11-15', 'resolved_on' => '2026-11-19']);
        $event = $this->executive($exec)['events']->firstWhere('key', 'finance:'.$risk->id.':due_on');
        $this->assertTrue($event['done']);
        $this->assertTrue($event['late']);
    }

    public function test_brands_submitted_fields_are_available_in_read_details_after_review(): void
    {
        $u = $this->person('brands_marketing', 'staff');
        $hod = $this->person('brands_marketing');
        $id = $this->actingAs($u)->postJson(route('portal.brands.reports.submit'), ['category' => 'activation', 'title' => 'Display saved brand brief', 'owner_id' => $u->id, 'week' => '2026-11-16', 'data' => ['partner' => 'castel', 'brief_status' => 'closed', 'outcome' => 'won', 'brief_date' => '2026-11-17', 'closed_on' => '2026-11-19', 'remarks' => 'BRANDS-READ-BACK-EVIDENCE']])->assertStatus(202)->json('id');
        $this->actingAs($hod)->postJson(route('portal.brands.reports.review', $id), ['revision' => 1, 'decision' => 'approve'])->assertOk();
        $row = PerformanceRegister::sole();
        $result = $this->actingAs($u)->getJson(route('portal.brands.reports.detail', ['type' => 'register', 'id' => $row->id]))->assertOk()->json();
        $values = collect($result['display'])->pluck('html')->implode(' ');
        $this->assertStringContainsString('BRANDS-READ-BACK-EVIDENCE', $values);
        $this->assertStringContainsString('2026-11-19', $values);
    }
}
