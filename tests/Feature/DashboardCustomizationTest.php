<?php

namespace Tests\Feature;

use App\Models\DashboardColumn;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedColumn;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_custom_column(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $response = $this->actingAs($manager)->post('/portal/dashboard/columns', [
            'department' => 'finance',
            'label' => 'Reconciliation Ref',
            'type' => 'text',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dashboard_columns', [
            'department' => 'finance',
            'label' => 'Reconciliation Ref',
            'column_key' => 'reconciliation_ref',
        ]);
    }

    public function test_executive_cannot_add_custom_column(): void
    {
        $executive = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
        ]);

        $response = $this->actingAs($executive)->post('/portal/dashboard/columns', [
            'department' => 'finance',
            'label' => 'Illegal Column',
            'type' => 'text',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('dashboard_columns', [
            'label' => 'Illegal Column',
        ]);
    }

    public function test_manager_can_delete_custom_column(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $column = DashboardColumn::create([
            'department' => 'finance',
            'column_key' => 'ref_code',
            'label' => 'Ref Code',
            'type' => 'text',
            'order' => 1,
        ]);

        $response = $this->actingAs($manager)->delete("/portal/dashboard/columns/{$column->id}");

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('dashboard_columns', [
            'id' => $column->id,
        ]);
    }

    public function test_manager_can_reassign_task(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'name' => 'Manager Joe',
            'department' => 'finance',
        ]);

        $staff1 = User::factory()->create(['name' => 'Staff Alice']);
        $staff2 = User::factory()->create(['name' => 'Staff Bob']);

        $task = Task::create([
            'title' => 'Test Task to Reassign',
            'details' => 'Original details',
            'assigned_to' => $staff1->id,
            'assigned_by' => $manager->id,
            'department' => 'finance',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($manager)->post("/portal/tasks/{$task->id}/reassign", [
            'assigned_to' => $staff2->id,
            'reason' => 'Alice is overloaded',
        ]);

        $response->assertSessionHasNoErrors();
        
        $task->refresh();
        $this->assertEquals($staff2->id, $task->assigned_to);
        $this->assertStringContainsString('Alice is overloaded', $task->notes_feedback);
        $this->assertStringContainsString('Staff Alice -> To: Staff Bob', $task->notes_feedback);
    }

    public function test_task_collaborator_can_reassign_task_from_any_department(): void
    {
        $executive = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'name' => 'Staff Reassigner',
        ]);

        $staff1 = User::factory()->create();
        $staff2 = User::factory()->create();

        $task = Task::create([
            'title' => 'Test Task 2',
            'details' => 'Original details',
            'assigned_to' => $staff1->id,
            'assigned_by' => $staff1->id,
            'department' => 'finance',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($executive)->post("/portal/tasks/{$task->id}/reassign", [
            'assigned_to' => $staff2->id,
            'reason' => 'Helping balance workload',
        ]);

        $task->refresh();
        $response->assertStatus(403);
        $this->assertEquals($staff1->id, $task->assigned_to);

        $task->update(['supporting_staff_ids' => [$executive->id]]);

        $response = $this->actingAs($executive)->post("/portal/tasks/{$task->id}/reassign", [
            'assigned_to' => $staff2->id,
            'reason' => 'Helping balance workload',
        ]);

        $task->refresh();
        $response->assertSessionHasNoErrors();
        $this->assertEquals($staff2->id, $task->assigned_to);
        $this->assertStringContainsString('Helping balance workload', $task->notes_feedback);
    }

    public function test_merchandiser_cannot_reassign_cmih_tasks(): void
    {
        $merchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $staff1 = User::factory()->create();
        $staff2 = User::factory()->create();

        $task = Task::create([
            'title' => 'Finance Task',
            'details' => 'Finance details',
            'assigned_to' => $staff1->id,
            'assigned_by' => $staff1->id,
            'department' => 'finance',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($merchandiser)->post("/portal/tasks/{$task->id}/reassign", [
            'assigned_to' => $staff2->id,
            'reason' => 'Outside CMIH portal boundary',
        ]);

        $response->assertStatus(403);
        $task->refresh();
        $this->assertEquals($staff1->id, $task->assigned_to);
    }

    public function test_task_creation_saves_supporting_staff_and_roles(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $staff1 = User::factory()->create(['status' => 'active']);
        $staff2 = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($manager)->post('/portal/tasks', [
            'title' => 'Collaborative Task',
            'priority' => 'medium',
            'due_on' => now()->addDays(2)->toDateString(),
            'supporting_staff_ids' => [$staff1->id, $staff2->id],
            'supporting_roles' => 'Co-Auditor and Assistant',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Collaborative Task',
            'supporting_roles' => 'Co-Auditor and Assistant',
        ]);

        $task = Task::where('title', 'Collaborative Task')->first();
        $this->assertEquals([$staff1->id, $staff2->id], $task->supporting_staff_ids);
    }

    public function test_staff_can_add_custom_column_for_own_department(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $response = $this->actingAs($staff)->post('/portal/dashboard/columns', [
            'department' => 'finance',
            'label' => 'Staff Column',
            'type' => 'text',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dashboard_columns', [
            'department' => 'finance',
            'label' => 'Staff Column',
            'column_key' => 'staff_column',
        ]);
    }

    public function test_staff_cannot_add_custom_column_for_other_departments(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $response = $this->actingAs($staff)->post('/portal/dashboard/columns', [
            'department' => 'creatives',
            'label' => 'Other Dept Column',
            'type' => 'text',
        ]);

        $response->assertStatus(403);
    }

    public function test_all_staff_can_view_all_departments_on_dashboard(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('departments', function($depts) {
            return count($depts) === 6; // All 6 departments are visible
        });
    }

    public function test_mega_table_is_paginated_per_department(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $creative = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            Task::create([
                'title' => "Creative Deliverable {$i}",
                'client_name' => 'Pagination Client',
                'assigned_to' => $creative->id,
                'assigned_by' => $manager->id,
                'department' => 'creatives',
                'status' => 'In Progress',
                'priority' => 'Medium',
                'due_on' => now()->addDays($i),
            ]);
        }

        $firstPage = $this->actingAs($manager)->get(route('dashboard', ['tab' => 'creatives']));

        $firstPage->assertOk();
        $firstPage->assertSee('data-mega-pagination', false);
        $firstPage->assertSee('mega_creatives_page=2', false);
        $firstPage->assertViewHas('departmentTables', function ($tables) {
            $paginator = $tables['creatives'] ?? null;

            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 12
                && $paginator->count() === 10
                && $paginator->currentPage() === 1;
        });

        $secondPage = $this->actingAs($manager)->get(route('dashboard', [
            'tab' => 'creatives',
            'mega_creatives_page' => 2,
        ]));

        $secondPage->assertOk();
        $secondPage->assertViewHas('departmentTables', function ($tables) {
            $paginator = $tables['creatives'] ?? null;

            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 12
                && $paginator->count() === 2
                && $paginator->currentPage() === 2;
        });
    }

    public function test_mega_table_defaults_to_recently_approved_or_updated_tasks(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'Creative Department',
        ]);

        $creative = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'Creative Department',
        ]);

        $olderApproval = Task::create([
            'title' => 'Earlier Approval With Later Deadline',
            'assigned_to' => $creative->id,
            'assigned_by' => $manager->id,
            'department' => 'creative_department',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
            'completion_review_status' => 'approved',
            'completion_reviewed_at' => Carbon::parse('2026-07-05 10:00:00'),
            'completion_reviewed_by' => $manager->id,
            'due_on' => Carbon::parse('2026-07-21'),
        ]);
        $olderApproval->forceFill([
            'created_at' => Carbon::parse('2026-07-05 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-05 10:00:00'),
        ])->save();

        $todaysApproval = Task::create([
            'title' => 'Approved Today With Older Deadline',
            'assigned_to' => $creative->id,
            'assigned_by' => $manager->id,
            'department' => 'Creative Department',
            'status' => 'Completed',
            'priority' => 'High',
            'progress' => 100,
            'completion_review_status' => 'approved',
            'completion_reviewed_at' => Carbon::parse('2026-07-21 11:00:00'),
            'completion_reviewed_by' => $manager->id,
            'due_on' => Carbon::parse('2026-07-05'),
        ]);
        $todaysApproval->forceFill([
            'created_at' => Carbon::parse('2026-07-21 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-21 11:00:00'),
        ])->save();

        $response = $this->actingAs($manager)->get(route('dashboard', ['tab' => 'creatives']));

        $response->assertOk();
        $response->assertViewHas('departmentTables', function ($tables) use ($todaysApproval) {
            $paginator = $tables['creatives'] ?? null;

            return $paginator instanceof LengthAwarePaginator
                && $paginator->first()?->id === $todaysApproval->id;
        });
        $response->assertSee('Approved Today With Older Deadline');
        $response->assertSee('21 Jul 2026');
    }

    public function test_weekly_consolidated_table_is_paginated(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'brands_marketing',
        ]);

        $lead = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'brands_marketing',
        ]);

        for ($i = 1; $i <= 11; $i++) {
            WeeklyConsolidatedItem::create([
                'department' => 'brands_marketing',
                'week_start' => now()->startOfWeek()->subWeeks($i)->toDateString(),
                'week_end' => now()->endOfWeek()->subWeeks($i)->toDateString(),
                'client_name' => 'Weekly Pagination Client',
                'campaign_name' => "Campaign {$i}",
                'lead_staff_id' => $lead->id,
                'deliverables' => "Weekly deliverable {$i}",
                'status' => 'In Progress',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
        }

        $firstPage = $this->actingAs($manager)->get(route('dashboard', [
            'weekly_department' => 'brands_marketing',
        ]));

        $firstPage->assertOk();
        $firstPage->assertSee('data-weekly-pagination', false);
        $firstPage->assertSee('weekly_page=2', false);
        $firstPage->assertViewHas('weeklyConsolidatedItems', function ($paginator) {
            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 11
                && $paginator->count() === 8
                && $paginator->currentPage() === 1;
        });

        $secondPage = $this->actingAs($manager)->get(route('dashboard', [
            'weekly_department' => 'brands_marketing',
            'weekly_page' => 2,
        ]));

        $secondPage->assertOk();
        $secondPage->assertViewHas('weeklyConsolidatedItems', function ($paginator) {
            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 11
                && $paginator->count() === 3
                && $paginator->currentPage() === 2;
        });
    }

    public function test_weekly_department_filter_includes_legacy_department_labels(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'Brands & Marketing',
        ]);

        WeeklyConsolidatedItem::create([
            'department' => 'Brands & Marketing',
            'week_start' => Carbon::parse('2026-07-20')->toDateString(),
            'week_end' => Carbon::parse('2026-07-24')->toDateString(),
            'client_name' => 'Legacy Brands Task',
            'campaign_name' => 'Legacy Brands Project',
            'deliverables' => 'A row saved with a human-readable department should still show.',
            'status' => 'In Progress',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard', [
            'weekly_department' => 'brands_marketing',
        ]));

        $response->assertOk();
        $response->assertSee('Legacy Brands Project');
        $response->assertViewHas('weeklyConsolidatedItems', function ($paginator) {
            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 1
                && $paginator->first()?->campaign_name === 'Legacy Brands Project';
        });
    }

    public function test_department_user_defaults_weekly_table_to_own_department_but_requested_tab_wins(): void
    {
        $hrManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'HR Admin',
        ]);

        $defaultResponse = $this->actingAs($hrManager)->get(route('dashboard'));

        $defaultResponse->assertOk();
        $defaultResponse->assertViewHas('weeklyDepartmentFilter', 'hr_admin');

        $creativeResponse = $this->actingAs($hrManager)->get(route('dashboard', [
            'weekly_department' => 'creatives',
        ]));

        $creativeResponse->assertOk();
        $creativeResponse->assertViewHas('weeklyDepartmentFilter', 'creatives');
        $creativeResponse->assertDontSee('+ Add Weekly Row');
    }

    public function test_cvo_weekly_consolidated_defaults_to_all_departments_latest_activity(): void
    {
        $cvo = User::factory()->create([
            'access_role' => 'staff',
            'position_title' => 'CVO',
            'job_level' => 'CVO',
            'status' => 'active',
            'department' => 'executive',
        ]);

        $financeRow = WeeklyConsolidatedItem::create([
            'department' => 'finance',
            'week_start' => Carbon::parse('2026-07-13')->toDateString(),
            'week_end' => Carbon::parse('2026-07-17')->toDateString(),
            'client_name' => 'Finance Client',
            'campaign_name' => 'Finance Earlier Row',
            'deliverables' => 'Earlier finance row.',
            'status' => 'Done',
            'created_by' => $cvo->id,
            'updated_by' => $cvo->id,
        ]);
        $financeRow->forceFill([
            'created_at' => Carbon::parse('2026-07-20 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-20 09:00:00'),
        ])->save();

        $brandsRow = WeeklyConsolidatedItem::create([
            'department' => 'Brands & Marketing',
            'week_start' => Carbon::parse('2026-07-20')->toDateString(),
            'week_end' => Carbon::parse('2026-07-24')->toDateString(),
            'client_name' => 'Brands Today Task',
            'campaign_name' => 'Brands Latest Row',
            'deliverables' => 'Latest brands row.',
            'status' => 'In Progress',
            'priority' => 'High',
            'progress_percent' => 60,
            'created_by' => $cvo->id,
            'updated_by' => $cvo->id,
        ]);
        $brandsRow->forceFill([
            'created_at' => Carbon::parse('2026-07-21 10:00:00'),
            'updated_at' => Carbon::parse('2026-07-21 10:00:00'),
        ])->save();

        $response = $this->actingAs($cvo)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('weeklyDepartmentFilter', 'all');
        $response->assertSee('All Departments');
        $response->assertSee('Brands Latest Row');
        $response->assertSee('Finance Earlier Row');
        $response->assertViewHas('weeklyConsolidatedItems', function ($paginator) use ($brandsRow) {
            return $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 2
                && $paginator->first()?->id === $brandsRow->id;
        });
    }

    public function test_collective_dashboard_metrics_use_cycle_task_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 12:00:00'));

        try {
            $manager = User::factory()->create([
                'access_role' => 'manager',
                'status' => 'active',
                'department' => 'finance',
            ]);

            $staff = User::factory()->create([
                'access_role' => 'staff',
                'status' => 'active',
                'department' => 'finance',
            ]);

            Task::create([
                'title' => 'Approved cycle task',
                'assigned_to' => $staff->id,
                'assigned_by' => $manager->id,
                'department' => 'finance',
                'status' => 'Completed',
                'priority' => 'Medium',
                'progress' => 100,
                'completion_review_status' => 'approved',
                'completion_reviewed_at' => now(),
                'completion_reviewed_by' => $manager->id,
            ]);

            Task::create([
                'title' => 'High priority overdue cycle task',
                'assigned_to' => $staff->id,
                'assigned_by' => $manager->id,
                'department' => 'finance',
                'status' => 'In Progress',
                'priority' => 'High',
                'progress' => 50,
                'due_on' => now()->subDay(),
            ]);

            Task::create([
                'title' => 'Low priority open cycle task',
                'assigned_to' => $staff->id,
                'assigned_by' => $manager->id,
                'department' => 'finance',
                'status' => 'Open',
                'priority' => 'Low',
            ]);

            $response = $this->actingAs($manager)->get(route('dashboard'));

            $response->assertOk();
            $response->assertViewHas('collectiveStats', function ($stats) {
                return $stats['target_activations'] === 3
                    && $stats['reached_activations'] === 1
                    && (float) $stats['win_rate'] === 33.3
                    && $stats['bottlenecks'] === 1;
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_line_manager_can_create_weekly_consolidated_item_and_staff_can_view_only(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);
        $lead = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);

        $response = $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'operations_projects',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'client_name' => 'SAB',
            'campaign_name' => 'Open Market Activations',
            'lead_staff_id' => $lead->id,
            'deliverables' => 'Complete weekly outlet activation plan.',
            'target_breakdown' => "Week 1: 100 activations\nWeek 2: 150 activations",
            'achieved_breakdown' => 'Week 1: 80 activations',
            'gap_breakdown' => 'Week 1: 20 remaining',
            'status' => 'In Progress',
        ]);

        $response->assertSessionHasNoErrors();
        $item = WeeklyConsolidatedItem::where('campaign_name', 'Open Market Activations')->first();
        $this->assertNotNull($item);

        $managerDashboard = $this->actingAs($manager)->get(route('dashboard'));
        $managerDashboard->assertOk();
        $managerDashboard->assertSee('Weekly Consolidated Table');
        $managerDashboard->assertSee('Open Market Activations');
        $managerDashboard->assertSee(route('portal.dashboard.weekly-consolidated.update', $item), false);

        $staffDashboard = $this->actingAs($staff)->get(route('dashboard', [
            'weekly_department' => 'operations_projects',
        ]));
        $staffDashboard->assertOk();
        $staffDashboard->assertSee('Weekly Consolidated Table');
        $staffDashboard->assertSee('Open Market Activations');
        $staffDashboard->assertDontSee(route('portal.dashboard.weekly-consolidated.update', $item), false);
        $staffDashboard->assertDontSee('+ Add Weekly Row');
    }

    public function test_regular_staff_can_see_shared_dashboard_tables_rankings_and_metrics(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        Task::create([
            'title' => 'Shared Finance Mega Task',
            'details' => 'Visible in the shared mega table.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'finance',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $weeklyItem = WeeklyConsolidatedItem::create([
            'department' => 'finance',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'client_name' => 'Finance Visibility Client',
            'campaign_name' => 'Finance Weekly Visibility',
            'lead_staff_id' => $staff->id,
            'deliverables' => 'Visible weekly consolidated row.',
            'status' => 'In Progress',
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);

        $dashboard = $this->actingAs($staff)->get(route('dashboard', [
            'weekly_department' => 'finance',
        ]));

        $dashboard->assertOk();
        $dashboard->assertSee('My Individual KPIs');
        $dashboard->assertSee('My Completion Rate');
        $dashboard->assertSee('Punctuality Score');
        $dashboard->assertSee('Collective Agency Dashboard');
        $dashboard->assertSee('Employee Award Standings');
        $dashboard->assertSee('Department Award Standings');
        $dashboard->assertSee('The Mega Table');
        $dashboard->assertSee('Shared Finance Mega Task');
        $dashboard->assertSee('Weekly Consolidated Table');
        $dashboard->assertSee('Finance Weekly Visibility');
        $dashboard->assertDontSee(route('portal.dashboard.weekly-consolidated.update', $weeklyItem), false);
        $dashboard->assertDontSee('+ Add Weekly Row');
    }

    public function test_cvo_can_see_mega_table_and_manage_weekly_consolidated_across_departments(): void
    {
        $cvo = User::factory()->create([
            'access_role' => 'staff',
            'position_title' => 'CVO',
            'job_level' => 'CVO',
            'status' => 'active',
            'department' => 'executive',
        ]);
        $creativeLead = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $operationsLead = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);

        Task::create([
            'title' => 'CVO Visible Mega Task',
            'details' => 'CVO should see this shared mega table task.',
            'assigned_to' => $creativeLead->id,
            'assigned_by' => $creativeLead->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);
        $weeklyItem = WeeklyConsolidatedItem::create([
            'department' => 'operations_projects',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'client_name' => 'CVO Operations Client',
            'campaign_name' => 'CVO Weekly Campaign',
            'lead_staff_id' => $operationsLead->id,
            'deliverables' => 'CVO should see and manage this weekly row.',
            'status' => 'In Progress',
            'created_by' => $operationsLead->id,
            'updated_by' => $operationsLead->id,
        ]);

        $dashboard = $this->actingAs($cvo)->get(route('dashboard', [
            'weekly_department' => 'operations_projects',
        ]));

        $dashboard->assertOk();
        $dashboard->assertSee('The Mega Table');
        $dashboard->assertSee('CVO Visible Mega Task');
        $dashboard->assertSee('Weekly Consolidated Table');
        $dashboard->assertSee('CVO Weekly Campaign');
        $dashboard->assertSee('+ Add Weekly Row');
        $dashboard->assertSee(route('portal.dashboard.weekly-consolidated.update', $weeklyItem), false);
        $dashboard->assertSee(route('portal.dashboard.weekly-consolidated.destroy', $weeklyItem), false);

        $weeklyCreate = $this->actingAs($cvo)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'finance',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'client_name' => 'CVO Finance Client',
            'campaign_name' => 'CVO Finance Weekly Review',
            'deliverables' => 'CVO can manage a weekly row outside their own department.',
            'status' => 'Planned',
        ]);

        $weeklyCreate->assertSessionHasNoErrors();
        $this->assertDatabaseHas('weekly_consolidated_items', [
            'department' => 'finance',
            'campaign_name' => 'CVO Finance Weekly Review',
            'created_by' => $cvo->id,
        ]);

        $columnCreate = $this->actingAs($cvo)->post(route('portal.dashboard.columns.store'), [
            'department' => 'finance',
            'label' => 'CVO Review Note',
            'type' => 'text',
        ]);

        $columnCreate->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dashboard_columns', [
            'department' => 'finance',
            'label' => 'CVO Review Note',
        ]);
    }

    public function test_staff_cannot_create_weekly_consolidated_item(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $response = $this->actingAs($staff)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'finance',
            'week_start' => now()->startOfWeek()->toDateString(),
            'deliverables' => 'Should not be allowed.',
            'status' => 'Planned',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('weekly_consolidated_items', [
            'deliverables' => 'Should not be allowed.',
        ]);
    }

    public function test_manager_weekly_columns_are_personal_and_supporting_roles_are_saved(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $otherManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $support = User::factory()->create([
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.columns.store'), [
            'department' => 'creatives',
            'label' => 'Client Risk Notes',
            'type' => 'rich_text',
        ])->assertSessionHasNoErrors();

        $column = WeeklyConsolidatedColumn::where('user_id', $manager->id)
            ->where('department', 'creatives')
            ->where('label', 'Client Risk Notes')
            ->first();
        $this->assertNotNull($column);

        $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'creatives',
            'week_start' => now()->startOfWeek()->toDateString(),
            'client_name' => 'Guinness',
            'campaign_name' => 'Trade Visit Review',
            'lead_staff_id' => $manager->id,
            'supporting_staff_ids' => [$support->id],
            'supporting_roles' => ['Retail audit support'],
            'deliverables' => '<p>Review all brand outlets.</p>',
            'target_breakdown' => '<table><tr><td>Target</td><td>25</td></tr></table>',
            'achieved_breakdown' => '<p>10 completed</p>',
            'gap_breakdown' => '<p>15 to go</p>',
            'custom_fields' => [
                $column->column_key => '<p>Client risk is low.</p>',
            ],
            'status' => 'In Progress',
        ])->assertSessionHasNoErrors();

        $item = WeeklyConsolidatedItem::where('campaign_name', 'Trade Visit Review')->firstOrFail();
        $this->assertSame([$support->id], $item->supporting_staff_ids);
        $this->assertSame('Retail audit support', $item->supporting_roles[$support->id] ?? null);
        $this->assertSame('<p>Client risk is low.</p>', $item->custom_fields[$column->column_key] ?? null);

        $dashboard = $this->actingAs($manager)->get(route('dashboard', ['weekly_department' => 'creatives']));
        $dashboard->assertOk();
        $dashboard->assertSee('Manage Columns');
        $dashboard->assertSee('Client Risk Notes');
        $dashboard->assertSee('Retail audit support');
        $dashboard->assertSee('<table><tr><td>Target</td><td>25</td></tr></table>', false);

        $otherDashboard = $this->actingAs($otherManager)->get(route('dashboard', ['weekly_department' => 'creatives']));
        $otherDashboard->assertOk();
        $otherDashboard->assertSee('Client Risk Notes');
        $otherDashboard->assertDontSee(route('portal.dashboard.weekly-consolidated.columns.update', $column), false);
    }

    public function test_brands_line_manager_can_create_update_and_delete_weekly_consolidated_rows(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'staff',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'Brands & Marketing',
        ]);
        $lead = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'brands_marketing',
        ]);

        $createResponse = $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'brands_marketing',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->addDays(10)->toDateString(),
            'client_name' => 'Guinness retail visibility mockup',
            'campaign_name' => 'Guinness Trade Campaign',
            'lead_staff_id' => $lead->id,
            'deliverables' => '<p>Create visibility assets and activation mockups.</p>',
            'priority' => 'High',
            'status' => 'In Progress',
            'progress_percent' => 35,
        ]);

        $createResponse->assertSessionHasNoErrors();
        $createResponse->assertRedirect();

        $item = WeeklyConsolidatedItem::where('campaign_name', 'Guinness Trade Campaign')->firstOrFail();
        $this->assertSame('brands_marketing', $item->department);
        $this->assertSame('High', $item->priority);
        $this->assertSame(35, $item->progress_percent);

        $dashboard = $this->actingAs($manager)->get(route('dashboard', [
            'weekly_department' => 'brands_marketing',
        ]));

        $dashboard->assertOk();
        $dashboard->assertSeeTextInOrder([
            'Task ID',
            'Project',
            'Project Brief',
            'Task Name',
            'Assigned To',
            'Due Date',
            'Priority',
            'Status',
            'Progress %',
        ]);
        $dashboard->assertSee('Guinness Trade Campaign');
        $dashboard->assertSee('35%');
        $dashboard->assertSee(route('portal.dashboard.weekly-consolidated.update', $item), false);
        $dashboard->assertSee(route('portal.dashboard.weekly-consolidated.destroy', $item), false);

        $updateResponse = $this->actingAs($manager)->patch(route('portal.dashboard.weekly-consolidated.update', $item), [
            'department' => 'brands_marketing',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->addDays(12)->toDateString(),
            'client_name' => 'Updated task name',
            'campaign_name' => 'Updated Brands Project',
            'lead_staff_id' => $lead->id,
            'deliverables' => '<p>Updated project brief.</p>',
            'priority' => 'Urgent',
            'status' => 'Done',
            'progress_percent' => 100,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $updateResponse->assertRedirect();

        $item->refresh();
        $this->assertSame('Updated Brands Project', $item->campaign_name);
        $this->assertSame('Updated task name', $item->client_name);
        $this->assertSame('Urgent', $item->priority);
        $this->assertSame('Done', $item->status);
        $this->assertSame(100, $item->progress_percent);

        $deleteResponse = $this->actingAs($manager)->delete(route('portal.dashboard.weekly-consolidated.destroy', $item));

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('weekly_consolidated_items', [
            'id' => $item->id,
        ]);
    }

    public function test_all_line_manager_departments_can_create_update_and_delete_weekly_consolidated_rows(): void
    {
        $departments = [
            ['hr_admin', 'HR & Admin'],
            ['finance', 'finance'],
            ['client_relations', 'Client Relations'],
            ['operations_projects', 'Operations Projects'],
            ['brands_marketing', 'Brands & Marketing'],
            ['creatives', 'Creative Department'],
        ];

        foreach ($departments as [$department, $managerDepartment]) {
            $manager = User::factory()->create([
                'access_role' => 'staff',
                'job_level' => 'manager',
                'status' => 'active',
                'department' => $managerDepartment,
            ]);
            $lead = User::factory()->create([
                'access_role' => 'staff',
                'status' => 'active',
                'department' => $department,
            ]);

            $createResponse = $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), [
                'department' => $managerDepartment,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
                'client_name' => "Client {$department}",
                'campaign_name' => "Campaign {$department}",
                'lead_staff_id' => $lead->id,
                'deliverables' => "Weekly work for {$department}.",
                'priority' => 'Medium',
                'status' => 'In Progress',
                'progress_percent' => 40,
            ]);

            $createResponse->assertSessionHasNoErrors();

            $item = WeeklyConsolidatedItem::where('campaign_name', "Campaign {$department}")->firstOrFail();
            $this->assertSame($department, $item->department);

            $updateResponse = $this->actingAs($manager)->patch(route('portal.dashboard.weekly-consolidated.update', $item), [
                'department' => $managerDepartment,
                'week_start' => now()->startOfWeek()->toDateString(),
                'week_end' => now()->endOfWeek()->toDateString(),
                'client_name' => "Updated Client {$department}",
                'campaign_name' => "Updated Campaign {$department}",
                'lead_staff_id' => $lead->id,
                'deliverables' => "Updated weekly work for {$department}.",
                'priority' => 'High',
                'status' => 'Done',
                'progress_percent' => 100,
            ]);

            $updateResponse->assertSessionHasNoErrors();

            $item->refresh();
            $this->assertSame("Updated Campaign {$department}", $item->campaign_name);
            $this->assertSame('Done', $item->status);
            $this->assertSame(100, $item->progress_percent);

            $deleteResponse = $this->actingAs($manager)->delete(route('portal.dashboard.weekly-consolidated.destroy', $item));

            $deleteResponse->assertRedirect();
            $this->assertDatabaseMissing('weekly_consolidated_items', [
                'id' => $item->id,
            ]);
        }
    }

    public function test_dashboard_server_side_search_filtering_resets_page(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'department' => 'finance',
        ]);

        // Create 15 tasks to trigger pagination (10 per page)
        for ($i = 1; $i <= 15; $i++) {
            Task::create([
                'title' => $i === 12 ? 'Special Search Task' : "Finance Task {$i}",
                'assigned_to' => $staff->id,
                'assigned_by' => $manager->id,
                'department' => 'finance',
                'status' => 'Open',
                'priority' => 'Medium',
                'due_on' => now()->addDays($i),
            ]);
        }

        // Search for the special task
        $response = $this->actingAs($manager)->get(route('dashboard', [
            'tab' => 'finance',
            'search_mega_finance' => 'Special Search',
            'mega_finance_page' => 2, // Emulate being on page 2 before search
        ]));

        $response->assertOk();
        $response->assertSee('Special Search Task');
        $response->assertDontSee('Finance Task 1');

        $response->assertViewHas('departmentTables', function ($tables) {
            $paginator = $tables['finance'] ?? null;
            return $paginator && $paginator->currentPage() === 1 && $paginator->total() === 1;
        });
    }
}
