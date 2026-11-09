<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\FleetRequest;
use App\Models\HrDashboardActivity;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HrDashboard
{
    public static function canAccess(User $user): bool
    {
        return $user->isActive() && !$user->isServiceAccount() && (
            $user->hasFullHrAccess() || User::normalizeDepartmentKey($user->department) === 'hr_admin'
        );
    }

    public static function showWorkspace(Request $request): bool
    {
        return self::canAccess($request->user())
            && !$request->boolean('hr_existing')
            && !$request->has('attendance_period')
            && (!$request->attributes->get('explicit_weekly_period') || $request->has('hr_tab'));
    }

    public static function tabs(User $user): array
    {
        $tabs = config('hr_dashboard');
        return $user->hasFullHrAccess() ? $tabs : array_intersect_key($tabs, ['overview' => true, 'operations' => true]);
    }

    public static function team()
    {
        return User::internalStaff()->whereIn('department', User::departmentAliases('hr_admin'))->orderBy('name');
    }

    public const SORT_COLUMNS = [
        'activity' => 'Activity', 'staff' => 'Staff / Department', 'opened_on' => 'Date Opened',
        'deadline' => 'Deadline', 'assignee' => 'Assigned To', 'status' => 'Status', 'remarks' => 'Remarks',
    ];

    public function build(Request $request): array
    {
        $user = $request->user();
        abort_unless(self::canAccess($user), 403);
        $tabs = self::tabs($user);
        $tab = $request->query('hr_tab');
        $tab = is_string($tab) && isset($tabs[$tab]) ? $tab : 'overview';
        $v = Validator::make($request->query(), [
            'hr_period' => 'nullable|in:fortnight,week,month,year,range',
            'hr_from' => 'nullable|date_format:Y-m-d',
            'hr_to' => 'nullable|date_format:Y-m-d',
            'hr_owner' => 'nullable|integer|min:1',
            'hr_q' => 'nullable|string|max:150',
            'hr_category' => ['nullable', Rule::in($tabs[$tab][1])],
            'hr_status' => ['nullable', Rule::in(HrDashboardActivity::STATUSES)],
            'hr_sort' => ['nullable', Rule::in(array_keys(self::SORT_COLUMNS))],
            'hr_direction' => 'nullable|in:asc,desc',
        ]);
        $filters = $v->fails() ? [] : $v->validated();
        $period = $filters['hr_period'] ?? 'fortnight';
        $anchor = Carbon::parse($filters['hr_from'] ?? today());
        [$start, $end] = match ($period) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            'range' => [$anchor, Carbon::parse($filters['hr_to'] ?? $anchor)->max($anchor)],
            default => [$anchor->copy()->subWeek()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };
        $from = $start->toDateString();
        $to = $end->toDateString();
        $team = self::team()->get(['id', 'name', 'status', 'profile_photo_path']);
        $owner = (int) ($filters['hr_owner'] ?? 0);
        if (!$team->contains('id', $owner)) {
            $owner = 0;
        }

        $search = trim($filters['hr_q'] ?? '');
        $category = $filters['hr_category'] ?? '';
        $status = $filters['hr_status'] ?? '';
        $sort = $filters['hr_sort'] ?? 'opened_on';
        $direction = $filters['hr_direction'] ?? 'desc';
        $sortColumn = match ($sort) {
            'staff' => User::select('name')->whereColumn('users.id', 'hr_dashboard_activities.staff_id'),
            'assignee' => User::select('name')->whereColumn('users.id', 'hr_dashboard_activities.assigned_to'),
            default => $sort,
        };
        $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
        $activities = HrDashboardActivity::with(['staff:id,name', 'assignee:id,name'])
            ->where('tab', $tab)
            ->whereDate('opened_on', '<=', $to)
            ->where(fn ($q) => $q->whereDate('deadline', '>=', $from)
                ->orWhereDate('opened_on', '>=', $from)
                ->orWhereNotIn('status', ['Completed', 'Cancelled']))
            ->when($owner, fn ($q) => $q->where(fn ($scope) => $scope->where('assigned_to', $owner)->orWhere('staff_id', $owner)))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($needle) {
                $query->whereRaw("LOWER(activity) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(remarks) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereHas('staff', fn ($staff) => $staff->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$needle]))
                    ->orWhereHas('assignee', fn ($staff) => $staff->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$needle]));
            }))
            ->orderBy($sortColumn, $direction)->orderByDesc('id')
            ->paginate(25, ['*'], 'hr_page')->withPath(route('dashboard'))->withQueryString()->fragment('hr-activity-table');

        $stats = [];
        if ($tab === 'overview') {
            $fleet = FleetRequest::query()->whereBetween('requested_date', [$from, $to])->when($owner, fn ($q) => $q->where('user_id', $owner));
            $stats['Fleet requests in period'] = $fleet->count();
            if ($user->hasFullHrAccess()) {
                $staff = User::internalStaff()->where('status', 'active')->when($owner, fn ($q) => $q->whereKey($owner));
                $stats['Current active headcount'] = (clone $staff)->count();
                $attendanceStaff = User::internalStaff()->get()->reject(fn ($person) => $person->isCvoOrSuperAdmin())->pluck('id');
                $attendance = Attendance::query()->whereIn('user_id', $attendanceStaff)
                    ->whereBetween('clock_in_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                    ->when($owner, fn ($q) => $q->where('user_id', $owner));
                $stats['Clock-in records in period'] = (clone $attendance)->count();
                $days = $attendance->selectRaw('MIN(clock_in_at) AS first_clock_in')
                    ->groupByRaw('user_id, DATE(clock_in_at)')->toBase()->pluck('first_clock_in');
                $stats['On-time staff days'] = $days->filter(fn ($clockIn) => Carbon::parse($clockIn)->format('H:i:s') <= '09:00:00')->count();
                $stats['Late staff days'] = $days->count() - $stats['On-time staff days'];
                $stats['Approved leave overlapping period'] = LeaveApplication::where('status', 'approved')
                    ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
                    ->when($owner, fn ($q) => $q->where('user_id', $owner))->count();
            }
        }

        $people = User::internalStaff()->where('status', 'active')->orderBy('name')->pluck('name', 'id');
        $anchorDate = $anchor->toDateString();
        return compact('tabs', 'tab', 'period', 'from', 'to', 'anchorDate', 'team', 'owner', 'activities', 'stats', 'people', 'search', 'category', 'status', 'sort', 'direction');
    }
}
