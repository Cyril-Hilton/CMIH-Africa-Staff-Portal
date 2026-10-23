<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DepartmentMetricCalculator
{
    public function calculate(Collection $registers): array
    {
        $approved = $registers->whereNotNull('approved_at')->where('status', '!=', 'cancelled');
        $output = [];
        foreach (config('performance_catalog') as $department => $definitions) {
            foreach ($definitions as $d) {
                $records = $approved->where('department', $department)->where('kind', $d['kind']);
                $all = $records;
                if ($d['status'] && ! in_array($d['operation'], ['status_rate'])) {
                    $records = $records->where('status', $d['status']);
                }
                $field = $d['field'];
                $other = $d['other'];
                $op = $d['operation'];
                $eligible = $records;
                if ($field) {
                    $eligible = $eligible->filter(fn ($r) => isset($r->data[$field]) && $r->data[$field] !== '');
                }
                if ($other && $op !== 'status_rate') {
                    $eligible = $eligible->filter(fn ($r) => isset($r->data[$other]) && $r->data[$other] !== '');
                }
                if ($op === 'profit_variance') {
                    $eligible = $eligible->filter(fn ($r) => isset($r->data[$d['target']]) && $r->data[$d['target']] !== '');
                }
                $value = null;
                $coverage = $eligible->count();
                $total = $all->count();
                if ($total) {
                    if ($op === 'count') {
                        $value = $records->count();
                        $coverage = $total;
                    } elseif ($op === 'count_field') {
                        $value = $eligible->count();
                    } elseif ($op === 'status_rate') {
                        $scope = $all->whereIn('status', explode(',', $other));
                        $value = PerformanceMath::percentage($scope->where('status', $d['status'])->count(), $scope->count());
                        $coverage = $scope->count();
                    } elseif ($coverage) {
                        $sum = fn ($key) => (float) $eligible->sum(fn ($r) => (float) $r->data[$key]);
                        $value = match ($op) {
                            'sum' => $sum($field),
                            'snapshot' => $eligible->groupBy('owner_id')->sum(fn ($g) => (float) $g->sortByDesc('period_end')->first()->data[$field]),
                            'average' => $eligible->avg(fn ($r) => (float) $r->data[$field]),
                            'difference' => $sum($field) - $sum($other),
                            'profit_variance' => $sum($field) - $sum($other) - $sum($d['target']),
                            'growth' => PerformanceMath::ratio($sum($field) - $sum($other), $sum($other)),
                            'margin' => PerformanceMath::ratio($sum($field) - $sum($other), $sum($field)),
                            'ratio' => PerformanceMath::ratio($sum($field), $sum($other)),
                            'ratio_raw' => PerformanceMath::ratio($sum($field), $sum($other), false),
                            'absence' => PerformanceMath::ratio($sum($other) - $sum($field), $sum($other)),
                            'flag_rate' => PerformanceMath::percentage($eligible->filter(fn ($r) => (float) $r->data[$field] === 1.0)->count(), $coverage),
                            'positive_rate' => PerformanceMath::percentage($eligible->filter(fn ($r) => (float) $r->data[$field] > 0)->count(), $coverage),
                            'presence_rate' => PerformanceMath::percentage($eligible->count(), $all->filter(fn ($r) => ! empty($r->data[$other]))->count()),
                            'elapsed' => $eligible->filter(fn ($r) => Carbon::parse($r->data[$other])->gte(Carbon::parse($r->data[$field])))->avg(fn ($r) => Carbon::parse($r->data[$field])->diffInDays(Carbon::parse($r->data[$other]))),
                            'on_time' => PerformanceMath::percentage($eligible->filter(fn ($r) => Carbon::parse($r->data[$field])->lte(Carbon::parse($r->data[$other])))->count(), $coverage),
                            'outstanding' => $eligible->sum(fn ($r) => max(0, (float) $r->data[$field] - (float) $r->data[$other])),
                            'overdue_balance' => $eligible->filter(fn ($r) => ! empty($r->data['due_on']) && Carbon::parse($r->data['due_on'])->lt(today()))->sum(fn ($r) => max(0, (float) $r->data[$field] - (float) $r->data[$other])),
                            default => null,
                        };
                    }
                }
                $output[] = $d + ['department' => $department, 'value' => $value === null ? null : round($value, 4), 'coverage' => $coverage, 'records' => $total, 'source_ids' => $eligible->pluck('id')->all(), 'definition' => ucfirst(str_replace('_', ' ', $op)).' of '.($field ?? 'records').($other ? ' and '.$other : '').'; approved '.$d['kind'].' records only.'];
            }
        }

        return $output;
    }
}
