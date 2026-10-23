<?php

namespace Tests\Feature;

use App\Models\PerformanceReportPack;
use App\Models\User;
use App\Services\PerformanceReportPacks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceReportPacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_pack_is_frozen_deduplicated_and_scoped(): void
    {
        $owner = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        $service = app(PerformanceReportPacks::class);
        $pack = $service->capture($owner, 'monthly', '2026-10-01', '2026-10-31');
        $this->assertSame($pack->id, $service->capture($owner, 'monthly', '2026-10-01', '2026-10-31')->id);
        $this->assertCount(1, PerformanceReportPack::all());
        $this->assertSame(64, strlen($pack->sha256));
        $this->actingAs($owner)->get(route('portal.performance.packs.show', [$pack, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $other = User::factory()->create(['department' => 'finance', 'status' => 'active']);
        $this->actingAs($other)->get(route('portal.performance.packs.show', [$pack, 'json']))->assertForbidden();
        $this->actingAs($other)->get(route('portal.performance.packs'))->assertOk()->assertDontSee($pack->sha256);
        $this->expectException(\LogicException::class);
        $pack->update(['sha256' => str_repeat('0', 64)]);
    }
}
