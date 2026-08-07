<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class PerformanceScoringService
{
    /**
     * Score punctuality as on-time clock-in days divided by expected workdays.
     */
    public static function attendanceSummary(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = self::effectiveEndDate($startDate, $endDate);

        if (! $rangeEnd) {
            return [
                'expected_workdays' => 0,
                'attendance_days' => 0,
                'punctual_days' => 0,
                'attendance_rate' => 100.0,
                'punctuality_score' => 100.0,
            ];
        }

        $expectedWorkdayDates = self::expectedWorkdayDates($user, $rangeStart, $rangeEnd);
        $expectedWorkdays = count($expectedWorkdayDates);
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('clock_in_at', [$rangeStart, $rangeEnd])
            ->get();

        $attendanceDays = $attendances
            ->map(fn (Attendance $attendance) => $attendance->clock_in_at?->toDateString())
            ->filter()
            ->filter(fn (string $date) => in_array($date, $expectedWorkdayDates, true))
            ->unique()
            ->count();

        $punctualDays = $attendances
            ->filter(fn (Attendance $attendance) => self::isPunctual($attendance))
            ->map(fn (Attendance $attendance) => $attendance->clock_in_at?->toDateString())
            ->filter()
            ->filter(fn (string $date) => in_array($date, $expectedWorkdayDates, true))
            ->unique()
            ->count();

        $punctualityScore = $expectedWorkdays > 0
            ? min(100, ($punctualDays / $expectedWorkdays) * 100)
            : 100;
        $attendanceRate = $expectedWorkdays > 0
            ? min(100, ($attendanceDays / $expectedWorkdays) * 100)
            : 100;

        return [
            'expected_workdays' => $expectedWorkdays,
            'attendance_days' => $attendanceDays,
            'punctual_days' => $punctualDays,
            'attendance_rate' => round($attendanceRate, 1),
            'punctuality_score' => round($punctualityScore, 1),
        ];
    }

    public static function trackingStartedAt(): ?Carbon
    {
        $firstClockIn = Attendance::whereNotNull('clock_in_at')->min('clock_in_at');

        return $firstClockIn ? Carbon::parse($firstClockIn)->startOfDay() : null;
    }

    private static function effectiveEndDate(Carbon $startDate, Carbon $endDate): ?Carbon
    {
        $today = Carbon::today();
        $effectiveEnd = $endDate->copy()->endOfDay();

        if ($effectiveEnd->gt($today->copy()->endOfDay())) {
            $effectiveEnd = $today->copy()->endOfDay();
        }

        if ($effectiveEnd->lt($startDate->copy()->startOfDay())) {
            return null;
        }

        return $effectiveEnd;
    }

    private static function expectedWorkdayDates(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $effectiveStart = $startDate->copy()->startOfDay();

        if ($user->start_date && $user->start_date->gt($effectiveStart)) {
            $effectiveStart = $user->start_date->copy()->startOfDay();
        }

        if ($effectiveStart->gt($endDate)) {
            return [];
        }

        $approvedLeaveDates = self::approvedLeaveDates($user, $effectiveStart, $endDate);
        $workdays = [];

        for ($date = $effectiveStart->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekend() || in_array($date->toDateString(), $approvedLeaveDates, true)) {
                continue;
            }

            $workdays[] = $date->toDateString();
        }

        return $workdays;
    }

    private static function approvedLeaveDates(User $user, Carbon $startDate, Carbon $endDate): array
    {
        return LeaveApplication::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get()
            ->flatMap(function (LeaveApplication $leave) use ($startDate, $endDate) {
                $leaveStart = $leave->start_date->copy()->max($startDate)->startOfDay();
                $leaveEnd = $leave->end_date->copy()->min($endDate)->endOfDay();
                $dates = [];

                for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                    if (! $date->isWeekend()) {
                        $dates[] = $date->toDateString();
                    }
                }

                return $dates;
            })
            ->unique()
            ->values()
            ->all();
    }

    private static function isPunctual(Attendance $attendance): bool
    {
        return $attendance->clock_in_at
            && $attendance->clock_in_at->format('H:i:s') <= '09:00:00';
    }
}
