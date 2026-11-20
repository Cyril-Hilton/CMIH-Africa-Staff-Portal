<?php

namespace Tests\Feature;

use App\Models\PhoneDirectory;
use App\Models\ThirdPartyVendor;
use App\Models\User;
use App\Services\HrLiveRegisters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrVendorConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_directory_vendors_are_visible_without_copying_or_inventing_active_status(): void
    {
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin', 'access_role' => 'super_admin']);
        $contact = PhoneDirectory::create(['name' => 'Vendor contact', 'company' => 'Existing maintenance vendor', 'phone' => '0200000000', 'email' => 'vendor@example.test', 'category' => 'vendor', 'is_vendor' => true]);
        PhoneDirectory::create(['name' => 'Another contact', 'company' => 'Existing maintenance vendor', 'phone' => '0200000001', 'category' => 'vendor']);
        PhoneDirectory::create(['name' => 'Private client contact', 'company' => 'Client only', 'category' => 'client']);
        $records = app(HrLiveRegisters::class)->records('vendor', $hr);
        $this->assertCount(1, $records);
        $this->assertSame(-$contact->id, $records->first()['id']);
        $this->assertTrue($records->first()['read_only']);
        $this->assertStringContainsString('0200000001', $records->first()['data']['contact']);
        $this->assertDatabaseCount('third_party_vendors', 0);
        $stats = app(HrLiveRegisters::class)->stats('supplies', $hr, '2026-11-16', '2026-11-22');
        $this->assertSame(1, $stats['Total vendors']);
        $this->assertSame(0, $stats['Active vendors']);
        $this->assertSame(1, $stats['Vendor status needs setup']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($hr)->get(route('portal.hr-workspace', ['hr_tab' => 'supplies']))->assertOk()->assertSee('Existing maintenance vendor')->assertSee('vendor@example.test')->assertDontSee('Private client contact')->assertDontSee(route('portal.hr-registers.destroy', ['vendor', -$contact->id]), false);
    }

    public function test_native_vendor_remains_authoritative_and_links_matching_directory_contacts_once(): void
    {
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin', 'access_role' => 'super_admin']);
        $vendor = ThirdPartyVendor::create(['name' => 'Shared Vendor', 'category' => 'Repairs', 'hr_details' => ['state' => 'Active']]);
        PhoneDirectory::create(['name' => 'Contact person', 'company' => ' shared  vendor ', 'phone' => '0200000000', 'is_vendor' => true]);
        $rows = app(HrLiveRegisters::class)->records('vendor', $hr);
        $this->assertCount(1, $rows);
        $this->assertSame($vendor->id, $rows->first()['id']);
        $this->assertSame('Active', $rows->first()['data']['state']);
        $this->assertStringContainsString('0200000000', $rows->first()['data']['contact']);
        $this->assertDatabaseCount('third_party_vendors', 1);
    }

    public function test_existing_directory_vendor_links_to_followup_and_counts_as_source_evidence_without_duplicate_entry(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 14:00:00'));
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin', 'access_role' => 'manager', 'position_title' => 'HR Manager', 'job_level' => 'manager']);
        $contact = PhoneDirectory::create(['name' => 'Existing vendor contact', 'company' => 'Existing vendor company', 'category' => 'vendor', 'is_vendor' => true]);
        $coverage = app(\App\Services\HrRegisterKpis::class)->build('2026-11-16', '2026-11-22');
        $supplies = collect($coverage['areas'])->firstWhere('name', 'Supplies, Vendors & Communications');
        $this->assertTrue($supplies['reported']);
        $this->assertSame(1, $supplies['records']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($hr)->post(route('portal.hr-dashboard.activities.store'), [
            'tab' => 'supplies', 'category' => 'Vendor follow-up', 'activity' => 'Follow up with existing directory vendor', 'assigned_to' => $hr->id,
            'opened_on' => '2026-11-20', 'deadline' => '2026-11-23', 'status' => 'Not Started', 'linked_record' => 'vendor:-'.$contact->id,
        ])->assertSessionHasNoErrors();
        $activity = \App\Models\HrDashboardActivity::sole();
        $this->assertSame('Existing vendor company', app(HrLiveRegisters::class)->linkedLabel($activity->linked_type, $activity->linked_id, $hr));
        $this->assertDatabaseCount('third_party_vendors', 0);
    }
}
