<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandFieldActivity;
use App\Models\BrandConsumerEntry;
use App\Models\BrandStaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandsPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_brands_platform_root_renders_default_brand_hub(): void
    {
        config(['cmih.app_kind' => 'brands']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('CMIH Brands Platform');
        $response->assertSee('Merchandiser Portal');
        $response->assertSee('OMO');
    }

    public function test_super_admin_can_assign_internal_staff_to_a_brand(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'client_relations',
        ]);
        $brand = Brand::where('slug', 'rexona')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('brands-platform.admin.assignments.store', $brand->slug), [
            'user_id' => $staff->id,
            'role' => BrandStaffAssignment::ROLE_AGENCY,
            'notes' => 'Client relations lead.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('brand_staff_assignments', [
            'brand_id' => $brand->id,
            'user_id' => $staff->id,
            'role' => BrandStaffAssignment::ROLE_AGENCY,
            'is_active' => true,
        ]);
    }

    public function test_regular_admin_cannot_open_brands_admin_console(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('brands-platform.admin'))
            ->assertForbidden();
    }

    public function test_assigned_staff_can_access_agency_dashboard_and_record_activity(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $brand = Brand::where('slug', 'guinness')->firstOrFail();

        BrandStaffAssignment::create([
            'brand_id' => $brand->id,
            'user_id' => $staff->id,
            'role' => BrandStaffAssignment::ROLE_SUPPORT,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($staff)
            ->get(route('brands-platform.agency', $brand->slug))
            ->assertOk()
            ->assertSee('Activation Dashboard');

        $this->actingAs($staff)
            ->post(route('brands-platform.field-activity.store', $brand->slug), [
                'staff_role' => 'supporting_staff',
                'activity_type' => 'sample_distributed',
                'location' => 'Accra Mall',
                'units' => 25,
                'notes' => 'Sampling completed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('brand_field_activities', [
            'brand_id' => $brand->id,
            'user_id' => $staff->id,
            'activity_type' => 'sample_distributed',
            'location' => 'Accra Mall',
            'units' => 25,
        ]);
    }

    public function test_public_brand_page_does_not_expose_field_activity_details(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $brand = Brand::where('slug', 'guinness')->firstOrFail();

        BrandFieldActivity::create([
            'brand_id' => $brand->id,
            'brand_activation_id' => $brand->activations()->first()?->id,
            'user_id' => $staff->id,
            'activity_type' => 'sensitive_retail_audit',
            'location' => 'Confidential Outlet Location',
            'units' => 12,
        ]);

        $this->get(route('brands-platform.show', $brand->slug))
            ->assertOk()
            ->assertSee('Field updates are restricted to assigned teams')
            ->assertDontSee('Confidential Outlet Location')
            ->assertDontSee($staff->name);
    }

    public function test_consumer_capture_saves_to_brand_activation(): void
    {
        $brand = Brand::where('slug', 'omo')->firstOrFail();
        $activation = $brand->activations()->firstOrFail();

        $this->post(route('brands-platform.consumer-entry.store', $brand->slug), [
            'name' => 'Test Consumer',
            'phone' => '0240000000',
            'age_band' => '23-27',
            'gender' => 'Female',
            'location' => 'Shoprite',
            'result_type' => 'Sample Distributed',
        ])->assertRedirect();

        $this->assertDatabaseHas('brand_consumer_entries', [
            'brand_id' => $brand->id,
            'brand_activation_id' => $activation->id,
            'phone' => '0240000000',
            'location' => 'Shoprite',
        ]);
        $this->assertSame(1, BrandConsumerEntry::where('brand_id', $brand->id)->count());
        $this->assertSame(1, $activation->refresh()->actual_reach);
    }

    public function test_client_report_route_uses_share_token_not_brand_slug(): void
    {
        $brand = Brand::where('slug', 'rexona')->firstOrFail();
        $activation = $brand->activations()->firstOrFail();

        $this->get(route('brands-platform.client-report', $activation->client_share_token))
            ->assertOk()
            ->assertSee('Client Live Report')
            ->assertSee($brand->name);
    }

    public function test_gallery_requires_brand_access_and_shows_only_selected_brand_evidence(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $assignedBrand = Brand::where('slug', 'dove')->firstOrFail();
        $otherBrand = Brand::where('slug', 'mtn')->firstOrFail();

        BrandStaffAssignment::create([
            'brand_id' => $assignedBrand->id,
            'user_id' => $staff->id,
            'role' => BrandStaffAssignment::ROLE_AGENCY,
            'assigned_by' => $admin->id,
        ]);
        BrandFieldActivity::create([
            'brand_id' => $assignedBrand->id,
            'brand_activation_id' => $assignedBrand->activations()->first()?->id,
            'user_id' => $staff->id,
            'activity_type' => 'sample_distributed',
            'location' => 'Dove Location',
            'units' => 12,
            'evidence_path' => 'brand-activities/dove.jpg',
        ]);
        BrandFieldActivity::create([
            'brand_id' => $otherBrand->id,
            'brand_activation_id' => $otherBrand->activations()->first()?->id,
            'user_id' => $admin->id,
            'activity_type' => 'lead_capture',
            'location' => 'MTN Location',
            'units' => 9,
            'evidence_path' => 'brand-activities/mtn.jpg',
        ]);

        $this->actingAs($staff)
            ->get(route('brands-platform.brand-gallery', $assignedBrand->slug))
            ->assertOk()
            ->assertSee('Dove Location')
            ->assertDontSee('MTN Location');

        $this->actingAs($staff)
            ->get(route('brands-platform.brand-gallery', $otherBrand->slug))
            ->assertForbidden();
    }
}
