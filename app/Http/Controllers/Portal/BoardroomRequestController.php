<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\{BoardroomRequest, User};
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class BoardroomRequestController extends Controller
{
    private const TIMEZONE = 'Africa/Accra';

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorizeStaff($user);
        $canManage = $user->canApproveFleetRequests();
        $canReadAll = $canManage || $user->canAuditHrAndFinance();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(BoardroomRequest::STATUSES))],
            'date' => 'nullable|date_format:Y-m-d',
            'booking' => 'nullable|integer|min:1',
        ]);
        $bookings = BoardroomRequest::with(['reviewer:id,name', 'checkInRecorder:id,name', 'checkOutRecorder:id,name'])
            ->when(!$canReadAll, fn ($query) => $query->where('user_id', $user->id));
        $lastUse = (clone $bookings)->whereNotNull('checked_in_at')->latest('checked_in_at')->first();
        $records = (clone $bookings)->when($filters['booking'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->orderByDesc('starts_at')->orderByDesc('id')->paginate(12)->withQueryString();
        if (!empty($filters['booking'])) {
            abort_if($records->total() === 0, 404);
        }

        return response()->view('portal.boardroom-requests', compact('records', 'lastUse', 'canManage', 'canReadAll'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorizeStaff($user);
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'start' => 'required|date_format:H:i',
            'end' => 'required|date_format:H:i|after:start',
            'purpose' => 'required|string|max:2000',
            'attendees' => 'required|integer|min:1|max:1000',
        ]);
        $start = Carbon::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start'], self::TIMEZONE);
        $end = Carbon::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['end'], self::TIMEZONE);
        if ($start->lte(now())) {
            throw ValidationException::withMessages(['start' => 'Choose a future start date and time.']);
        }

        $booking = BoardroomRequest::create([
            'user_id' => $user->id, 'requester_name' => $user->name, 'purpose' => $data['purpose'],
            'attendees' => $data['attendees'], 'starts_at' => $start, 'ends_at' => $end, 'status' => 'pending',
        ]);
        NotificationService::sendToMany(NotificationService::activeFleetApproverIds($user->id),
            'Boardroom request needs review', $user->name.' requested access to the boardroom.', $this->url($booking));

        return redirect()->to($this->url($booking))->with('status', 'Request submitted to HR/Admin. Wait for approval before using the room.');
    }

    public function action(Request $request, BoardroomRequest $booking)
    {
        $user = $request->user();
        $this->authorizeStaff($user);
        $decision = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'cancel', 'check_in', 'check_out'])],
            'review_note' => 'nullable|string|max:2000',
            'accessed_by_name' => 'required_if:action,check_in|nullable|string|max:255',
            'room_condition' => 'required_if:action,check_out|nullable|in:good,issue_reported',
            'condition_notes' => 'required_if:room_condition,issue_reported|nullable|string|max:2000',
        ]);
        $manage = $user->canApproveFleetRequests();
        abort_unless($manage || ($decision['action'] === 'cancel' && (int) $booking->user_id === $user->id), 403);

        $record = DB::transaction(function () use ($booking, $decision, $user) {
            // Serialize approvals and physical access even for different booking rows.
            DB::table('boardroom_calendar_lock')->where('id', 1)->increment('revision');
            $record = BoardroomRequest::findOrFail($booking->id);
            $action = $decision['action'];
            $allowed = match ($action) {
                'approve', 'reject' => $record->status === 'pending',
                'cancel' => in_array($record->status, ['pending', 'approved'], true),
                'check_in' => $record->status === 'approved',
                'check_out' => $record->status === 'checked_in',
            };
            if (!$allowed) {
                throw ValidationException::withMessages(['booking' => 'The booking changed. Refresh to see its current status.']);
            }
            if ($action === 'approve') {
                if ($record->ends_at->lte(now())) {
                    throw ValidationException::withMessages(['booking' => 'This booking time has already ended.']);
                }
                $overlap = BoardroomRequest::whereIn('status', ['approved', 'checked_in'])
                    ->where('starts_at', '<', $record->ends_at)->where('ends_at', '>', $record->starts_at)->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['booking' => 'The boardroom already has an approved booking for part of this time.']);
                }
            }
            if ($action === 'check_in') {
                if (now()->lt($record->starts_at->copy()->subMinutes(15)) || now()->gte($record->ends_at)) {
                    throw ValidationException::withMessages(['booking' => 'Check-in opens 15 minutes before the booking and closes at its scheduled end.']);
                }
                if (BoardroomRequest::where('status', 'checked_in')->exists()) {
                    throw ValidationException::withMessages(['booking' => 'Record the previous group’s check-out before handing over the room.']);
                }
            }

            $values = match ($action) {
                'approve', 'reject' => ['status' => $action === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $user->id,
                    'reviewed_at' => now(), 'review_note' => $decision['review_note'] ?? null],
                'cancel' => ['status' => 'cancelled'],
                'check_in' => ['status' => 'checked_in', 'checked_in_by' => $user->id, 'checked_in_at' => now(),
                    'accessed_by_name' => $decision['accessed_by_name']],
                'check_out' => ['status' => 'completed', 'checked_out_by' => $user->id, 'checked_out_at' => now(),
                    'room_condition' => $decision['room_condition'], 'condition_notes' => $decision['condition_notes'] ?? null],
            };
            $record->update($values);

            return $record;
        }, 3);

        if (in_array($decision['action'], ['approve', 'reject', 'cancel'], true) && $record->user_id && $record->user_id !== $user->id) {
            NotificationService::send($record->user_id, 'Boardroom request updated', BoardroomRequest::STATUSES[$record->status], $this->url($record));
        }

        return redirect()->to($this->url($record))->with('status', 'Boardroom request updated.');
    }

    private function authorizeStaff(User $user): void
    {
        abort_unless($user->isActive() && User::internalStaff()->whereKey($user->id)->exists(), 403);
    }

    private function url(BoardroomRequest $booking): string
    {
        return route('portal.boardroom-requests', ['booking' => $booking->id]).'#booking-'.$booking->id;
    }
}
