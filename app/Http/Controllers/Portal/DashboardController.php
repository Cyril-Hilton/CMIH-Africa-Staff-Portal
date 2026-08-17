<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Task;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Campaign;
use App\Models\DashboardColumn;
use App\Models\WeeklyConsolidatedColumn;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceScoringService;
use App\Services\TaskStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $megaSort = $this->sanitizeSort(
            $request->query('mega_sort'),
            ['activity', 'client', 'campaign', 'lead_staff', 'supporting', 'role', 'deliverables', 'deadline', 'status', 'priority'],
            'activity'
        );
        $megaSortDirection = $this->sanitizeDirection($request->query('mega_dir'), 'desc');
        $weeklySort = $this->sanitizeSort(
            $request->query('weekly_sort'),
            ['activity', 'week', 'client_campaign', 'lead_staff', 'supporting', 'deliverables', 'target', 'achieved', 'gap', 'priority', 'status', 'progress'],
            'activity'
        );
        $weeklySortDirection = $this->sanitizeDirection($request->query('weekly_dir'), 'desc');

        // Ensure auto-clock-in/out is complete for the current scoring month.
        \App\Services\AutoClockService::backfillCurrentMonthForUser($user);

        // Send daily birthday wishes to staff celebrating today
        \App\Services\NotificationService::checkAndSendBirthdayWishes();

        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', Carbon::today())
            ->first();

        $hasTodayTask = Task::where(function ($q) use ($user) {
            $q->where('assigned_to', $user->id)
              ->orWhere('assigned_by', $user->id);
        })->whereDate('created_at', Carbon::today())->exists();

        // 1. Individual KPIs
        $userTaskStats = TaskStatsService::forUser($user);
        $myTaskCompletionRate = round($userTaskStats['completion_rate']);
        $myOpenDeliverables = $userTaskStats['pending'];

        $attendanceSummary = PerformanceScoringService::attendanceSummary(
            $user,
            Carbon::today()->startOfMonth(),
            Carbon::today()
        );
        $myPunctualityScore = round($attendanceSummary['punctuality_score']);

        $totalOvertimeMinutes = Attendance::where('user_id', $user->id)->sum('overtime_minutes');
        $myOvertimeHours      = round($totalOvertimeMinutes / 60, 1);

        $individualStats = [
            'completion_rate'   => $myTaskCompletionRate,
            'open_deliverables' => $myOpenDeliverables,
            'punctuality_score' => $myPunctualityScore,
            'expected_attendance_days' => $attendanceSummary['expected_workdays'],
            'clocked_attendance_days' => $attendanceSummary['attendance_days'],
            'overtime_hours'    => $myOvertimeHours,
        ];

        // 2. Collective KPIs
        $globalTaskStats = TaskStatsService::global();
        $targetActivations   = $globalTaskStats['total'];
        $reachedActivations  = $globalTaskStats['completed'];
        $agencyWinRate       = $globalTaskStats['completion_rate'];
        $criticalBottlenecks = Task::query()
            ->realWork()
            ->whereRaw('LOWER(priority) = ?', ['high'])
            ->where(function (Builder $query) {
                $query->whereIn('status', ['Delayed', 'At Risk', 'Overdue', 'Rejected', 'delayed', 'at_risk', 'overdue', 'rejected'])
                    ->orWhere(function (Builder $dueQuery) {
                        $dueQuery->pendingFinalSignOff()
                            ->whereNotNull('due_on')
                            ->where('due_on', '<', now()->startOfDay());
                    });
            })->count();

        $collectiveStats = [
            'target_activations'  => $targetActivations,
            'reached_activations' => $reachedActivations,
            'win_rate'            => $agencyWinRate,
            'bottlenecks'         => $criticalBottlenecks,
        ];

        // 3. Departmental Golden Badge
        $departments = [
            'hr_admin'            => 'HR & Admin',
            'finance'             => 'Finance',
            'client_relations'    => 'Client Relations',
            'operations_projects' => 'Operations / Projects',
            'brands_marketing'    => 'Brands & Marketing',
            'creatives'           => 'Creatives',
        ];

        $currentMonth = Carbon::now()->format('Y-m');
        $currentYear  = Carbon::now()->format('Y');
        $awardStandings = app(PerformanceAwardController::class)->calculateStandingsData($currentMonth);
        $awardDepartmentScores = collect($awardStandings['departments'] ?? [])->keyBy('key');

        $deptPerformances = [];
        $winningDept      = null;
        $maxPerformance   = -1;

        $deptMapping = [
            'hr_admin'            => ['hr_admin', 'admin', 'transport'],
            'finance'             => ['finance'],
            'client_relations'    => ['client_relations', 'client_service'],
            'operations_projects' => ['operations_projects', 'operations'],
            'brands_marketing'    => ['brands_marketing', 'brands'],
            'creatives'           => ['creatives'],
        ];

        foreach ($departments as $key => $label) {
            $mappedDepts   = $deptMapping[$key] ?? [$key];
            $departmentTaskStats = TaskStatsService::forDepartments($mappedDepts);
            $awardDepartmentScore = $awardDepartmentScores->get($key, []);
            $performance = (float) ($awardDepartmentScore['score'] ?? 0);
            $memberCount = (int) ($awardDepartmentScore['member_count'] ?? 0);
            $activeMemberCount = (int) ($awardDepartmentScore['active_member_count'] ?? 0);

            $deptPerformances[$key] = [
                'label'     => $label,
                'total'     => $departmentTaskStats['total'],
                'completed' => $departmentTaskStats['completed'],
                'member_count' => $memberCount,
                'active_member_count' => $activeMemberCount,
                'performance' => $performance,
            ];

            if ($memberCount > 0 && $performance > $maxPerformance) {
                $maxPerformance = $performance;
                $winningDept    = $key;
            }
        }

        // 4. Charts
        $departmentChartData = [];
        foreach ($departments as $key => $label) {
            $mappedDepts   = $deptMapping[$key] ?? [$key];
            $departmentTaskStats = TaskStatsService::forDepartments($mappedDepts);
            $departmentChartData[] = [
                'department'  => $label,
                'completed'   => $departmentTaskStats['completed'],
                'in_progress' => $departmentTaskStats['pending'],
                'delayed'     => $departmentTaskStats['overdue'],
            ];
        }

        $weeklyTrends = [];
        for ($i = 3; $i >= 0; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end   = Carbon::now()->subWeeks($i)->endOfWeek();
            $weeklyTrends[] = [
                'week'      => 'Week ' . (4 - $i),
                'completed' => Task::whereBetween('updated_at', [$start, $end])
                    ->approvedForPerformance()
                    ->count(),
            ];
        }

        // 5. Recent Items
        $recentTasks           = Task::with(['assigner', 'assignee'])->where('assigned_to', $user->id)->realWork()->latest()->take(5)->get();
        $announcements         = Announcement::visibleTo($user)->with('user')->latest()->take(3)->get();
        $teamActiveCount       = User::internalStaff()->where('status', 'active')->count();
        $pendingApprovalsCount = User::internalStaff()->where('status', 'pending')->count();
        $allStaff              = User::internalStaff()->orderBy('name')->get();

        // 6. Mega Table + Dynamic Custom Columns
        $departmentTables  = [];
        $departmentColumns = [];
        $megaPerPage = 10;
        foreach ($departments as $key => $label) {
            $mappedDepts = $deptMapping[$key] ?? [$key];
            $mappedDeptAliases = $this->departmentQueryValues($mappedDepts);
            $deptUserIds = User::internalStaff()
                ->whereIn('department', $mappedDeptAliases)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $searchQuery = $request->query("search_mega_{$key}");

            if (!empty($searchQuery)) {
                $request->merge(["mega_{$key}_page" => 1]);
            }

            $departmentTables[$key] = $this->applyMegaTaskSort(
                $this->applyMegaTaskSearch(
                    $this->megaTaskQuery($mappedDeptAliases, $deptUserIds),
                    $searchQuery
                ),
                $megaSort,
                $megaSortDirection
            )
                ->paginate($megaPerPage, ['*'], "mega_{$key}_page")
                ->withQueryString();
            $departmentColumns[$key] = DashboardColumn::forDepartment($key);
        }

        $canViewAllWeeklyDepartments = $user->isCvoOrSuperAdmin();
        $defaultWeeklyDepartment = $canViewAllWeeklyDepartments
            ? 'all'
            : $this->normalizeDepartment((string) $user->department);
        if (! array_key_exists($defaultWeeklyDepartment, $departments)) {
            $defaultWeeklyDepartment = $canViewAllWeeklyDepartments ? 'all' : array_key_first($departments);
        }

        $weeklyDepartmentOptions = $canViewAllWeeklyDepartments
            ? array_merge(['all' => 'All Departments'], $departments)
            : $departments;
        $weeklyDepartmentFilter = (string) $request->query('weekly_department', $defaultWeeklyDepartment);
        if (! array_key_exists($weeklyDepartmentFilter, $weeklyDepartmentOptions)) {
            $weeklyDepartmentFilter = $defaultWeeklyDepartment;
        }

        $searchWeekly = $request->query('search_weekly');
        $isAllWeeklyDepartments = $weeklyDepartmentFilter === 'all';
        $weeklyDepartmentQueryValues = $isAllWeeklyDepartments ? [] : $this->departmentQueryValues([$weeklyDepartmentFilter]);
        $hasWeeklyItemsTable = \Illuminate\Support\Facades\Schema::hasTable('weekly_consolidated_items');
        $hasWeeklyColsTable = \Illuminate\Support\Facades\Schema::hasTable('weekly_consolidated_columns');
        $today = Carbon::today();

        if ($hasWeeklyItemsTable) {
            $weeklyBaseQuery = WeeklyConsolidatedItem::query();
            if (! $isAllWeeklyDepartments) {
                $weeklyBaseQuery->whereIn('department', $weeklyDepartmentQueryValues);
            }

            $weeklyFilteredBaseQuery = $this->applyWeeklyConsolidatedSearch($weeklyBaseQuery, $searchWeekly);

            if (! empty($searchWeekly)) {
                $request->merge(['weekly_page' => 1]);
            }

            $weeklyConsolidatedItems = $this->applyWeeklyConsolidatedSort(
                (clone $weeklyFilteredBaseQuery)->with(['leadStaff', 'creator', 'updater']),
                $weeklySort,
                $weeklySortDirection
            )
                ->paginate(8, ['*'], 'weekly_page')
                ->withQueryString();

            $weeklyDepartmentHasBreakdown = $isAllWeeklyDepartments
                || in_array($weeklyDepartmentFilter, ['operations_projects'], true)
                || (clone $weeklyFilteredBaseQuery)
                    ->where(function ($query) {
                        $query->whereNotNull('target_breakdown')->where('target_breakdown', '!=', '')
                            ->orWhere(function ($achievedQuery) {
                                $achievedQuery->whereNotNull('achieved_breakdown')->where('achieved_breakdown', '!=', '');
                            })
                            ->orWhere(function ($gapQuery) {
                                $gapQuery->whereNotNull('gap_breakdown')->where('gap_breakdown', '!=', '');
                            });
                    })
                    ->exists();

            $weeklyConsolidatedMetrics = [
                'completed' => (clone $weeklyFilteredBaseQuery)->where('status', 'Done')->count(),
                'pending' => (clone $weeklyFilteredBaseQuery)
                    ->whereIn('status', ['Planned', 'In Progress'])
                    ->where(function ($query) use ($today) {
                        $query->whereDate('week_end', '>=', $today)
                            ->orWhere(function ($fallbackQuery) use ($today) {
                                $fallbackQuery->whereNull('week_end')
                                    ->whereDate('week_start', '>=', $today->copy()->startOfWeek());
                            });
                    })
                    ->count(),
                'blocked' => (clone $weeklyFilteredBaseQuery)->whereIn('status', ['Blocked', 'Deferred'])->count(),
                'overdue' => (clone $weeklyFilteredBaseQuery)
                    ->whereNotIn('status', ['Done', 'Blocked', 'Deferred'])
                    ->where(function ($query) use ($today) {
                        $query->whereDate('week_end', '<', $today)
                            ->orWhere(function ($fallbackQuery) use ($today) {
                                $fallbackQuery->whereNull('week_end')
                                    ->whereDate('week_start', '<', $today->copy()->startOfWeek());
                            });
                    })
                    ->count(),
            ];
        } else {
            $weeklyConsolidatedItems = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 8);
            $weeklyDepartmentHasBreakdown = false;
            $weeklyConsolidatedMetrics = ['completed' => 0, 'pending' => 0, 'blocked' => 0, 'overdue' => 0];
        }

        $canManageWeeklyConsolidated = $this->canManageWeeklyConsolidated($user);

        if ($hasWeeklyColsTable && ! $isAllWeeklyDepartments && isset($weeklyFilteredBaseQuery)) {
            $weeklyColumnOwnerIds = (clone $weeklyFilteredBaseQuery)
                ->select('created_by')
                ->distinct()
                ->pluck('created_by')
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();
            $weeklyConsolidatedDisplayColumns = WeeklyConsolidatedColumn::with('user')
                ->whereIn('user_id', $weeklyColumnOwnerIds)
                ->where('department', $weeklyDepartmentFilter)
                ->orderBy('user_id')
                ->orderBy('order')
                ->orderBy('label')
                ->get();
            $myWeeklyConsolidatedColumns = WeeklyConsolidatedColumn::where('user_id', $user->id)
                ->where('department', $weeklyDepartmentFilter)
                ->orderBy('order')
                ->orderBy('label')
                ->get();
        } else {
            $weeklyConsolidatedDisplayColumns = collect();
            $myWeeklyConsolidatedColumns = collect();
        }

        $weeklyConsolidatedDepartments = collect($weeklyDepartmentOptions);
        $canManageActiveWeeklyDepartment = ! $isAllWeeklyDepartments
            && $this->canManageWeeklyConsolidatedDepartment($user, $weeklyDepartmentFilter);

        $lockedAwards = \Illuminate\Support\Facades\Schema::hasTable('performance_awards')
            ? \App\Models\PerformanceAward::with(['winner', 'firstRunnerUp', 'secondRunnerUp'])
                ->whereIn('period', [$currentMonth, $currentYear])
                ->get()
                ->keyBy('award_type')
            : collect();

        $lockedDepartmentAward = $lockedAwards->get('department_of_the_month');
        $topAwardDepartment = collect($awardStandings['departments'] ?? [])->first();
        $winningDept = $lockedDepartmentAward?->winner_val ?: ($topAwardDepartment['key'] ?? $winningDept);

        $actionPoints = \Illuminate\Support\Facades\Schema::hasTable('action_points')
            ? \App\Models\ActionPoint::with(['assignee', 'creator', 'updater'])
                ->latest()
                ->get()
            : collect();

        return view('portal.dashboard', compact(
            'user', 'individualStats', 'collectiveStats', 'winningDept',
            'deptPerformances', 'departmentChartData', 'weeklyTrends',
            'recentTasks', 'announcements', 'teamActiveCount',
            'pendingApprovalsCount', 'departmentTables', 'departmentColumns',
            'departments', 'todayAttendance', 'hasTodayTask', 'allStaff',
            'lockedAwards', 'currentMonth', 'currentYear', 'deptMapping',
            'weeklyConsolidatedItems', 'canManageWeeklyConsolidated',
            'weeklyConsolidatedDisplayColumns', 'myWeeklyConsolidatedColumns',
            'weeklyConsolidatedMetrics', 'weeklyConsolidatedDepartments',
            'weeklyDepartmentFilter', 'megaSort', 'megaSortDirection',
            'weeklySort', 'weeklySortDirection', 'weeklyDepartmentHasBreakdown',
            'canManageActiveWeeklyDepartment', 'isAllWeeklyDepartments',
            'actionPoints'
        ));
    }

    private function sanitizeSort(?string $sort, array $allowed, string $fallback): string
    {
        if ($sort && str_starts_with($sort, 'custom:')) {
            return $sort;
        }

        return in_array($sort, $allowed, true) ? $sort : $fallback;
    }

    private function sanitizeDirection(?string $direction, string $fallback = 'asc'): string
    {
        return in_array($direction, ['asc', 'desc'], true) ? $direction : $fallback;
    }

    private function megaTaskQuery(array $mappedDepts, array $deptUserIds): Builder
    {
        return Task::with(['assignee', 'assigner', 'campaign'])
            ->realWork()
            ->where(function ($query) use ($mappedDepts, $deptUserIds) {
                $query->whereIn('department', $mappedDepts);

                if (!empty($deptUserIds)) {
                    $query->orWhereIn('assigned_to', $deptUserIds)
                        ->orWhere(function ($supportingQuery) use ($deptUserIds) {
                            foreach ($deptUserIds as $staffId) {
                                $supportingQuery
                                    ->orWhereJsonContains('supporting_staff_ids', $staffId)
                                    ->orWhereJsonContains('supporting_staff_ids', (string) $staffId);
                            }
                        });
                }
            })
            ->where(function ($reviewQuery) {
                $reviewQuery->whereNull('completion_review_status')
                    ->orWhere('completion_review_status', 'approved');
            });
    }

    private function applyMegaTaskSearch(Builder $query, mixed $search): Builder
    {
        $term = trim((string) $search);
        if ($term === '') {
            return $query;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return $query->where(function (Builder $searchQuery) use ($like) {
            $searchQuery->where('title', 'like', $like)
                ->orWhere('details', 'like', $like)
                ->orWhere('client_name', 'like', $like)
                ->orWhere('supporting_roles', 'like', $like)
                ->orWhereHas('assignee', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like))
                ->orWhereHas('assigner', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like))
                ->orWhereHas('campaign', function (Builder $campaignQuery) use ($like) {
                    $campaignQuery->where('name', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                });
        });
    }

    private function applyMegaTaskSort(Builder $query, string $sort, string $direction): Builder
    {
        if (str_starts_with($sort, 'custom:')) {
            return $query->latest();
        }

        return match ($sort) {
            'activity' => $query
                ->orderByRaw("COALESCE(completion_reviewed_at, updated_at, created_at) {$direction}")
                ->latest('tasks.created_at'),
            'client' => $query
                ->orderByRaw('(select client_name from campaigns where campaigns.id = tasks.campaign_id) ' . $direction)
                ->orderBy('client_name', $direction)
                ->latest('tasks.created_at'),
            'campaign' => $query
                ->orderBy(Campaign::select('name')->whereColumn('campaigns.id', 'tasks.campaign_id'), $direction)
                ->latest('tasks.created_at'),
            'lead_staff' => $query
                ->orderBy(User::select('name')->whereColumn('users.id', 'tasks.assigned_to'), $direction)
                ->latest('tasks.created_at'),
            'supporting', 'role' => $query->orderBy('supporting_roles', $direction)->latest('tasks.created_at'),
            'deliverables' => $query->orderBy('title', $direction)->latest('tasks.created_at'),
            'deadline' => $query->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END asc')
                ->orderBy('due_on', $direction)
                ->latest('tasks.created_at'),
            'status' => $query->orderBy('status', $direction)->latest('tasks.created_at'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 9 END {$direction}")
                ->latest('tasks.created_at'),
            default => $query->latest('tasks.created_at'),
        };
    }

    private function applyWeeklyConsolidatedSearch(Builder $query, mixed $search): Builder
    {
        $term = trim((string) $search);
        if ($term === '') {
            return $query;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return $query->where(function (Builder $searchQuery) use ($like) {
            $searchQuery->where('client_name', 'like', $like)
                ->orWhere('campaign_name', 'like', $like)
                ->orWhere('deliverables', 'like', $like)
                ->orWhere('target_breakdown', 'like', $like)
                ->orWhere('achieved_breakdown', 'like', $like)
                ->orWhere('gap_breakdown', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('custom_fields', 'like', $like)
                ->orWhereHas('leadStaff', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like))
                ->orWhereHas('creator', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like));
        });
    }

    private function applyWeeklyConsolidatedSort(Builder $query, string $sort, string $direction): Builder
    {
        if (str_starts_with($sort, 'custom:')) {
            return $query->latest('week_start')->latest();
        }

        return match ($sort) {
            'activity' => $query->orderByRaw("COALESCE(updated_at, created_at, week_start) {$direction}")->latest(),
            'week' => $query->orderBy('week_start', $direction)->latest(),
            'client_campaign' => $query->orderBy('client_name', $direction)
                ->orderBy('campaign_name', $direction)
                ->latest(),
            'lead_staff' => $query->orderBy(User::select('name')->whereColumn('users.id', 'weekly_consolidated_items.lead_staff_id'), $direction)
                ->latest('weekly_consolidated_items.created_at'),
            'supporting' => $query->orderBy('supporting_roles', $direction)->latest(),
            'deliverables' => $query->orderBy('deliverables', $direction)->latest(),
            'target' => $query->orderBy('target_breakdown', $direction)->latest(),
            'achieved' => $query->orderBy('achieved_breakdown', $direction)->latest(),
            'gap' => $query->orderBy('gap_breakdown', $direction)->latest(),
            'priority' => $query->orderBy('priority', $direction)->latest(),
            'status' => $query->orderBy('status', $direction)->latest(),
            'progress' => $query->orderBy('progress_percent', $direction)->latest(),
            default => $query->latest('week_start')->latest(),
        };
    }

    private function sortString(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function isWeeklyConsolidatedOverdue(WeeklyConsolidatedItem $item): bool
    {
        if (in_array($item->status, ['Done', 'Blocked', 'Deferred'], true)) {
            return false;
        }

        $dueDate = $item->week_end ?? $item->week_start?->copy()->endOfWeek();

        return $dueDate !== null && $dueDate->copy()->endOfDay()->lt(now());
    }

    // ── Custom Dashboard Column Management ────────────────────────────────────

    public function live(Request $request): View
    {
        return $this->index($request);
    }

    public function storeColumn(Request $request): RedirectResponse
    {
        $this->authorizeColumnManagement($request->user(), $request->input('department'));
        $request->validate([
            'department' => ['required', 'string'],
            'label'      => ['required', 'string', 'max:100'],
            'type'       => ['required', 'string', 'in:text,number,date,status,dropdown'],
        ]);
        $maxOrder = DashboardColumn::where('department', $request->department)->max('order') ?? 0;
        DashboardColumn::create([
            'department' => $request->department,
            'column_key' => \Illuminate\Support\Str::slug($request->label, '_'),
            'label'      => $request->label,
            'type'       => $request->type,
            'order'      => $maxOrder + 1,
        ]);
        return back()->with('status', 'Custom column added to the department table.');
    }

    public function updateColumn(DashboardColumn $column, Request $request): RedirectResponse
    {
        $this->authorizeColumnManagement($request->user(), $column->department);
        $request->validate(['label' => ['required', 'string', 'max:100']]);
        $column->update(['label' => $request->label]);
        return back()->with('status', 'Column label updated.');
    }

    public function destroyColumn(DashboardColumn $column, Request $request): RedirectResponse
    {
        $this->authorizeColumnManagement($request->user(), $column->department);
        $column->delete();
        return back()->with('status', 'Custom column removed.');
    }

    public function storeWeeklyConsolidated(Request $request): RedirectResponse
    {
        $department = $this->normalizeWeeklyConsolidatedDepartmentInput($request);
        $this->authorizeWeeklyConsolidatedManagement($request->user(), $department);

        $validated = $this->validateWeeklyConsolidated($request);
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        [$supportingStaffIds, $supportingRoles] = $this->normalizedSupportingStaffWithRoles($request);
        $validated['supporting_staff_ids'] = $supportingStaffIds;
        $validated['supporting_roles'] = $supportingRoles;
        $validated['custom_fields'] = $this->validatedWeeklyCustomFields($request->user()->id, $request, $validated['department']);
        $validated['week_end'] = $validated['week_end'] ?? Carbon::parse($validated['week_start'])->endOfWeek()->toDateString();
        $validated['progress_percent'] = $this->normalizeWeeklyProgress($validated['progress_percent'] ?? null, $validated['status']);

        WeeklyConsolidatedItem::create($validated);

        return back()->with('status', 'Weekly consolidated item added.');
    }

    public function updateWeeklyConsolidated(Request $request, WeeklyConsolidatedItem $item): RedirectResponse
    {
        $department = $this->normalizeWeeklyConsolidatedDepartmentInput($request, $item->department);
        $this->authorizeWeeklyConsolidatedManagement($request->user(), $department, $item);

        $validated = $this->validateWeeklyConsolidated($request);
        $validated['updated_by'] = $request->user()->id;
        [$supportingStaffIds, $supportingRoles] = $this->normalizedSupportingStaffWithRoles($request);
        $validated['supporting_staff_ids'] = $supportingStaffIds;
        $validated['supporting_roles'] = $supportingRoles;
        $validated['custom_fields'] = $this->validatedWeeklyCustomFields((int) $item->created_by, $request, $validated['department']);
        $validated['week_end'] = $validated['week_end'] ?? Carbon::parse($validated['week_start'])->endOfWeek()->toDateString();
        $validated['progress_percent'] = $this->normalizeWeeklyProgress($validated['progress_percent'] ?? null, $validated['status']);

        $item->update($validated);

        return back()->with('status', 'Weekly consolidated item updated.');
    }

    public function destroyWeeklyConsolidated(Request $request, WeeklyConsolidatedItem $item): RedirectResponse
    {
        $this->authorizeWeeklyConsolidatedManagement($request->user(), $item->department, $item);
        $item->delete();

        return back()->with('status', 'Weekly consolidated item removed.');
    }

    public function storeWeeklyConsolidatedColumn(Request $request): RedirectResponse
    {
        $this->normalizeWeeklyConsolidatedDepartmentInput($request);

        $validated = $request->validate([
            'department' => ['required', 'string', 'in:hr_admin,finance,client_relations,operations_projects,brands_marketing,creatives'],
            'label' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:rich_text,text,number,date,status'],
        ]);
        $department = $this->normalizeDepartment($validated['department']);

        $this->authorizeWeeklyConsolidatedColumnManagement($request->user(), null, $department);

        $baseKey = WeeklyConsolidatedColumn::makeKey($validated['label']);
        $key = $baseKey;
        $suffix = 2;

        while (WeeklyConsolidatedColumn::where('user_id', $request->user()->id)->where('department', $department)->where('column_key', $key)->exists()) {
            $key = $baseKey . '_' . $suffix++;
        }

        $maxOrder = WeeklyConsolidatedColumn::where('user_id', $request->user()->id)
            ->where('department', $department)
            ->max('order') ?? 0;

        WeeklyConsolidatedColumn::create([
            'user_id' => $request->user()->id,
            'department' => $department,
            'column_key' => $key,
            'label' => $validated['label'],
            'type' => $validated['type'] ?? 'rich_text',
            'order' => $maxOrder + 1,
        ]);

        return back()->with('status', 'Weekly consolidated column added for your entries.');
    }

    public function updateWeeklyConsolidatedColumn(Request $request, WeeklyConsolidatedColumn $column): RedirectResponse
    {
        $this->authorizeWeeklyConsolidatedColumnManagement($request->user(), $column, $column->department);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:rich_text,text,number,date,status'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $column->update([
            'label' => $validated['label'],
            'type' => $validated['type'] ?? $column->type,
            'order' => $validated['order'] ?? $column->order,
        ]);

        return back()->with('status', 'Weekly consolidated column updated.');
    }

    public function destroyWeeklyConsolidatedColumn(Request $request, WeeklyConsolidatedColumn $column): RedirectResponse
    {
        $this->authorizeWeeklyConsolidatedColumnManagement($request->user(), $column, $column->department);
        $column->delete();

        return back()->with('status', 'Weekly consolidated column removed from your entries.');
    }

    private function authorizeColumnManagement(User $user, string $department): void
    {
        $isSuperOrAdmin = $user->isCvoOrSuperAdmin() || $user->access_role === 'admin';
        
        if ($isSuperOrAdmin) {
            return;
        }

        if ($user->access_role === 'manager' || $user->access_role === 'staff') {
            $userDept = strtolower(trim($user->department ?? ''));
            $deptNormMap = [
                'admin'              => 'hr_admin',
                'transport'          => 'hr_admin',
                'client_service'     => 'client_relations',
                'operations'         => 'operations_projects',
                'brands'             => 'brands_marketing',
                'hr_admin'           => 'hr_admin',
                'finance'            => 'finance',
                'client_relations'   => 'client_relations',
                'operations_projects'=> 'operations_projects',
                'brands_marketing'   => 'brands_marketing',
                'creatives'          => 'creatives',
            ];
            $userNormDept = $deptNormMap[$userDept] ?? $userDept;
            $reqNormDept  = $deptNormMap[strtolower($department)] ?? strtolower($department);

            if ($userNormDept === $reqNormDept) {
                return;
            }
        }

        abort(403, '🔒 Access Denied. You cannot customise dashboard columns for other departments.');
    }

    private function validateWeeklyConsolidated(Request $request): array
    {
        $this->normalizeWeeklyConsolidatedDepartmentInput($request);

        if ($request->has('supporting_staff_ids')) {
            $ids = $request->input('supporting_staff_ids');
            $roles = $request->input('supporting_roles');
            
            $filteredIds = [];
            $filteredRoles = [];
            
            if (is_array($ids)) {
                foreach ($ids as $idx => $id) {
                    if ($id !== null && $id !== '') {
                        $filteredIds[] = $id;
                        $filteredRoles[] = $roles[$idx] ?? '';
                    }
                }
            }
            
            $request->merge([
                'supporting_staff_ids' => empty($filteredIds) ? null : $filteredIds,
                'supporting_roles' => empty($filteredRoles) ? null : $filteredRoles,
            ]);
        }

        $isBrandsWeeklyDepartment = $request->input('department') === 'brands_marketing';

        $rules = [
            'department' => ['required', 'string', 'in:hr_admin,finance,client_relations,operations_projects,brands_marketing,creatives'],
            'brands_task_id' => $isBrandsWeeklyDepartment
                ? ['required', 'string', 'max:80']
                : ['nullable', 'string', 'max:80'],
            'week_start' => ['required', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'lead_staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'supporting_staff_ids' => ['nullable', 'array'],
            'supporting_staff_ids.*' => ['integer', 'exists:users,id'],
            'supporting_roles' => ['nullable', 'array'],
            'supporting_roles.*' => ['nullable', 'string', 'max:255'],
            'deliverables' => ['required', 'string', 'max:15000'],
            'target_breakdown' => ['nullable', 'string', 'max:15000'],
            'achieved_breakdown' => ['nullable', 'string', 'max:15000'],
            'gap_breakdown' => ['nullable', 'string', 'max:15000'],
            'status' => ['required', 'string', 'in:Planned,In Progress,Done,Blocked,Deferred'],
            'priority' => ['nullable', 'string', 'max:50'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => $isBrandsWeeklyDepartment
                ? ['required', 'string', Rule::in(WeeklyConsolidatedItem::BRANDS_UPDATE_STATUSES)]
                : ['nullable', 'string', 'max:5000'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:15000'],
        ];

        $validated = $request->validate($rules);
        unset($validated['brands_task_id']);

        return $validated;
    }

    private function normalizeWeeklyConsolidatedDepartmentInput(Request $request, ?string $fallback = null): string
    {
        $department = $this->normalizeDepartment((string) $request->input('department', $fallback ?? ''));
        $request->merge(['department' => $department]);

        return $department;
    }

    private function authorizeWeeklyConsolidatedManagement(User $user, string $department, ?WeeklyConsolidatedItem $item = null): void
    {
        if ($user->isCvoOrSuperAdmin()) {
            return;
        }

        if ($item && $this->isWeeklyConsolidatedCollaborator($item, $user)) {
            return;
        }

        if ($this->canManageWeeklyConsolidated($user)
            && $this->normalizeDepartment((string) $user->department) === $this->normalizeDepartment($department)) {
            return;
        }

        abort(403, 'You can only manage weekly consolidated rows for your department or rows where you are a collaborator.');
    }

    private function authorizeWeeklyConsolidatedColumnManagement(User $user, ?WeeklyConsolidatedColumn $column = null, ?string $department = null): void
    {
        if (! $this->canManageWeeklyConsolidated($user)) {
            abort(403, 'Only line managers, CVO, and Super Admin can manage weekly consolidated columns.');
        }

        if ($column && (int) $column->user_id !== (int) $user->id) {
            abort(403, 'You can only manage your own weekly consolidated columns.');
        }

        if ($department && ! $this->canManageWeeklyConsolidatedDepartment($user, $department)) {
            abort(403, 'You can only manage weekly columns for your own department.');
        }
    }

    private function canManageWeeklyConsolidated(User $user): bool
    {
        return $user->isEffectiveLineManager() || $user->isCvoOrSuperAdmin();
    }

    private function canManageWeeklyConsolidatedDepartment(User $user, string $department): bool
    {
        if (! $this->canManageWeeklyConsolidated($user)) {
            return false;
        }

        if ($user->isCvoOrSuperAdmin()) {
            return true;
        }

        $userDept = $this->normalizeDepartment((string) $user->department);
        $targetDept = $this->normalizeDepartment($department);

        if ($userDept === $targetDept) {
            return true;
        }

        // Check if user is acting as relief line manager for any manager in target department
        $delegatedManagerIds = $user->activeDelegatedManagerIds();
        if (! empty($delegatedManagerIds)) {
            $delegatedManagers = User::whereIn('id', $delegatedManagerIds)->get();
            foreach ($delegatedManagers as $delManager) {
                if ($this->normalizeDepartment((string) $delManager->department) === $targetDept) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isWeeklyConsolidatedCollaborator(WeeklyConsolidatedItem $item, User $user): bool
    {
        $supportingIds = collect($item->supporting_staff_ids ?? [])->map(fn ($id) => (int) $id);

        if ((int) $item->created_by === (int) $user->id
            || (int) $item->lead_staff_id === (int) $user->id
            || $supportingIds->contains((int) $user->id)) {
            return true;
        }

        // Also if user is acting line manager for item's creator or lead staff
        if ($user->isActingLineManagerFor((int) $item->created_by) || $user->isActingLineManagerFor((int) $item->lead_staff_id)) {
            return true;
        }

        return false;
    }

    private function normalizeDepartment(string $department): string
    {
        return User::normalizeDepartmentKey($department);
    }

    private function departmentQueryValues(array $departments): array
    {
        return collect($departments)
            ->flatMap(fn ($department) => User::departmentAliases($department))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeWeeklyProgress(mixed $progress, string $status): int
    {
        if ($progress !== null && $progress !== '') {
            return max(0, min(100, (int) $progress));
        }

        return $this->weeklyProgressFromStatus($status);
    }

    private function weeklyProgressFromStatus(?string $status): int
    {
        return match ($status) {
            'Done' => 100,
            'In Progress' => 50,
            'Blocked', 'Deferred' => 0,
            default => 0,
        };
    }

    private function normalizedIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            $ids = $ids ? [$ids] : [];
        }

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedSupportingStaffWithRoles(Request $request): array
    {
        $ids = is_array($request->input('supporting_staff_ids')) ? $request->input('supporting_staff_ids') : [];
        $roles = is_array($request->input('supporting_roles')) ? $request->input('supporting_roles') : [];
        $normalizedIds = [];
        $normalizedRoles = [];

        foreach ($ids as $index => $id) {
            if (! is_numeric($id) || (int) $id <= 0) {
                continue;
            }

            $staffId = (int) $id;

            if (in_array($staffId, $normalizedIds, true)) {
                continue;
            }

            $normalizedIds[] = $staffId;
            $role = trim((string) ($roles[$index] ?? ''));

            if ($role !== '') {
                $normalizedRoles[$staffId] = $role;
            }
        }

        return [$normalizedIds, $normalizedRoles];
    }

    private function validatedWeeklyCustomFields(int $ownerId, Request $request, ?string $department = null): array
    {
        $fields = is_array($request->input('custom_fields')) ? $request->input('custom_fields') : [];
        $columns = WeeklyConsolidatedColumn::where('user_id', $ownerId)
            ->when($department, fn ($query) => $query->where('department', $this->normalizeDepartment($department)))
            ->pluck('column_key')
            ->all();

        $customFields = collect($fields)
            ->only($columns)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->all();

        if ($this->normalizeDepartment((string) $department) === 'brands_marketing') {
            $taskId = trim((string) $request->input('brands_task_id'));

            if ($taskId !== '') {
                $customFields[WeeklyConsolidatedItem::BRANDS_TASK_ID_FIELD] = $taskId;
            }
        }

        return $customFields;
    }
}
