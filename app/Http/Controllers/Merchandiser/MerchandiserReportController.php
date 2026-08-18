<?php

namespace App\Http\Controllers\Merchandiser;

use App\Http\Controllers\Controller;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserLocation;
use App\Models\MerchandiserPcmClockin;
use App\Models\MerchandiserPjpClockin;
use App\Models\MerchandiserReport;
use App\Models\PosmLedger;
use App\Models\User;
use App\Models\KeyDistributor;
use App\Services\PerfectStoreKpiService;
use Illuminate\Support\Carbon;

class MerchandiserReportController extends Controller
{
    /**
     * Public read-only view for shared report link.
     * No authentication required.
     */
    public function show(string $token)
    {
        $report = MerchandiserReport::where('token', $token)->firstOrFail();

        // Validate link
        if (!$report->isValid()) {
            return view('merchandisers.report-expired', compact('report'));
        }

        // Track views
        $report->increment('view_count');
        $report->update(['last_viewed_at' => now()]);

        //  Load data for enabled sections 
        $data = [];

        if ($report->section('show_overview')) {
            $data['total_active']     = User::merchandisers()->where('status', 'active')->count();
            $data['total_kds']        = KeyDistributor::count();
            $data['today_clockins']   = MerchandiserAttendance::whereDate('clock_in_time', Carbon::today())->count()
                + MerchandiserPcmClockin::whereDate('clocked_in_at', Carbon::today())->count()
                + MerchandiserPjpClockin::whereDate('clocked_in_at', Carbon::today())->count();
            $data['agent_clock_details'] = User::merchandisers()
                ->where('status', 'active')
                ->with(['merchandiserKd', 'supervisor'])
                ->orderBy('name')
                ->get()
                ->map(function (User $agent) {
                    $recentOutletClockIns = MerchandiserAttendance::with('outlet')
                        ->where('user_id', $agent->id)
                        ->where('clock_in_time', '>=', Carbon::today()->subDays(30))
                        ->latest('clock_in_time')
                        ->get();
                    $recentPcmClockIns = MerchandiserPcmClockin::with('keyDistributor')
                        ->where('user_id', $agent->id)
                        ->where('clocked_in_at', '>=', Carbon::today()->subDays(30))
                        ->latest('clocked_in_at')
                        ->get();
                    $clockRows = $recentOutletClockIns->map(fn ($clock) => [
                            'clocked_at' => $clock->clock_in_time,
                            'weekday' => $clock->clock_in_time?->format('D'),
                            'date' => $clock->clock_in_time?->format('d M'),
                            'time' => $clock->clock_in_time?->format('H:i'),
                            'type' => strtoupper((string) $clock->clock_in_type),
                            'outlet' => $clock->outlet?->name ?? '',
                            'status' => $clock->status,
                        ])
                        ->merge($recentPcmClockIns->map(fn ($clock) => [
                            'clocked_at' => $clock->clocked_in_at,
                            'weekday' => $clock->clocked_in_at?->format('D'),
                            'date' => $clock->clocked_in_at?->format('d M'),
                            'time' => $clock->clocked_in_at?->format('H:i'),
                            'type' => 'PCM/KD',
                            'outlet' => $clock->keyDistributor?->name ?? 'KD',
                            'status' => $clock->status,
                        ]))
                        ->sortByDesc('clocked_at')
                        ->values();

                    return [
                        'name' => $agent->name,
                        'kd' => $agent->merchandiserKd?->name ?? '',
                        'supervisor' => $agent->supervisor?->name ?? '',
                        'total_days_worked' => $clockRows
                            ->map(fn ($clock) => $clock['clocked_at']?->toDateString())
                            ->filter()
                            ->unique()
                            ->count(),
                        'clockins' => $clockRows->take(12)->values(),
                    ];
                });
            $data['supervisor_clock_details'] = User::merchandiserSupervisors()
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(function (User $supervisor) {
                    $assignedCount = User::merchandisers()->where('supervisor_id', $supervisor->id)->count();
                    $recentClockIns = MerchandiserPjpClockin::with('pjp')
                        ->where('user_id', $supervisor->id)
                        ->where('clocked_in_at', '>=', Carbon::today()->subDays(30))
                        ->latest('clocked_in_at')
                        ->take(12)
                        ->get();

                    return [
                        'name' => $supervisor->name,
                        'assigned_merchandisers' => $assignedCount,
                        'clockins' => $recentClockIns->map(fn ($clock) => [
                            'weekday' => $clock->clocked_in_at?->format('D'),
                            'date' => $clock->clocked_in_at?->format('d M'),
                            'time' => $clock->clocked_in_at?->format('H:i'),
                            'type' => 'PJP',
                            'outlet' => $clock->pjp?->title ?? 'PJP',
                            'status' => $clock->status,
                        ])->values(),
                    ];
                });
        }

        if ($report->section('show_attendance_chart')) {
            $chart = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $chart[$date->format('D d')] = MerchandiserAttendance::whereDate('clock_in_time', $date)->count()
                    + MerchandiserPcmClockin::whereDate('clocked_in_at', $date)->count()
                    + MerchandiserPjpClockin::whereDate('clocked_in_at', $date)->count();
            }
            $data['attendance_chart'] = $chart;
        }

        if ($report->section('show_overview') || $report->section('show_top_performers')) {
            $data['perfect_store_summary'] = app(PerfectStoreKpiService::class)->summary(
                Carbon::today()->subDays(6),
                Carbon::today()
            );
        }

        if ($report->section('show_tracking')) {
            $locations = [];
            User::merchandisers()->where('status', 'active')->each(function ($m) use (&$locations) {
                $loc = MerchandiserLocation::where('user_id', $m->id)->latest('recorded_at')->first();
                $clocked = MerchandiserAttendance::where('user_id', $m->id)->whereDate('clock_in_time', Carbon::today())->exists()
                    || MerchandiserPcmClockin::where('user_id', $m->id)->whereDate('clocked_in_at', Carbon::today())->exists()
                    || MerchandiserPjpClockin::where('user_id', $m->id)->whereDate('clocked_in_at', Carbon::today())->exists();
                if ($loc) {
                    $locations[] = [
                        'name'       => $m->name,
                        'clocked_in' => $clocked,
                        'latitude'   => (float) $loc->latitude,
                        'longitude'  => (float) $loc->longitude,
                        'last_seen'  => $loc->recorded_at->diffForHumans(),
                    ];
                }
            });
            $data['tracking_locations'] = $locations;
        }

        if ($report->section('show_top_performers')) {
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();

            $data['top_performers'] = User::merchandisers()
                ->where('status', 'active')
                ->withCount(['merchandiserVisits' => fn($q) => $q->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])])
                ->orderByDesc('merchandiser_visits_count')
                ->take(10)
                ->get();
        }

        if ($report->section('show_assets')) {
            $data['assets'] = PosmLedger::with('createdBy')
                ->orderByDesc('created_at')
                ->take(50)
                ->get();
        }

        if ($report->section('show_kds')) {
            $data['kds'] = KeyDistributor::with(['region', 'outlets'])->withCount('merchandisers')->get();
        }

        $reportPeriodStart = Carbon::today()->subDays(6)->startOfDay();
        $reportPeriodEnd = Carbon::today()->endOfDay();

        // ── ShelfWatch Section: Executive Summary ──────────────────────────────
        if ($report->section('show_exec_summary')) {
            $scheduled = \App\Models\MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $reportPeriodStart->toDateString())
                ->whereDate('assigned_date', '<=', $reportPeriodEnd->toDateString())
                ->count();
            $actual = \App\Models\MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $reportPeriodStart->toDateString())
                ->whereDate('assigned_date', '<=', $reportPeriodEnd->toDateString())
                ->where(fn ($query) => $query
                    ->where('status', 'completed')
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('visit_id'))
                ->count();
            $data['exec_scheduled'] = $scheduled;
            $data['exec_actual'] = $actual;
            $data['exec_compliance'] = $this->boundedPercent($actual, $scheduled);
            
            $visitTrend = ['labels' => [], 'scheduled' => [], 'actual' => []];
            for ($i = 6; $i >= 0; $i--) {
                $day = Carbon::today()->subDays($i);
                $visitTrend['labels'][] = $day->format('d M');
                $visitTrend['scheduled'][] = \App\Models\MerchandiserOutletAssignment::whereDate('assigned_date', $day->toDateString())->count();
                $visitTrend['actual'][] = \App\Models\MerchandiserOutletAssignment::whereDate('assigned_date', $day->toDateString())
                    ->where(fn ($query) => $query
                        ->where('status', 'completed')
                        ->orWhereNotNull('completed_at')
                        ->orWhereNotNull('visit_id'))
                    ->count();
            }
            $data['exec_visit_trend'] = $visitTrend;
        }

        // ── ShelfWatch Section: Category Level KPIs ─────────────────────────────
        if ($report->section('show_category_kpi')) {
            $data['category_kpis'] = app(PerfectStoreKpiService::class)->categoryKpis($reportPeriodStart, $reportPeriodEnd);
        }

        // ── ShelfWatch Section: User Performance ───────────────────────────────
        if ($report->section('show_user_performance')) {
            $data['user_performance'] = User::merchandisers()
                ->where('status', 'active')
                ->with(['merchandiserKd'])
                ->withCount(['merchandiserVisits as total_visits' => fn($q) => $q->whereBetween('created_at', [$reportPeriodStart, $reportPeriodEnd])])
                ->get()
                ->map(function ($user) use ($reportPeriodStart, $reportPeriodEnd) {
                    $scheduled = \App\Models\MerchandiserOutletAssignment::where('user_id', $user->id)
                        ->whereDate('assigned_date', '>=', $reportPeriodStart->toDateString())
                        ->whereDate('assigned_date', '<=', $reportPeriodEnd->toDateString())
                        ->count();
                    $completedAssignments = \App\Models\MerchandiserOutletAssignment::where('user_id', $user->id)
                        ->whereDate('assigned_date', '>=', $reportPeriodStart->toDateString())
                        ->whereDate('assigned_date', '<=', $reportPeriodEnd->toDateString())
                        ->where(fn ($query) => $query
                            ->where('status', 'completed')
                            ->orWhereNotNull('completed_at')
                            ->orWhereNotNull('visit_id'))
                        ->count();
                    $images = \Illuminate\Support\Facades\DB::table('merchandiser_visit_skus as vs')
                        ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                        ->where('v.user_id', $user->id)
                        ->whereNotNull('vs.photo_path')
                        ->whereBetween('v.created_at', [$reportPeriodStart, $reportPeriodEnd])
                        ->count();
                    $user->scheduled_visits = $scheduled;
                    $user->completed_assignments = $completedAssignments;
                    $user->coverage_pct = $this->boundedPercent($completedAssignments, $scheduled);
                    $user->images_uploaded = $images;
                    return $user;
                })
                ->sortByDesc('coverage_pct');
        }

        // ── ShelfWatch Section: Image Gallery ──────────────────────────────────
        if ($report->section('show_gallery')) {
            $data['gallery_photos'] = \Illuminate\Support\Facades\DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->join('outlets as o', 'o.id', '=', 'v.outlet_id')
                ->join('users as u', 'u.id', '=', 'v.user_id')
                ->join('skus as s', 's.id', '=', 'vs.sku_id')
                ->whereNotNull('vs.photo_path')
                ->select('vs.photo_path', 'vs.created_at', 'o.name as outlet_name', 'u.name as user_name', 's.name as sku_name')
                ->orderByDesc('vs.created_at')
                ->take(24)
                ->get();
        }

        // ── ShelfWatch Section: Price & Promo ─────────────────────────────────
        if ($report->section('show_price_promo')) {
            $totalVisits = \App\Models\MerchandiserVisit::whereBetween('created_at', [$reportPeriodStart, $reportPeriodEnd])->count();
            $withPosm = \Illuminate\Support\Facades\DB::table('merchandiser_visits as v')
                ->whereExists(fn($q) => $q->from('merchandiser_visit_skus as vs')->whereColumn('vs.visit_id', 'v.id')->whereNotNull('vs.photo_path'))
                ->whereBetween('v.created_at', [$reportPeriodStart, $reportPeriodEnd])
                ->count();
            $data['posm_compliance'] = $this->boundedPercent($withPosm, $totalVisits);
            
            $withPrice = \Illuminate\Support\Facades\DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->whereNotNull('vs.shelf_price')
                ->whereBetween('v.created_at', [$reportPeriodStart, $reportPeriodEnd])
                ->count();
            $totalSkuChecks = \Illuminate\Support\Facades\DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->whereBetween('v.created_at', [$reportPeriodStart, $reportPeriodEnd])
                ->count();
            $data['price_compliance'] = $this->boundedPercent($withPrice, $totalSkuChecks);
        }

        return view('merchandisers.report', compact('report', 'data'));
    }

    private function boundedPercent(int|float $part, int|float $total): float
    {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return min(100.0, max(0.0, round(((float) $part / (float) $total) * 100, 1)));
    }
}
