<?php

namespace App\Services;

use App\Models\AdminWeeklyReport;
use App\Models\HrPeriodicReport;

class SharedPeopleReporting
{
    /** Explicit public numeric projection: no employee names, cases, notes or costs. */
    public function build(string $from, string $to): array
    {
        $result = ['hr' => [], 'administrator' => []];
        $reports = HrPeriodicReport::with('entries')->whereNotNull('approved_at')->where('frequency', 'weekly')
            ->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->orderByDesc('period_start')->get();
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach ($report->entries->where('section', 'metrics') as $entry) {
                $d = $entry->data;
                if (! in_array($d['metric'] ?? '', ['Headcount', 'New starters', 'Leavers', 'Open vacancies', 'Training completion rate'], true)) {
                    continue;
                }
                $actual = $d['actual'] ?? null;
                if ($d['metric'] === 'Training completion rate') {
                    $actual = PerformanceMath::ratio($d['numerator'] ?? null, $d['denominator'] ?? null, true);
                }
                $result['hr'][] = ['period' => $period, 'metric' => ucwords($d['metric']), 'actual' => $actual, 'unit' => $d['metric'] === 'Training completion rate' ? '%' : ''];
            }
        }
        $reports = AdminWeeklyReport::with('lines')->whereNotNull('approved_at')
            ->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->orderByDesc('period_start')->get();
        $allowed = ['fleet' => ['total_vehicles', 'vehicles_available', 'fuel_litres', 'distance_km'], 'drivers' => ['total_drivers', 'attendance_rate', 'assignments_completed'], 'assets' => ['total_assets', 'assigned_assets', 'missing_assets', 'damaged_assets', 'transferred']];
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach ($report->lines as $line) {
                if (! in_array($line->code, $allowed[$line->section] ?? [], true)) {
                    continue;
                }
                $result['administrator'][] = ['period' => $period, 'metric' => config('admin_weekly.'.$line->section.'.rows.'.$line->code.'.label'), 'actual' => $line->numeric_value, 'unit' => ''];
            }
            $fleet = $report->lines->where('section', 'fleet')->keyBy('code');
            $litres = $fleet->get('fuel_litres')?->numeric_value;
            $distance = $fleet->get('distance_km')?->numeric_value;
            $result['administrator'][] = ['period' => $period, 'metric' => 'Fuel Efficiency', 'actual' => $litres !== null && $distance > 0 ? round(100 * $litres / $distance, 2) : null, 'unit' => 'L/100km'];
        }

        return $result;
    }
}
