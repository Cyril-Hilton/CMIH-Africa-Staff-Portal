<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\AppraisalMetric;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PerformanceScoringService;
use App\Services\TaskStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppraisalController extends Controller
{
    // ─── Permission helpers ───────────────────────────────────────────────────

    private function isSuperAdmin(User $user): bool
    {
        return $user->access_role === 'super_admin';
    }

    private function isDeveloper(User $user): bool
    {
        return in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    }

    private function isHR(User $user): bool
    {
        return $user->hasFullHrAccess();
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    /**
     * Staff personal appraisal ledger + overall metrics.
     */
    public function index(Request $request): View
    {
        $user       = $request->user();
        $metrics    = AppraisalMetric::orderBy('category')->get();
        $appraisals = Appraisal::where('user_id', $user->id)
            ->latest()
            ->paginate(5, ['*'], 'my_appraisals_page')
            ->withQueryString();

        // Manager transparency matrix: list appraisals of staff assigned to them
        if ($this->isHR($user)) {
            $staffAppraisals = Appraisal::with('employee')
                ->latest()
                ->paginate(5, ['*'], 'staff_appraisals_page')
                ->withQueryString();
        } elseif (in_array($user->access_role, ['admin', 'manager'])) {
            $deptStaff = User::where('department', $user->department)
                ->where('id', '!=', $user->id)
                ->pluck('id');
            $staffAppraisals = Appraisal::with('employee')
                ->whereIn('user_id', $deptStaff)
                ->latest()
                ->paginate(5, ['*'], 'staff_appraisals_page')
                ->withQueryString();
        } else {
            $staffAppraisals = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 5, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'staff_appraisals_page'
            ]);
        }

        // Compute individual performance stats with the shared task and attendance definitions.
        $statsStart = PerformanceScoringService::trackingStartedAt() ?? Carbon::today();
        $statsEnd = Carbon::today();
        $taskStats = TaskStatsService::forUser($user);
        $attendanceStats = PerformanceScoringService::attendanceSummary($user, $statsStart, $statsEnd);
        $overtimeHours  = round(
            Attendance::where('user_id', $user->id)->sum('overtime_minutes') / 60,
            1
        );

        $stats = [
            'completion_rate' => round($taskStats['completion_rate']),
            'punctuality'     => round($attendanceStats['punctuality_score']),
            'overtime_hours'  => $overtimeHours,
            'total_tasks'     => $taskStats['assigned_total'],
            'completed_tasks' => $taskStats['completed'],
        ];

        // 1. Profile Tracking Ledger details
        $myRecentTasks = Task::where('assigned_to', $user->id)->realWork()->latest()->take(10)->get();
        $myRecentAttendances = Attendance::where('user_id', $user->id)->latest()->take(10)->get();

        // 2. Manager Transparency Matrix details
        $matrixStaff = collect();
        if ($this->isHR($user)) {
            $matrixStaff = User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        } elseif (in_array($user->access_role, ['admin', 'manager'])) {
            $matrixStaff = User::where('department', $user->department)
                ->internalStaff()
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get();
        }

        $transparencyMatrix = [];
        foreach ($matrixStaff as $staff) {
            $staffTaskStats = TaskStatsService::forUser($staff);
            $staffAttendanceStats = PerformanceScoringService::attendanceSummary($staff, $statsStart, $statsEnd);
            $sOvertime = round(Attendance::where('user_id', $staff->id)->sum('overtime_minutes') / 60, 1);
            $latenesses = Attendance::where('user_id', $staff->id)->where('status', 'Late')->count();

            // Calculate average check-in delay (minutes) for late arrivals
            $allAttendances = Attendance::where('user_id', $staff->id)->get();
            $totalDelayMinutes = 0;
            $lateCount = 0;
            foreach ($allAttendances as $att) {
                if ($att->clock_in_at) {
                    $clockInTime = Carbon::parse($att->clock_in_at);
                    $nineAmTime = $clockInTime->copy()->setTime(9, 0, 0);
                    if ($clockInTime->gt($nineAmTime)) {
                        $totalDelayMinutes += $clockInTime->diffInMinutes($nineAmTime);
                        $lateCount++;
                    }
                }
            }
            $avgDelayMinutes = $lateCount > 0 ? round($totalDelayMinutes / $lateCount) : 0;

            $transparencyMatrix[] = [
                'user'               => $staff,
                'total_tasks'        => $staffTaskStats['assigned_total'],
                'completion_rate'    => round($staffTaskStats['completion_rate']),
                'punctuality'        => round($staffAttendanceStats['punctuality_score']),
                'overtime_hours'     => $sOvertime,
                'latenesses'         => $latenesses,
                'avg_delay_minutes'  => $avgDelayMinutes,
            ];
        }

        return view('portal.appraisals.index', compact(
            'user', 'metrics', 'appraisals', 'staffAppraisals', 'stats',
            'myRecentTasks', 'myRecentAttendances', 'transparencyMatrix'
        ));
    }

    // ─── Create/Open appraisal cycle ─────────────────────────────────────────

    /**
     * HR/Super Admin opens a fresh appraisal cycle for a staff member.
     */
    public function create(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->isHR($user)) {
            abort(403, 'Only HR or Super Admin can open appraisal cycles.');
        }

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'quarter' => ['required', 'string', 'in:Q1,Q2,Q3,Q4'],
            'year'    => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $exists = Appraisal::where('user_id', $request->user_id)
            ->where('quarter', $request->quarter)
            ->where('year', $request->year)
            ->exists();

        if ($exists) {
            return back()->withErrors(['appraisal' => 'An appraisal cycle already exists for this staff member, quarter, and year.']);
        }

        Appraisal::create([
            'user_id' => $request->user_id,
            'quarter' => $request->quarter,
            'year'    => $request->year,
            'status'  => 'draft',
        ]);

        return back()->with('status', "Appraisal cycle {$request->quarter} {$request->year} opened for staff member.");
    }

    // ─── Step 1: Self Assessment ──────────────────────────────────────────────

    public function showSelfForm(Appraisal $appraisal, Request $request): View
    {
        $user = $request->user();

        if ($appraisal->user_id !== $user->id && ! $this->isSuperAdmin($user) && ! $this->isDeveloper($user)) {
            abort(403, 'You can only submit your own self-assessment.');
        }

        if (! in_array($appraisal->status, ['draft', 'self_assessment'])) {
            return redirect()->route('portal.appraisals.index')
                ->withErrors(['appraisal' => 'Self-assessment phase is already closed for this cycle.']);
        }

        $metrics = AppraisalMetric::orderBy('category')->get();

        return view('portal.appraisals.form', compact('appraisal', 'metrics', 'user'));
    }

    public function submitSelf(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();

        if ($appraisal->user_id !== $user->id && ! $this->isSuperAdmin($user) && ! $this->isDeveloper($user)) {
            abort(403);
        }

        if (! in_array($appraisal->status, ['draft', 'self_assessment'])) {
            return back()->withErrors(['appraisal' => 'Self-assessment window has closed.']);
        }

        $request->validate([
            'scores'     => ['nullable', 'array'],
            'scores.*'   => ['required', 'integer', 'min:1', 'max:10'],
            'table_data' => ['nullable', 'array'],
            'comments'   => ['nullable', 'string', 'max:3000'],
        ]);

        $scores = $request->input('scores', []);
        $tableData = $request->input('table_data', []);

        foreach ($tableData as $metricId => $rows) {
            $rowScores = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (isset($row['score']) && is_numeric($row['score'])) {
                        $rowScores[] = (int) $row['score'];
                    }
                }
            }
            if (count($rowScores) > 0) {
                $scores[$metricId] = (int) round(array_sum($rowScores) / count($rowScores));
            } else {
                $scores[$metricId] = 5;
            }
        }

        $appraisal->update([
            'self_assessment' => [
                'scores'       => $scores,
                'comments'     => $request->input('comments'),
                'submitted_at' => now()->toDateTimeString(),
            ],
            'self_table_data' => $tableData,
            'status' => 'submitted',
        ]);

        $employee = $appraisal->user;
        $approverIds = [];
        if ($employee?->line_manager_id) {
            $approverIds[] = (int) $employee->line_manager_id;
        }

        NotificationService::sendApprovalNeededToMany(
            $approverIds,
            'Appraisal Manager Review Needed',
            "{$employee?->name} submitted a self-assessment that needs manager review.",
            route('portal.appraisals.manager.form', $appraisal),
            $employee?->id
        );

        return redirect()->route('portal.appraisals.index')
            ->with('status', '✅ Self-assessment submitted successfully. Your line manager has been notified to proceed.');
    }

    // ─── Step 2: Manager Review ───────────────────────────────────────────────

    public function showManagerForm(Appraisal $appraisal, Request $request): View
    {
        $user = $request->user();

        $canReview = $this->isHR($user)
            || in_array($user->access_role, ['admin', 'manager'])
            || $this->isSuperAdmin($user);

        if (! $canReview) {
            abort(403, 'Only managers, HR, or Super Admin can complete manager reviews.');
        }

        if ($appraisal->status !== 'submitted') {
            return redirect()->route('portal.appraisals.index')
                ->withErrors(['appraisal' => 'This appraisal is not ready for manager review.']);
        }

        $metrics     = AppraisalMetric::orderBy('category')->get();
        $selfScores  = $appraisal->self_assessment['scores'] ?? [];

        return view('portal.appraisals.manager-form', compact('appraisal', 'metrics', 'user', 'selfScores'));
    }

    public function submitManager(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();

        $canReview = $this->isHR($user)
            || in_array($user->access_role, ['admin', 'manager'])
            || $this->isSuperAdmin($user);

        if (! $canReview) {
            abort(403);
        }

        if ($appraisal->status !== 'submitted') {
            return back()->withErrors(['appraisal' => 'Appraisal is not at manager review stage.']);
        }

        $request->validate([
            'scores'          => ['nullable', 'array'],
            'scores.*'        => ['required', 'integer', 'min:1', 'max:10'],
            'table_data'      => ['nullable', 'array'],
            'manager_comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $scores = $request->input('scores', []);
        $tableData = $request->input('table_data', []);

        foreach ($tableData as $metricId => $rows) {
            $rowScores = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (isset($row['score']) && is_numeric($row['score'])) {
                        $rowScores[] = (int) $row['score'];
                    }
                }
            }
            if (count($rowScores) > 0) {
                $scores[$metricId] = (int) round(array_sum($rowScores) / count($rowScores));
            } else {
                $scores[$metricId] = 5;
            }
        }

        $appraisal->update([
            'manager_review' => [
                'reviewer_id'     => $user->id,
                'reviewer_name'   => $user->name,
                'scores'          => $scores,
                'manager_comment' => $request->input('manager_comment'),
                'reviewed_at'     => now()->toDateTimeString(),
            ],
            'manager_table_data' => $tableData,
            'status' => 'manager_reviewed',
        ]);

        NotificationService::sendApprovalNeededToMany(
            NotificationService::activeHrApproverIds($user->id),
            'Appraisal HR Audit Needed',
            "{$appraisal->employee?->name}'s appraisal has been reviewed by a manager and needs HR audit.",
            route('portal.appraisals.audit.form', $appraisal),
            $user->id
        );

        return redirect()->route('portal.appraisals.index')
            ->with('status', '✅ Manager review submitted. HR will now conduct the final audit.');
    }

    // ─── Step 3: HR Final Audit ───────────────────────────────────────────────

    public function showAuditForm(Appraisal $appraisal, Request $request): View
    {
        $user = $request->user();
        if (! $this->isHR($user)) {
            abort(403, 'Only HR or Super Admin can audit appraisals.');
        }

        if ($appraisal->status !== 'manager_reviewed') {
            return redirect()->route('portal.appraisals.index')
                ->withErrors(['appraisal' => 'This appraisal has not yet completed the manager review stage.']);
        }

        $metrics    = AppraisalMetric::orderBy('category')->get();
        $selfScores = $appraisal->self_assessment['scores'] ?? [];
        $mgr        = $appraisal->manager_review ?? [];

        return view('portal.appraisals.audit-form', compact('appraisal', 'metrics', 'user', 'selfScores', 'mgr'));
    }

    public function submitAudit(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->isHR($user)) {
            abort(403);
        }

        if ($appraisal->status !== 'manager_reviewed') {
            return back()->withErrors(['appraisal' => 'Cannot audit: wrong stage.']);
        }

        $request->validate([
            'hr_notes'       => ['required', 'string', 'max:3000'],
            'final_decision' => ['required', 'string', 'in:approved,revision_requested'],
        ]);

        $current = $appraisal->manager_review ?? [];
        $current['hr_notes']       = $request->input('hr_notes');
        $current['final_decision'] = $request->input('final_decision');
        $current['hr_auditor_id']  = $user->id;
        $current['audited_at']     = now()->toDateTimeString();

        $appraisal->update([
            'manager_review' => $current,
            'status'         => $request->input('final_decision') === 'approved' ? 'approved' : 'submitted',
        ]);

        $msg = $request->final_decision === 'approved'
            ? '🏆 Appraisal approved and finalised!'
            : '🔄 Appraisal sent back for revision.';

        return redirect()->route('portal.appraisals.index')->with('status', $msg);
    }

    // ─── Super Admin Override Unlock ──────────────────────────────────────────

    public function unlock(Appraisal $appraisal, Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->isSuperAdmin($user) && ! $this->isDeveloper($user)) {
            abort(403, 'Only Super Admin can unlock appraisals mid-flight.');
        }

        $appraisal->update(['status' => 'draft']);

        return back()->with('status', '🔓 Appraisal unlocked and reset to draft stage.');
    }

    /**
     * Generate printable performance accountability report for a specific user.
     */
    public function report(Request $request, User $user): View
    {
        $currentUser = $request->user();
        
        // Authorization check: Must be HR, or Manager of the same department
        $isAuthorized = false;
        if ($this->isHR($currentUser)) {
            $isAuthorized = true;
        } elseif (in_array($currentUser->access_role, ['admin', 'manager'], true)) {
            if ($currentUser->department === $user->department) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized access to this performance report.');
        }

        // 1. Task calculations & list
        $taskStats = TaskStatsService::forUser($user);
        $allTasks = Task::where('assigned_to', $user->id)->realWork()->orderBy('due_on', 'asc')->get();
        $totalTasks = $taskStats['assigned_total'];
        $completedTasks = $allTasks->filter->isApprovedForPerformance();
        $completedCount = $taskStats['completed'];
        
        $completedEarly = 0;
        $completedLate = 0;
        foreach ($completedTasks as $task) {
            if ($task->due_on) {
                $completedDate = Carbon::parse($task->updated_at)->startOfDay();
                $dueDate = Carbon::parse($task->due_on)->startOfDay();
                if ($completedDate->lte($dueDate)) {
                    $completedEarly++;
                } else {
                    $completedLate++;
                }
            } else {
                $completedEarly++;
            }
        }
        
        $pendingCount = $taskStats['pending'];
        $completionRate = round($taskStats['completion_rate']);

        // 2. Attendance calculations & list
        $allAttendances = Attendance::where('user_id', $user->id)->orderBy('clock_in_at', 'desc')->get();
        $attendanceReportStart = PerformanceScoringService::trackingStartedAt() ?? Carbon::today();
        $attendanceSummary = PerformanceScoringService::attendanceSummary($user, $attendanceReportStart, Carbon::today());
        $totalDays = max(1, (int) $attendanceSummary['expected_workdays']);
        $punctualCount = (int) $attendanceSummary['punctual_days'];
        $punctuality = round($attendanceSummary['punctuality_score']);
        $totalOvertimeMin = $allAttendances->sum('overtime_minutes');
        $overtimeHours = round($totalOvertimeMin / 60, 1);
        $latenesses = $allAttendances->where('status', 'Late')->count();

        // Calculate average check-in delay (minutes) for late arrivals
        $totalDelayMinutes = 0;
        $lateCount = 0;
        foreach ($allAttendances as $att) {
            if ($att->clock_in_at) {
                $clockInTime = Carbon::parse($att->clock_in_at);
                $nineAmTime = $clockInTime->copy()->setTime(9, 0, 0);
                if ($clockInTime->gt($nineAmTime)) {
                    $totalDelayMinutes += $clockInTime->diffInMinutes($nineAmTime);
                    $lateCount++;
                }
            }
        }
        $avgDelayMinutes = $lateCount > 0 ? round($totalDelayMinutes / $lateCount) : 0;

        // 3. Appraisal History
        $appraisals = Appraisal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->orderBy('year', 'desc')
            ->orderBy('quarter', 'desc')
            ->get();

        // 4. Monthly Overtime (last 6 months with attendance logs)
        $monthlyOvertime = Attendance::where('user_id', $user->id)
            ->whereNotNull('clock_out_at')
            ->get()
            ->groupBy(function($att) {
                return Carbon::parse($att->clock_in_at)->format('M Y');
            })
            ->map(function($monthGroup) {
                return round($monthGroup->sum('overtime_minutes') / 60, 1);
            })
            ->reverse() // Chronological order
            ->take(6);

        return view('portal.appraisals.report', compact(
            'user', 'allTasks', 'totalTasks', 'completedCount', 'completedEarly', 'completedLate',
            'pendingCount', 'completionRate', 'allAttendances', 'totalDays', 'punctuality',
            'overtimeHours', 'latenesses', 'avgDelayMinutes', 'appraisals', 'monthlyOvertime'
        ));
    }
}
