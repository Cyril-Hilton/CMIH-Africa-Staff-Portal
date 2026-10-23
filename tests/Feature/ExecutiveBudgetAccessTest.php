<?php

namespace Tests\Feature;

use App\Models\ProjectBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveBudgetAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cvo_can_access_another_departments_budget_without_collaborator_membership(): void
    {
        $owner = User::factory()->create(['status' => 'active', 'department' => 'operations_projects']);
        $budget = ProjectBudget::create(['created_by' => $owner->id, 'title' => 'Access QA Budget', 'total_amount' => 100, 'currency' => 'GHC', 'status' => 'Draft']);
        $cvo = User::factory()->create(['status' => 'active', 'access_role' => 'staff', 'job_level' => 'CVO', 'department' => 'creatives']);
        $this->assertTrue($budget->canView($cvo));
        $this->assertTrue($budget->canEdit($cvo));
        $this->actingAs($cvo)->get(route('portal.finance.budgets.show', $budget))->assertOk();
        $outsider = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'access_role' => 'staff']);
        $this->assertFalse($budget->canView($outsider));
        $this->assertFalse($budget->canEdit($outsider));
        $this->actingAs($outsider)->get(route('portal.finance.budgets.show', $budget))->assertForbidden();
    }

    public function test_cvo_can_open_workspace_documents_while_unassigned_staff_cannot(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $document = \App\Models\CollaborativeDocument::create(['title' => 'Executive access QA', 'doc_type' => 'text', 'content' => 'Example', 'created_by' => $owner->id, 'current_holder_id' => $owner->id, 'status' => 'draft']);
        $cvo = User::factory()->create(['status' => 'active', 'access_role' => 'staff', 'job_level' => 'CVO']);
        $this->actingAs($cvo)->get(route('portal.workspace.show', $document))->assertOk();
        $outsider = User::factory()->create(['status' => 'active', 'access_role' => 'staff']);
        $this->actingAs($outsider)->get(route('portal.workspace.show', $document))->assertForbidden();
    }
}
