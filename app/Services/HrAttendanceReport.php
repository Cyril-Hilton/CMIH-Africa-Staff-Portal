<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class HrAttendanceReport
{
    public function build(array $filters = [], string $pageName = 'page'): array
    {
        $v = \Illuminate\Support\Facades\Validator::make($filters, ['period' => 'nullable|in:weekly,monthly,yearly', 'date' => 'nullable|date', 'q' => 'nullable|string|max:100', 'department' => 'nullable|string|max:100', 'punctuality' => 'nullable|in:all,late,on_time,missing,no_clockins', 'sort' => 'nullable|in:name,newest,oldest', 'direction' => 'nullable|in:asc,desc'])->validate();
        $period = $v['period'] ?? 'weekly';
        $anchor = Carbon::parse($v['date'] ?? today());
        $from = match ($period) {
            'monthly' => $anchor->copy()->startOfMonth(),'yearly' => $anchor->copy()->startOfYear(),default => $anchor->copy()->startOfWeek()
        };
        $to = match ($period) {
            'monthly' => $from->copy()->endOfMonth(),'yearly' => $from->copy()->endOfYear(),default => $from->copy()->endOfWeek()
        };
        $eligible = User::internalStaff()->where(fn ($q) => $q->where('status', 'active')->orWhereHas('attendances', fn ($q) => $q->whereBetween('clock_in_at', [$from, $to])))->get()->reject(fn ($u) => $u->isCvoOrSuperAdmin() || strtolower((string) $u->access_role) === 'cvo');
        $departments = $eligible->pluck('department')->filter()->unique()->sort()->values();
        $staff = $eligible->filter(fn ($u) => (empty($v['q']) || str_contains(mb_strtolower($u->name), mb_strtolower($v['q']))) && (empty($v['department']) || in_array($u->department, User::departmentAliases($v['department']))));
        $base = Attendance::whereIn('user_id', $staff->pluck('id'))->whereBetween('clock_in_at', [$from, $to]);
        $all = (clone $base)->orderBy('clock_in_at')->get()->groupBy('user_id');
        $tracking = Attendance::whereIn('user_id', $eligible->pluck('id'))->min('clock_in_at');
        $trackingFrom = $tracking ? Carbon::parse($tracking)->startOfDay() : today();
        $leaves = LeaveApplication::whereIn('user_id', $staff->pluck('id'))->where('status', 'approved')->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get()->groupBy('user_id');
        $todayRows = Attendance::whereIn('user_id', $staff->pluck('id'))->whereBetween('clock_in_at', [today(), today()->endOfDay()])->orderBy('clock_in_at')->get()->unique('user_id')->keyBy('user_id');
        $todayWorkday = ! today()->isWeekend() && ! in_array(today()->toDateString(), config('company_calendar.non_working_dates', []), true);
        $summary = $staff->map(function ($user) use ($all, $from, $to, $trackingFrom, $leaves, $todayWorkday, $todayRows) {
            $rows = $all->get($user->id, collect());
            $days = $rows->unique(fn ($r) => $r->clock_in_at->toDateString());
            $onTime = $days->filter(fn ($r) => $r->clock_in_at->lte($r->clock_in_at->copy()->setTime(9, 0)))->count();
            $expected = [];
            $leave = $leaves->get($user->id, collect());
            $end = $to->copy()->min(today());
            $start = $from->copy()->max($trackingFrom);
            if ($user->start_date) {
                $start = $start->max($user->start_date)->copy();
            }
            // Inactive historical staff remain visible through their logs; no employment end date is guessed.
            if ($user->isActive()) {
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    if ($d->isWeekend() || in_array($d->toDateString(), config('company_calendar.non_working_dates', []), true) || ($d->isToday() && now()->lt(today()->setTime(9, 0))) || $leave->contains(fn ($l) => $d->betweenIncluded($l->start_date, $l->end_date))) {
                        continue;
                    }
                    $expected[] = $d->toDateString();
                }
            }
            $missing = array_values(array_diff($expected, $days->map(fn ($r) => $r->clock_in_at->toDateString())->all()));
            $todayRow = $todayRows->get($user->id);
            $todayStatus = ! $user->isActive() ? 'Inactive / historical' : ($todayRow ? ($todayRow->clock_in_at->gt(today()->setTime(9, 0)) ? 'Clocked In · Late' : 'Clocked In · On Time') : (! $todayWorkday ? 'Non-working Day' : ($leave->contains(fn ($l) => today()->betweenIncluded($l->start_date, $l->end_date)) ? 'Approved Leave' : ($user->start_date?->isFuture() ? 'Not Started' : (now()->lt(today()->setTime(9, 0)) ? 'Not Due Yet' : 'Not Clocked In')))));

            return ['id' => $user->id, 'name' => $user->name, 'department' => $user->department, 'sessions' => $rows->count(), 'days' => $days->count(), 'punctuality' => $days->count() ? round(100 * $onTime / $days->count(), 1) : null, 'late' => $days->count() - $onTime, 'overtime' => $rows->sum('overtime_minutes'), 'expected' => count($expected), 'missing' => $missing, 'today_status' => $todayStatus, 'today_in' => $todayRow?->clock_in_at, 'latest' => $rows->last()?->clock_in_at, 'first' => $rows->first()?->clock_in_at];
        });
        $filter = $v['punctuality'] ?? 'all';
        $summary = $summary->filter(fn ($s) => match ($filter) {
            'late' => $s['late'] > 0,'on_time' => $s['days'] > $s['late'],'missing' => count($s['missing']) > 0,'no_clockins' => $s['days'] === 0,default => true
        });
        $base->whereIn('user_id', $summary->pluck('id'));
        if ($filter === 'late') {
            $base->whereTime('clock_in_at', '>', '09:00:00');
        }if ($filter === 'on_time') {
            $base->whereTime('clock_in_at', '<=', '09:00:00');
        }
        $sort = $v['sort'] ?? 'name';
        $direction = $v['direction'] ?? 'asc';
        $summary = $sort === 'name' ? $summary->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc') : $summary->sortBy($sort === 'newest' ? 'latest' : 'first', SORT_REGULAR, $sort === 'newest');
        if ($sort === 'name') {
            $base->orderBy(User::select('name')->whereColumn('users.id', 'attendance.user_id'), $direction)->orderByDesc('clock_in_at');
        } else {
            $base->orderBy('clock_in_at', $sort === 'newest' ? 'desc' : 'asc');
        }
        $logs = $base->with('user:id,name,department')->paginate(30, ['*'], $pageName)->withQueryString();
        $coverage = ['staff' => $summary->count(), 'no_clockins' => $summary->where('days', 0)->count(), 'missing_days' => $summary->sum(fn ($s) => count($s['missing'])), 'not_in_today' => $summary->where('today_status','Not Clocked In')->count()];

        return compact('logs','summary','departments','period','from','to','coverage','trackingFrom');
    }
}
