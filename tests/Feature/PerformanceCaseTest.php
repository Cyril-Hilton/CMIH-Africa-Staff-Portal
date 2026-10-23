<?php

namespace Tests\Feature;

use App\Models\PerformanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_requires_owner_evidence_independent_review_and_closed_children(): void
    {
        $manager = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        $owner = User::factory()->create(['department' => 'creatives', 'access_role' => 'staff', 'status' => 'active']);
        $payload = ['department' => 'creatives', 'kind' => 'issue', 'title' => 'Missing approval', 'description' => 'Client review delayed', 'owner_id' => $owner->id, 'reviewer_id' => $manager->id, 'business_impact' => 'Artwork delivery blocked', 'due_on' => '2026-10-23', 'priority' => 'High'];
        $this->actingAs($manager)->post(route('portal.performance.cases.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $case = PerformanceCase::first();
        $this->get(route('portal.performance.cases'))->assertOk()->assertSee('Missing approval');
        $transition = ['action' => 'submit', 'revision' => 1, 'reason' => 'Approval received'];
        $this->actingAs($owner)->post(route('portal.performance.cases.transition', $case), $transition)->assertStatus(422);
        $transition['evidence_url'] = 'https://example.com/approval';
        $this->post(route('portal.performance.cases.transition', $case), $transition)->assertRedirect();
        $this->post(route('portal.performance.cases.transition', $case), ['action' => 'close', 'revision' => 2, 'reason' => 'Self review'])->assertForbidden();
        $child = PerformanceCase::create($payload + ['created_by' => $manager->id, 'parent_id' => $case->id]);
        $this->actingAs($manager)->post(route('portal.performance.cases.transition', $case), ['action' => 'close', 'revision' => 2, 'reason' => 'Reviewed evidence'])->assertStatus(422);
        $child->update(['status' => 'closed', 'closed_at' => now()]);
        $this->post(route('portal.performance.cases.transition', $case), ['action' => 'close', 'revision' => 2, 'reason' => 'Reviewed evidence'])->assertRedirect();
        $this->assertSame('closed', $case->fresh()->status);
        $this->post(route('portal.performance.cases.transition', $case), ['action' => 'reopen', 'revision' => 2, 'reason' => 'New issue'])->assertStatus(409);
        $other = User::factory()->create(['department' => 'finance', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($other)->get(route('portal.performance.cases'))->assertOk()->assertDontSee('Missing approval');
        $this->post(route('portal.performance.cases.transition', $case), ['action' => 'close', 'revision' => 3, 'reason' => 'Unauthorized'])->assertForbidden();
    }
}
