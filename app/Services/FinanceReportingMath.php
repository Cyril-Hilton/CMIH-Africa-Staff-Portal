<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Unknown money is distinct from a confirmed zero. */
class FinanceReportingMath
{
    public static function sumKnown(Collection $rows, string $field): ?float
    {
        $known = $rows->filter(fn ($row) => is_numeric($row->data[$field] ?? null));
        return $known->isEmpty() ? null : round($known->sum(fn ($row) => (float) $row->data[$field]), 2);
    }

    public static function combine(?float ...$values): ?float
    {
        $known = array_filter($values, fn ($value) => $value !== null);
        return $known === [] ? null : round(array_sum($known), 2);
    }

    public static function balances(Collection $rows): ?float
    {
        $known = $rows->filter(fn ($row) => is_numeric($row->data['value'] ?? null) && is_numeric($row->data['paid_amount'] ?? null));
        return $known->isEmpty() ? null : round($known->sum(fn ($row) => max(0, (float) $row->data['value'] - (float) $row->data['paid_amount'] - (float) ($row->data['credited_amount'] ?? 0))), 2);
    }

    /** A consolidated monthly total already includes its direct entries. */
    public static function revenueSources(Collection $rows): Collection
    {
        return $rows->where('kind', 'revenue')->groupBy(fn ($row) => ($row->period_start ?? $row->period_end)?->format('Y-m') ?? 'undated')
            ->flatMap(function ($month) {
                $ledger = $month->reject(fn ($row) => ($row->data['revenue_type'] ?? 'monthly_total') === 'direct');
                return $ledger->isNotEmpty() ? $ledger : $month;
            })->values();
    }
}
