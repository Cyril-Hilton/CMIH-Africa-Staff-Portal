<?php

namespace Tests\Feature;

use App\Models\PerformancePolicy;
use App\Models\User;
use App\Services\PerformancePolicyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_policy_requires_independent_approval_and_preserves_current_rules(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21));
        $cvo = User::factory()->create(['status' => 'active', 'access_role' => 'staff', 'job_level' => 'CVO']);
        $admin = User::factory()->create(['status' => 'active', 'access_role' => 'super_admin']);
        $rules = ['budget_tolerance_percent' => 15, 'executive_after_days' => 4, 'repeated_failure_periods' => 3];
        $this->actingAs($cvo)->post(route('portal.performance.policies.store'), ['effective_on' => '2026-11-01', 'rules' => $rules, 'reason' => 'Approved operating policy proposal'])->assertRedirect();
        $policy = PerformancePolicy::firstOrFail();
        $this->actingAs($cvo)->post(route('portal.performance.policies.review', $policy), ['decision' => 'approve', 'reason' => 'Self review'])->assertForbidden();
        $this->actingAs($admin)->post(route('portal.performance.policies.review', $policy), ['decision' => 'approve', 'reason' => 'Independently checked'])->assertRedirect();
        $this->assertEquals(config('performance.exceptions.budget_tolerance_percent'), PerformancePolicyRules::value('budget_tolerance_percent'));
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertEquals(15, PerformancePolicyRules::value('budget_tolerance_percent'));
        $staff = User::factory()->create(['status' => 'active', 'access_role' => 'staff']);
        $this->actingAs($staff)->post(route('portal.performance.policies.store'), [])->assertForbidden();
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'performance_policy', 'entity_id' => $policy->id, 'action' => 'approve']);
    }
}
