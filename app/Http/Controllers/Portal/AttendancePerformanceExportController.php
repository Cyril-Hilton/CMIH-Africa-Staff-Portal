<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\PerformanceScoringService;
use App\Services\TaskStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendancePerformanceExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $viewer = $request->user();

        if (! $this->canExportAttendancePerformance($viewer)) {
            abort(403, 'Only Super Admin, HR, and CVO can export attendance performance data.');
        }

        [$start, $end] = $this->dateRange($request);
        $fileName = 'attendance_punctuality_performance_'.$start->toDateString().'_to_'.$end->toDateString().'.csv';

        $response = new StreamedResponse(function () use ($start, $end) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Report Period', $start->toDateString().' to '.$end->toDateString()]);
            fputcsv($handle, ['Login Log Storage', 'Only last_login_at and previous_login_at are currently stored on user accounts.']);
            fputcsv($handle, []);
            fputcsv($handle, $this->headers());

            User::query()
                ->internalStaff()
                ->where('status', 'active')
                ->orderBy('name')
                ->chunk(100, function (Collection $staffMembers) use ($handle, $start, $end) {
                    foreach ($staffMembers as $staff) {
                        fputcsv($handle, $this->rowFor($staff, $start, $end));
                    }
                });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function canExportAttendancePerformance(User $viewer): bool
    {
        if ($viewer->isCvoOrSuperAdmin()) {
            return true;
        }

        $department = strtolower(trim((string) $viewer->department));

        return in_array($department, ['hr_admin', 'admin'], true)
            && $viewer->hrLevel() <= 2;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        $start = filled($validated['start'] ?? null)
            ? Carbon::parse($validated['start'])->startOfDay()
            : Carbon::today()->startOfMonth();

        $end = filled($validated['end'] ?? null)
            ? Carbon::parse($validated['end'])->endOfDay()
            : Carbon::today()->endOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end' => 'The export end date must be on or after the start date.',
            ]);
        }

        return [$start, $end];
    }

    private function headers(): array
    {
        return [
            'Name',
            'Email',
            'Account Type',
            'Department',
            'Role',
            'Position',
            'Last Login',
            'Previous Login',
            'Login Log Note',
            'Login/Clock-In Flag',
            'Expected Workdays',
            'Clocked Days',
            'Missed Days',
            'On-Time Days',
            'Late Days',
            'Attendance Rate %',
            'Punctuality %',
            'First Clock-In',
            'Last Clock-In',
            'Average Clock-In Time',
            'Open Clock-In Days',
            'Clocked Today',
            'Clock-In Log',
            'Clock-Out Log',
            'Attendance Status Log',
            'Tasks In Range',
            'Completed In Range',
            'Pending In Range',
            'Overdue In Range',
            'Completion Rate In Range %',
            'Tasks All-Time',
            'Completed All-Time',
            'Pending All-Time',
        ];
    }

    private function rowFor(User $staff, Carbon $start, Carbon $end): array
    {
        $attendances = Attendance::query()
            ->where('user_id', $staff->id)
            ->whereBetween('clock_in_at', [$start, $end])
            ->orderBy('clock_in_at')
            ->get();

        $attendanceSummary = PerformanceScoringService::attendanceSummary($staff, $start, $end);
        $taskStats = $this->taskStats($staff, $start, $end);
        $allTimeTaskStats = $this->allTimeTaskStats($staff);

        $expectedWorkdays = (int) $attendanceSummary['expected_workdays'];
        $clockedDays = (int) $attendanceSummary['attendance_days'];
        $punctualDays = (int) $attendanceSummary['punctual_days'];
        $missedDays = max(0, $expectedWorkdays - $clockedDays);
        $lateDays = max(0, $clockedDays - $punctualDays);
        $openClockInDays = $attendances
            ->filter(fn (Attendance $attendance) => $attendance->clock_in_at && ! $attendance->clock_out_at)
            ->map(fn (Attendance $attendance) => $attendance->clock_in_at->toDateString())
            ->unique()
            ->count();

        return [
            $staff->name,
            $staff->email,
            $this->accountType($staff),
            User::departmentLabel($staff->department),
            User::roleLabel($staff->access_role),
            $staff->position_title ?: $staff->job_title ?: $staff->job_level ?: '',
            $this->formatDateTime($staff->last_login_at),
            $this->formatDateTime($staff->previous_login_at),
            'Only last_login_at and previous_login_at are stored.',
            $this->loginClockInFlag($staff, $start, $end, $expectedWorkdays, $clockedDays),
            $expectedWorkdays,
            $clockedDays,
            $missedDays,
            $punctualDays,
            $lateDays,
            $attendanceSummary['attendance_rate'],
            $attendanceSummary['punctuality_score'],
            $this->formatDateTime($attendances->first()?->clock_in_at),
            $this->formatDateTime($attendances->last()?->clock_in_at),
            $this->averageClockInTime($attendances),
            $openClockInDays,
            $attendances->contains(fn (Attendance $attendance) => $attendance->clock_in_at?->isToday()) ? 'Yes' : 'No',
            $this->clockInLog($attendances),
            $this->clockOutLog($attendances),
            $this->attendanceStatusLog($attendances),
            $taskStats['total'],
            $taskStats['completed'],
            $taskStats['pending'],
            $taskStats['overdue'],
            $taskStats['completion_rate'],
            $allTimeTaskStats['total'],
            $allTimeTaskStats['completed'],
            $allTimeTaskStats['pending'],
        ];
    }

    private function taskStats(User $staff, Carbon $start, Carbon $end): array
    {
        $stats = TaskStatsService::forUser($staff, $start, $end);

        return [
            'total' => $stats['accountable_total'],
            'completed' => $stats['completed'],
            'pending' => $stats['pending'],
            'overdue' => $stats['overdue'],
            'completion_rate' => $stats['completion_rate'],
        ];
    }

    private function allTimeTaskStats(User $staff): array
    {
        $stats = TaskStatsService::forUser($staff);

        return [
            'total' => $stats['accountable_total'],
            'completed' => $stats['completed'],
            'pending' => $stats['pending'],
        ];
    }

    private function loginClockInFlag(User $staff, Carbon $start, Carbon $end, int $expectedWorkdays, int $clockedDays): string
    {
        $hasLoginStampInRange = collect([$staff->last_login_at, $staff->previous_login_at])
            ->filter()
            ->contains(fn ($date) => Carbon::parse($date)->betweenIncluded($start, $end));

        if ($expectedWorkdays > 0 && $clockedDays >= $expectedWorkdays) {
            return 'Clocked all expected workdays';
        }

        if ($clockedDays > 0 && $expectedWorkdays > 0) {
            return 'Missed '.($expectedWorkdays - $clockedDays).' expected workday(s)';
        }

        if ($clockedDays > 0) {
            return 'Clocked with no expected workday in range';
        }

        if ($hasLoginStampInRange) {
            return 'Logged in but no clock-in in range';
        }

        if ($staff->last_login_at || $staff->previous_login_at) {
            return 'No clock-in in range';
        }

        return 'No login stamp and no clock-in in range';
    }

    private function averageClockInTime(Collection $attendances): string
    {
        $clockInTimes = $attendances
            ->map(fn (Attendance $attendance) => $attendance->clock_in_at)
            ->filter();

        if ($clockInTimes->isEmpty()) {
            return '';
        }

        $averageSeconds = (int) round($clockInTimes->avg(fn (Carbon $clockInAt) => ($clockInAt->hour * 3600) + ($clockInAt->minute * 60) + $clockInAt->second));

        return Carbon::today()->startOfDay()->addSeconds($averageSeconds)->format('h:i A');
    }

    private function clockInLog(Collection $attendances): string
    {
        return $attendances
            ->map(fn (Attendance $attendance) => $this->formatDateTime($attendance->clock_in_at))
            ->filter()
            ->implode('; ');
    }

    private function clockOutLog(Collection $attendances): string
    {
        return $attendances
            ->map(function (Attendance $attendance) {
                $date = $attendance->clock_in_at?->toDateString() ?? 'No clock-in date';

                return $date.': '.($this->formatDateTime($attendance->clock_out_at) ?: 'Open');
            })
            ->implode('; ');
    }

    private function attendanceStatusLog(Collection $attendances): string
    {
        return $attendances
            ->map(function (Attendance $attendance) {
                $date = $attendance->clock_in_at?->toDateString() ?? 'No clock-in date';

                return $date.': '.($attendance->status ?: 'No status');
            })
            ->implode('; ');
    }

    private function formatDateTime($value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function accountType(User $staff): string
    {
        return strtolower((string) $staff->email) === 'cmihstaffs@cmih.africa'
            ? 'Neutral presentation account'
            : 'Staff';
    }
}
