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
            $data['top_performers'] = User::merchandisers()
                ->where('status', 'active')
                ->withCount(['merchandiserVisits' => fn($q) => $q->whereMonth('created_at', now()->month)])
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

        return view('merchandisers.report', compact('report', 'data'));
    }
}
