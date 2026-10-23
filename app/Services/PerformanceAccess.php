<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Database\Eloquent\Builder;

class PerformanceAccess
{
    public static function department(User $user): string
    {
        return User::normalizeDepartmentKey($user->department);
    }

    public static function executive(User $user): bool
    {
        return $user->isCvoOrSuperAdmin();
    }

    public static function manages(User $user, string $department): bool
    {
        if (self::executive($user)) {
            return true;
        }
        if ($department === 'client_relations' && self::department($user) !== 'client_relations') {
            return false;
        }

        return self::executive($user) || ($user->isLineManager() && (self::department($user) === $department || ($department === 'admin_drivers' && self::department($user) === 'hr_admin')));
    }

    public static function scope(Builder $query, User $user, string $owner = 'lead_staff_id'): Builder
    {
        if (self::executive($user)) {
            return $query;
        }
        if (self::department($user) !== 'client_relations') {
            $query->where('department', '!=', 'client_relations');
        }
        if (self::executive($user)) {
            return $query;
        }
        if ($user->isLineManager()) {
            return $query->whereIn('department', array_filter([self::department($user), self::department($user) === 'hr_admin' ? 'admin_drivers' : null]));
        }
        if ($query->getModel() instanceof WeeklyConsolidatedItem) {
            return $query->where(fn ($q) => $q->where($owner, $user->id)->orWhere('reviewer_id', $user->id));
        }

        return $query->where($owner, $user->id);
    }

    public static function view(User $user, WeeklyConsolidatedItem $item): bool
    {
        if (self::executive($user)) {
            return true;
        }
        if ($item->department === 'client_relations' && self::department($user) !== 'client_relations') {
            return false;
        }

        return self::manages($user, $item->department) || (int) $item->lead_staff_id === $user->id || (int) $item->reviewer_id === $user->id;
    }
}
