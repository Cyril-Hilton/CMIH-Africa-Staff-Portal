<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WorkdayReminderService
{
    private const WORK_TIMEZONE = 'Africa/Accra';

    public static function sendMorningReminder(?Carbon $date = null): array
    {
        $date = self::workdayDate($date);
        $cacheKey = 'workday_reminders:morning:' . $date->toDateString();

        if (! Cache::add($cacheKey, true, $date->copy()->endOfDay()->addHours(8))) {
            return ['period' => 'morning', 'sent' => 0, 'skipped' => true];
        }

        $staffIds = self::activeInternalStaffIds();

        NotificationService::sendToMany(
            $staffIds,
            'Workday check-in reminder',
            "Good morning. Work has started for today. Please log in and clock in once you begin work. Clock-in works whether you're at the office, on the field, with a client, or travelling in for work.",
            route('dashboard')
        );

        return ['period' => 'morning', 'sent' => count($staffIds), 'skipped' => false];
    }

    public static function sendEveningReminder(?Carbon $date = null): array
    {
        $date = self::workdayDate($date);
        $cacheKey = 'workday_reminders:evening:' . $date->toDateString();

        if (! Cache::add($cacheKey, true, $date->copy()->endOfDay()->addHours(8))) {
            return ['period' => 'evening', 'sent' => 0, 'manager_sent' => 0, 'skipped' => true];
        }

        $staffIds = self::activeInternalStaffIds();

        NotificationService::sendToMany(
            $staffIds,
            'Workday clock-out reminder',
            "Workday wrap-up: please clock out before you close your day. If you're still on the field or finishing client/work follow-up, clock out once your work has genuinely wrapped.",
            route('dashboard')
        );

        $managerSent = self::sendPendingApprovalReminders();

        return [
            'period' => 'evening',
            'sent' => count($staffIds),
            'manager_sent' => $managerSent,
            'skipped' => false,
        ];
    }

    private static function sendPendingApprovalReminders(): int
    {
        $countsByManager = self::pendingApprovalCountsByManager();

        if ($countsByManager->isEmpty()) {
            return 0;
        }

        $managers = User::internalStaff()
            ->where('status', 'active')
            ->whereIn('id', $countsByManager->keys()->all())
            ->get()
            ->filter(fn (User $user) => $user->isLineManager() || $user->isCvoOrSuperAdmin());

        foreach ($managers as $manager) {
            $count = (int) $countsByManager->get($manager->id, 0);
            $taskLabel = $count === 1 ? 'task approval' : 'task approvals';

            NotificationService::send(
                $manager->id,
                'Pending task approvals reminder',
                "Quick wrap-up nudge: you have {$count} pending {$taskLabel}. Please review them today so completed work does not sit waiting or drift overdue.",
                route('portal.tasks', ['view' => 'pending'])
            );
        }

        return $managers->count();
    }

    private static function pendingApprovalCountsByManager(): Collection
    {
        $counts = collect();

        Task::with('assignee:id,line_manager_id')
            ->realWork()
            ->where('completion_review_status', 'pending')
            ->get(['id', 'assigned_to', 'copied_manager_ids', 'custom_fields'])
            ->each(function (Task $task) use ($counts) {
                $managerIds = collect($task->copied_manager_ids ?? [])
                    ->push($task->custom_fields['completion_manager_id'] ?? null)
                    ->push($task->assignee?->line_manager_id)
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();

                foreach ($managerIds as $managerId) {
                    $counts->put($managerId, ((int) $counts->get($managerId, 0)) + 1);
                }
            });

        return $counts;
    }

    private static function activeInternalStaffIds(): array
    {
        return User::internalStaff()
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function workdayDate(?Carbon $date = null): Carbon
    {
        return ($date ? $date->copy() : Carbon::now(self::WORK_TIMEZONE))
            ->timezone(self::WORK_TIMEZONE);
    }
}
