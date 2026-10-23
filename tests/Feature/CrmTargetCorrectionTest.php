<?php

namespace Tests\Feature;

use App\Models\CrmAccountTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmTargetCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrections_require_independent_confirmation_and_preserve_source(): void
    {
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $reviewer = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $item = CrmAccountTarget::create(['client_name' => 'Source target', 'owner_id' => $owner->id, 'frequency' => 'monthly', 'reference' => 'import-test', 'source_reference' => 'Sheet 1 / row 20', 'needs_reconciliation' => true]);
        $this->actingAs($owner)->post(route('portal.crm.target.correct', $item), ['revision' => 1, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'frequency' => 'monthly', 'target_amount' => 100, 'reason' => 'Checked source allocation'])->assertRedirect();
        $this->assertTrue($item->fresh()->needs_reconciliation);
        $this->assertNull($item->fresh()->target_amount);
        $this->actingAs($owner)->post(route('portal.crm.target.review', $item), ['revision' => 2, 'decision' => 'confirm', 'reason' => 'Checked'])->assertForbidden();
        $this->actingAs($reviewer)->post(route('portal.crm.target.review', $item), ['revision' => 1, 'decision' => 'confirm', 'reason' => 'Checked'])->assertStatus(409);
        $this->actingAs($reviewer)->post(route('portal.crm.target.review', $item), ['revision' => 2, 'decision' => 'confirm', 'reason' => 'Confirmed against source'])->assertRedirect();
        $this->assertFalse($item->fresh()->needs_reconciliation);
        $this->assertEquals(100, $item->fresh()->target_amount);
        $this->assertSame('Sheet 1 / row 20', $item->fresh()->source_reference);
        $outsider = User::factory()->create(['department' => 'creatives', 'status' => 'active']);
        $this->actingAs($outsider)->post(route('portal.crm.target.correct', $item), [])->assertForbidden();
    }

    public function test_source_correction_reminders_are_private_and_deduplicated(): void
    {
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'staff', 'status' => 'active']);
        $manager = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $outsider = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        CrmAccountTarget::create(['client_name' => 'Private source', 'owner_id' => $owner->id, 'frequency' => 'monthly', 'reference' => 'source-review-qa', 'needs_reconciliation' => true]);
        app(\App\Services\PerformanceAutomation::class)->sweep();
        app(\App\Services\PerformanceAutomation::class)->sweep();
        $this->assertSame(1, \App\Models\PerformanceOutbox::where('user_id', $owner->id)->where('title', 'Client Relations Source Corrections Required')->count());
        $this->assertSame(1, \App\Models\PerformanceOutbox::where('user_id', $manager->id)->where('title', 'Client Relations Source Corrections Required')->count());
        $this->assertSame(0, \App\Models\PerformanceOutbox::where('user_id', $outsider->id)->count());
    }
}
