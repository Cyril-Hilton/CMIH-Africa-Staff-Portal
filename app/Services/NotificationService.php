<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     */
    public static function send(int $userId, string $title, string $message, ?string $url = null): ?Notification
    {
        // Don't notify if the user doesn't exist
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        return Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'url'     => $url,
            'read_at' => null,
        ]);
    }

    /**
     * Send a notification to multiple users.
     */
    public static function sendToMany(array $userIds, string $title, string $message, ?string $url = null): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            self::send((int) $userId, $title, $message, $url);
        }
    }

    /**
     * Active Super Admins should see every approval-needed event.
     */
    public static function activeSuperAdminIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->where('access_role', 'super_admin')
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active CVO-level approvers plus Super Admins.
     */
    public static function activeCvoApproverIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->where(function ($query) {
                $query->where('access_role', 'super_admin')
                    ->orWhere('job_level', 'super_admin');
            })
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active Finance approvers plus Super Admins.
     */
    public static function activeFinanceApproverIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->where(function ($query) {
                $query->where('department', 'finance')
                    ->orWhere('access_role', 'super_admin');
            })
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active Brands team members plus system admins who manage the merchandiser portal.
     */
    public static function activeMerchandiserPortalAdminIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->isMerchandiserPortalAdmin())
            ->reject(fn (User $user) => $excludeUserId && $user->id === $excludeUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active HR approvers plus Super Admins.
     */
    public static function activeHrApproverIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->hasFullHrAccess())
            ->reject(fn (User $user) => $excludeUserId && $user->id === $excludeUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active Fleet Approvers (HR & Admin department, HR Manager, Super Admin).
     */
    public static function activeFleetApproverIds(?int $excludeUserId = null): array
    {
        return User::where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->canApproveFleetRequests())
            ->reject(fn (User $user) => $excludeUserId && $user->id === $excludeUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Active internal staff only. External merchandisers are intentionally excluded.
     */
    public static function activeInternalStaffIds(?int $excludeUserId = null): array
    {
        return User::internalStaff()
            ->where('status', 'active')
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Send an approval-needed notification to named approvers and always include Super Admins.
     */
    public static function sendApprovalNeededToMany(
        array $userIds,
        string $title,
        string $message,
        ?string $url = null,
        ?int $excludeUserId = null
    ): void {
        $ids = array_merge($userIds, self::activeSuperAdminIds($excludeUserId));

        if ($excludeUserId) {
            $ids = array_filter($ids, fn ($id) => (int) $id !== $excludeUserId);
        }

        self::sendToMany($ids, $title, $message, $url);
    }

    /**
     * Broadcast an announcement notification to all active users.
     */
    public static function broadcast(string $title, string $message, ?string $url = null, ?int $excludeUserId = null): void
    {
        $users = User::where('status', 'active');
        if ($excludeUserId) {
            $users->where('id', '!=', $excludeUserId);
        }

        foreach ($users->cursor() as $user) {
            self::send($user->id, $title, $message, $url);
        }
    }

    /**
     * Broadcast to active internal staff only.
     */
    public static function broadcastToInternalStaff(string $title, string $message, ?string $url = null, ?int $excludeUserId = null): void
    {
        self::sendToMany(self::activeInternalStaffIds($excludeUserId), $title, $message, $url);
    }

    /**
     * Check if today is any active user's birthday, and send wishes.
     */
    public static function checkAndSendBirthdayWishes(): void
    {
        self::sendBirthdayReminderForTomorrow();

        $todayStr = now()->toDateString();
        
        \Illuminate\Support\Facades\Cache::remember('birthday_wishes_run_' . $todayStr, 86400, function () {
            $today = now();
            $month = $today->month;
            $day = $today->day;

            $celebrants = User::internalStaff()
                ->where('status', 'active')
                ->where('birthday_month', $month)
                ->where('birthday_day', $day)
                ->get();

            foreach ($celebrants as $user) {
                // 1. Personal wish
                self::send(
                    $user->id,
                    '🎉 Happy Birthday!',
                    "Dear {$user->name}, CMIH Africa wishes you a wonderful Happy Birthday! Have an amazing day!",
                    route('portal.profile')
                );

                // 2. Team announcement
                self::broadcastToInternalStaff(
                    "🎂 Birthday Celebration: {$user->name}",
                    "Today is {$user->name}'s birthday! Join us in wishing them a fantastic day!",
                    route('portal.directory'),
                    $user->id
                );
            }

            return true;
        });
    }

    private static function sendBirthdayReminderForTomorrow(): void
    {
        $todayStr = now()->toDateString();

        \Illuminate\Support\Facades\Cache::remember('birthday_reminders_run_' . $todayStr, 86400, function () {
            $tomorrow = now()->addDay();
            $month = $tomorrow->month;
            $day = $tomorrow->day;

            $celebrants = User::internalStaff()
                ->where('status', 'active')
                ->where('birthday_month', $month)
                ->where('birthday_day', $day)
                ->get();

            foreach ($celebrants as $user) {
                self::broadcastToInternalStaff(
                    "Birthday Tomorrow: {$user->name}",
                    "Tomorrow is {$user->name}'s birthday. Let's get ready to celebrate them!",
                    route('portal.directory'),
                    $user->id
                );
            }

            return true;
        });
    }
}
