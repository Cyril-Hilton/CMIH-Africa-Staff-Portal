<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class TaskPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_tasks_pagination_returns_the_requested_page_and_stable_silent_region(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'operations_projects',
        ]);

        foreach (range(1, 10) as $index) {
            $this->createTask($user, $user, sprintf('Personal Task %02d', $index));
        }

        $response = $this->actingAs($user)->get('/portal/tasks?view=my-tasks&sort=created&direction=asc&my_page=2');

        $response->assertOk()
            ->assertSee('data-silent-region="my-task-list"', false)
            ->assertSee('Personal Task 09')
            ->assertSee('Personal Task 10')
            ->assertDontSee('Personal Task 01');
        $response->assertViewHas('myTasks', fn ($tasks) => $tasks instanceof LengthAwarePaginator
            && $tasks->currentPage() === 2
            && $tasks->total() === 10);
    }

    public function test_pending_task_pagination_preserves_pending_view_and_filters(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'operations_projects',
        ]);

        foreach (range(1, 16) as $index) {
            $this->createTask($manager, $manager, sprintf('Pending Queue Task %02d', $index));
        }

        $response = $this->actingAs($manager)->get('/portal/tasks?view=pending&filter=high_priority');

        $response->assertOk()
            ->assertSee('data-silent-region="pending-task-list"', false);

        $html = html_entity_decode($response->getContent());
        preg_match('/href="([^"]*p_page=2[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);
        $this->assertSame('pending', $query['view'] ?? null);
        $this->assertSame('high_priority', $query['filter'] ?? null);

        $secondPage = $this->actingAs($manager)->get('/portal/tasks?view=pending&filter=high_priority&p_page=2');
        $secondPage->assertOk()->assertSee('Pending Queue Task 16');
        $secondPage->assertViewHas('pendingTasks', fn ($tasks) => $tasks instanceof LengthAwarePaginator
            && $tasks->currentPage() === 2
            && $tasks->total() === 16);
    }

    public function test_approval_queue_pagination_keeps_pending_view_and_other_table_page(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'position_title' => 'Department Head',
            'department' => 'operations_projects',
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'client_relations',
        ]);

        foreach (range(1, 11) as $index) {
            Task::create([
                'title' => sprintf('Approval Queue Task %02d', $index),
                'assigned_to' => $staff->id,
                'assigned_by' => $staff->id,
                'department' => 'client_relations',
                'status' => 'Awaiting Approval',
                'priority' => 'Medium',
                'progress' => 95,
                'completion_review_status' => 'pending',
                'copied_manager_ids' => [$manager->id],
            ]);
        }

        $response = $this->actingAs($manager)->get('/portal/tasks?view=pending&p_page=2&approval_page=1');

        $response->assertOk()
            ->assertSee('data-silent-region="task-approval-queue"', false);

        $html = html_entity_decode($response->getContent());
        preg_match('/href="([^"]*approval_page=2[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);
        $this->assertSame('pending', $query['view'] ?? null);
        $this->assertSame('2', (string) ($query['p_page'] ?? ''));

        $secondPage = $this->actingAs($manager)->get('/portal/tasks?view=pending&p_page=2&approval_page=2');
        $secondPage->assertOk()->assertSee('Approval Queue Task 01');
        $secondPage->assertViewHas('myPendingApprovals', fn ($tasks) => $tasks instanceof LengthAwarePaginator
            && $tasks->currentPage() === 2
            && $tasks->total() === 11);
    }

    private function createTask(User $assignee, User $assigner, string $title): Task
    {
        return Task::create([
            'title' => $title,
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'High',
            'progress' => 10,
        ]);
    }
}
