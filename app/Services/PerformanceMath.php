<?php

namespace App\Services;

class PerformanceMath
{
    public static function achievement(?float $target, ?float $actual, string $direction): ?float
    {
        if ($target === null || $actual === null || $target == 0) {
            return null;
        }

        return round($direction === 'lower' ? max(0, (1 + ($target - $actual) / abs($target)) * 100) : $actual / $target * 100, 2);
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
