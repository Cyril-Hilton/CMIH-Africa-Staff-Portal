<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AppraisalMetric;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Campaign;
use App\Models\FreelancePromoter;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\Task;
use App\Models\ThirdPartyVendor;
use App\Models\User;
use App\Models\VisitorLog;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    private function authorizeDepartment(string $deptName, User $user): void
    {
        $isSuperAdmin = $user->access_role === 'super_admin';
        
        if ($isSuperAdmin) {
            return;
        }

        $accessGroups = [
            'hr_admin'            => 'admin',
            'finance'             => 'finance',
            'client_relations'    => 'creatives',
            'creatives'           => 'creatives',
            'operations_projects' => 'operations',
            'brands_marketing'    => 'brands',
        ];
        
        $userDeptKey = User::normalizeDepartmentKey($user->department);
        $reqDeptKey  = User::normalizeDepartmentKey($deptName);
        $userDeptNorm = $accessGroups[$userDeptKey] ?? $userDeptKey;
        $reqDeptNorm  = $accessGroups[$reqDeptKey] ?? $reqDeptKey;

        if ($userDeptNorm === $reqDeptNorm) {
            return;
        }

        abort(403, '🔒 Access Denied. You do not belong to the ' . ucfirst($deptName) . ' department.');
    }

    // ─── CVO Helper ───────────────────────────────────────────────────────────

    /**
     * Determine if user is CVO (Chief Visionary Officer) or Super Admin.
     * CVO is identified by job_level = super_admin OR access_role = super_admin.
     */
    private function isCVO(User $user): bool
    {
        return $user->isCvoOrSuperAdmin();
    }

    private function canViewFinanceFile(User $user, int $ownerId): bool
    {
        return (int) $user->id === $ownerId
            || User::normalizeDepartmentKey($user->department) === 'finance'
            || $this->isCVO($user);
    }

    private function downloadPrivateOrLegacyPublicFile(?string $path, ?string $downloadName = null)
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $downloadName = $downloadName ?: basename($path);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $downloadName);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $downloadName);
        }

        abort(404);
    }

    // ─── Currency Helper ──────────────────────────────────────────────────────

    private const ALLOWED_CURRENCIES = ['GHC', 'GH₵', 'USD', 'EUR', 'GBP', 'NGN', 'ZAR', 'XOF', 'SLE'];

    private function validateCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === 'GHC') {
            return 'GH₵';
        }
        return in_array($currency, self::ALLOWED_CURRENCIES, true)
            ? $currency : 'GH₵';
    }

    /**
     * Staff-facing Visitor Pre-Ticketing View
     */
    public function visitors(Request $request): View
    {
        $user = $request->user();
        $staff = User::internalStaff()->orderBy('name')->get();
        
        // Regular staff only see pre-tickets where they are the host or which they created
        $preTickets = \App\Models\VisitorPreTicket::where('host_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->with('host')
            ->orderBy('expected_arrival')
            ->paginate(8, ['*'], 'pt_page')
            ->withQueryString();

        return view('portal.visitors', compact('preTickets', 'staff'));
    }

    /**
     * HR & Admin Department View
     */
    public function hr(Request $request): View
    {
        $viewer = $request->user();
        $this->authorizeDepartment('admin', $viewer);

        $visitors         = VisitorLog::with('host')->latest()->paginate(5, ['*'], 'v_page')->withQueryString();
        $staff            = User::internalStaff()->orderBy('name')->get();
        $metrics          = AppraisalMetric::orderBy('category')->get();
        $preTickets       = \App\Models\VisitorPreTicket::with('host')->orderBy('expected_arrival')->paginate(5, ['*'], 'pt_page')->withQueryString();
        $directoryEntries = \App\Models\PhoneDirectory::orderBy('category')->orderBy('name')->paginate(5, ['*'], 'd_page')->withQueryString();
        $recentAnnouncements = Announcement::with('user')->latest()->take(6)->get();
        $identityDocuments = $viewer->canReviewIdentityDocuments()
            ? User::internalStaff()->where('status', 'active')->orderBy('name')->get()
            : collect();
        $canManageLeaves = $viewer->hasFullHrAccess();
        $allLeaves = $canManageLeaves
            ? LeaveApplication::with(['user', 'lineManager', 'coveringStaff', 'delegateLineManager'])
                ->latest('start_date')
                ->latest('id')
                ->paginate(15, ['*'], 'leave_page')
                ->withQueryString()
            : collect();
        $today = today();
        $leaveStats = $canManageLeaves ? [
            'pending' => LeaveApplication::whereIn('status', ['pending_manager', 'pending_cvo', 'pending_hr'])->count(),
            'approved' => LeaveApplication::where('status', 'approved')->count(),
            'active' => LeaveApplication::where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'completed' => LeaveApplication::where('status', 'approved')
                ->whereDate('end_date', '<', $today)
                ->count(),
        ] : ['pending' => 0, 'approved' => 0, 'active' => 0, 'completed' => 0];

        return view('portal.departments.hr', compact(
            'visitors',
            'staff',
            'metrics',
            'preTickets',
            'directoryEntries',
            'recentAnnouncements',
            'identityDocuments',
            'canManageLeaves',
            'allLeaves',
            'leaveStats'
        ));
    }

    public function storeHrAnnouncement(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());

        if (! $request->user()->canManageHrAnnouncements()) {
            abort(403, 'Access denied. You cannot send HR announcements.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:3000'],
            'pinned' => ['nullable', 'boolean'],
            'audience_type' => ['required', 'string', 'in:all,selected,departments'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'department_keys' => ['nullable', 'array'],
            'department_keys.*' => ['string', 'in:hr_admin,finance,client_relations,operations_projects,brands_marketing,creatives'],
        ]);

        $recipientIds = collect($validated['recipient_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $departmentKeys = collect($validated['department_keys'] ?? [])
            ->map(fn ($department) => User::normalizeDepartmentKey($department))
            ->filter()
            ->unique()
            ->values();

        if ($validated['audience_type'] === 'selected' && $recipientIds->isEmpty()) {
            return back()->withErrors(['recipient_ids' => 'Select at least one staff member for a selected-staff announcement.'])->withInput();
        }

        if ($validated['audience_type'] === 'departments' && $departmentKeys->isEmpty()) {
            return back()->withErrors(['department_keys' => 'Select at least one department for a department announcement.'])->withInput();
        }

        $announcement = Announcement::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'pinned' => (bool) ($validated['pinned'] ?? false),
            'audience_type' => $validated['audience_type'],
            'recipient_ids' => $validated['audience_type'] === 'selected' ? $recipientIds->all() : null,
            'department_keys' => $validated['audience_type'] === 'departments' ? $departmentKeys->all() : null,
        ]);

        $recipients = User::internalStaff()
            ->where('status', 'active')
            ->where('id', '!=', $request->user()->id);

        if ($validated['audience_type'] === 'selected') {
            $recipients->whereIn('id', $recipientIds->all());
        }

        $recipientUsers = $recipients->get(['id', 'department']);

        if ($validated['audience_type'] === 'departments') {
            $allowedDepartments = $departmentKeys->all();
            $recipientUsers = $recipientUsers->filter(
                fn (User $recipient) => in_array(User::normalizeDepartmentKey($recipient->department), $allowedDepartments, true)
            );
        }

        $recipientIds = $recipientUsers->pluck('id')->map(fn ($id) => (int) $id)->all();

        NotificationService::sendToMany(
            $recipientIds,
            'HR Announcement: ' . $announcement->title,
            $announcement->plainBody(150),
            route('portal.announcements')
        );

        return back()->with('status', 'Announcement sent to ' . count($recipientIds) . ' staff member(s).');
    }

    public function updateLeaveBalance(Request $request, User $user): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());
        abort_unless($request->user()->hasFullHrAccess(), 403);

        $validated = $request->validate([
            'leave_balance' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $user->update([
            'leave_balance' => (int) $validated['leave_balance'],
        ]);

        return back()->with('status', "{$user->name}'s leave balance has been updated to {$validated['leave_balance']} day(s).");
    }

    /**
     * Store new visitor
     */
    public function storeVisitor(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:1000'],
            'host_id' => ['required', 'exists:users,id'],
        ]);

        VisitorLog::create([
            'name' => $request->input('name'),
            'company' => $request->input('company'),
            'purpose' => $request->input('purpose'),
            'host_id' => $request->input('host_id'),
            'time_in' => Carbon::now(),
            'status' => 'checked_in',
        ]);

        return back()->with('status', 'Visitor registered and checked in successfully!');
    }

    /**
     * Checkout visitor
     */
    public function checkoutVisitor(VisitorLog $visitor, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());

        $visitor->update([
            'time_out' => Carbon::now(),
            'status' => 'checked_out',
        ]);

        return back()->with('status', 'Visitor checked out successfully.');
    }

    public function storeAppraisalMetric(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:General,Technical,Leadership'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metric_type' => ['required', 'string', 'in:slider,table'],
            'table_template' => ['nullable', 'string'],
            'default_rows' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $template = null;
        if ($request->input('metric_type') === 'table' && $request->filled('table_template')) {
            $template = json_decode($request->input('table_template'), true);
        }

        AppraisalMetric::create([
            'name' => $request->name,
            'category' => $request->category,
            'description' => $request->description,
            'metric_type' => $request->metric_type,
            'table_template' => $template,
            'default_rows' => $request->input('default_rows', 3),
        ]);

        return back()->with('status', 'Appraisal metric added successfully!');
    }

    /**
     * Delete appraisal metric
     */
    public function destroyAppraisalMetric(AppraisalMetric $metric, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());

        $metric->delete();

        return back()->with('status', 'Appraisal metric deleted.');
    }

    /**
     * Finance Department View
     */
    public function finance(Request $request): View
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        if ($isFinance) {
            $claims   = PettyCashClaim::with('user')->latest()->paginate(5, ['*'], 'c_page')->withQueryString();
            $invoices = \App\Models\SupplierInvoice::with('submitter')->latest()->paginate(5, ['*'], 'i_page')->withQueryString();
        } else {
            $claims   = PettyCashClaim::where('user_id', $user->id)->with('user')->latest()->paginate(5, ['*'], 'c_page')->withQueryString();
            $invoices = \App\Models\SupplierInvoice::where('submitted_by', $user->id)->with('submitter')->latest()->paginate(5, ['*'], 'i_page')->withQueryString();
        }

        return view('portal.departments.finance', compact('user', 'claims', 'invoices'));
    }

    /**
     * Submit reimbursement claim
     */
    public function storeClaim(Request $request): RedirectResponse
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['nullable', 'string'],
            'description' => ['required', 'string', 'max:2000'],
            'receipt'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'submit_to'   => ['required', 'string', 'in:finance,cvo'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'local');
        }

        $currency = $this->validateCurrency($request->input('currency', 'GHC'));
        $submitTo = $request->input('submit_to', 'cvo');
        
        $status = ($submitTo === 'finance') ? 'Submitted to Finance' : 'Submitted to CVO';

        $claim = PettyCashClaim::create([
            'user_id'      => $request->user()->id,
            'amount'       => $request->input('amount'),
            'currency'     => $currency,
            'description'  => $request->input('description'),
            'receipt_path' => $receiptPath,
            'status'       => $status,
        ]);

        if ($submitTo === 'finance') {
            // Notify Finance Users
            $financeUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('department', 'finance')
                      ->orWhere('access_role', 'super_admin');
                })->get();
            foreach ($financeUsers as $fin) {
                \App\Services\NotificationService::send(
                    $fin->id,
                    'New Expense Claim Submitted to Finance',
                    "{$request->user()->name} submitted a claim of {$claim->currency} {$claim->amount} to Finance.",
                    route('portal.finance')
                );
            }
            return back()->with('status', '📤 Claim submitted to Finance for verification.');
        } else {
            // Notify CVO Users
            $cvoUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('access_role', 'super_admin')
                      ->orWhere('job_level', 'super_admin');
                })->get();
            foreach ($cvoUsers as $cvo) {
                \App\Services\NotificationService::send(
                    $cvo->id,
                    'New Expense Claim Submitted to CVO',
                    "{$request->user()->name} submitted a claim of {$claim->currency} {$claim->amount} directly to CVO.",
                    route('portal.cvo')
                );
            }
            return back()->with('status', '📤 Claim submitted directly to CVO for approval.');
        }
    }

    /**
     * Finance actions on claims — ONLY after CVO has approved.
     * CVO actions are handled via cvoActionClaim().
     */
    public function actionClaim(PettyCashClaim $claim, string $action, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('finance', $request->user());

        $action = strtolower(trim($action));

        if ($claim->status === 'Submitted to Finance') {
            if ($action === 'verify' || $action === 'approve') {
                $claim->update(['status' => 'Finance Approved']);
                
                // Notify CVO/Super Admin
                $cvoUsers = User::internalStaff()->where('status', 'active')
                    ->where(function($q) {
                        $q->where('access_role', 'super_admin')
                          ->orWhere('job_level', 'super_admin');
                    })->get();
                foreach ($cvoUsers as $cvo) {
                    \App\Services\NotificationService::send(
                        $cvo->id,
                        'Petty Cash Claim Approved by Finance',
                        "A claim from {$claim->user->name} has been verified and approved by Finance. Needs your final approval.",
                        route('portal.cvo')
                    );
                }

                return back()->with('status', '✅ Claim verified and approved by Finance. Sent to CVO.');
            }

            if ($action === 'reject') {
                $claim->update(['status' => 'Rejected']);
                
                // Notify claimant
                \App\Services\NotificationService::send(
                    $claim->user_id,
                    'Claim Rejected',
                    "Your petty cash claim of {$claim->currency} {$claim->amount} has been rejected by Finance.",
                    route('portal.finance')
                );

                return back()->with('status', 'Claim rejected by Finance.');
            }

            if ($action === 'return') {
                $claim->update([
                    'status' => 'Returned for Correction',
                    'notes' => $request->input('notes'),
                ]);

                // Notify claimant
                \App\Services\NotificationService::send(
                    $claim->user_id,
                    'Claim Returned for Correction',
                    "Your petty cash claim of {$claim->currency} {$claim->amount} has been returned for correction by Finance.",
                    route('portal.finance')
                );

                return back()->with('status', 'Claim returned to the creator for correction.');
            }

            return back()->withErrors(['error' => 'Invalid action for Submitted to Finance status.']);
        }

        // Finance can only Pay, Flag, or Reject AFTER CVO has approved
        if ($claim->status !== 'CVO Approved') {
            return back()->withErrors(['error' => '⚠️ This claim requires CVO approval first before Finance can action it.']);
        }

        $validActions = ['flag' => 'Flagged', 'reject' => 'Rejected', 'pay' => 'Paid', 'return' => 'Returned for Correction'];
        $status = $validActions[$action] ?? null;

        if (! $status) {
            return back()->withErrors(['error' => 'Invalid action performed on claim.']);
        }

        $updateData = ['status' => $status];
        if ($status === 'Returned for Correction') {
            $updateData['notes'] = $request->input('notes');
        }

        $claim->update($updateData);

        // Notify claimant
        \App\Services\NotificationService::send(
            $claim->user_id,
            'Claim Status Updated',
            "Your petty cash claim status is now: {$status}.",
            route('portal.finance')
        );

        return back()->with('status', "Claim successfully marked as {$status}.");
    }

    public function downloadClaimReceipt(PettyCashClaim $claim, Request $request)
    {
        if (! $this->canViewFinanceFile($request->user(), (int) $claim->user_id)) {
            abort(403);
        }

        return $this->downloadPrivateOrLegacyPublicFile(
            $claim->receipt_path,
            'claim-'.$claim->id.'-receipt.'.pathinfo((string) $claim->receipt_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * CVO approves or rejects a petty cash/reimbursement claim.
     */
    public function cvoActionClaim(PettyCashClaim $claim, string $action, Request $request): RedirectResponse
    {
        if (! $this->isCVO($request->user())) {
            abort(403, 'Only the CVO or Super Admin can approve financial claims.');
        }

        if ($action === 'approve') {
            $claim->update(['status' => 'CVO Approved']);
            return back()->with('status', '✅ Claim approved by CVO. Finance can now process it.');
        }

        if ($action === 'reject') {
            $claim->update(['status' => 'Rejected']);
            return back()->with('status', '✗ Claim rejected by CVO.');
        }

        if ($action === 'return') {
            $claim->update([
                'status' => 'Returned for Correction',
                'notes' => $request->input('notes'),
            ]);
            return back()->with('status', '🔄 Claim returned by CVO for correction.');
        }

        return back()->withErrors(['error' => 'Invalid CVO action.']);
    }

    /**
     * Resubmit a corrected reimbursement claim
     */
    public function resubmitClaim(Request $request, PettyCashClaim $claim): RedirectResponse
    {
        $user = $request->user();
        if ($claim->user_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403);
        }

        $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['nullable', 'string'],
            'description' => ['required', 'string', 'max:2000'],
            'receipt'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'submit_to'   => ['required', 'string', 'in:finance,cvo'],
        ]);

        $submitTo = $request->input('submit_to', 'cvo');
        $status = ($submitTo === 'finance') ? 'Submitted to Finance' : 'Submitted to CVO';

        $updateData = [
            'amount'      => $request->input('amount'),
            'currency'    => $this->validateCurrency($request->input('currency', 'GHC')),
            'description' => $request->input('description'),
            'notes'       => null,
            'status'      => $status,
        ];

        if ($request->hasFile('receipt')) {
            if ($claim->receipt_path) {
                Storage::disk('local')->delete($claim->receipt_path);
                Storage::disk('public')->delete($claim->receipt_path);
            }

            $updateData['receipt_path'] = $request->file('receipt')->store('receipts', 'local');
        }

        $claim->update($updateData);

        if ($submitTo === 'finance') {
            // Notify Finance Users
            $financeUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('department', 'finance')
                      ->orWhere('access_role', 'super_admin');
                })->get();
            foreach ($financeUsers as $fin) {
                \App\Services\NotificationService::send(
                    $fin->id,
                    'New Petty Cash Claim Resubmitted to Finance',
                    "{$user->name} resubmitted a claim of {$claim->currency} {$claim->amount} to Finance for verification.",
                    route('portal.finance')
                );
            }
            return back()->with('status', '📤 Claim resubmitted to Finance for verification.');
        } else {
            // Notify CVO Users
            $cvoUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('access_role', 'super_admin')
                      ->orWhere('job_level', 'super_admin');
                })->get();
            foreach ($cvoUsers as $cvo) {
                \App\Services\NotificationService::send(
                    $cvo->id,
                    'New Petty Cash Claim Resubmitted to CVO',
                    "{$user->name} resubmitted a claim of {$claim->currency} {$claim->amount} directly to CVO.",
                    route('portal.cvo')
                );
            }
            return back()->with('status', '📤 Claim resubmitted directly to CVO.');
        }
    }

    /**
     * Operations & Projects Department View
     */
    public function operations(Request $request): View
    {
        $user = $request->user();
        $this->authorizeDepartment('operations', $user);

        $vendors    = ThirdPartyVendor::with('project')->latest()->paginate(5, ['*'], 'v_page')->withQueryString();
        $promoters  = FreelancePromoter::latest()->paginate(5, ['*'], 'p_page')->withQueryString();
        $assets     = Asset::latest()->get();
        $assetLogs  = AssetLog::with(['asset', 'user'])->latest()->paginate(5, ['*'], 'al_page')->withQueryString();
        $projects   = Task::whereIn('department', ['operations', 'operations_projects'])->realWork()->latest()->get();
        $campaigns  = Campaign::with(['projectLead', 'tasks.assignee', 'tasks.assigner', 'assetLogs.asset', 'assetLogs.user', 'campaignPhotos'])
            ->latest()
            ->get()
            ->each(function (Campaign $campaign) use ($user) {
                $campaign->setRelation(
                    'tasks',
                    $campaign->tasks
                        ->filter(fn (Task $task) => $this->canViewCampaignTaskActivity($task, $user))
                        ->values()
                );
            });
        $staff      = User::internalStaff()->where('status', 'active')->orderBy('name')->get();

        return view('portal.departments.operations', compact('vendors', 'promoters', 'assets', 'assetLogs', 'projects', 'campaigns', 'staff'));
    }

    private function canViewCampaignTaskActivity(Task $task, User $viewer): bool
    {
        if (! $this->isSensitiveCampaignTask($task)) {
            return true;
        }

        if ($viewer->isCvoOrSuperAdmin() || $viewer->hasRole('admin')) {
            return true;
        }

        if ($this->departmentKey($viewer) === $this->departmentKey($task)) {
            return true;
        }

        if ($task->isAssociatedWith($viewer)) {
            return true;
        }

        return (int) ($task->assignee?->line_manager_id ?? 0) === (int) $viewer->id;
    }

    private function isSensitiveCampaignTask(Task $task): bool
    {
        return in_array($this->departmentKey($task), ['finance', 'hr_admin', 'admin'], true);
    }

    private function departmentKey(User|Task $model): string
    {
        $department = strtolower(trim((string) $model->department));

        return match ($department) {
            'hr admin', 'hr_admin', 'human resources', 'transport' => 'hr_admin',
            'operations', 'operations projects', 'operations_projects' => 'operations_projects',
            'brands', 'brand', 'brand marketing', 'brands marketing', 'brands_marketing' => 'brands_marketing',
            'client service', 'client_service', 'client relations', 'client_relations' => 'client_relations',
            'creative', 'creatives' => 'creatives',
            default => preg_replace('/[^a-z0-9]+/', '_', $department) ?: $department,
        };
    }

    /**
     * Add third party vendor
     */
    public function storeVendor(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('operations', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:Projects,Office'],
            'assigned_project_id' => ['nullable', 'exists:tasks,id'],
            'performance_review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        ThirdPartyVendor::create($request->only('name', 'category', 'assigned_project_id', 'performance_review_notes'));

        return back()->with('status', 'Vendor details saved successfully!');
    }

    /**
     * Add freelance promoter
     */
    public function storePromoter(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('operations', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:255'],
            'tshirt_size' => ['nullable', 'string', 'max:8'],
            'height' => ['nullable', 'string', 'max:16'],
        ]);

        FreelancePromoter::create($request->only('name', 'contact', 'city', 'language', 'tshirt_size', 'height'));

        return back()->with('status', 'Freelance Promoter registered.');
    }

    /**
     * Asset Log Checkout
     */
    public function checkoutAsset(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('operations', $request->user());

        $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        AssetLog::create([
            'asset_id' => $request->input('asset_id'),
            'user_id' => $request->input('user_id'),
            'action' => 'checkout',
            'notes' => $request->input('notes'),
            'reported_condition' => 'Good',
        ]);

        // Update asset status
        Asset::find($request->input('asset_id'))->update([
            'status' => 'checked_out',
            'assigned_to' => $request->input('user_id'),
        ]);

        return back()->with('status', 'Asset checked out successfully.');
    }

    /**
     * Asset Log Checkin
     */
    public function checkinAsset(AssetLog $log, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('operations', $request->user());

        $request->validate([
            'reported_condition' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        AssetLog::create([
            'asset_id' => $log->asset_id,
            'user_id' => $request->user()->id,
            'action' => 'checkin',
            'reported_condition' => $request->input('reported_condition'),
            'notes' => $request->input('notes'),
        ]);

        // Update asset status
        Asset::find($log->asset_id)->update([
            'status' => 'available',
            'condition' => $request->input('reported_condition'),
            'assigned_to' => null,
        ]);

        return back()->with('status', 'Asset checked back in successfully.');
    }

    /**
     * Brands & Marketing Department View
     */
    public function brands(Request $request): View
    {
        $this->authorizeDepartment('brands', $request->user());

        $materials   = Asset::where('type', 'POSM')->orWhere('type', 'posm')->latest()->paginate(5, ['*'], 'm_page')->withQueryString();
        $blueprints  = Asset::where('type', 'blueprint')->latest()->paginate(5, ['*'], 'bp_page')->withQueryString();
        $posmEntries = \App\Models\PosmLedger::with('creator')->latest()->paginate(5, ['*'], 'pe_page')->withQueryString();

        return view('portal.departments.brands', compact('materials', 'blueprints', 'posmEntries'));
    }

    /**
     * Store strategy blueprint file
     */
    public function storeStrategy(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('brands', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:8192'],
        ]);

        $filePath = $request->file('file')->store('blueprints', 'public');

        Asset::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => 'blueprint',
            'status' => 'available',
            'location' => 'Digital Vault',
            'notes' => 'Strategy blueprint file uploaded.',
            'image_path' => $filePath,
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Brand Strategy Blueprint uploaded successfully.');
    }

    /**
     * Creative Department View
     */
    public function creative(Request $request): View
    {
        $this->authorizeDepartment('creatives', $request->user());

        $briefs           = Task::where('department', 'creatives')->realWork()->latest()->paginate(5, ['*'], 'b_page')->withQueryString();
        $designs          = Asset::where('type', 'design')->latest()->paginate(5, ['*'], 'd_page')->withQueryString();
        $creativeComments = \App\Models\CreativeComment::with(['user', 'task'])->latest()->paginate(5, ['*'], 'c_page')->withQueryString();

        return view('portal.departments.creative', compact('briefs', 'designs', 'creativeComments'));
    }

    /**
     * Store creative brief
     */
    public function storeBrief(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('creatives', $request->user());

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:255'],
            'job_description_custom' => ['nullable', 'required_if:job_description,Other', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'due_on' => ['nullable', 'date'],
        ]);

        $jobDesc = $request->input('job_description');
        if ($jobDesc === 'Other' && $request->filled('job_description_custom')) {
            $jobDesc = $request->input('job_description_custom');
        }

        $fullTitle = '[' . $jobDesc . '] ' . $request->input('title');

        Task::create([
            'title' => $fullTitle,
            'details' => $request->input('details'),
            'assigned_to' => $request->user()->id,
            'assigned_by' => $request->user()->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => ucfirst($request->input('priority')),
            'due_on' => $request->input('due_on'),
            'progress' => 0,
        ]);

        return back()->with('status', 'Creative Design Brief submitted successfully!');
    }

    /**
     * Store design checkin
     */
    public function storeDesignFile(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('creatives', $request->user());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:255'],
            'job_description_custom' => ['nullable', 'required_if:job_description,Other', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $filePath = $request->file('file')->store('designs', 'public');
        
        $jobDesc = $request->input('job_description');
        if ($jobDesc === 'Other' && $request->filled('job_description_custom')) {
            $jobDesc = $request->input('job_description_custom');
        }

        $fullName = '[' . $jobDesc . '] ' . $request->input('name');

        Asset::create([
            'name' => $fullName,
            'description' => $request->input('description'),
            'type' => 'design',
            'status' => 'available',
            'location' => 'Creative Hub',
            'notes' => 'Design file check-in version.',
            'image_path' => $filePath,
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Design file version checked in successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PHASE 3 METHODS
    // ──────────────────────────────────────────────────────────────────────────

    /** HR: Store visitor pre-ticket */
    public function storePreTicket(Request $request): RedirectResponse
    {
        $request->validate([
            'visitor_name'     => ['required', 'string', 'max:255'],
            'visitor_company'  => ['nullable', 'string', 'max:255'],
            'visitor_email'    => ['nullable', 'email', 'max:255'],
            'visitor_phone'    => ['nullable', 'string', 'max:30'],
            'purpose'          => ['required', 'string', 'max:1000'],
            'host_id'          => ['required', 'exists:users,id'],
            'expected_arrival' => ['required', 'date'],
        ]);
        \App\Models\VisitorPreTicket::create(array_merge(
            $request->only('visitor_name', 'visitor_company', 'visitor_email', 'visitor_phone', 'purpose', 'host_id', 'expected_arrival'),
            ['created_by' => $request->user()->id, 'status' => 'pending']
        ));
        return back()->with('status', 'Visitor pre-ticket created. Front desk has been notified.');
    }

    /** HR: Mark pre-ticket as arrived */
    public function markPreTicketArrived(\App\Models\VisitorPreTicket $ticket, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('admin', $request->user());
        $ticket->update(['status' => 'arrived']);
        return back()->with('status', 'Visitor marked as arrived.');
    }

    /** HR: Store phone/vendor directory entry */
    public function storeDirectoryEntry(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isDev = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
        $canEdit = $isDev || $user->access_role === 'super_admin'
            || in_array(strtolower($user->department ?? ''), ['admin', 'hr_admin']);
        if (! $canEdit) abort(403, 'Only HR/Admin or Super Admin can manage the directory.');
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department'=> ['nullable', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'extension' => ['nullable', 'string', 'max:10'],
            'email'     => ['nullable', 'email', 'max:255'],
            'category'  => ['required', 'string', 'in:staff,vendor,client,emergency'],
            'company'   => ['nullable', 'string', 'max:255'],
        ]);
        \App\Models\PhoneDirectory::create(array_merge(
            $request->only('name', 'job_title', 'department', 'phone', 'extension', 'email', 'category', 'company'),
            ['is_vendor' => $request->input('category') === 'vendor']
        ));
        return back()->with('status', 'Directory entry added successfully.');
    }

    /** HR: Delete directory entry */
    public function destroyDirectoryEntry(\App\Models\PhoneDirectory $entry, Request $request): RedirectResponse
    {
        $user = $request->user();
        $isDev = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
        if (! $isDev && $user->access_role !== 'super_admin'
            && ! in_array(strtolower($user->department ?? ''), ['admin', 'hr_admin'])) {
            abort(403);
        }
        $entry->delete();
        return back()->with('status', 'Directory entry removed.');
    }

    /**
     * View Salary Advances
     */
    public function advancesIndex(Request $request): View
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';
            
        $isCVO = $user->job_level === 'super_admin'
            || $user->access_role === 'super_admin';

        if ($isFinance || $isCVO) {
            $advances = \App\Models\SalaryAdvance::with('user')->latest()->get();
            $pendingCvoAdvances = \App\Models\SalaryAdvance::with('user')
                ->where('status', 'pending_cvo')
                ->latest()
                ->get();
        } else {
            $advances = \App\Models\SalaryAdvance::where('user_id', $user->id)->with('user')->latest()->get();
            $pendingCvoAdvances = collect();
        }

        return view('portal.finance.advances', compact('user', 'advances', 'pendingCvoAdvances', 'isFinance', 'isCVO'));
    }

    /** Finance: Store supplier invoice */
    public function storeInvoice(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_name'  => ['required', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'description'    => ['required', 'string', 'max:2000'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'currency'       => ['nullable', 'string'],
            'task_id'        => ['nullable', 'exists:tasks,id'],
            'attachment'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'submit_to'      => ['required', 'string', 'in:finance,cvo'],
        ]);
        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('invoices', 'local');
        }
        $currency = $this->validateCurrency($request->input('currency', 'GHC'));
        $submitTo = $request->input('submit_to', 'cvo');
        
        $status = ($submitTo === 'finance') ? 'Submitted to Finance' : 'Submitted to CVO';

        $invoice = \App\Models\SupplierInvoice::create([
            'submitted_by'    => $request->user()->id,
            'task_id'         => $request->input('task_id'),
            'invoice_number'  => $request->input('invoice_number'),
            'supplier_name'   => $request->input('supplier_name'),
            'description'     => $request->input('description'),
            'amount'          => $request->input('amount'),
            'currency'        => $currency,
            'attachment_path' => $path,
            'status'          => $status,
        ]);

        if ($submitTo === 'finance') {
            // Notify Finance Users
            $financeUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('department', 'finance')
                      ->orWhere('access_role', 'super_admin');
                })->get();
            foreach ($financeUsers as $fin) {
                \App\Services\NotificationService::send(
                    $fin->id,
                    'New Supplier Invoice Submitted to Finance',
                    "{$request->user()->name} submitted invoice #{$invoice->invoice_number} from {$invoice->supplier_name} to Finance.",
                    route('portal.finance')
                );
            }
            return back()->with('status', '📤 Supplier invoice submitted to Finance for verification.');
        } else {
            // Notify CVO Users
            $cvoUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('access_role', 'super_admin')
                      ->orWhere('job_level', 'super_admin');
                })->get();
            foreach ($cvoUsers as $cvo) {
                \App\Services\NotificationService::send(
                    $cvo->id,
                    'New Supplier Invoice Submitted to CVO',
                    "{$request->user()->name} submitted invoice #{$invoice->invoice_number} from {$invoice->supplier_name} directly to CVO.",
                    route('portal.cvo')
                );
            }
            return back()->with('status', '📤 Supplier invoice submitted directly to CVO for approval.');
        }
    }

    /** Finance: Action on invoice */
    public function actionInvoice(\App\Models\SupplierInvoice $invoice, string $action, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('finance', $request->user());

        $action = strtolower(trim($action));

        if ($invoice->status === 'Submitted to Finance') {
            if ($action === 'verify' || $action === 'approve') {
                $invoice->update(['status' => 'Finance Approved']);
                
                // Notify CVO/Super Admin
                $cvoUsers = User::internalStaff()->where('status', 'active')
                    ->where(function($q) {
                        $q->where('access_role', 'super_admin')
                          ->orWhere('job_level', 'super_admin');
                    })->get();
                foreach ($cvoUsers as $cvo) {
                    \App\Services\NotificationService::send(
                        $cvo->id,
                        'Supplier Invoice Approved by Finance',
                        "Invoice #{$invoice->invoice_number} from {$invoice->supplier_name} has been verified and approved by Finance. Needs your final approval.",
                        route('portal.cvo')
                    );
                }

                return back()->with('status', '✅ Invoice verified and approved by Finance. Sent to CVO.');
            }

            if ($action === 'reject') {
                $invoice->update(['status' => 'Rejected']);
                
                // Notify submitter
                \App\Services\NotificationService::send(
                    $invoice->submitted_by,
                    'Invoice Rejected',
                    "Your supplier invoice #{$invoice->invoice_number} from {$invoice->supplier_name} has been rejected by Finance.",
                    route('portal.finance')
                );

                return back()->with('status', 'Invoice rejected by Finance.');
            }

            if ($action === 'return') {
                $invoice->update([
                    'status' => 'Returned for Correction',
                    'notes' => $request->input('notes'),
                ]);

                // Notify submitter
                \App\Services\NotificationService::send(
                    $invoice->submitted_by,
                    'Invoice Returned for Correction',
                    "Your supplier invoice #{$invoice->invoice_number} from {$invoice->supplier_name} has been returned for correction by Finance.",
                    route('portal.finance')
                );

                return back()->with('status', 'Invoice returned to the creator for correction.');
            }

            return back()->withErrors(['error' => 'Invalid action for Submitted to Finance status.']);
        }

        // Finance can only Pay, Flag, or Reject AFTER CVO has approved
        if ($invoice->status !== 'CVO Approved') {
            return back()->withErrors(['error' => '⚠️ This invoice requires CVO approval first before Finance can action it.']);
        }

        $validActions = ['flag' => 'Flagged', 'reject' => 'Rejected', 'pay' => 'Paid', 'return' => 'Returned for Correction'];
        $status = $validActions[$action] ?? null;

        if (! $status) {
            return back()->withErrors(['error' => 'Invalid action performed on invoice.']);
        }

        $updateData = ['status' => $status];
        if ($status === 'Returned for Correction') {
            $updateData['notes'] = $request->input('notes');
        }

        $invoice->update($updateData);

        // Notify submitter
        \App\Services\NotificationService::send(
            $invoice->submitted_by,
            'Invoice Status Updated',
            "Your supplier invoice status is now: {$status}.",
            route('portal.finance')
        );

        return back()->with('status', "Invoice successfully marked as {$status}.");
    }

    /** CVO: Approve or reject a supplier invoice */
    public function cvoActionInvoice(\App\Models\SupplierInvoice $invoice, string $action, Request $request): RedirectResponse
    {
        if (! $this->isCVO($request->user())) {
            abort(403, 'Only the CVO or Super Admin can approve supplier invoices.');
        }

        if ($action === 'approve') {
            $invoice->update(['status' => 'CVO Approved']);
            return back()->with('status', '✅ Invoice approved by CVO. Finance can now process it.');
        }

        if ($action === 'reject') {
            $invoice->update(['status' => 'Rejected']);
            return back()->with('status', '✗ Invoice rejected by CVO.');
        }

        if ($action === 'return') {
            $invoice->update([
                'status' => 'Returned for Correction',
                'notes' => $request->input('notes'),
            ]);
            return back()->with('status', '🔄 Invoice returned by CVO for correction.');
        }

        return back()->withErrors(['error' => 'Invalid CVO action.']);
    }

    public function downloadInvoiceAttachment(\App\Models\SupplierInvoice $invoice, Request $request)
    {
        if (! $this->canViewFinanceFile($request->user(), (int) $invoice->submitted_by)) {
            abort(403);
        }

        return $this->downloadPrivateOrLegacyPublicFile(
            $invoice->attachment_path,
            'invoice-'.$invoice->id.'-attachment.'.pathinfo((string) $invoice->attachment_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * Resubmit a corrected supplier invoice
     */
    public function resubmitInvoice(Request $request, \App\Models\SupplierInvoice $invoice): RedirectResponse
    {
        $user = $request->user();
        if ($invoice->submitted_by !== $user->id && !$user->hasRole('super_admin')) {
            abort(403);
        }

        $request->validate([
            'supplier_name'  => ['required', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'description'    => ['required', 'string', 'max:2000'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'currency'       => ['nullable', 'string'],
            'task_id'        => ['nullable', 'exists:tasks,id'],
            'attachment'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'submit_to'      => ['required', 'string', 'in:finance,cvo'],
        ]);

        $submitTo = $request->input('submit_to', 'cvo');
        $status = ($submitTo === 'finance') ? 'Submitted to Finance' : 'Submitted to CVO';

        $updateData = [
            'supplier_name'  => $request->input('supplier_name'),
            'invoice_number' => $request->input('invoice_number'),
            'description'    => $request->input('description'),
            'amount'         => $request->input('amount'),
            'currency'       => $this->validateCurrency($request->input('currency', 'GHC')),
            'task_id'        => $request->input('task_id'),
            'notes'          => null,
            'status'         => $status,
        ];

        if ($request->hasFile('attachment')) {
            if ($invoice->attachment_path) {
                Storage::disk('local')->delete($invoice->attachment_path);
                Storage::disk('public')->delete($invoice->attachment_path);
            }

            $updateData['attachment_path'] = $request->file('attachment')->store('invoices', 'local');
        }

        $invoice->update($updateData);

        if ($submitTo === 'finance') {
            // Notify Finance Users
            $financeUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('department', 'finance')
                      ->orWhere('access_role', 'super_admin');
                })->get();
            foreach ($financeUsers as $fin) {
                \App\Services\NotificationService::send(
                    $fin->id,
                    'Supplier Invoice Resubmitted to Finance',
                    "{$user->name} resubmitted invoice #{$invoice->invoice_number} from {$invoice->supplier_name} to Finance for verification.",
                    route('portal.finance')
                );
            }
            return back()->with('status', '📤 Invoice resubmitted to Finance for verification.');
        } else {
            // Notify CVO Users
            $cvoUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('access_role', 'super_admin')
                      ->orWhere('job_level', 'super_admin');
                })->get();
            foreach ($cvoUsers as $cvo) {
                \App\Services\NotificationService::send(
                    $cvo->id,
                    'Supplier Invoice Resubmitted to CVO',
                    "{$user->name} resubmitted invoice #{$invoice->invoice_number} from {$invoice->supplier_name} directly to CVO.",
                    route('portal.cvo')
                );
            }
            return back()->with('status', '📤 Invoice resubmitted directly to CVO.');
        }
    }

    /** Brands: Store POSM ledger entry */
    public function storePosmEntry(Request $request): RedirectResponse
    {
        $this->authorizeDepartment('brands', $request->user());
        $request->validate([
            'item_name'    => ['required', 'string', 'max:255'],
            'item_type'    => ['required', 'string', 'in:POSM,Uniform,Banner,Tablet,AV,Other'],
            'client_brand' => ['nullable', 'string', 'max:255'],
            'quantity_in'  => ['required', 'integer', 'min:0'],
            'quantity_out' => ['required', 'integer', 'min:0'],
            'location'     => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);
        \App\Models\PosmLedger::create(array_merge(
            $request->only('item_name', 'item_type', 'client_brand', 'quantity_in', 'quantity_out', 'location', 'notes'),
            ['created_by' => $request->user()->id]
        ));
        return back()->with('status', 'POSM / Materials ledger entry saved.');
    }

    /** Brands: Delete POSM entry */
    public function destroyPosmEntry(\App\Models\PosmLedger $entry, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('brands', $request->user());
        $entry->delete();
        return back()->with('status', 'POSM entry removed.');
    }

    /** Creative: Store proofing comment on a brief */
    public function storeCreativeComment(Task $task, Request $request): RedirectResponse
    {
        $this->authorizeDepartment('creatives', $request->user());
        $request->validate([
            'comment'       => ['required', 'string', 'max:2000'],
            'version_label' => ['nullable', 'string', 'max:20'],
            'status'        => ['required', 'string', 'in:feedback,approved,revision_requested'],
            'attachment'    => ['nullable', 'file', 'max:10240'],
        ]);
        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('creative_files', 'public');
        }
        \App\Models\CreativeComment::create([
            'task_id'         => $task->id,
            'user_id'         => $request->user()->id,
            'comment'         => $request->input('comment'),
            'version_label'   => $request->input('version_label'),
            'status'          => $request->input('status'),
            'attachment_path' => $path,
        ]);
        return back()->with('status', 'Proofing comment added to design brief.');
    }

    /**
     * Submit salary advance / staff loan request
     */
    public function storeAdvance(Request $request): RedirectResponse
    {
        $user = $request->user();
        $maxAmount = $user->monthlySalary() * 2;

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$maxAmount}"],
            'repayment_style' => ['required', 'string', 'in:monthly_deduction,pay_all_at_once'],
            'monthly_deduction_amount' => [
                'nullable',
                'required_if:repayment_style,monthly_deduction',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->repayment_style === 'monthly_deduction' && $value < 1000) {
                        $fail('The monthly deduction amount must be at least 1000 GH₵.');
                    }
                }
            ],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        \App\Models\SalaryAdvance::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'repayment_style' => $request->repayment_style,
            'monthly_deduction_amount' => $request->repayment_style === 'monthly_deduction' ? $request->monthly_deduction_amount : null,
            'reason' => $request->reason,
            'status' => 'pending_finance',
        ]);

        NotificationService::sendApprovalNeededToMany(
            NotificationService::activeFinanceApproverIds($user->id),
            'Salary Advance Verification Needed',
            "{$user->name} submitted a salary advance request for Finance verification.",
            route('portal.finance.advances.index'),
            $user->id
        );

        return back()->with('status', '📤 Salary advance request submitted to Finance for verification.');
    }

    /**
     * Resubmit a corrected salary advance request
     */
    public function resubmitAdvance(Request $request, \App\Models\SalaryAdvance $advance): RedirectResponse
    {
        $user = $request->user();
        if ($advance->user_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403);
        }

        $maxAmount = $user->monthlySalary() * 2;

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$maxAmount}"],
            'repayment_style' => ['required', 'string', 'in:monthly_deduction,pay_all_at_once'],
            'monthly_deduction_amount' => [
                'nullable',
                'required_if:repayment_style,monthly_deduction',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->repayment_style === 'monthly_deduction' && $value < 1000) {
                        $fail('The monthly deduction amount must be at least 1000 GH₵.');
                    }
                }
            ],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $advance->update([
            'amount' => $request->amount,
            'repayment_style' => $request->repayment_style,
            'monthly_deduction_amount' => $request->repayment_style === 'monthly_deduction' ? $request->monthly_deduction_amount : null,
            'reason' => $request->reason,
            'status' => 'pending_finance',
            'finance_feedback' => null,
        ]);

        NotificationService::sendApprovalNeededToMany(
            NotificationService::activeFinanceApproverIds($user->id),
            'Salary Advance Verification Needed',
            "{$user->name} resubmitted a salary advance request for Finance verification.",
            route('portal.finance.advances.index'),
            $user->id
        );

        return back()->with('status', '📤 Salary advance request resubmitted to Finance.');
    }

    /**
     * Action on a salary advance request by Finance department
     */
    public function financeActionAdvance(Request $request, \App\Models\SalaryAdvance $advance): RedirectResponse
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin'
            || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);

        if (!$isFinance) {
            abort(403, 'Only Finance department staff or Super Admin can action this request.');
        }

        $request->validate([
            'action' => ['required', 'string', 'in:verify,correction,reject'],
            'feedback' => ['nullable', 'required_if:action,correction', 'string', 'max:1000'],
        ]);

        $action = $request->action;

        if ($action === 'verify') {
            $advance->update([
                'status' => 'pending_cvo',
            ]);

            NotificationService::sendApprovalNeededToMany(
                NotificationService::activeCvoApproverIds($user->id),
                'Salary Advance Approval Needed',
                "Finance verified {$advance->user->name}'s salary advance request. CVO approval is needed.",
                route('portal.finance.advances.index'),
                $user->id
            );
            return back()->with('status', '✅ Verified salary advance. Forwarded to CVO for approval.');
        } elseif ($action === 'correction') {
            $advance->update([
                'status' => 'returned_for_correction',
                'finance_feedback' => $request->feedback,
            ]);
            return back()->with('status', '🔄 Request returned to user for correction.');
        } else {
            $advance->update([
                'status' => 'rejected',
            ]);
            return back()->with('status', '✗ Salary advance request rejected.');
        }
    }

    /**
     * Action on a salary advance request by CVO
     */
    public function cvoActionAdvance(Request $request, \App\Models\SalaryAdvance $advance): RedirectResponse
    {
        if (!$this->isCVO($request->user())) {
            abort(403, 'Only CVO or Super Admin can approve salary advance requests.');
        }

        $request->validate([
            'action' => ['required', 'string', 'in:approve,reject,return_to_finance,return_for_correction'],
            'feedback' => ['nullable', 'required_if:action,return_for_correction', 'string', 'max:1000'],
        ]);

        $action = $request->action;

        if ($action === 'approve') {
            $advance->update([
                'status' => 'approved',
            ]);
            return back()->with('status', '🎉 Salary advance approved by CVO.');
        } elseif ($action === 'return_to_finance') {
            $advance->update([
                'status' => 'pending_finance',
            ]);

            NotificationService::sendApprovalNeededToMany(
                NotificationService::activeFinanceApproverIds($request->user()->id),
                'Salary Advance Re-Verification Needed',
                "CVO returned {$advance->user->name}'s salary advance request to Finance for verification.",
                route('portal.finance.advances.index'),
                $request->user()->id
            );
            return back()->with('status', '🔄 Salary advance request returned to Finance for verification.');
        } elseif ($action === 'return_for_correction') {
            $advance->update([
                'status' => 'returned_for_correction',
                'finance_feedback' => $request->feedback,
            ]);
            return back()->with('status', '🔄 Request returned to user for correction by CVO.');
        } else {
            $advance->update([
                'status' => 'rejected',
            ]);
            return back()->with('status', '✗ Salary advance request rejected by CVO.');
        }
    }
}
