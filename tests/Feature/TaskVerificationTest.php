<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected array $staffMembers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $departments = [
            'hr_admin',
            'finance',
            'client_relations',
            'operations_projects',
            'brands_marketing',
            'creatives'
        ];

        foreach ($departments as $dept) {
            $this->staffMembers[$dept] = User::factory()->create([
                'name' => 'Staff ' . strtoupper($dept),
                'email' => $dept . '@cmih.africa',
                'access_role' => 'staff',
                'status' => 'active',
                'department' => $dept,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_my_task_stats_exclude_audit_tasks_and_count_performance_completed_tasks(): void
    {
        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $completedPendingAudit = Task::create([
            'title' => 'Completed but waiting audit',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'High',
            'completion_review_status' => 'pending',
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        $auditTask = Task::create([
            'title' => 'Audit completion: Completed but waiting audit',
            'assigned_to' => $manager->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'High',
            'completion_review_status' => 'audit_task',
            'custom_fields' => ['linked_task_id' => $completedPendingAudit->id],
        ]);
        $completedPendingAudit->forceFill(['completion_review_task_id' => $auditTask->id])->save();

        Task::create([
            'title' => 'Approved completed work',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => $manager->id,
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        Task::create([
            'title' => 'Second review-manager approved work',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => $manager->id,
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        $response = $this->actingAs($staff)->get(route('portal.tasks'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('myTotal'));
        $this->assertSame(3, $response->viewData('myCreatedTotal'));
        $this->assertSame(2, $response->viewData('myCompleted'));
        $this->assertSame(2, $response->viewData('myApproved'));
        $this->assertSame(1, $response->viewData('myInProgress'));
        $this->assertSame(0, $response->viewData('myOverdue'));
    }

    public function test_line_manager_my_task_stats_show_tasks_approved_by_that_manager(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $staff = $this->staffMembers['creatives'];
        $staff->update(['line_manager_id' => $manager->id]);

        Task::create([
            'title' => 'Manager Approved First Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => $manager->id,
            'completion_reviewed_at' => now(),
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        Task::create([
            'title' => 'Manager Approved Second Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => $manager->id,
            'completion_reviewed_at' => now(),
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        Task::create([
            'title' => 'Approved By Another Manager',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => User::factory()->create([
                'access_role' => 'manager',
                'status' => 'active',
            ])->id,
            'completion_reviewed_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('portal.tasks'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('myApproved'));
        $this->assertSame('Approved by you', $response->viewData('myApprovalLabel'));
    }

    public function test_my_task_stats_ignore_tasks_created_before_july_first_cycle_start(): void
    {
        $staff = $this->staffMembers['creatives'];

        $oldTask = Task::create([
            'title' => 'Old June Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);
        $oldTask->forceFill(['created_at' => Carbon::parse('2026-06-30 17:00:00')])->save();

        $currentTask = Task::create([
            'title' => 'Current July Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);
        $currentTask->forceFill(['created_at' => Carbon::parse('2026-07-01 09:00:00')])->save();

        $response = $this->actingAs($staff)->get(route('portal.tasks'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('myTotal'));
        $this->assertSame(1, $response->viewData('myCreatedTotal'));
        $this->assertSame(1, $response->viewData('myCompleted'));
        $response->assertDontSee('Old June Task');
        $response->assertSee('Current July Task');
    }

    public function test_my_task_stats_count_assigned_created_and_supporting_tasks(): void
    {
        $staff = $this->staffMembers['creatives'];
        $coworker = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        Task::create([
            'title' => 'Assigned to me',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        Task::create([
            'title' => 'Created by me for someone else',
            'assigned_to' => $coworker->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        Task::create([
            'title' => 'Supporting only',
            'assigned_to' => $coworker->id,
            'assigned_by' => $coworker->id,
            'supporting_staff_ids' => [$staff->id],
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($staff)->get(route('portal.tasks'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('myTotal'));
        $this->assertSame(2, $response->viewData('myCreatedTotal'));
        $this->assertSame(0, $response->viewData('myCompleted'));
        $this->assertSame(0, $response->viewData('myApproved'));
        $this->assertSame(3, $response->viewData('myInProgress'));
        $this->assertSame(3, $response->viewData('myTasks')->total());
    }

    public function test_cyril_can_complete_supporting_task_that_counts_toward_his_score(): void
    {
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'email' => 'cyrilhilton@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $coworker = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Collaborative render cleanup',
            'details' => 'Cyril is responsible for the final render pass.',
            'assigned_to' => $coworker->id,
            'assigned_by' => $coworker->id,
            'supporting_staff_ids' => [$cyril->id],
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($cyril)->patch(route('portal.tasks.update', $task), [
            'title' => 'Collaborative render cleanup',
            'details' => 'Cyril is responsible for the final render pass.',
            'priority' => 'Medium',
            'status' => 'Completed',
            'supporting_staff_ids' => [$cyril->id],
            'copied_manager_ids' => [],
            'supporting_roles' => 'Final 3D/4D render pass',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);

        $dashboard = $this->actingAs($cyril)->get(route('dashboard'));

        $dashboard->assertOk();
        $dashboard->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['completion_rate'] === 100
                && (int) $stats['open_deliverables'] === 0;
        });
    }

    public function test_create_task_page_does_not_prompt_second_clock_in_when_already_clocked_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => Carbon::parse('2026-07-14 08:30:00'),
            'daily_objective' => 'First daily task',
            'status' => 'On Time',
        ]);

        Task::create([
            'title' => 'First daily task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($staff)->post(route('portal.tasks.store'), [
            'title' => 'Second daily task',
            'details' => 'Extra task update for the same workday.',
            'status' => 'Open',
            'priority' => 'medium',
            'due_on' => '2026-07-14',
            'completion_manager_id' => $manager->id,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response->assertRedirect(route('portal.tasks', ['view' => 'create']));
        $response->assertSessionHas('status', 'Task created successfully. You are already clocked in for today.');

        $createPage = $this->actingAs($staff)->get(route('portal.tasks', ['view' => 'create']));

        $createPage->assertOk();
        $createPage->assertSee("Today's clock-in is complete", false);
        $createPage->assertDontSee('Clock In Now');
        $createPage->assertDontSee('Go Clock In');
    }

    public function test_task_awaiting_approval_notifies_approvers(): void
    {
        $owner = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
        ]);
        $superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        $task = Task::create([
            'title' => 'Approval Required Task',
            'details' => 'Goal details',
            'assigned_to' => $owner->id,
            'assigned_by' => $manager->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($owner)->patch(route('portal.tasks.update', $task), [
            'title' => 'Approval Required Task',
            'details' => 'Goal details',
            'status' => 'Awaiting Approval',
            'priority' => 'Medium',
            'progress' => 95,
            'completion_manager_id' => $manager->id,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'title' => 'Task Approval Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'Task Approval Needed',
        ]);
    }

    /**
     * Test that once someone creates a task, if the person is a creative (or any department),
     * the task shows on The Mega Table on the dashboard under that department.
     */
    public function test_tasks_are_seen_on_the_mega_table_for_all_departments_staff(): void
    {
        $creativeUser = $this->staffMembers['creatives'];
        $financeUser = $this->staffMembers['finance'];
        $creativeManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $creativeUser->update(['line_manager_id' => $creativeManager->id]);

        // 1. Creative User creates a task
        $this->actingAs($creativeUser);

        $response = $this->post(route('portal.tasks.store'), [
            'title' => 'Design CMIH Logo Redux',
            'details' => 'Re-illustrate high-res assets',
            'priority' => 'high',
            'due_on' => now()->addDays(2)->toDateString(),
            'completion_manager_id' => $creativeManager->id,
        ]);

        $response->assertRedirect();
        
        $task = Task::where('title', 'Design CMIH Logo Redux')->first();
        $this->assertNotNull($task);
        $this->assertEquals('creatives', $task->department);

        // 2. Creative user views dashboard and sees it under Creatives
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Design CMIH Logo Redux');

        // 3. Finance user views dashboard and can also see it (Mega Table is shared)
        $this->actingAs($financeUser);
        $dashboardResponseForFinance = $this->get(route('dashboard'));
        $dashboardResponseForFinance->assertStatus(200);
        $dashboardResponseForFinance->assertSee('Design CMIH Logo Redux');
    }

    public function test_mega_table_hides_tasks_created_before_july_first_cycle_start(): void
    {
        $staff = $this->staffMembers['creatives'];

        $oldTask = Task::create([
            'title' => 'April Mega Table Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);
        $oldTask->forceFill(['created_at' => Carbon::parse('2026-04-15 09:00:00')])->save();

        $currentTask = Task::create([
            'title' => 'July Mega Table Task',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);
        $currentTask->forceFill(['created_at' => Carbon::parse('2026-07-01 09:00:00')])->save();

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('April Mega Table Task');
        $response->assertSee('July Mega Table Task');
    }

    public function test_staff_task_creation_requires_manually_selected_line_manager(): void
    {
        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $staff->update(['line_manager_id' => $manager->id]);

        $response = $this->actingAs($staff)->post(route('portal.tasks.store'), [
            'title' => 'Line Manager Copied Task',
            'details' => 'Needs the selected line manager copied.',
            'priority' => 'medium',
            'due_on' => now()->addDay()->toDateString(),
            'completion_manager_id' => $manager->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task = Task::where('title', 'Line Manager Copied Task')->firstOrFail();
        $this->assertContains($manager->id, array_map('intval', $task->copied_manager_ids ?? []));
        $this->assertSame($manager->id, (int) ($task->custom_fields['completion_manager_id'] ?? 0));
    }

    public function test_staff_task_creation_requires_a_selected_line_manager(): void
    {
        $staff = $this->staffMembers['creatives'];

        $response = $this->actingAs($staff)->from(route('portal.tasks', ['view' => 'create']))->post(route('portal.tasks.store'), [
            'title' => 'Missing Manager Task',
            'details' => 'This should not save without a selected line manager.',
            'priority' => 'medium',
            'due_on' => now()->addDay()->toDateString(),
        ]);

        $response->assertRedirect(route('portal.tasks', ['view' => 'create']));
        $response->assertSessionHasErrors('completion_manager_id');
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Missing Manager Task',
        ]);
    }

    public function test_staff_completion_requires_manager_approval_before_mega_table(): void
    {
        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $staff->update(['line_manager_id' => $manager->id]);

        $task = Task::create([
            'title' => 'Needs Completion Audit',
            'details' => 'Work must be reviewed before the Mega Table.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'progress' => 50,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response = $this->actingAs($staff)->patch(route('portal.tasks.update', $task), [
            'title' => 'Needs Completion Audit',
            'details' => 'Work must be reviewed before the Mega Table.',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
            'completion_manager_id' => $manager->id,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Awaiting Approval', $task->status);
        $this->assertSame(95, $task->progress);
        $this->assertSame('pending', $task->completion_review_status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'title' => 'Task Completion Audit Needed',
        ]);

        $reviewTask = Task::where('assigned_to', $manager->id)
            ->where('completion_review_status', 'audit_task')
            ->first();
        $this->assertNotNull($reviewTask);

        $dashboardBeforeApproval = $this->actingAs($staff)->get(route('dashboard'));
        $dashboardBeforeApproval->assertOk();
        $dashboardBeforeApproval->assertViewHas('departmentTables', function ($tables) use ($task) {
            return ! $tables['creatives']->contains('id', $task->id);
        });

        $approvalResponse = $this->actingAs($manager)->post(route('portal.tasks.completion-review', $task), [
            'action' => 'approve',
            'review_comment' => 'Verified and approved.',
        ]);

        $approvalResponse->assertRedirect();
        $approvalResponse->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertSame('approved', $task->completion_review_status);

        $dashboardAfterApproval = $this->actingAs($staff)->get(route('dashboard'));
        $dashboardAfterApproval->assertOk();
        $dashboardAfterApproval->assertViewHas('departmentTables', function ($tables) use ($task) {
            return $tables['creatives']->contains('id', $task->id);
        });
    }

    public function test_line_manager_can_complete_task_without_completion_review(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Manager Direct Completion',
            'details' => 'Manager can complete directly.',
            'assigned_to' => $manager->id,
            'assigned_by' => $manager->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'progress' => 50,
        ]);

        $response = $this->actingAs($manager)->patch(route('portal.tasks.update', $task), [
            'title' => 'Manager Direct Completion',
            'details' => 'Manager can complete directly.',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNull($task->completion_review_status);
    }

    public function test_cyril_can_complete_assigned_task_without_curtis_approval(): void
    {
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Cyril Assigned Completion',
            'details' => 'Cyril should complete directly.',
            'assigned_to' => $cyril->id,
            'assigned_by' => $cyril->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'progress' => 50,
        ]);

        $response = $this->actingAs($cyril)->patch(route('portal.tasks.update', $task), [
            'title' => 'Cyril Assigned Completion',
            'details' => 'Cyril should complete directly.',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNull($task->completion_review_status);
    }

    public function test_cyril_can_complete_supporting_staff_task_without_curtis_approval(): void
    {
        $owner = $this->staffMembers['creatives'];
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Cyril Supporting Completion',
            'details' => 'Cyril is supporting staff and can complete his work directly.',
            'assigned_to' => $owner->id,
            'assigned_by' => $owner->id,
            'department' => 'creatives',
            'status' => 'In Progress',
            'priority' => 'High',
            'progress' => 50,
            'supporting_staff_ids' => [$cyril->id],
        ]);

        $response = $this->actingAs($cyril)->patch(route('portal.tasks.update', $task), [
            'title' => 'Cyril Supporting Completion',
            'details' => 'Cyril is supporting staff and can complete his work directly.',
            'status' => 'Completed',
            'priority' => 'High',
            'progress' => 100,
            'supporting_staff_ids' => [$cyril->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNull($task->completion_review_status);
        $this->assertSame([$cyril->id], array_map('intval', $task->supporting_staff_ids ?? []));
    }

    public function test_cyril_can_clear_existing_pending_completion_review_on_his_task(): void
    {
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $curtis = User::factory()->create([
            'name' => 'Curtis Barnor',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Old Cyril Pending Completion',
            'details' => 'This task was waiting for Curtis before Cyril became a line manager.',
            'assigned_to' => $cyril->id,
            'assigned_by' => $cyril->id,
            'department' => 'creatives',
            'status' => 'Awaiting Approval',
            'priority' => 'High',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'completion_review_requested_at' => now(),
            'copied_manager_ids' => [$curtis->id],
            'custom_fields' => ['completion_manager_id' => $curtis->id],
        ]);
        $reviewTask = Task::create([
            'title' => 'Audit completion: Old Cyril Pending Completion',
            'details' => 'Old review task.',
            'assigned_to' => $curtis->id,
            'assigned_by' => $cyril->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'High',
            'completion_review_status' => 'audit_task',
        ]);
        $task->forceFill(['completion_review_task_id' => $reviewTask->id])->save();

        $response = $this->actingAs($cyril)->patch(route('portal.tasks.update', $task), [
            'title' => 'Old Cyril Pending Completion',
            'details' => 'This task was waiting for Curtis before Cyril became a line manager.',
            'status' => 'Completed',
            'priority' => 'High',
            'progress' => 100,
            'copied_manager_ids' => [$curtis->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertSame('approved', $task->completion_review_status);
        $this->assertSame($cyril->id, (int) $task->completion_reviewed_by);

        $reviewTask->refresh();
        $this->assertSame('Completed', $reviewTask->status);
        $this->assertSame(100, $reviewTask->progress);
        $this->assertSame('audit_task', $reviewTask->completion_review_status);
    }

    public function test_task_edit_hides_progress_field_and_status_drives_progress(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $task = Task::create([
            'title' => 'Status Driven Progress',
            'details' => 'No manual progress field should be needed.',
            'assigned_to' => $manager->id,
            'assigned_by' => $manager->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($manager)->get(route('portal.tasks.edit', $task));

        $response->assertOk();
        $response->assertDontSee('Progress (%)');
        $response->assertSee('Completed — 100%', false);

        $update = $this->actingAs($manager)->patch(route('portal.tasks.update', $task), [
            'title' => 'Status Driven Progress',
            'details' => 'No manual progress field should be needed.',
            'status' => 'Completed',
            'priority' => 'Medium',
        ]);

        $update->assertRedirect();
        $update->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
    }

    public function test_dashboard_completion_rate_waits_for_manager_approval(): void
    {
        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $staff->update(['line_manager_id' => $manager->id]);

        $task = Task::create([
            'title' => 'Score Only After Clearance',
            'details' => 'Self-completed work should not count yet.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Awaiting Approval',
            'priority' => 'Medium',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        $beforeApproval = $this->actingAs($staff)->get(route('dashboard'));

        $beforeApproval->assertOk();
        $beforeApproval->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['completion_rate'] === 0
                && (int) $stats['open_deliverables'] === 1;
        });

        $approval = $this->actingAs($manager)->post(route('portal.tasks.completion-review', $task), [
            'action' => 'approve',
            'review_comment' => 'Verified.',
        ]);

        $approval->assertRedirect();
        $approval->assertSessionHasNoErrors();

        $afterApproval = $this->actingAs($staff)->get(route('dashboard'));

        $afterApproval->assertOk();
        $afterApproval->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['completion_rate'] === 100
                && (int) $stats['open_deliverables'] === 0;
        });
    }

    public function test_dashboard_completion_rate_counts_historical_completed_tasks_before_review_gate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 12:00:00'));

        $staff = $this->staffMembers['creatives'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);
        $staff->update(['line_manager_id' => $manager->id]);

        for ($i = 1; $i <= 8; $i++) {
            $task = Task::create([
                'title' => "Historical Completed Task {$i}",
                'details' => 'Completed before line manager approval existed.',
                'assigned_to' => $staff->id,
                'assigned_by' => $staff->id,
                'department' => 'creatives',
                'status' => 'Completed',
                'priority' => 'Medium',
                'progress' => 100,
                'completion_review_status' => 'pending',
                'copied_manager_ids' => [$manager->id],
                'custom_fields' => ['completion_manager_id' => $manager->id],
            ]);
            $task->forceFill(['created_at' => Carbon::parse('2026-07-01 10:00:00')])->save();
        }

        $retroPendingTask = Task::create([
            'title' => 'Historical Retro Pending Task',
            'details' => 'Was completed historically but later got marked pending by the new approval flow.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Awaiting Approval',
            'priority' => 'Medium',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);
        $retroPendingTask->forceFill(['created_at' => Carbon::parse('2026-07-01 11:00:00')])->save();

        for ($i = 1; $i <= 2; $i++) {
            $task = Task::create([
                'title' => "New Pending Approval Task {$i}",
                'details' => 'New task should still wait for manager approval.',
                'assigned_to' => $staff->id,
                'assigned_by' => $staff->id,
                'department' => 'creatives',
                'status' => 'Awaiting Approval',
                'priority' => 'Medium',
                'progress' => 95,
                'completion_review_status' => 'pending',
                'copied_manager_ids' => [$manager->id],
                'custom_fields' => ['completion_manager_id' => $manager->id],
            ]);
            $task->forceFill(['created_at' => Carbon::parse('2026-07-14 10:00:00')])->save();
        }

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['completion_rate'] === 82
                && (int) $stats['open_deliverables'] === 2;
        });
    }

    public function test_dashboard_active_deliverables_ignore_manager_approved_stale_status_tasks(): void
    {
        $staff = $this->staffMembers['brands_marketing'];
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'brands_marketing',
        ]);
        $staff->update(['line_manager_id' => $manager->id]);

        Task::create([
            'title' => 'Approved But Stale Status',
            'details' => 'Manager approval should be the final source of truth.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'brands_marketing',
            'status' => 'Awaiting Approval',
            'priority' => 'Medium',
            'progress' => 95,
            'completion_review_status' => 'approved',
            'completion_reviewed_by' => $manager->id,
            'completion_reviewed_at' => now(),
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['completion_rate'] === 100
                && (int) $stats['open_deliverables'] === 0;
        });
    }

    public function test_dashboard_punctuality_uses_clock_in_time_when_status_text_is_inconsistent(): void
    {
        $staff = $this->staffMembers['creatives'];
        $staff->update(['start_date' => Carbon::today()]);

        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => Carbon::today()->setTime(8, 45),
            'clock_out_at' => Carbon::today()->setTime(20, 0),
            'status' => 'present',
            'overtime_minutes' => 120,
            'daily_objective' => 'Morning task check.',
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['punctuality_score'] === 100
                && (float) $stats['overtime_hours'] === 2.0;
        });
    }

    public function test_dashboard_punctuality_penalizes_missing_workday_clock_ins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

        $staff = $this->staffMembers['creatives'];
        $staff->update(['start_date' => Carbon::parse('2026-07-01')]);

        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => Carbon::parse('2026-07-01 08:45:00'),
            'clock_out_at' => Carbon::parse('2026-07-01 20:00:00'),
            'status' => 'On Time',
            'overtime_minutes' => 120,
            'daily_objective' => 'Daily task update.',
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('individualStats', function ($stats) {
            return (int) $stats['expected_attendance_days'] === 8
                && (int) $stats['clocked_attendance_days'] === 1
                && (int) $stats['punctuality_score'] === 13;
        });
    }

    public function test_line_manager_editor_completion_clears_pending_review_state(): void
    {
        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $staff = $this->staffMembers['creatives'];
        $staff->update(['line_manager_id' => $manager->id]);

        $reviewTask = Task::create([
            'title' => 'Audit completion: Pending Editor Approval',
            'details' => 'Review task',
            'assigned_to' => $manager->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'High',
            'progress' => 10,
            'completion_review_status' => 'audit_task',
        ]);

        $task = Task::create([
            'title' => 'Pending Editor Approval',
            'details' => 'Approved from edit page.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Awaiting Approval',
            'priority' => 'Medium',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'completion_review_task_id' => $reviewTask->id,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response = $this->actingAs($manager)->patch(route('portal.tasks.update', $task), [
            'title' => 'Pending Editor Approval',
            'details' => 'Approved from edit page.',
            'status' => 'Completed',
            'priority' => 'Medium',
            'progress' => 100,
            'copied_manager_ids' => [$manager->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertSame('approved', $task->completion_review_status);
        $this->assertSame($manager->id, $task->completion_reviewed_by);

        $reviewTask->refresh();
        $this->assertSame('Completed', $reviewTask->status);

        $dashboard = $this->actingAs($staff)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertViewHas('departmentTables', function ($tables) use ($task) {
            return $tables['creatives']->contains('id', $task->id);
        });
    }

    /**
     * Test that internal staff can edit visible CMIH tasks regardless of original owner.
     */
    public function test_staff_can_edit_their_tasks_regardless_of_privilege(): void
    {
        $creativeUser = $this->staffMembers['creatives'];
        $financeUser = $this->staffMembers['finance'];
        $creativeManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        // Creative user creates a task
        $task = Task::create([
            'title' => 'Creatives Task',
            'details' => 'Goal details',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        // Creative user should be able to view edit page
        $this->actingAs($creativeUser);
        $editResponse = $this->get(route('portal.tasks.edit', $task));
        $editResponse->assertStatus(200);

        // Creative user updates the task
        $updateResponse = $this->patch(route('portal.tasks.update', $task), [
            'title' => 'Updated Creatives Task',
            'details' => 'New details',
            'status' => 'In Progress',
            'priority' => 'High',
            'progress' => 50,
            'completion_manager_id' => $creativeManager->id,
        ]);
        $updateResponse->assertRedirect();
        
        $task->refresh();
        $this->assertEquals('Updated Creatives Task', $task->title);
        $this->assertEquals('In Progress', $task->status);
        $this->assertEquals('High', $task->priority);
        $this->assertEquals(50, $task->progress);

        // Unassociated internal staff can see shared work but cannot manage it.
        $this->actingAs($financeUser);
        $financeEditResponse = $this->get(route('portal.tasks.edit', $task));
        $financeEditResponse->assertStatus(403);

        $task->update(['supporting_staff_ids' => [$financeUser->id]]);

        // Once added as a collaborator, finance can help manage the task.
        $financeEditResponse = $this->get(route('portal.tasks.edit', $task));
        $financeEditResponse->assertStatus(200);

        $financeUpdateResponse = $this->patch(route('portal.tasks.update', $task), [
            'title' => 'Finance Assisted Update',
            'status' => 'In Progress',
            'priority' => 'Low',
            'progress' => 50,
            'completion_manager_id' => $creativeManager->id,
        ]);
        $financeUpdateResponse->assertRedirect();

        $task->refresh();
        $this->assertEquals('Finance Assisted Update', $task->title);
        $this->assertEquals('Low', $task->priority);
    }

    public function test_my_tasks_actions_are_visible_without_hover_on_mobile(): void
    {
        $creativeUser = $this->staffMembers['creatives'];

        $task = Task::create([
            'title' => 'Mobile Visible Edit Task',
            'details' => 'Goal details',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($creativeUser)->get(route('portal.tasks'));

        $response->assertStatus(200);
        $response->assertSee('Your Assignments');
        $response->assertSee(route('portal.tasks.edit', $task), false);
        $response->assertDontSee('sm:opacity-0 sm:group-hover:opacity-100', false);
        $response->assertSee('inline-flex flex-1 items-center justify-center', false);
    }

    public function test_task_actions_are_visible_for_tasks_created_by_user_even_if_not_assignee(): void
    {
        $creator = $this->staffMembers['hr_admin'];
        $assignee = $this->staffMembers['creatives'];

        $task = Task::create([
            'title' => 'Creator Should Still Edit',
            'details' => 'Created by HR but assigned to creative',
            'assigned_to' => $assignee->id,
            'assigned_by' => $creator->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($creator)->get(route('portal.tasks'));

        $response->assertOk();
        $response->assertSee('Creator Should Still Edit');
        $response->assertSee(route('portal.tasks.edit', $task), false);
        $response->assertSee(route('portal.tasks.destroy', $task), false);
    }

    public function test_mega_table_actions_are_not_hidden_until_hover(): void
    {
        $creativeUser = $this->staffMembers['creatives'];

        $task = Task::create([
            'title' => 'Always Visible Mega Action',
            'details' => 'Goal details',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($creativeUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Always Visible Mega Action');
        $response->assertSee(route('portal.tasks.edit', $task), false);
        $response->assertDontSee('lg:opacity-0 lg:group-hover:opacity-100', false);
    }

    public function test_mega_table_actions_are_visible_to_task_collaborators(): void
    {
        $creativeUser = $this->staffMembers['creatives'];
        $financeUser = $this->staffMembers['finance'];

        $task = Task::create([
            'title' => 'Shared Mega Action Task',
            'details' => 'Finance can see the shared action controls as a collaborator.',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'supporting_staff_ids' => [$financeUser->id],
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($financeUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Shared Mega Action Task');
        $response->assertSee(route('portal.tasks.edit', $task), false);
        $response->assertSee(route('portal.tasks.destroy', $task), false);
        $response->assertSee('openReassignModal('.$task->id, false);
    }

    public function test_mega_table_includes_tasks_where_department_staff_are_supporting_staff(): void
    {
        $creativeUser = $this->staffMembers['creatives'];
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'access_role' => 'super_admin',
            'status' => 'active',
            'department' => 'executive',
        ]);

        Task::create([
            'title' => 'Supporting Creative Should Appear In Creatives',
            'details' => 'Created from executive but supported by creatives.',
            'assigned_to' => $superAdmin->id,
            'assigned_by' => $superAdmin->id,
            'supporting_staff_ids' => [$creativeUser->id],
            'department' => 'executive',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($creativeUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Supporting Creative Should Appear In Creatives');
        $response->assertSee('data-cross-department-lead="true"', false);
        $response->assertSee('External lead', false);
    }

    /**
     * Test that task collaborators can delete visible Mega Table tasks, but unassociated users and merchandisers cannot.
     */
    public function test_task_collaborators_can_delete_cmih_tasks_but_unassociated_users_cannot(): void
    {
        $creativeUser = $this->staffMembers['creatives'];
        $financeUser = $this->staffMembers['finance'];
        $merchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        // Creative user creates a task
        $task = Task::create([
            'title' => 'Creatives Task to Delete',
            'details' => 'Delete me details',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        // Merchandiser users stay outside CMIH task management.
        $this->actingAs($merchandiser);
        $forbiddenDeleteResponse = $this->delete(route('portal.tasks.destroy', $task));
        $forbiddenDeleteResponse->assertStatus(403);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        // Unassociated internal staff cannot manage the task.
        $this->actingAs($financeUser);
        $deleteResponse = $this->delete(route('portal.tasks.destroy', $task));
        $deleteResponse->assertStatus(403);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        // Once added as a collaborator, finance can manage the shared task.
        $task->update(['supporting_staff_ids' => [$financeUser->id]]);

        $deleteResponse = $this->delete(route('portal.tasks.destroy', $task));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /**
     * Test that status and progress are automatically synchronized at the model level.
     */
    public function test_task_status_and_progress_are_synchronized(): void
    {
        $creativeUser = $this->staffMembers['creatives'];

        // 1. Create task
        $task = Task::create([
            'title' => 'Sync Test Task',
            'details' => 'Details',
            'assigned_to' => $creativeUser->id,
            'assigned_by' => $creativeUser->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
            'progress' => 0,
        ]);

        $this->assertEquals(10, $task->progress);

        // 2. Set status to Completed, verify progress is 100
        $task->update(['status' => 'Completed']);
        $task->refresh();
        $this->assertEquals(100, $task->progress);

        // 3. Set status back to In Progress, set progress to 100, verify status becomes Completed
        $task->update(['status' => 'In Progress', 'progress' => 50]);
        $task->refresh();
        $this->assertEquals('In Progress', $task->status);

        $task->update(['progress' => 100]);
        $task->refresh();
        $this->assertEquals('Completed', $task->status);

        // 4. Move progress back to 50 on Completed task, verify status reverts to In Progress
        $task->update(['progress' => 50]);
        $task->refresh();
        $this->assertEquals('In Progress', $task->status);

        // 5. Set status to Approved, verify progress is 100
        $task->update(['status' => 'Approved']);
        $task->refresh();
        $this->assertEquals(100, $task->progress);
    }
}
