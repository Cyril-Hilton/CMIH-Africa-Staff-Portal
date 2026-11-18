<?php

namespace App\Services;

class PerformanceMath
{
    public static function achievement(?float $target, ?float $actual, string $direction): ?float
    {
        if ($target === null || $actual === null || ($target == 0 && $direction !== 'lower')) {
            return null;
        }

        if ($target == 0) {
            return $actual <= 0 ? 100.0 : 0.0;
        }

        return round($direction === 'lower' ? max(0, (1 + ($target - $actual) / abs($target)) * 100) : $actual / $target * 100, 2);
    }

    /** Full assigned weight stays in the denominator, including missing results. */
    public static function creditedWeightedScore(iterable $measures): ?float
    {
        $weight = 0.0;
        $points = 0.0;
        foreach ($measures as $measure) {
            $assigned = max(0, (float) ($measure['weight'] ?? 1));
            $weight += $assigned;
            $points += min(100, max(0, (float) ($measure['achievement'] ?? 0))) * $assigned;
        }

        return $weight > 0 ? round($points / $weight, 2) : null;
    }

    public static function ratio(?float $numerator, ?float $denominator, bool $percent = true): ?float
    {
        return $numerator !== null && $denominator !== null && $denominator > 0 ? round($numerator / $denominator * ($percent ? 100 : 1), 4) : null;
    }

    public static function rag(?float $achievement, float $green, float $amber): string
    {
        return $achievement === null ? 'unmeasured' : ($achievement >= $green ? 'green' : ($achievement >= $amber ? 'amber' : 'red'));
    }

    public static function percentage(int $part, int $total): ?float
    {
        return $total ? round(100 * $part / $total, 1) : null;
    }
}
