<?php

namespace App\Services;

use App\Models\PerformancePolicy;

class PerformancePolicyRules
{
    public static function snapshot(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('performance_policies')) {
            return ['policy_id' => null, 'rules' => config('performance.exceptions')];
        }
        $policy = PerformancePolicy::where('status', 'approved')->whereNotNull('approved_at')->whereDate('effective_on', '<=', today())->orderByDesc('effective_on')->first();

        return ['policy_id' => $policy?->id, 'rules' => array_replace(config('performance.exceptions'), $policy?->rules ?? [])];
    }

    public static function value(string $key): int|float
    {
        return self::snapshot()['rules'][$key];
    }
}
