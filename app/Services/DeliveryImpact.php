<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliveryImpact
{
    public static function mayRate(User $actor, ?User $owner): bool
    {
        return $owner && $actor->isActive() && (int)$actor->id !== (int)$owner->id
            && ($actor->isCvoOrSuperAdmin() || ($actor->isLineManager() &&
                ((int)$owner->line_manager_id === (int)$actor->id || PerformanceAccess::manages($actor, PerformanceAccess::department($owner)))));
    }

    public static function points(Model $work, User $owner): int
    {
        // The protected controller authorizes the rating when it is made. A later
        // suspension or manager change must not rewrite the recorded impact.
        if (!$work->weight_approved_at || !$work->weight_approved_by || (int)$work->weight_approved_by === (int)$owner->id) return 1;
        return max(1,min(100,(int)$work->performance_weight));
    }
}
