<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Task;
use App\Models\User;
use App\Models\Attendance;
use App\Services\TaskStatsService;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(): View
    {
        $taskStats = TaskStatsService::global();

        $stats = [
            'total_staff' => User::count(),
            'pending_approvals' => User::internalStaff()->where('status', 'pending')->count(),
            'task_total' => $taskStats['total'],
            'active_updates' => Task::query()->realWork()->where('progress', '>', 0)->count(),
            'open_tasks' => $taskStats['pending'],
        ];

        $taskStatusLabels = [
            'completed' => 'Completed',
            'pending' => 'Pending final sign-off',
            'overdue' => 'Overdue',
        ];

        $taskStatusCounts = collect([
            'completed' => $taskStats['completed'],
            'pending' => $taskStats['pending'],
            'overdue' => $taskStats['overdue'],
        ]);

        $taskStatusChart = [];

        foreach ($taskStatusLabels as $status => $label) {
            $taskStatusChart[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($taskStatusCounts[$status] ?? 0),
            ];
        }

        $recentSignups = User::latest()->take(5)->get();
        $recentAnnouncements = Announcement::latest()->take(3)->get();

        $staffLocations = collect();

        // 1. Fetch GPS-based check-in locations (latest check-in per user)
        $gpsAttendances = Attendance::whereHas('user')
            ->with('user')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id');

        $usersWithLocation = [];
        foreach ($gpsAttendances as $att) {
            $usersWithLocation[] = $att->user_id;
            $staffLocations->push([
                'latitude' => (float)$att->latitude,
                'longitude' => (float)$att->longitude,
                'status' => $att->status,
                'clock_in_at' => $att->clock_in_at ? $att->clock_in_at->toIso8601String() : null,
                'daily_objective' => $att->daily_objective,
                'remote_notes' => $att->remote_notes,
                'user' => [
                    'id' => $att->user->id,
                    'name' => $att->user->name,
                    'department' => $att->user->department,
                ],
                'source' => 'GPS Check-In',
            ]);
        }

        // 2. Fetch all active users who do not have a GPS-based location, and geolocate their IP
        $remainingUsers = User::internalStaff()
            ->where('status', 'active')
            ->whereNotIn('id', $usersWithLocation)
            ->get();

        foreach ($remainingUsers as $user) {
            $ip = $user->last_login_ip;
            $lat = null;
            $lng = null;
            $locationLabel = null;

            if ($ip) {
                $geo = $this->geolocateIp($ip);
                if ($geo) {
                    $lat = $geo['latitude'];
                    $lng = $geo['longitude'];
                    $locationLabel = "IP: {$ip} (" . ($geo['city'] ?? 'Unknown') . ", " . ($geo['country'] ?? 'Unknown') . ")";
                }
            }

            if ($lat !== null && $lng !== null) {
                $usersWithLocation[] = $user->id;
                $staffLocations->push([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'status' => 'Offline',
                    'clock_in_at' => $user->last_login_at ? $user->last_login_at->toIso8601String() : null,
                    'daily_objective' => 'No GPS check-in recorded.',
                    'remote_notes' => $locationLabel ?: "IP Geolocation",
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'department' => $user->department,
                    ],
                    'source' => 'IP Geolocation',
                ]);
            }
        }

        // 3. Fallback to Accra, Ghana Office Base for any active users who still don't have location coordinates
        $noLocationUsers = User::internalStaff()
            ->where('status', 'active')
            ->whereNotIn('id', $usersWithLocation)
            ->get();

        foreach ($noLocationUsers as $user) {
            $staffLocations->push([
                'latitude' => 5.6037, // Accra, Ghana office coordinates
                'longitude' => -0.1870,
                'status' => 'Offline',
                'clock_in_at' => null,
                'daily_objective' => 'No activity/location logs found.',
                'remote_notes' => 'Defaulting to Main Office Base',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department,
                ],
                'source' => 'Office Base',
            ]);
        }

        $attendanceLogs = $staffLocations->values()->all();

        return view('admin.dashboard', compact('stats', 'recentSignups', 'recentAnnouncements', 'taskStatusChart', 'attendanceLogs'));
    }

    /**
     * Helper to geolocate an IP address using AbstractAPI with a 30-day cache.
     */
    private function geolocateIp(string $ip): ?array
    {
        // Ignore local and private IP ranges
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $apiKey = config('services.ip_geolocation.api_key');
        if (!$apiKey) {
            return null;
        }

        return Cache::remember("ip_geo_v1_{$ip}", now()->addDays(30), function () use ($ip, $apiKey) {
            try {
                $response = Http::withoutVerifying()->timeout(3)->get("https://ipgeolocation.abstractapi.com/v1/", [
                    'api_key' => $apiKey,
                    'ip_address' => $ip
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['latitude']) && isset($data['longitude'])) {
                        return [
                            'latitude' => (float)$data['latitude'],
                            'longitude' => (float)$data['longitude'],
                            'city' => $data['city'] ?? 'Unknown City',
                            'country' => $data['country'] ?? 'Unknown Country',
                        ];
                    }
                }
            } catch (\Exception $e) {
                \Log::error("IP Geolocation failed for IP {$ip}: " . $e->getMessage());
            }
            return null;
        });
    }
}
