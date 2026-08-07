<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FleetRequest;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FleetRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $myRequests = FleetRequest::with('reviewer')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(8, ['*'], 'my_fleet_page')
            ->withQueryString();

        $pendingRequests = null;
        if ($user->canApproveFleetRequests()) {
            $pendingRequests = FleetRequest::with(['user', 'reviewer'])
                ->latest()
                ->paginate(12, ['*'], 'fleet_review_page')
                ->withQueryString();
        }

        return view('portal.fleet-requests', [
            'myRequests' => $myRequests,
            'pendingRequests' => $pendingRequests,
            'companyVehicles' => FleetRequest::COMPANY_VEHICLES,
            'rideHailingOptions' => FleetRequest::RIDE_HAILING_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateFleetRequest($request);

        $fleetRequest = FleetRequest::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending_hr',
        ]);

        NotificationService::sendToMany(
            NotificationService::activeFleetApproverIds($request->user()->id),
            'Fleet Request Needs Review',
            "{$request->user()->name} requested {$fleetRequest->optionLabel()} transport assistance.",
            route('portal.fleet-requests')
        );

        return back()->with('status', 'Fleet request submitted for review.');
    }

    public function resubmit(Request $request, FleetRequest $fleetRequest): RedirectResponse
    {
        abort_unless((int) $fleetRequest->user_id === (int) $request->user()->id || $request->user()->hasRole('super_admin'), 403);

        if ($fleetRequest->status !== 'returned_for_correction') {
            return back()->withErrors(['fleet_request' => 'Only returned fleet requests can be corrected and resubmitted.']);
        }

        $validated = $this->validateFleetRequest($request);

        $fleetRequest->update([
            ...$validated,
            'status' => 'pending_hr',
            'hr_comment' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        NotificationService::sendToMany(
            NotificationService::activeFleetApproverIds($request->user()->id),
            'Fleet Request Resubmitted',
            "{$request->user()->name} corrected and resubmitted a transport assistance request.",
            route('portal.fleet-requests')
        );

        return back()->with('status', 'Fleet request corrected and resubmitted.');
    }

    public function action(Request $request, FleetRequest $fleetRequest): RedirectResponse
    {
        abort_unless($request->user()->canApproveFleetRequests(), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'hr_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match ($validated['action']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'return' => 'returned_for_correction',
        };

        $fleetRequest->update([
            'status' => $status,
            'hr_comment' => $validated['hr_comment'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        NotificationService::send(
            (int) $fleetRequest->user_id,
            'Fleet Request Updated',
            'Your fleet request has been marked as '.str_replace('_', ' ', $status).'.',
            route('portal.fleet-requests')
        );

        return back()->with('status', 'Fleet request updated.');
    }

    private function allowedOptions(?string $assistanceType): array
    {
        return $assistanceType === 'ride_hailing'
            ? FleetRequest::RIDE_HAILING_OPTIONS
            : FleetRequest::COMPANY_VEHICLES;
    }

    private function validateFleetRequest(Request $request): array
    {
        $options = $this->allowedOptions($request->input('assistance_type'));

        return $request->validate([
            'assistance_type' => ['required', Rule::in(['company_vehicle', 'ride_hailing'])],
            'vehicle_option' => ['required', Rule::in(array_keys($options))],
            'pickup_location' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'requested_date' => ['required', 'date', 'after_or_equal:today'],
            'requested_time' => ['nullable', 'date_format:H:i'],
            'passengers' => ['required', 'integer', 'min:1', 'max:50'],
            'purpose' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
