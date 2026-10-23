<?php

namespace Tests\Feature;

use App\Models\PerformanceRegister;
use App\Models\ProjectBudget;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceSourceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_and_approved_budget_link_once_without_inventing_results(): void
    {
        $manager = User::factory()->create(['department' => 'operations_projects', 'access_role' => 'manager', 'status' => 'active']);
        $owner = User::factory()->create(['department' => 'operations_projects', 'status' => 'active']);
        $task = Task::create(['title' => 'Source integration QA', 'client_name' => 'Example client', 'department' => 'operations_projects', 'assigned_to' => $owner->id, 'assigned_by' => $manager->id, 'status' => 'In Progress', 'progress' => 50]);
        $budget = ProjectBudget::create(['created_by' => $manager->id, 'task_id' => $task->id, 'title' => 'Approved plan', 'total_amount' => 500, 'currency' => 'GHC', 'status' => 'CVO Approved']);
        $payload = ['task_id' => $task->id, 'budget_id' => $budget->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
        $this->actingAs($manager)->post(route('portal.performance.sources.task'), $payload)->assertRedirect();
        $record = PerformanceRegister::firstOrFail();
        $this->assertEquals(500, $record->data['budget']);
        $this->assertSame($task->id, $record->data['_source_task_id']);
        $this->assertNull($record->approved_at);
        $this->assertArrayNotHasKey('actual_cost', $record->data);
        $this->assertArrayNotHasKey('revenue', $record->data);
        $this->actingAs($manager)->post(route('portal.performance.sources.task'), $payload)->assertRedirect();
        $this->assertDatabaseCount('performance_registers', 1);
        $this->actingAs($owner)->post(route('portal.performance.sources.task'), $payload)->assertForbidden();
        $outsider = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($outsider)->post(route('portal.performance.sources.task'), $payload)->assertForbidden();
        $token = hash('sha256', json_encode($record->fresh()->getAttributes()));
        $correction = ['token' => $token, 'reason' => 'Verified execution costs', 'data' => ['budget' => 999, 'actual_cost' => 250]];
        $this->actingAs($manager)->post(route('portal.performance.sources.correct', $record), $correction)->assertRedirect();
        $this->assertEquals(500, $record->fresh()->data['budget']);
        $this->assertEquals(250, $record->fresh()->data['actual_cost']);
        $this->assertNull($record->fresh()->approved_at);
        $this->actingAs($manager)->post(route('portal.performance.sources.correct', $record), $correction)->assertStatus(409);
        $record->update(['approved_at' => now()]);
        $correction['token'] = hash('sha256', json_encode($record->fresh()->getAttributes()));
        $this->actingAs($manager)->post(route('portal.performance.sources.correct', $record), $correction)->assertStatus(409);
        $budget->update(['status' => 'Draft']);
        $this->actingAs($manager)->post(route('portal.performance.sources.task'), $payload)->assertStatus(422);
    }
}
