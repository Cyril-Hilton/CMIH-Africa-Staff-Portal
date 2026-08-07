<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Appraisal;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\ProjectBudget;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\VisitorLog;
use App\Services\TaskStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CVOController extends Controller
{
    /**
     * Determine if user is CVO or Super Admin.
     */
    private function isCVO(User $user): bool
    {
        return $user->isCvoOrSuperAdmin();
    }

    /**
     * CVO Executive Command Centre dashboard.
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();

        if (! $this->isCVO($user)) {
            abort(403, '🔒 This area is reserved for the Chief Visionary Officer.');
        }

        // ── Staff / Headcount ─────────────────────────────────────────────────
        $totalStaff    = User::internalStaff()->where('status', 'active')->count();
        $identityDocuments = User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $staffByDept   = User::internalStaff()->where('status', 'active')
            ->selectRaw('department, COUNT(*) as count')
            ->groupBy('department')
            ->pluck('count', 'department')
            ->toArray();

        // ── Tasks KPIs ────────────────────────────────────────────────────────
        $globalTaskStats = TaskStatsService::global();
        $totalTasks     = $globalTaskStats['total'];
        $completedTasks = $globalTaskStats['completed'];
        $pendingTasks   = $globalTaskStats['pending'];
        $overdueTasks   = $globalTaskStats['overdue'];

        // ── Financial Pending CVO Approval ────────────────────────────────────
        $pendingClaims   = PettyCashClaim::with('user')
            ->whereIn('status', ['Pending', 'Updated', 'Submitted to CVO', 'Finance Approved'])
            ->latest()
            ->get();
        $pendingBudgets  = ProjectBudget::with('creator')
            ->whereIn('status', ['Submitted', 'Submitted to CVO', 'Finance Approved'])
            ->latest()
            ->get();
        $pendingInvoices = SupplierInvoice::with('submittedBy')
            ->whereIn('status', ['Pending', 'Updated', 'Submitted to CVO', 'Finance Approved'])
            ->latest()
            ->get();

        $pendingFinanceCount = $pendingClaims->count() + $pendingBudgets->count() + $pendingInvoices->count();

        // ── Recently CVO-Approved (Finance to process) ───────────────────────
        $cvoApprovedClaims   = PettyCashClaim::with('user')->where('status', 'CVO Approved')->latest()->take(5)->get();
        $cvoApprovedBudgets  = ProjectBudget::with('creator')->where('status', 'CVO Approved')->latest()->take(5)->get();
        $cvoApprovedInvoices = SupplierInvoice::with('submittedBy')->where('status', 'CVO Approved')->latest()->take(5)->get();

        // ── Leave Approvals pending CVO ───────────────────────────────────────
        $pendingLeaves = LeaveApplication::with(['user', 'coveringStaff', 'lineManager'])
            ->whereIn('status', ['pending_cvo', 'pending_manager_2', 'pending_hr_director'])
            ->latest()
            ->get();

        // ── Appraisals needing CVO attention ─────────────────────────────────
        $pendingAppraisals = Appraisal::with(['user', 'reviewer'])
            ->whereIn('status', ['pending_cvo', 'pending_hr'])
            ->latest()
            ->take(10)
            ->get();

        // ── Announcements ─────────────────────────────────────────────────────
        $recentAnnouncements = Announcement::visibleTo($user)->latest()->take(5)->get();

        // ── Recent visitors ───────────────────────────────────────────────────
        $todayVisitors = VisitorLog::whereDate('check_in', today())->count();

        // ── Financial totals ─────────────────────────────────────────────────
        $totalApprovedClaims   = PettyCashClaim::where('status', 'Paid')->sum('amount');
        $totalApprovedInvoices = SupplierInvoice::where('status', 'Paid')->sum('amount');
        $totalPendingValue     = PettyCashClaim::whereIn('status', ['Pending', 'CVO Approved'])->sum('amount')
                               + SupplierInvoice::whereIn('status', ['Pending', 'CVO Approved'])->sum('amount')
                               + ProjectBudget::whereIn('status', ['Submitted', 'CVO Approved'])->sum('total_amount');

        // ── Staff performance (task completion rate per dept) ─────────────────
        $deptTaskStats = collect($staffByDept)
            ->keys()
            ->map(function ($department) {
                $stats = TaskStatsService::forDepartments([$department]);

                return [
                    'dept'      => $department,
                    'total'     => $stats['total'],
                    'completed' => $stats['completed'],
                    'rate'      => round($stats['completion_rate']),
                ];
            });

        return view('portal.cvo', compact(
            'user',
            'totalStaff',
            'staffByDept',
            'totalTasks',
            'completedTasks',
            'pendingTasks',
            'overdueTasks',
            'pendingClaims',
            'pendingBudgets',
            'pendingInvoices',
            'pendingFinanceCount',
            'cvoApprovedClaims',
            'cvoApprovedBudgets',
            'cvoApprovedInvoices',
            'pendingLeaves',
            'pendingAppraisals',
            'recentAnnouncements',
            'todayVisitors',
            'totalApprovedClaims',
            'totalApprovedInvoices',
            'totalPendingValue',
            'deptTaskStats',
            'identityDocuments'
        ));
    }
}
