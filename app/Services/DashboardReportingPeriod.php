<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardReportingPeriod
{
    public function apply(Request $request): void
    {
        $request->validate([
            'report_period' => 'nullable|in:week,month,year',
            'report_date' => 'nullable|date_format:Y-m-d',
            'brands_date' => 'nullable|date_format:Y-m-d',
            'hr_from' => 'nullable|date_format:Y-m-d',
            'hr_to' => 'nullable|date_format:Y-m-d',
            'revenue_date' => 'nullable|date_format:Y-m-d',
            'attendance_date' => 'nullable|date_format:Y-m-d',
            'weekly_from' => 'nullable|date_format:Y-m-d',
            'weekly_to' => 'nullable|date_format:Y-m-d|after_or_equal:weekly_from',
        ]);
        $explicit = !$request->has('report_period') && ($request->filled('weekly_from') || $request->filled('weekly_to'));
        $request->attributes->set('explicit_weekly_period', $explicit);
        $legacyHrRange = !$request->has('report_period') && in_array($request->query('hr_period'), ['range', 'fortnight'], true);
        $mode = $request->query('report_period');
        $anchor = $request->query('report_date');
        if (!$mode) {
            $mode = $request->query('brands_period') ?: $request->query('hr_period');
            $mode = ['weekly'=>'week', 'monthly'=>'month', 'yearly'=>'year'][$request->query('attendance_period') ?: $request->query('revenue_period') ?: $request->query('reporting_summary_period')] ?? $mode;
            if (!in_array($mode, ['week', 'month', 'year'], true)) $mode = 'week';
            $anchor = $anchor ?: $request->query('brands_date') ?: $request->query('hr_from') ?: $request->query('revenue_date') ?: $request->query('attendance_date');
        }
        $date = Carbon::parse($anchor ?: $request->query('weekly_from') ?: $request->query('weekly_to') ?: today());
        $from = match ($mode) {'month'=>$date->copy()->startOfMonth(), 'year'=>$date->copy()->startOfYear(), default=>$date->copy()->startOfWeek()};
        $to = match ($mode) {'month'=>$from->copy()->endOfMonth(), 'year'=>$from->copy()->endOfYear(), default=>$from->copy()->endOfWeek()};
        // Existing saved links with explicit ranges retain those dates.
        if (!$request->has('report_period') && !$request->has('report_date') && $explicit) {
            $from = Carbon::parse($request->query('weekly_from') ?: $from)->startOfDay();
            $to = Carbon::parse($request->query('weekly_to') ?: $from->copy()->endOfWeek())->endOfDay();
            $mode = $from->isSameDay($from->copy()->startOfYear()) && $to->isSameDay($to->copy()->endOfYear()) ? 'year'
                : ($from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($to->copy()->endOfMonth()) ? 'month' : 'week');
        }
        if ($legacyHrRange) {
            $from = Carbon::parse($request->query('hr_from') ?: today());
            $to = $request->query('hr_period') === 'fortnight' ? $from->copy()->endOfWeek() : Carbon::parse($request->query('hr_to') ?: $from);
            if ($request->query('hr_period') === 'fortnight') $from = $from->copy()->subWeek()->startOfWeek();
        }
        $long = ['week'=>'weekly', 'month'=>'monthly', 'year'=>'yearly'][$mode];
        $request->query->add([
            'report_period'=>$mode, 'report_date'=>$from->toDateString(),
            'weekly_from'=>$from->toDateString(), 'weekly_to'=>$to->toDateString(),
            'brands_period'=>$mode, 'brands_date'=>$from->toDateString(),
            'hr_period'=>$legacyHrRange?'range':$mode, 'hr_from'=>$from->toDateString(), 'hr_to'=>$to->toDateString(),
            'revenue_period'=>$long, 'revenue_date'=>$from->toDateString(),
            'reporting_summary_period'=>$long,
        ]);
        if ($request->has('attendance_period')) $request->query->add(['attendance_period'=>$long, 'attendance_date'=>$from->toDateString()]);
        $request->attributes->set('dashboard_reporting_period', true);
    }
}
