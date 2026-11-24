<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsClockedIn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If not logged in, let standard auth middleware handle it
        if (! $user) {
            return $next($request);
        }

        // Auto clock in developer and super admins
        \App\Services\AutoClockService::handleForUser($user);

        // Bypass check in testing environment unless X-Test-Enforce-ClockIn header is set
        if (app()->environment('testing') && ! $request->headers->has('X-Test-Enforce-ClockIn')) {
            return $next($request);
        }

        // Check if the current route is allowed without clocking in
        $allowedRoutes = [
            'dashboard',
            'portal.attendance.clock-in',
            'portal.attendance.clock-in.recover',
            'portal.attendance-performance.export',
            'portal.notifications.poll',
            'portal.announcements',
            'portal.notifications.read',
            'portal.notifications.readAll',
            'portal.dashboard.live',
            'portal.awards.standings',
            'portal.tasks',
            'portal.tasks.show',
            'portal.project-journey.tracker',
            'portal.tasks.store',
            'admin.tasks',
            'admin.tasks.store',
            'portal.profile',
            'profile.edit',
            'profile.update',
            'logout',
        ];

        $currentRoute = $request->route() ? $request->route()->getName() : null;

        $isDeveloper = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true);
        $bypassesClockIn = $isDeveloper || $user->isSupervisionOnly();

        $clockedIn = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', Carbon::today())
            ->exists();

        if ($bypassesClockIn || in_array($currentRoute, $allowedRoutes, true)) {
            return $next($request);
        }

        // Check if user has clocked in today
        if (! $clockedIn) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Please clock in first to access this resource.',
                    'message' => 'Please clock in first, then retry this action.'
                ], 403);
            }

            // Weekly entry forms can be opened on the dashboard before clock-in.
            // Keep the completed form available when its write is blocked.
            if ($currentRoute === 'portal.operations.reporting.save' && ! $request->boolean('module_entry')) {
                $kind = $request->input('kind');
                $parameters = ['weekly_department' => 'operations_projects', 'report_period' => 'week'];
                $week = $request->input('week');
                if (is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) && strtotime($week) !== false) {
                    $parameters['report_date'] = $week;
                }
                $anchor = 'operations-entry';
                if ($request->integer('submission') > 0) {
                    $parameters['ops_review'] = $request->integer('submission');
                    $anchor = 'operations-reviews';
                } elseif ($request->integer('source') > 0) {
                    $parameters['ops_edit'] = $request->integer('source');
                } elseif (is_string($kind) && array_key_exists($kind, \App\Services\OperationsReporting::schemas())) {
                    $parameters['ops_add'] = $kind;
                }

                return redirect()->route('dashboard', $parameters)->withFragment($anchor)
                    ->withInput($request->except(['_token', '_method']))
                    ->withErrors(['attendance' => 'Your report has not been saved: clock in first. Your entries are preserved below. Open Clock In in another tab, then return here and submit again.']);
            }

            return redirect()->route('dashboard')->withErrors([
                'attendance' => 'You must clock in and set your daily objective before accessing other portal features.'
            ]);
        }

        return $next($request);
    }
}
