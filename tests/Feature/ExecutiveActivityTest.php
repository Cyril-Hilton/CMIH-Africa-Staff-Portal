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
            'activity_staff' => $support->id, 'activity_department' => 'brands_marketing', 'activity_date' => '2026-11-16',
        ]))->assertOk()->assertViewHas('executiveActivity', fn ($view) => $view['rows']->pluck('id')->all() === [$shared->id])
            ->assertSee('Support: '.$support->name);
        $this->get('/portal/hr')->assertForbidden();
        $claim = \App\Models\PettyCashClaim::create([
            'user_id' => $lead->id, 'amount' => 100, 'currency' => 'GH₵',
            'description' => 'Private staff claim', 'status' => 'Submitted to CVO',
        ]);
        $this->get('/portal/finance')->assertOk()->assertDontSee('Private staff claim');
        $this->get(route('portal.finance.claims.receipt', $claim))->assertForbidden();
        $this->assertFalse($traffic->isCvoOrSuperAdmin());
    }

    public function test_executive_activity_requires_a_grant_or_existing_executive_privilege(): void
    {
        foreach ([
            ['department' => 'executive', 'permissions_matrix' => []],
            ['department' => 'brands_marketing', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]],
            ['department' => 'executive', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION], 'is_service_account' => true],
        ] as $attributes) {
            $staff = User::factory()->create($attributes + ['status' => 'active', 'access_role' => 'staff']);
            $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertDontSee('id="executive-activity"', false);
        }

        $cvo = User::factory()->create(['position_title' => 'CVO', 'status' => 'active']);
        $this->actingAs($cvo)->get(route('dashboard'))->assertOk()
            ->assertSee('id="executive-activity"', false)->assertSee('aria-label="Executive Review Queues"', false);
    }
}
