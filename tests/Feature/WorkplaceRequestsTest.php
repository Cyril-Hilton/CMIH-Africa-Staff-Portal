<?php

namespace Tests\Feature;

use App\Models\{ActionPoint, BoardroomRequest, FleetRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkplaceRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_actions_are_archived_and_can_be_reopened(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $point = ActionPoint::create(['status' => 'pending', 'action_point' => 'Record handover', 'comments' => 'Key received', 'created_by' => $staff->id]);
        $this->actingAs($staff)->patchJson(route('portal.action-points.update', $point), ['status' => 'done'])->assertOk();
        $this->get(route('portal.action-points.table'))->assertOk()->assertDontSee('Record handover');
        $this->get(route('portal.action-points.archive'))->assertOk()->assertSee('Record handover')->assertSee('Key received');
        $this->patch(route('portal.action-points.update', $point), ['status' => 'in_progress'])->assertRedirect();
        $this->get(route('portal.action-points.archive'))->assertOk()->assertDontSee('Record handover');
        $this->get(route('portal.action-points.table'))->assertOk()->assertSee('Record handover');
    }

    public function test_vehicle_downloads_match_record_permissions_and_export_real_files(): void
    {
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin']);
        $owner = User::factory()->create(['status' => 'active', 'department' => 'creatives']);
        $other = User::factory()->create(['status' => 'active', 'department' => 'operations_projects']);
        $trip = FleetRequest::create(['user_id' => $owner->id, 'assistance_type' => 'company_vehicle', 'vehicle_option' => 'office_truck_small',
            'pickup_location' => 'Office', 'destination' => 'Osu', 'requested_date' => '2026-11-16', 'requested_time' => '10:00',
            'passengers' => 2, 'purpose' => 'Collect equipment', 'status' => 'approved']);
        $file = route('portal.fleet-requests.download', [$trip, 'pdf']);
        $this->actingAs($other)->get($file)->assertForbidden();
        $this->actingAs($owner)->get($file)->assertDownload('vehicle-request-'.$trip->id.'.pdf');
        $this->get(route('portal.fleet-requests.export', 'xlsx'))->assertForbidden();
        $booking = $this->actingAs($hr)->get($file)->assertDownload('vehicle-request-'.$trip->id.'.pdf');
        $this->assertStringStartsWith('%PDF-', $booking->getContent());
        $register = $this->get(route('portal.fleet-requests.export', 'xlsx'))->assertDownload('vehicle-request-register.xlsx');
        $this->assertStringStartsWith('PK', $register->getContent());
        $this->get(route('portal.fleet-requests.export', 'pdf'))->assertDownload('vehicle-request-register.pdf');
    }

    public function test_boardroom_rejects_overlaps_and_records_actual_access_and_damage(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00', 'Africa/Accra'));
        $first = User::factory()->create(['status' => 'active', 'department' => 'client_relations']);
        $other = User::factory()->create(['status' => 'active', 'department' => 'creatives']);
        $hr = User::factory()->create(['status' => 'active', 'department' => 'hr_admin']);
        $data = ['date' => '2026-11-16', 'start' => '14:00', 'end' => '15:00', 'purpose' => 'Private planning session', 'attendees' => 4];
        $this->actingAs($first)->post(route('portal.boardroom-requests.store'), array_replace($data, ['end' => '13:00']))->assertSessionHasErrors('end');
        $this->post(route('portal.boardroom-requests.store'), $data)->assertSessionHasNoErrors();
        $initial = BoardroomRequest::sole();
        $this->actingAs($other)->get(route('portal.boardroom-requests', ['booking' => $initial->id]))->assertNotFound();
        $this->post(route('portal.boardroom-requests.action', $initial), ['action' => 'approve'])->assertForbidden();
        $this->post(route('portal.boardroom-requests.store'), array_replace($data, ['start' => '14:15']))->assertSessionHasNoErrors();
        $overlap = BoardroomRequest::latest('id')->firstOrFail();
        $this->actingAs($hr)->post(route('portal.boardroom-requests.action', $initial), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->post(route('portal.boardroom-requests.action', $overlap), ['action' => 'approve'])->assertSessionHasErrors('booking');
        $this->assertSame('pending', $overlap->fresh()->status);

        $entry = route('portal.boardroom-requests.action', $initial);
        $this->post($entry, ['action' => 'check_in', 'accessed_by_name' => 'Key collector'])->assertSessionHasErrors('booking');
        $this->travelTo(Carbon::parse('2026-11-16 13:55', 'Africa/Accra'));
        $this->post($entry, ['action' => 'check_in', 'accessed_by_name' => 'Key collector'])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-11-16 14:40', 'Africa/Accra'));
        $this->post($entry, ['action' => 'check_out', 'room_condition' => 'issue_reported'])->assertSessionHasErrors('condition_notes');
        $this->post($entry, ['action' => 'check_out', 'room_condition' => 'issue_reported', 'condition_notes' => 'Damaged chair'])->assertSessionHasNoErrors();
        $this->get(route('portal.boardroom-requests'))->assertOk()->assertSee('Last recorded access')->assertSee('Key collector')->assertSee('Damaged chair');
        $this->assertSame('completed', $initial->fresh()->status);
        $this->assertEquals(45, $initial->fresh()->checked_in_at->diffInMinutes($initial->fresh()->checked_out_at));
    }
}
