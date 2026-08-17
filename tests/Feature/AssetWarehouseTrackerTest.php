<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetWarehouseTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_page_filters_by_brand_condition_status_and_staff(): void
    {
        $viewer = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);

        $assignedStaff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        Asset::create([
            'name' => 'Rexona Tent',
            'description' => 'Warehouse item',
            'type' => 'Other',
            'status' => 'Available',
            'condition' => 'Good',
            'brand' => 'Rexona',
            'assigned_to' => $assignedStaff->id,
            'added_by' => $viewer->id,
            'is_warehouse_tracked' => true,
            'warehouse_quantity' => 2,
        ]);

        Asset::create([
            'name' => 'Guinness Cooler',
            'description' => 'Different brand item',
            'type' => 'Hardware',
            'status' => 'Maintenance',
            'condition' => 'Fair',
            'brand' => 'Guinness',
            'added_by' => $viewer->id,
            'is_warehouse_tracked' => true,
            'warehouse_quantity' => 1,
        ]);

        $response = $this->actingAs($viewer)->get(route('portal.assets', [
            'brand' => 'Rexona',
            'condition' => 'Good',
            'status' => 'Available',
            'staff' => $assignedStaff->id,
        ]));

        $response->assertOk();
        $response->assertSee('Rexona Tent');
        $response->assertDontSee('Guinness Cooler');
    }

    public function test_staff_can_request_correct_and_upload_warehouse_evidence(): void
    {
        Storage::fake('public');

        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'client_relations',
        ]);

        $asset = $this->warehouseAsset();

        $this->actingAs($staff)->post(route('portal.assets.warehouse.request', $asset), [
            'requested_quantity' => 1,
            'requested_for' => now()->addDay()->toDateString(),
            'destination_location' => 'Accra Mall Activation',
            'purpose' => 'Use for a sampling booth setup.',
            'requester_notes' => 'Need clean handover.',
        ])->assertRedirect();

        $request = AssetWarehouseRequest::firstOrFail();

        $this->assertSame(AssetWarehouseRequest::STATUS_PENDING_CHECK, $request->status);

        $request->update([
            'status' => AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
            'review_note' => 'Add the exact destination.',
        ]);

        $this->actingAs($staff)->patch(route('portal.assets.warehouse.correct', $request), [
            'requested_quantity' => 1,
            'requested_for' => now()->addDays(2)->toDateString(),
            'destination_location' => 'Accra Mall - Food Court',
            'purpose' => 'Use for a sampling booth setup.',
            'requester_notes' => 'Corrected destination.',
        ])->assertRedirect();

        $this->assertSame(AssetWarehouseRequest::STATUS_PENDING_CHECK, $request->fresh()->status);

        $request->update(['status' => AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK]);

        $this->actingAs($staff)->post(route('portal.assets.warehouse.evidence', $request), [
            'stage' => 'pre_use',
            'evidence_image' => $this->fakePngUpload('before.png'),
            'note' => 'Item inspected.',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame(AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED, $request->status);
        $this->assertNotNull($request->pre_use_image_path);
        Storage::disk('public')->assertExists($request->pre_use_image_path);
    }

    public function test_warehouse_manager_can_approve_issue_close_and_export_requests(): void
    {
        Storage::fake('public');

        $manager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'operations_projects',
            'position_title' => 'Department Head',
            'job_level' => 'manager',
        ]);

        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $asset = $this->warehouseAsset(['warehouse_quantity' => 3]);
        $request = AssetWarehouseRequest::create([
            'request_code' => 'AWR-202608-0001',
            'asset_id' => $asset->id,
            'requested_by' => $staff->id,
            'requested_quantity' => 1,
            'destination_location' => 'Spintex Warehouse',
            'purpose' => 'Field activation',
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ]);

        $this->actingAs($manager)->post(route('portal.assets.warehouse.action', $request), [
            'action' => 'approve_check',
            'note' => 'Go and inspect.',
        ])->assertRedirect();

        $this->assertSame(AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK, $request->fresh()->status);

        $request->update([
            'status' => AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
            'pre_use_image_path' => $this->fakePngUpload('before.png')->store('assets/warehouse', 'public'),
        ]);

        $this->actingAs($manager)->post(route('portal.assets.warehouse.action', $request), [
            'action' => 'approve_use',
            'note' => 'Approved for use.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('portal.assets.warehouse.action', $request), [
            'action' => 'issue',
            'note' => 'Issued clean.',
            'evidence_image' => $this->fakePngUpload('issued.png'),
        ])->assertRedirect();

        $asset->refresh();
        $request->refresh();
        $this->assertSame(2, (int) $asset->warehouse_quantity);
        $this->assertSame('In Use', $asset->status);
        $this->assertSame($staff->id, (int) $asset->assigned_to);
        $this->assertSame(AssetWarehouseRequest::STATUS_ISSUED, $request->status);

        $this->actingAs($staff)->post(route('portal.assets.warehouse.evidence', $request), [
            'stage' => 'return',
            'evidence_image' => $this->fakePngUpload('returned.png'),
            'note' => 'Returned after use.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('portal.assets.warehouse.action', $request), [
            'action' => 'close',
            'note' => 'Closed after checking image.',
        ])->assertRedirect();

        $asset->refresh();
        $request->refresh();
        $this->assertSame(3, (int) $asset->warehouse_quantity);
        $this->assertSame('Available', $asset->status);
        $this->assertNull($asset->assigned_to);
        $this->assertSame(AssetWarehouseRequest::STATUS_CLOSED, $request->status);

        $export = $this->actingAs($manager)->get(route('portal.assets.warehouse.export', ['format' => 'csv']));
        $export->assertOk();
        $this->assertStringContainsString('AWR-202608-0001', $export->streamedContent());
    }

    public function test_regular_staff_cannot_approve_warehouse_requests(): void
    {
        $staff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $request = AssetWarehouseRequest::create([
            'request_code' => 'AWR-202608-0002',
            'asset_id' => $this->warehouseAsset()->id,
            'requested_by' => $staff->id,
            'requested_quantity' => 1,
            'destination_location' => 'North Legon',
            'purpose' => 'Field use',
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ]);

        $this->actingAs($staff)->post(route('portal.assets.warehouse.action', $request), [
            'action' => 'approve_check',
        ])->assertForbidden();
    }

    private function warehouseAsset(array $overrides = []): Asset
    {
        $creator = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);

        return Asset::create(array_merge([
            'name' => 'Activation Booth',
            'description' => 'Stored booth kit',
            'type' => 'Other',
            'status' => 'Available',
            'condition' => 'Good',
            'brand' => 'Rexona',
            'warehouse_location' => 'Warehouse Rack A',
            'warehouse_quantity' => 2,
            'is_warehouse_tracked' => true,
            'added_by' => $creator->id,
        ], $overrides));
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
