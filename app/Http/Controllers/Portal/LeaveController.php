<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\LeaveApplicantStatusMail;
use App\Mail\LeaveApprovalNeededMail;
use App\Mail\LeaveCoverNotificationMail;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    /**
     * Display a listing of leaves and the application form.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // User's own leaves history
        $myLeaves = LeaveApplication::with(['lineManager', 'coveringStaff', 'delegateLineManager'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(5, ['*'], 'my_leaves_page')
            ->withQueryString();

        $leaveRecords = LeaveApplication::where('user_id', $user->id)->get();
        $approvedRecords = $leaveRecords->where('status', 'approved');
        $pendingRecords = $leaveRecords->whereIn('status', ['pending_manager', 'pending_cvo', 'pending_hr']);
        $approvedDays = $approvedRecords->sum(fn (LeaveApplication $leave): int => $leave->workingDays());
        $pendingDays = $pendingRecords->sum(fn (LeaveApplication $leave): int => $leave->workingDays());
        $leaveSummary = [
            // The system stores the remaining balance. Reconstruct the current entitlement
            // from that balance plus approved working days already deducted from it.
            'total_entitlement' => (int) $user->leave_balance + $approvedDays,
            'approved_days' => $approvedDays,
            'remaining_days' => (int) $user->leave_balance,
            'pending_days' => $pendingDays,
            'total_requests' => $leaveRecords->count(),
            'approved_requests' => $approvedRecords->count(),
            'pending_requests' => $pendingRecords->count(),
        ];

        // Load active users for selection (exclude applicant)
        $colleagues = User::internalStaff()->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        // Filter managers for line manager selector
        $managers = User::internalStaff()->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->where(function ($query) {
                $query->where('job_level', 'manager')
                      ->orWhere('access_role', 'admin')
                      ->orWhere('access_role', 'super_admin')
                      ->orWhere('access_role', 'manager')
                      ->orWhere('position_title', 'like', '%Manager%')
                      ->orWhere('position_title', 'like', '%Director%');
            })
            ->orderBy('name')
            ->get();

        // Pending Approvals query based on routing matrix
        $pendingApprovalsQuery = LeaveApplication::with(['user', 'coveringStaff', 'lineManager', 'delegateLineManager'])
            ->whereIn('status', ['pending_manager', 'pending_cvo', 'pending_hr']);

        if ($user->access_role === 'super_admin') {
            // Super Admin gets all pending requests
            $pendingApprovals = $pendingApprovalsQuery->latest()->paginate(5, ['*'], 'pending_leaves_page')->withQueryString();
        } else {
            $isCvoApprover = $this->isLeaveCvoApprover($user);
            $isFinalApprover = $this->isLeaveFinalApprover($user);
            $delegatedManagerIds = $user->activeDelegatedManagerIds();

            // Filter query specifically matching user's approval authority
            $pendingApprovals = $pendingApprovalsQuery->where(function ($q) use ($user, $isCvoApprover, $isFinalApprover, $delegatedManagerIds) {
                // 1. Designated line manager for Tier 1 pending (or acting relief line manager)
                $q->where(function ($lmQuery) use ($user, $delegatedManagerIds) {
                    $lmQuery->where('line_manager_id', $user->id);
                    if (! empty($delegatedManagerIds)) {
                        $lmQuery->orWhereIn('line_manager_id', $delegatedManagerIds);
                    }
                })->where('status', 'pending_manager');

                // 1b. Peer line manager — same department, can see pending requests for their dept colleagues
                // 2. Legacy CVO-stage requests
                if ($isCvoApprover) {
                    $q->orWhere('status', 'pending_cvo');
                }

                // 3. Final HR / CVO / Super Admin sign-off after line manager approval
                if ($isFinalApprover) {
                    $q->orWhere('status', 'pending_hr');
                }
            })->latest()->paginate(5, ['*'], 'pending_leaves_page')->withQueryString();
        }

        return view('portal.leaves', compact(
            'user',
            'myLeaves',
            'colleagues',
            'managers',
            'pendingApprovals',
            'leaveSummary'
        ));
    }

    /**
     * Store a newly created leave application.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $requiresLineManager = $this->requiresLineManagerApproval($user);
        $isLineManagerApplicant = $user->isLineManager();
        $activeInternalStaffRule = Rule::exists('users', 'id')->where(function ($query) {
            $query->where('status', 'active')
                ->whereNotIn('access_role', [
                    User::MERCHANDISER_ROLE,
                    User::MERCHANDISER_SUPERVISOR_ROLE,
                    User::BRAND_PROMOTER_ROLE,
                ]);
        });

        $rules = [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['required', 'string', 'in:annual,sick,casual,maternity,paternity'],
            'covering_staff_id' => ['required', 'exists:users,id', 'different:line_manager_id', 'not_in:' . $user->id],
            'delegate_line_manager_id' => [
                $isLineManagerApplicant ? 'required' : 'nullable',
                $activeInternalStaffRule,
                'different:line_manager_id',
                'not_in:' . $user->id,
            ],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];

        $rules['line_manager_id'] = [
            $requiresLineManager ? 'required' : 'nullable',
            'exists:users,id',
            'different:covering_staff_id',
            'not_in:' . $user->id,
        ];

        $validated = $request->validate($rules);

        // Every normal applicant starts with line manager approval, then HR / CVO / Super Admin final sign-off.
        $status = ! empty($validated['line_manager_id']) ? 'pending_manager' : 'pending_hr';

        // Calculate leave days requested, excluding Saturdays and Sundays.
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $days = LeaveApplication::workingDaysBetween($start, $end);

        if ($days < 1) {
            return back()->withErrors(['end_date' => 'Leave requests must include at least one weekday.']);
        }

        if ($user->leave_balance < $days) {
            return back()->withErrors(['leave_balance' => 'You do not have enough leave days remaining (' . $user->leave_balance . ' days left, requested ' . $days . ' days).']);
        }

        $leave = LeaveApplication::create([
            'user_id' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'leave_type' => $validated['leave_type'],
            'line_manager_id' => $validated['line_manager_id'] ?? null,
            'covering_staff_id' => $validated['covering_staff_id'],
            'delegate_line_manager_id' => $isLineManagerApplicant ? ($validated['delegate_line_manager_id'] ?? null) : null,
            'status' => $status,
            'comments' => $validated['comments'] ?? null,
        ]);

        $this->notifyLeaveApprovalNeeded($leave);
        $this->notifyLeaveCoverSelected($leave);

        return back()->with('status', 'Your leave request has been successfully submitted and routed.');
    }

    /**
     * Approve a leave application at the current tier.
     */
    public function approve(Request $request, LeaveApplication $leave): RedirectResponse
    {
        $approver = $request->user();
        $designatedLmId = (int) $leave->line_manager_id;
        $isLineManagerOrDelegate = ($designatedLmId > 0 && (int) $approver->id === $designatedLmId)
            || $approver->isActingLineManagerFor($designatedLmId);

        // Line Manager or active acting relief approval routes to HR / CVO / Super Admin final sign-off.
        if ($leave->status === 'pending_manager' && $isLineManagerOrDelegate) {
            $leave->update(['status' => 'pending_hr']);
            $this->notifyLeaveApprovalNeeded($leave->fresh());
            $onBehalf = $designatedLmId > 0 && (int) $approver->id !== $designatedLmId && $leave->lineManager
                ? " (Acting Line Manager on behalf of {$leave->lineManager->name})"
                : '';
            return back()->with('status', "Leave approved{$onBehalf}. Routed to HR / CVO / Super Admin for final sign-off.");
        }

        // Legacy CVO-stage requests are forwarded into the final sign-off queue.
        if ($leave->status === 'pending_cvo' && $approver->id !== $leave->user_id && $this->isLeaveCvoApprover($approver)) {
            $leave->update(['status' => 'pending_hr']);
            $this->notifyLeaveApprovalNeeded($leave->fresh());
            return back()->with('status', 'Leave approved by CVO. Routed to HR / CVO / Super Admin for final sign-off.');
        }

        // Final sign-off may be completed by HR, CVO, or Super Admin.
        if ($leave->status === 'pending_hr' && $approver->id !== $leave->user_id && $this->isLeaveFinalApprover($approver)) {
            $this->finalizeApproval($leave);
            return back()->with('status', 'Leave request approved (Final sign-off).');
        }

        return back()->withErrors(['leave' => 'You are not authorized to approve this request at its current stage.']);
    }

    /**
     * Reject a leave application.
     */
    public function reject(Request $request, LeaveApplication $leave): RedirectResponse
    {
        if (! in_array($leave->status, $this->reviewableLeaveStatuses(), true)) {
            return back()->withErrors([
                'leave' => 'This leave request is already finalized and can no longer be rejected. Ask the staff member to submit a new request if more changes are needed.',
            ]);
        }

        $approver = $request->user();
        $authorized = false;
        $designatedLmId = (int) $leave->line_manager_id;
        $isLineManagerOrDelegate = ($designatedLmId > 0 && (int) $approver->id === $designatedLmId)
            || $approver->isActingLineManagerFor($designatedLmId);

        if ($approver->access_role === 'super_admin') {
            $authorized = true;
        } elseif ($leave->status === 'pending_manager' && $isLineManagerOrDelegate) {
            $authorized = true;
        } elseif ($leave->status === 'pending_cvo' && $approver->id !== $leave->user_id && $this->isLeaveCvoApprover($approver)) {
            $authorized = true;
        } elseif ($leave->status === 'pending_hr' && $approver->id !== $leave->user_id && $this->isLeaveFinalApprover($approver)) {
            $authorized = true;
        }

        if (! $authorized) {
            return back()->withErrors(['leave' => 'You are not authorized to reject this request.']);
        }

        $leave->update([
            'status' => 'rejected',
            'comments' => $request->input('rejection_comments') ? 'Rejected: ' . $request->input('rejection_comments') : $leave->comments,
        ]);
        $this->notifyLeaveApplicantStatus($leave->fresh(), 'Rejected', $request->input('rejection_comments'));

        return back()->with('status', 'Leave request has been rejected.');
    }

    /**
     * Helper to complete approval, deduct balance, and dispatch cover notification email.
     */
    protected function finalizeApproval(LeaveApplication $leave): void
    {
        $leave->update(['status' => 'approved']);

        $start = Carbon::parse($leave->start_date);
        $end = Carbon::parse($leave->end_date);
        $days = LeaveApplication::workingDaysBetween($start, $end);

        $leave->user->decrement('leave_balance', $days);

        if ($leave->coveringStaff && $leave->coveringStaff->contact_email) {
            try {
                Mail::to($leave->coveringStaff->contact_email)
                    ->send(new LeaveCoverNotificationMail($leave));
            } catch (\Exception $e) {
                Log::error('Leave cover email dispatch failed: ' . $e->getMessage());
            }
        }

        if ($leave->delegate_line_manager_id && (int) $leave->delegate_line_manager_id !== (int) $leave->user_id) {
            $startDateStr = $start->format('M d, Y');
            $endDateStr = $end->format('M d, Y');
            NotificationService::send(
                (int) $leave->delegate_line_manager_id,
                'Appointed Acting Line Manager',
                "{$leave->user->name} appointed you as Acting Line Manager from {$startDateStr} to {$endDateStr}. You are authorized to approve tasks and manage department deliverables during this period.",
                route('portal.leaves')
            );
        }

        $this->notifyLeaveApplicantStatus(
            $leave->fresh(),
            'Approved',
            'Your leave request has passed line manager and final HR/CVO/Super Admin approval.'
        );
    }

    /**
     * Return a leave request for correction.
     */
    public function returnForCorrection(Request $request, LeaveApplication $leave): RedirectResponse
    {
        if (! in_array($leave->status, $this->reviewableLeaveStatuses(), true)) {
            return back()->withErrors([
                'leave' => 'This leave request is already finalized and can no longer be returned for correction. Ask the staff member to submit a new request if more changes are needed.',
            ]);
        }

        $approver = $request->user();
        $authorized = false;
        $designatedLmId = (int) $leave->line_manager_id;
        $isLineManagerOrDelegate = ($designatedLmId > 0 && (int) $approver->id === $designatedLmId)
            || $approver->isActingLineManagerFor($designatedLmId);

        if ($approver->access_role === 'super_admin') {
            $authorized = true;
        } elseif ($leave->status === 'pending_manager' && $isLineManagerOrDelegate) {
            $authorized = true;
        } elseif ($leave->status === 'pending_cvo' && $approver->id !== $leave->user_id && $this->isLeaveCvoApprover($approver)) {
            $authorized = true;
        } elseif ($leave->status === 'pending_hr' && $approver->id !== $leave->user_id && $this->isLeaveFinalApprover($approver)) {
            $authorized = true;
        }

        if (! $authorized) {
            return back()->withErrors(['leave' => 'You are not authorized to return this request.']);
        }

        $reason = $request->input('rejection_comments') ?: 'No correction notes provided.';
        $leave->update([
            'status' => 'returned_for_correction',
            'comments' => 'Returned for Correction: ' . $reason,
        ]);
        $this->notifyLeaveApplicantStatus($leave->fresh(), 'Returned for Correction', $reason);

        return back()->with('status', '🔄 Leave request returned to user for correction.');
    }

    /**
     * Resubmit corrected leave application.
     */
    public function resubmit(Request $request, LeaveApplication $leave): RedirectResponse
    {
        $user = $request->user();
        if ((int) $leave->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! in_array($leave->status, $this->editableLeaveStatuses(), true)) {
            return back()->withErrors([
                'leave' => 'This leave request can no longer be edited because it has already been finalized. Please submit a new leave request for any extra days or changes.',
            ]);
        }

        $requiresLineManager = $this->requiresLineManagerApproval($user);
        $isLineManagerApplicant = $user->isLineManager();
        $activeInternalStaffRule = Rule::exists('users', 'id')->where(function ($query) {
            $query->where('status', 'active')
                ->whereNotIn('access_role', [
                    User::MERCHANDISER_ROLE,
                    User::MERCHANDISER_SUPERVISOR_ROLE,
                    User::BRAND_PROMOTER_ROLE,
                ]);
        });

        $rules = [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['required', 'string', 'in:annual,sick,casual,maternity,paternity'],
            'covering_staff_id' => ['required', 'exists:users,id', 'different:line_manager_id', 'not_in:' . $user->id],
            'delegate_line_manager_id' => [
                $isLineManagerApplicant ? 'required' : 'nullable',
                $activeInternalStaffRule,
                'different:line_manager_id',
                'not_in:' . $user->id,
            ],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];

        $rules['line_manager_id'] = [
            $requiresLineManager ? 'required' : 'nullable',
            'exists:users,id',
            'different:covering_staff_id',
            'not_in:' . $user->id,
        ];

        $validated = $request->validate($rules);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $days = LeaveApplication::workingDaysBetween($start, $end);

        if ($days < 1) {
            return back()->withErrors(['end_date' => 'Leave requests must include at least one weekday.']);
        }

        if ($user->leave_balance < $days) {
            return back()->withErrors(['leave_balance' => 'You do not have enough leave days remaining (' . $user->leave_balance . ' days left, requested ' . $days . ' days).']);
        }

        $status = ! empty($validated['line_manager_id']) ? 'pending_manager' : 'pending_hr';

        $leave->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'leave_type' => $validated['leave_type'],
            'line_manager_id' => $validated['line_manager_id'] ?? null,
            'covering_staff_id' => $validated['covering_staff_id'],
            'delegate_line_manager_id' => $isLineManagerApplicant ? ($validated['delegate_line_manager_id'] ?? null) : null,
            'status' => $status,
            'comments' => $validated['comments'] ?? null,
        ]);

        $leave = $leave->fresh();
        $this->notifyLeaveApprovalNeeded($leave);
        $this->notifyLeaveCoverSelected($leave);

        return back()->with('status', 'Leave request updated and routed for approval.');
    }

    protected function editableLeaveStatuses(): array
    {
        return [
            'pending_manager',
            'pending_cvo',
            'pending_hr',
            'returned_for_correction',
        ];
    }

    protected function reviewableLeaveStatuses(): array
    {
        return [
            'pending_manager',
            'pending_cvo',
            'pending_hr',
        ];
    }

    protected function requiresLineManagerApproval(User $user): bool
    {
        return $user->access_role !== 'super_admin';
    }

    protected function isLeaveCvoApprover(User $user): bool
    {
        $position = strtolower(trim($user->position_title ?? ''));
        $jobLevel = strtolower(trim($user->job_level ?? ''));

        return $user->access_role === 'super_admin'
            || $jobLevel === 'super_admin'
            || $jobLevel === 'cvo'
            || $position === 'cvo';
    }

    protected function isLeaveFinalApprover(User $user): bool
    {
        return $user->hasFullHrAccess() || $this->isLeaveCvoApprover($user);
    }

    protected function finalLeaveApproverIds(?int $excludeUserId = null): array
    {
        return array_values(array_unique(array_merge(
            NotificationService::activeHrApproverIds($excludeUserId),
            NotificationService::activeCvoApproverIds($excludeUserId)
        )));
    }

    protected function notifyLeaveCoverSelected(LeaveApplication $leave): void
    {
        $leave->loadMissing(['user', 'coveringStaff']);

        if (! $leave->covering_staff_id || $leave->covering_staff_id === $leave->user_id) {
            return;
        }

        $start = Carbon::parse($leave->start_date)->format('M d, Y');
        $end = Carbon::parse($leave->end_date)->format('M d, Y');

        NotificationService::send(
            (int) $leave->covering_staff_id,
            'Leave Cover Duty Assigned',
            "{$leave->user->name} selected you to cover their duties from {$start} to {$end}.",
            route('portal.leaves')
        );
    }

    protected function notifyLeaveApplicantStatus(LeaveApplication $leave, string $statusLabel, ?string $note = null): void
    {
        $leave->loadMissing(['user', 'lineManager', 'coveringStaff']);

        if (! $leave->user) {
            return;
        }

        NotificationService::send(
            (int) $leave->user_id,
            "Leave Request {$statusLabel}",
            "Your {$leave->leave_type} leave request has been marked as {$statusLabel}.",
            route('portal.leaves')
        );

        $recipient = $leave->user->contact_email ?: $leave->user->email;
        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new LeaveApplicantStatusMail($leave, $statusLabel, $note));
        } catch (\Throwable $e) {
            Log::error('Leave applicant status email dispatch failed: ' . $e->getMessage(), [
                'leave_id' => $leave->id,
                'user_id' => $leave->user_id,
                'status' => $statusLabel,
            ]);
        }
    }

    protected function notifyLeaveApprovalNeeded(LeaveApplication $leave): void
    {
        $leave->loadMissing(['user', 'lineManager', 'coveringStaff']);

        $approverIds = match ($leave->status) {
            'pending_manager' => array_filter([(int) $leave->line_manager_id]),
            'pending_cvo' => NotificationService::activeCvoApproverIds($leave->user_id),
            'pending_hr' => $this->finalLeaveApproverIds($leave->user_id),
            default => null,
        };

        if ($approverIds === null) {
            return;
        }

        $notificationTitle = 'Leave Approval Needed';
        $notificationMessage = "{$leave->user->name} submitted a {$leave->leave_type} leave request that needs approval.";

        if ($leave->status === 'pending_hr') {
            $cvoApproverIds = NotificationService::activeCvoApproverIds($leave->user_id);
            $hrApproverIds = array_diff(
                NotificationService::activeHrApproverIds($leave->user_id),
                $cvoApproverIds
            );

            NotificationService::sendToMany(
                $hrApproverIds,
                $notificationTitle,
                $notificationMessage,
                route('portal.hr')
            );
            NotificationService::sendToMany(
                $cvoApproverIds,
                $notificationTitle,
                $notificationMessage,
                route('portal.leaves')
            );
        } else {
            NotificationService::sendApprovalNeededToMany(
                $approverIds,
                $notificationTitle,
                $notificationMessage,
                route('portal.leaves'),
                $leave->user_id
            );
        }

        $emailApproverIds = $approverIds;
        if ($leave->status === 'pending_manager') {
            $emailApproverIds = array_merge(
                $emailApproverIds,
                NotificationService::activeHrApproverIds($leave->user_id)
            );
        }

        $emailApproverIds = array_merge(
            $emailApproverIds,
            NotificationService::activeSuperAdminIds($leave->user_id)
        );

        $approvers = User::whereIn('id', array_values(array_unique(array_filter($emailApproverIds))))
            ->where('status', 'active')
            ->get();

        foreach ($approvers as $approver) {
            $recipient = $approver->contact_email ?: $approver->email;
            if (! $recipient) {
                continue;
            }

            try {
                Mail::to($recipient)->send(new LeaveApprovalNeededMail($leave, $approver));
            } catch (\Throwable $e) {
                Log::error('Leave approval email dispatch failed: ' . $e->getMessage(), [
                    'leave_id' => $leave->id,
                    'approver_id' => $approver->id,
                ]);
            }
        }
    }
}
