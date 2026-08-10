<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TaskStatsService
{
    public static function forUser(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $assignedQuery = self::assignedToUserQuery($user, $startDate, $endDate);
        $createdQuery = self::createdByUserQuery($user, $startDate, $endDate);
        $assignedOrCreatedQuery = self::assignedOrCreatedQuery($user, $startDate, $endDate);
        $accountableQuery = self::accountableToUserQuery($user, $startDate, $endDate);

        $assignedTotal = (clone $assignedQuery)->count();
        $createdTotal = (clone $createdQuery)->count();
        $assignedOrCreatedTotal = (clone $assignedOrCreatedQuery)->count();
        $accountableTotal = (clone $accountableQuery)->count();
        $completed = (clone $accountableQuery)->approvedForPerformance()->count();
        $approvedOwnWork = self::ownWorkApprovedCount($user, clone $accountableQuery);
        $approvedByUser = self::approvedByReviewerCount($user, $startDate, $endDate);
        $showsReviewerApprovals = $user->isLineManager() || $approvedByUser > 0;
        $approved = $showsReviewerApprovals ? $approvedByUser : $approvedOwnWork;
        $pending = (clone $accountableQuery)->pendingFinalSignOff()->count();
        $overdue = self::overdueCount(clone $accountableQuery, $endDate);

        return [
            'assigned_total' => $assignedTotal,
            'created_total' => $createdTotal,
            'assigned_or_created_total' => $assignedOrCreatedTotal,
            'accountable_total' => $accountableTotal,
            'completed' => $completed,
            'approved' => $approved,
            'approved_own_work' => $approvedOwnWork,
            'approved_by_user' => $approvedByUser,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rate' => $accountableTotal > 0 ? round(($completed / $accountableTotal) * 100, 1) : 0,
            'approval_label' => self::approvalLabel($user, $showsReviewerApprovals),
        ];
    }

    public static function global(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $taskQuery = self::withDateRange(Task::query()->realWork(), $startDate, $endDate);
        $total = (clone $taskQuery)->count();
        $completed = (clone $taskQuery)->approvedForPerformance()->count();
        $pending = (clone $taskQuery)->pendingFinalSignOff()->count();
        $overdue = self::overdueCount(clone $taskQuery, $endDate);

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public static function forDepartments(array $departments, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $departmentAliases = collect($departments)
            ->flatMap(fn ($department) => User::departmentAliases($department))
            ->unique()
            ->values()
            ->all();

        $taskQuery = self::withDateRange(
            Task::query()->whereIn('department', $departmentAliases)->realWork(),
            $startDate,
            $endDate
        );
        $total = (clone $taskQuery)->count();
        $completed = (clone $taskQuery)->approvedForPerformance()->count();
        $pending = (clone $taskQuery)->pendingFinalSignOff()->count();
        $overdue = self::overdueCount(clone $taskQuery, $endDate);

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public static function ownWorkApprovedCount(User $user, Builder $assignedTaskQuery): int
    {
        return $assignedTaskQuery
            ->where('completion_review_status', 'approved')
            ->get(['id', 'completion_review_status', 'completion_reviewed_by', 'copied_manager_ids', 'custom_fields'])
            ->filter(fn (Task $task) => self::wasApprovedByExpectedManager($task, $user))
            ->count();
    }

    public static function approvedByReviewerCount(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        $query = Task::query()
            ->realWork()
            ->where('completion_review_status', 'approved')
            ->where('completion_reviewed_by', $user->id);

        if ($startDate && $endDate) {
            $query->whereBetween('completion_reviewed_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ]);
        } elseif ($startDate) {
            $query->where('completion_reviewed_at', '>=', $startDate->copy()->startOfDay());
        } elseif ($endDate) {
            $query->where('completion_reviewed_at', '<=', $endDate->copy()->endOfDay());
        }

        return $query->count();
    }

    public static function wasApprovedByExpectedManager(Task $task, User $user): bool
    {
        if ($task->completion_review_status !== 'approved' || ! $task->completion_reviewed_by) {
            return false;
        }

        $expectedManagerIds = self::expectedApprovalManagerIds($task, $user);

        if ($expectedManagerIds === []) {
            return true;
        }

        return in_array((int) $task->completion_reviewed_by, $expectedManagerIds, true);
    }

    public static function approvalLabel(User $user, bool $showsReviewerApprovals = false): string
    {
        if ($showsReviewerApprovals) {
            return 'Approved by you';
        }

        return $user->line_manager_id ? 'Approved by line manager' : 'Approved by review manager';
    }

    public static function assignedToUserQuery(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Builder
    {
        return self::withDateRange(
            Task::query()->where('assigned_to', $user->id)->realWork(),
            $startDate,
            $endDate
        );
    }

    public static function createdByUserQuery(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Builder
    {
        return self::withDateRange(
            Task::query()->where('assigned_by', $user->id)->realWork(),
            $startDate,
            $endDate
        );
    }

    public static function assignedOrCreatedQuery(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Builder
    {
        return self::withDateRange(
            Task::query()
                ->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('assigned_by', $user->id);
                })
                ->realWork(),
            $startDate,
            $endDate
        );
    }

    public static function accountableToUserQuery(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Builder
    {
        return self::withDateRange(
            Task::query()
                ->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('assigned_by', $user->id)
                        ->orWhereJsonContains('supporting_staff_ids', $user->id)
                        ->orWhereJsonContains('supporting_staff_ids', (string) $user->id);
                })
                ->realWork(),
            $startDate,
            $endDate
        );
    }

    private static function expectedApprovalManagerIds(Task $task, User $user): array
    {
        $managerIds = collect();

        if ($user->line_manager_id) {
            $managerIds->push((int) $user->line_manager_id);
        }

        $customFields = $task->custom_fields ?? [];
        if (isset($customFields['completion_manager_id'])) {
            $managerIds->push((int) $customFields['completion_manager_id']);
        }

        foreach (($task->copied_manager_ids ?? []) as $managerId) {
            $managerIds->push((int) $managerId);
        }

        return $managerIds
            ->filter(fn (int $managerId) => $managerId > 0)
            ->unique()
            ->values()
            ->all();
    }

    private static function overdueCount(Builder $query, ?Carbon $endDate = null): int
    {
        $cutoff = ($endDate ?: now())->copy()->endOfDay();

        return $query
            ->pendingFinalSignOff()
            ->whereNotNull('due_on')
            ->where('due_on', '<', $cutoff)
            ->count();
    }

    private static function withDateRange(Builder $query, ?Carbon $startDate, ?Carbon $endDate): Builder
    {
        $cycleStart = Task::cycleStart();
        $effectiveStart = $startDate && $startDate->gt($cycleStart)
            ? $startDate->copy()->startOfDay()
            : $cycleStart;

        if ($endDate && $endDate->copy()->endOfDay()->lt($effectiveStart)) {
            return $query->whereRaw('1 = 0');
        }

        if ($startDate && $endDate) {
            return $query->whereBetween('created_at', [
                $effectiveStart,
                $endDate->copy()->endOfDay(),
            ]);
        }

        if ($endDate) {
            return $query->whereBetween('created_at', [
                $effectiveStart,
                $endDate->copy()->endOfDay(),
            ]);
        }

        return $query->where('created_at', '>=', $effectiveStart);
    }
}
