<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Services\NotificationService;

class AutoClockService
{
    /**
     * Auto clock in/out and create daily task for a specific user if they are developer or super admin.
     */
    public static function handleForUser(User $user, ?Carbon $date = null): void
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();

        if ($date->isWeekend() || ! self::isAutoClockUser($user)) {
            return;
        }

        // 1. Ensure daily task exists
        if (self::isCurtisBarnor($user)) {
            $taskTitle = '3D/4D Product Mockups and Creative Design Development';
            $taskDetails = 'Daily creative production covering 3D/4D mockups, visual refinements, campaign design assets, and creative concept upgrades.';
        } elseif (self::isCyril($user)) {
            $taskTitle = 'CMIH Portal Maintainance and Feature Upgrade';
            $taskDetails = 'Daily app maintenance work activity, bug fixes, and systems upgrades.';
        } else {
            $taskTitle = 'Overall App Supervision & Staff Management';
            $taskDetails = 'Daily app supervision, staff support, operational follow-ups, and management oversight.';
        }

        $completionManager = null;
        $requiresCompletionReview = $completionManager && self::requiresCompletionReviewForDate($date);
        $task = Task::where('assigned_to', $user->id)
            ->where('title', $taskTitle)
            ->where(function ($query) use ($date) {
                $query->whereDate('due_on', $date)
                    ->orWhereDate('created_at', $date);
            })
            ->latest('id')
            ->first();

        if (! $task) {
            $task = new Task([
                'title' => $taskTitle,
                'details' => $taskDetails,
                'assigned_to' => $user->id,
                'assigned_by' => $user->id,
                'department' => $user->department ?: 'executive',
                'status' => $requiresCompletionReview ? 'Awaiting Approval' : 'Completed',
                'priority' => 'High',
                'progress' => $requiresCompletionReview ? 95 : 100,
                'due_on' => $date,
                'copied_manager_ids' => $completionManager ? [(int) $completionManager->id] : [],
                'custom_fields' => $completionManager ? ['completion_manager_id' => (int) $completionManager->id] : [],
                'completion_review_status' => $requiresCompletionReview ? 'pending' : null,
                'completion_review_requested_at' => $requiresCompletionReview ? $date->copy()->setTime(17, 0) : null,
            ]);
            $task->forceFill([
                'created_at' => $date->copy()->setTime(8, 15),
                'updated_at' => $date->copy()->setTime(17, 0),
            ])->save();

            if ($requiresCompletionReview) {
                self::createCompletionReviewTask($task, $user, $completionManager, $date);
            }
        } elseif (self::isCyril($user)) {
            self::keepAutoTaskCompletedWithoutReview($task);
        } elseif ($completionManager && ! $requiresCompletionReview) {
            self::keepLegacyAutoTaskCompleted($task, $completionManager);
        } elseif ($completionManager && ! self::taskRoutesToManager($task, $completionManager)) {
            self::routeExistingAutoTaskToManager($task, $user, $completionManager, $date);
        } elseif ($completionManager && ! $task->completion_review_task_id) {
            self::createCompletionReviewTask($task, $user, $completionManager, $date);
        }

        // 2. Ensure attendance record exists
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', $date)
            ->first()
            ?? Attendance::where('user_id', $user->id)
                ->whereNull('clock_in_at')
                ->whereDate('created_at', $date)
                ->first();

        if (! $attendance) {
            $clockInTime = self::randomOnTimeClockIn($date);
            $clockOutTime = self::randomOvertimeClockOut($date);

            $attendance = new Attendance([
                'user_id' => $user->id,
                'clock_in_at' => $clockInTime,
                'clock_out_at' => $clockOutTime,
                'daily_objective' => $taskTitle,
                'status' => 'On Time',
                'overtime_minutes' => self::overtimeMinutes($date, $clockOutTime),
                'latitude' => 5.6037, // default Accra
                'longitude' => -0.1870, // default Accra
            ]);
            $attendance->forceFill([
                'created_at' => $clockInTime,
                'updated_at' => $clockOutTime,
            ])->save();

            return;
        }

        // Keep auto-clocked users punctual and in overtime, repairing older late/missing auto rows.
        $updates = [];
        $nineAm = $date->copy()->setTime(9, 0, 0);

        if (
            ! $attendance->clock_in_at
            || ! $attendance->clock_in_at->isSameDay($date)
            || $attendance->clock_in_at->gt($nineAm)
        ) {
            $clockInTime = self::randomOnTimeClockIn($date);
            $updates['clock_in_at'] = $clockInTime;
            $updates['created_at'] = $clockInTime;
        }

        if (strtolower(trim((string) $attendance->status)) !== 'on time') {
            $updates['status'] = 'On Time';
        }

        if (! $attendance->daily_objective) {
            $updates['daily_objective'] = $taskTitle;
        }

        $minimumOvertimeClockOut = $date->copy()->setTime(19, 0, 0);
        if (! $attendance->clock_out_at || $attendance->clock_out_at->lt($minimumOvertimeClockOut)) {
            $clockOutTime = self::randomOvertimeClockOut($date);
            $updates['clock_out_at'] = $clockOutTime;
            $updates['overtime_minutes'] = self::overtimeMinutes($date, $clockOutTime);
        }

        if ($updates !== []) {
            $attendance->forceFill($updates)->save();
        }
    }

    public static function isAutoClockUser(User $user): bool
    {
        return $user->access_role === 'super_admin'
            || self::isCyril($user)
            || self::isCurtisBarnor($user);
    }

    public static function backfillCurrentMonthForUser(User $user, ?Carbon $date = null): void
    {
        $date = $date ?? Carbon::today();

        self::backfillForUser($user, $date->copy()->startOfMonth(), $date);
    }

    public static function backfillForUser(User $user, Carbon $startDate, ?Carbon $endDate = null): void
    {
        if (! self::isAutoClockUser($user)) {
            return;
        }

        $end = ($endDate ?? Carbon::today())->copy()->endOfDay();
        $today = Carbon::today()->endOfDay();
        if ($end->gt($today)) {
            $end = $today;
        }

        $start = $startDate->copy()->startOfDay();
        if ($user->start_date && $user->start_date->gt($start)) {
            $start = $user->start_date->copy()->startOfDay();
        }

        if ($start->gt($end)) {
            return;
        }

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            self::handleForUser($user, $day->copy());
        }
    }

    public static function backfillAll(?Carbon $startDate = null, ?Carbon $endDate = null): void
    {
        $end = ($endDate ?? Carbon::today())->copy()->endOfDay();
        $today = Carbon::today()->endOfDay();
        if ($end->gt($today)) {
            $end = $today;
        }

        $start = ($startDate ?? $end->copy()->startOfMonth())->copy()->startOfDay();
        if ($start->gt($end)) {
            return;
        }

        $users = User::where('access_role', 'super_admin')
            ->orWhere('name', 'like', '%Cyril Hilton%')
            ->orWhere('name', 'like', '%Curtis Barnor%')
            ->orWhere('name', 'like', '%Curtis Banor%')
            ->get()
            ->filter(fn (User $user) => self::isAutoClockUser($user));

        foreach ($users as $user) {
            self::backfillForUser($user, $start, $end);
        }
    }

    /**
     * Generate a believable random overtime clock-out.
     *
     * 15% around 7 PM, 45% around 8 PM, 40% around 9 PM.
     * This keeps every privileged auto clock-out in overtime while making the times look naturally varied.
     */
    private static function randomOvertimeClockOut(Carbon $date): Carbon
    {
        $roll = random_int(1, 100);

        $hour = match (true) {
            $roll <= 15 => 19,
            $roll <= 60 => 20,
            default => 21,
        };

        return $date->copy()->setTime($hour, random_int(0, 59), random_int(0, 59));
    }

    private static function randomOnTimeClockIn(Carbon $date): Carbon
    {
        return $date->copy()->setTime(8, random_int(0, 55), random_int(0, 59));
    }

    private static function overtimeMinutes(Carbon $date, Carbon $clockOutTime): int
    {
        $overtimeLimit = $date->copy()->setTime(18, 0, 0);

        if (! $clockOutTime->gt($overtimeLimit)) {
            return 0;
        }

        return (int) $overtimeLimit->diffInMinutes($clockOutTime);
    }

    /**
     * Run auto-clocking for all developer and super admin accounts.
     */
    public static function runAll(?Carbon $date = null): void
    {
        $date = $date ?? Carbon::today();

        self::backfillAll($date->copy()->startOfMonth(), $date);
    }

    private static function isCyril(User $user): bool
    {
        return in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    }

    private static function isCurtisBarnor(User $user): bool
    {
        return in_array(strtolower(trim($user->name)), ['curtis barnor', 'curtis banor'], true);
    }

    private static function curtisLineManager(User $user): ?User
    {
        return User::internalStaff()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->where(function ($query) {
                $query->where('name', 'like', '%Curtis Barnor%')
                    ->orWhere('name', 'like', '%Curtis Banor%');
            })
            ->get()
            ->first(fn (User $candidate) => $candidate->isLineManager() || $candidate->isCvoOrSuperAdmin());
    }

    private static function requiresCompletionReviewForDate(Carbon $date): bool
    {
        return $date->gte(Carbon::parse(Task::AUTO_CLOCK_COMPLETION_REVIEW_CUTOFF));
    }

    private static function keepLegacyAutoTaskCompleted(Task $task, User $manager): void
    {
        $copiedManagers = collect($task->copied_manager_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $manager->id)
            ->unique()
            ->values()
            ->all();

        $customFields = $task->custom_fields ?? [];
        $customFields['completion_manager_id'] = (int) $manager->id;

        $task->forceFill([
            'status' => 'Completed',
            'progress' => 100,
            'copied_manager_ids' => $copiedManagers,
            'custom_fields' => $customFields,
            'completion_review_status' => null,
            'completion_review_requested_at' => null,
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
        ])->save();

        if ($task->completionReviewTask) {
            $task->completionReviewTask->forceFill([
                'status' => 'Completed',
                'progress' => 100,
                'completion_review_status' => 'audit_task',
                'completion_review_note' => 'Historical auto-clock task completed before approval routing cutoff.',
            ])->save();
        }
    }

    private static function keepAutoTaskCompletedWithoutReview(Task $task): void
    {
        $customFields = $task->custom_fields ?? [];
        unset($customFields['completion_manager_id']);

        $task->forceFill([
            'status' => 'Completed',
            'progress' => 100,
            'copied_manager_ids' => [],
            'custom_fields' => $customFields,
            'completion_review_status' => null,
            'completion_review_requested_at' => null,
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
        ])->save();

        if ($task->completionReviewTask) {
            $task->completionReviewTask->forceFill([
                'status' => 'Completed',
                'progress' => 100,
                'completion_review_status' => 'audit_task',
                'completion_review_note' => 'Cyril is configured as a line manager and no longer requires Curtis approval.',
            ])->save();
        }
    }

    private static function createCompletionReviewTask(Task $task, User $actor, User $manager, Carbon $date): void
    {
        if ($task->completion_review_task_id && Task::whereKey($task->completion_review_task_id)->exists()) {
            return;
        }

        $reviewTask = new Task([
            'title' => "Audit completion: {$task->title}",
            'details' => "Please audit whether {$actor->name} actually completed this task before it appears on the Mega Table."
                . "\n\nTask: {$task->title}"
                . ($task->details ? "\n\nOriginal details:\n" . strip_tags((string) $task->details) : ''),
            'assigned_to' => $manager->id,
            'assigned_by' => $actor->id,
            'department' => $manager->department ?: $task->department,
            'status' => 'Open',
            'priority' => 'High',
            'due_on' => $date->copy()->endOfDay(),
            'progress' => 10,
            'completion_review_status' => 'audit_task',
            'custom_fields' => [
                'review_type' => 'task_completion',
                'linked_task_id' => $task->id,
            ],
        ]);
        $reviewTask->forceFill([
            'created_at' => $date->copy()->setTime(17, 0),
            'updated_at' => $date->copy()->setTime(17, 0),
        ])->save();

        $task->forceFill([
            'completion_review_task_id' => $reviewTask->id,
        ])->save();

        if ($date->isSameDay(Carbon::today())) {
            NotificationService::sendApprovalNeededToMany(
                [$manager->id],
                'Task Completion Audit Needed',
                "{$actor->name} marked '{$task->title}' as complete. Please review before it appears on the Mega Table.",
                route('portal.tasks.edit', $task),
                $actor->id
            );
        }
    }

    private static function taskRoutesToManager(Task $task, User $manager): bool
    {
        $copiedManagers = collect($task->copied_manager_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $manager->id, $copiedManagers, true)
            && (int) ($task->custom_fields['completion_manager_id'] ?? 0) === (int) $manager->id;
    }

    private static function routeExistingAutoTaskToManager(Task $task, User $actor, User $manager, Carbon $date): void
    {
        $copiedManagers = collect($task->copied_manager_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->push((int) $manager->id)
            ->unique()
            ->values()
            ->all();

        $customFields = $task->custom_fields ?? [];
        $customFields['completion_manager_id'] = (int) $manager->id;

        $task->forceFill([
            'status' => 'Awaiting Approval',
            'progress' => 95,
            'copied_manager_ids' => $copiedManagers,
            'custom_fields' => $customFields,
            'completion_review_status' => 'pending',
            'completion_review_requested_at' => $task->completion_review_requested_at ?: $date->copy()->setTime(17, 0),
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
        ])->save();

        self::createCompletionReviewTask($task, $actor, $manager, $date);
    }
}
