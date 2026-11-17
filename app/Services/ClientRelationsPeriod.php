<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ClientRelationsPeriod
{
    public static function dates(array $filters): array
    {
        $anchor = Carbon::parse($filters['date'] ?? $filters['from'] ?? $filters['to'] ?? today());
        [$from, $to] = match ($filters['period'] ?? 'week') {
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };

        if (empty($filters['period'])) {
            $from = isset($filters['from']) ? Carbon::parse($filters['from']) : $from;
            $to = isset($filters['to']) ? Carbon::parse($filters['to']) : $to;
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
