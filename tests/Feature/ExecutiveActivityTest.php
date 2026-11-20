<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_can_track_shared_and_pending_tasks_without_seeing_confidential_titles_or_approval_controls(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 12:00:00'));
        $traffic = User::factory()->create([
            'department' => 'executive', 'status' => 'active', 'access_role' => 'staff',
            'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION],
        ]);
        $lead = User::factory()->create(['department' => 'brands_marketing', 'status' => 'active']);
        $support = User::factory()->create(['department' => 'operations_projects', 'status' => 'active']);
        $attributes = ['assigned_to' => $lead->id, 'assigned_by' => $lead->id, 'status' => 'Open', 'priority' => 'Medium'];
        $shared = Task::create($attributes + ['department' => 'brands_marketing', 'title' => 'Shared retail plan', 'supporting_staff_ids' => [(string) $support->id]]);
        $pending = Task::create($attributes + ['department' => 'brands_marketing', 'title' => 'Unpublished results', 'completion_review_status' => 'pending']);
        $private = Task::create($attributes + ['department' => 'finance', 'title' => 'Confidential account details']);

        $overview = $this->actingAs($traffic)->get(route('dashboard'));
        $overview->assertOk()->assertViewHas('weeklyDepartmentFilter', 'all')
            ->assertDontSee('aria-label="Executive Review Queues"', false);
        $activity = $overview->viewData('executiveActivity')['rows']->getCollection()->keyBy('id');
        $this->assertSame('Shared retail plan', $activity[$shared->id]->shared_title);
        $this->assertSame('pending', $activity[$pending->id]->completion_review_status);
        $this->assertNull($activity[$pending->id]->shared_title);
        $this->assertNull($activity[$private->id]->shared_title);
        $this->get(route('dashboard', [
            'exec_view' => 'calendar', 'exec_scope' => 'activity', 'exec_person' => $support->id, 'activity_staff' => $support->id, 'activity_department' => 'brands_marketing', 'activity_date' => '2026-11-16',
        ]))->assertOk()->assertViewHas('executiveActivity', fn ($view) => $view['rows']->pluck('id')->all() === [$shared->id])
            ->assertSee('Collaborators')->assertSee($support->name);
        $this->get('/portal/hr')->assertForbidden();
        $claim = \App\Models\PettyCashClaim::create([
            'user_id' => $lead->id, 'amount' => 100, 'currency' => 'GH₵',
            'description' => 'Private staff claim', 'status' => 'Submitted to CVO',
        ]);
        $this->get('/portal/finance')->assertOk()->assertDontSee('Private staff claim');
        $this->get(route('portal.finance.claims.receipt', $claim))->assertForbidden();
        $this->assertFalse($traffic->isCvoOrSuperAdmin());
    }

    public function test_executive_sees_real_task_names_and_can_open_details_without_granting_traffic_private_access(): void
    {
        $owner = User::factory()->create(['status' => 'active', 'department' => 'creatives']);
        $cvo = User::factory()->create(['status' => 'active', 'position_title' => 'CVO']);
        $traffic = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'staff',
            'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        $base = ['assigned_to' => $owner->id, 'assigned_by' => $owner->id, 'status' => 'Open', 'priority' => 'Medium'];
        $shared = Task::create($base + ['department' => 'operations_projects', 'title' => 'Retail execution', 'details' => '<p>Install display stands</p>']);
        $private = Task::create($base + ['department' => 'finance', 'title' => 'Payroll reconciliation']);
        $pending = Task::create($base + ['department' => 'brands_marketing', 'title' => 'Campaign review', 'completion_review_status' => 'pending']);
        $creative = Task::create($base + ['department' => 'creatives', 'title' => 'Dove display design']);
        \App\Models\CreativeRequest::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'reference' => 'CR-TEST',
            'task_id' => $creative->id, 'title' => $creative->title, 'visibility' => 'private', 'requester_id' => $owner->id]);

        $dashboard = $this->actingAs($cvo)->get(route('dashboard', ['exec_view' => 'calendar']))->assertOk();
        foreach ([$shared, $private, $pending, $creative] as $task) {
            if ($task->id === $creative->id) {
                $dashboard->assertSee($task->title);
            }
            $this->get(route('portal.tasks.show', $task))->assertOk()->assertSee($task->title);
        }
        $this->get(route('portal.tasks.show', $shared))->assertSee('Install display stands');
        $this->actingAs($traffic)->get(route('portal.tasks.show', $shared))->assertOk()->assertDontSee('Edit task');
        foreach ([$private, $pending, $creative] as $task) {
            $this->get(route('portal.tasks.show', $task))->assertForbidden();
        }
        $traffic->update(['permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION, User::HR_FINANCE_AUDIT_PERMISSION]]);
        $this->actingAs($traffic->fresh())->get(route('portal.tasks.show', $private))->assertOk()->assertDontSee('Edit task');
        $this->get(route('portal.tasks.show', $creative))->assertForbidden();
        $this->actingAs($owner)->get(route('portal.tasks.show', $creative))->assertOk()->assertSee('Open creative brief');
    }

    public function test_executive_activity_requires_a_grant_or_existing_executive_privilege(): void
    {
        foreach ([
            ['department' => 'executive', 'permissions_matrix' => []],
            ['department' => 'brands_marketing', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]],
            ['department' => 'executive', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION], 'is_service_account' => true],
        ] as $attributes) {
            $staff = User::factory()->create($attributes + ['status' => 'active', 'access_role' => 'staff']);
            $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertDontSee('id="executive-command"', false);
        }

        $cvo = User::factory()->create(['position_title' => 'CVO', 'status' => 'active']);
        $this->actingAs($cvo)->get(route('dashboard', ['exec_view' => 'accountability']))->assertOk()
            ->assertSee('id="executive-command"', false)->assertSee('aria-label="Executive Review Queues"', false);
    }
}
