<?php

namespace Tests\Feature;

use App\Models\FleetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_submit_and_resubmit_returned_fleet_request(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $hr = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'HR Admin',
            'position_title' => 'HR Manager',
        ]);

        $submit = $this->actingAs($staff)->post(route('portal.fleet-requests.store'), [
            'assistance_type' => 'company_vehicle',
            'vehicle_option' => 'toyota_corolla_salon_car',
            'pickup_location' => 'Office',
            'destination' => 'Client site',
            'requested_date' => now()->addDay()->toDateString(),
            'requested_time' => '09:30',
            'passengers' => 2,
            'purpose' => 'Client presentation',
            'notes' => 'Carry samples',
        ]);

        $submit->assertRedirect();
        $submit->assertSessionHasNoErrors();

        $fleetRequest = FleetRequest::firstOrFail();
        $this->assertSame('pending_hr', $fleetRequest->status);

        $return = $this->actingAs($hr)->post(route('portal.fleet-requests.action', $fleetRequest), [
            'action' => 'return',
            'hr_comment' => 'Please confirm passengers.',
        ]);

        $return->assertRedirect();
        $this->assertSame('returned_for_correction', $fleetRequest->fresh()->status);

        $page = $this->actingAs($staff)->get(route('portal.fleet-requests'));
        $page->assertOk();
        $page->assertSee('Correction needed', false);
        $page->assertSee(route('portal.fleet-requests.resubmit', $fleetRequest), false);

        $resubmit = $this->actingAs($staff)->post(route('portal.fleet-requests.resubmit', $fleetRequest), [
            'assistance_type' => 'ride_hailing',
            'vehicle_option' => 'bolt',
            'pickup_location' => 'Office',
            'destination' => 'Client site',
            'requested_date' => now()->addDays(2)->toDateString(),
            'requested_time' => '10:00',
            'passengers' => 3,
            'purpose' => 'Client presentation with revised passenger count',
            'notes' => null,
        ]);

        $resubmit->assertRedirect();
        $resubmit->assertSessionHasNoErrors();

        $fleetRequest->refresh();
        $this->assertSame('pending_hr', $fleetRequest->status);
        $this->assertSame('ride_hailing', $fleetRequest->assistance_type);
        $this->assertSame('bolt', $fleetRequest->vehicle_option);
        $this->assertSame(3, $fleetRequest->passengers);
        $this->assertNull($fleetRequest->hr_comment);
    }

    public function test_non_hr_user_cannot_action_fleet_request(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $otherStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $fleetRequest = FleetRequest::create([
            'user_id' => $staff->id,
            'assistance_type' => 'company_vehicle',
            'vehicle_option' => 'office_truck_medium',
            'pickup_location' => 'Office',
            'destination' => 'Warehouse',
            'requested_date' => now()->addDay()->toDateString(),
            'passengers' => 1,
            'purpose' => 'Asset pickup',
            'status' => 'pending_hr',
        ]);

        $response = $this->actingAs($otherStaff)->post(route('portal.fleet-requests.action', $fleetRequest), [
            'action' => 'approve',
        ]);

        $response->assertForbidden();
        $this->assertSame('pending_hr', $fleetRequest->fresh()->status);
    }
}
