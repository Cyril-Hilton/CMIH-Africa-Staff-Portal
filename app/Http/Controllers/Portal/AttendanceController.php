<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * Handle Clock In request.
     */
    public function clockIn(Request $request): RedirectResponse
    {
        $request->validate([
            'daily_objective' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'remote_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        
        // Check if already checked in today. The attendance day is based on the
        // actual clock-in timestamp, not when the database row happened to be saved.
        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', Carbon::today())
            ->first();

        if ($existing) {
            return back()->withErrors(['attendance' => 'You have already clocked in today. Clock-out will become available after 6:00 PM.']);
        }

        // Enforce: Clock in only works after you add a task
        $todayTask = Task::where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('assigned_by', $user->id);
            })
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at')
            ->first();

        if (! $todayTask) {
            return back()->withErrors(['attendance' => 'You must add a task or project for today before you can clock in.']);
        }

        $dailyObjective = trim((string) $request->input('daily_objective', ''));
        if ($dailyObjective === '') {
            $dailyObjective = $todayTask->title;
        }

        $now = Carbon::now();
        $nineAm = Carbon::today()->setTime(9, 0, 0);
        $status = $now->gt($nineAm) ? 'Late' : 'On Time';

        Attendance::create([
            'user_id' => $user->id,
            'clock_in_at' => $now,
            'daily_objective' => $dailyObjective,
            'status' => $status,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'remote_notes' => $request->input('remote_notes'),
        ]);

        return back()->with('status', 'Successfully clocked in for today!');
    }

    /**
     * Handle Clock Out request.
     */
    public function clockOut(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Get today's active clock-in
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', Carbon::today())
            ->whereNull('clock_out_at')
            ->first();

        if (! $attendance) {
            return back()->withErrors(['attendance' => 'Active clock-in session not found for today.']);
        }

        $now = Carbon::now();
        $sixPm = Carbon::today()->setTime(18, 0, 0);

        if ($now->lt($sixPm)) {
            return back()->withErrors(['attendance' => 'Clock out is only available after 6:00 PM.']);
        }

        $overtimeMinutes = 0;

        if ($now->gte($sixPm)) {
            $overtimeMinutes = (int) abs($now->diffInMinutes($sixPm));
        }

        $attendance->update([
            'clock_out_at' => $now,
            'overtime_minutes' => $overtimeMinutes,
        ]);

        return back()->with('status', 'Successfully clocked out. Have a great evening!');
    }
}
