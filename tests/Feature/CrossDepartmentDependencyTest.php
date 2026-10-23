<?php

namespace Tests\Feature;

use App\Models\PerformanceDependencyRequest;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossDepartmentDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_department_confirmation_is_required_and_cycles_are_rejected(): void
    {
        $creative = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        $operations = User::factory()->create(['department' => 'operations_projects', 'access_role' => 'manager', 'status' => 'active']);
        $third = User::factory()->create(['department' => 'finance', 'access_role' => 'manager', 'status' => 'active']);
        $make = fn ($user) => WeeklyConsolidatedItem::create(['department' => $user->department, 'lead_staff_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'week_start' => today(), 'week_end' => today()->addDays(5), 'campaign_name' => 'Coordination QA', 'deliverables' => 'Deliver sample work', 'workflow_state' => 'assigned', 'status' => 'Planned']);
        $up = $make($creative);
        $down = $make($operations);
        $payload = ['upstream_id' => $up->id, 'downstream_id' => $down->id, 'impact' => 'Production depends on artwork'];
        $this->actingAs($creative)->post(route('portal.performance.dependency-requests.store'), $payload)->assertRedirect();
        $request = PerformanceDependencyRequest::firstOrFail();
        $this->assertDatabaseCount('performance_dependencies', 0);
        $review = ['decision' => 'approve', 'reason' => 'Delivery sequence confirmed'];
        $this->actingAs($creative)->post(route('portal.performance.dependency-requests.review', $request), $review)->assertForbidden();
        $this->actingAs($third)->post(route('portal.performance.dependency-requests.review', $request), $review)->assertForbidden();
        $this->actingAs($operations)->post(route('portal.performance.dependency-requests.review', $request), ['decision' => 'return', 'reason' => 'Clarify timing'])->assertRedirect();
        $this->assertSame('returned', $request->fresh()->status);
        $this->actingAs($creative)->post(route('portal.performance.dependency-requests.store'), $payload + [])->assertRedirect();
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->reviewed_by);
        $this->actingAs($operations)->post(route('portal.performance.dependency-requests.review', $request), $review)->assertRedirect();
        $this->assertDatabaseCount('performance_dependencies', 1);
        $this->actingAs($operations)->post(route('portal.performance.dependency-requests.store'), ['upstream_id' => $down->id, 'downstream_id' => $up->id, 'impact' => 'Reverse request'])->assertRedirect();
        $reverse = PerformanceDependencyRequest::where('upstream_id', $down->id)->firstOrFail();
        $this->actingAs($creative)->post(route('portal.performance.dependency-requests.review', $reverse), $review)->assertSessionHasErrors('upstream_id');
        $this->assertDatabaseCount('performance_dependencies', 1);
    }
}
