<?php

namespace App\Services;

use App\Models\MerchandiserOutletAssignment;
use App\Models\MerchandiserVisit;
use App\Models\MerchandiserVisitSku;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PerfectStoreKpiService
{
    public const WEIGHTS = [
        'coverage' => 20,
        'osa' => 20,
        'npd' => 12,
        'mhs' => 12,
        'planogram' => 12,
        'facing' => 12,
        'sos' => 12,
    ];

    public const TARGETS = [
        'coverage' => 100,
        'osa' => 95,
        'npd' => 100,
        'mhs' => 100,
        'planogram' => 100,
        'facing' => 100,
        'sos' => 100,
    ];

    public function summary(Carbon $from, Carbon $to): array
    {
        $assignments = MerchandiserOutletAssignment::with(['user', 'outlet.keyDistributor.region'])
            ->whereDate('assigned_date', '>=', $from->toDateString())
            ->whereDate('assigned_date', '<=', $to->toDateString())
            ->get();

        $visits = MerchandiserVisit::with(['user', 'outlet.keyDistributor.region', 'visitSkus.sku.brand'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $visitScores = $visits->map(fn (MerchandiserVisit $visit) => $this->scoreVisit($visit));
        $brandScores = $this->scoreVisitsByBrand($visits);

        $overview = $this->rollup($assignments, $visitScores, null);
        $merchandiserRollups = $this->rollupsBy(
            $assignments,
            $visitScores,
            fn ($assignment) => (int) $assignment->user_id,
            fn ($score) => (int) $score['user_id'],
            fn ($assignment, $key) => $assignment?->user?->name ?: 'Merchandiser #'.$key
        );
        $kdRollups = $this->rollupsBy(
            $assignments,
            $visitScores,
            fn ($assignment) => (int) ($assignment->outlet?->kd_id ?? 0),
            fn ($score) => (int) ($score['kd_id'] ?? 0),
            fn ($assignment, $key) => $assignment?->outlet?->keyDistributor?->name ?: 'KD #'.$key
        );
        $regionRollups = $this->rollupsBy(
            $assignments,
            $visitScores,
            fn ($assignment) => (int) ($assignment->outlet?->keyDistributor?->region_id ?? 0),
            fn ($score) => (int) ($score['region_id'] ?? 0),
            fn ($assignment, $key) => $assignment?->outlet?->keyDistributor?->region?->name ?: 'Region #'.$key
        );
        $brandRollups = $this->rollupsByScores(
            $brandScores,
            fn ($score) => (int) ($score['brand_id'] ?? 0),
            fn ($score, $key) => $score['brand_name'] ?: 'Brand #'.$key
        );

        return [
            'targets' => self::TARGETS,
            'weights' => self::WEIGHTS,
            'overview' => $overview,
            'merchandisers' => $merchandiserRollups,
            'kds' => $kdRollups,
            'regions' => $regionRollups,
            'brands' => $brandRollups,
            'alerts' => $this->alerts($overview, $merchandiserRollups, $kdRollups),
            'coaching' => $this->coaching($merchandiserRollups),
        ];
    }

    public static function emptySummary(): array
    {
        $emptyRollup = [
            'scheduled' => 0,
            'scored' => 0,
            'coverage' => 0,
            'osa' => null,
            'npd' => null,
            'mhs' => null,
            'planogram' => null,
            'facing' => null,
            'sos' => null,
            'perfect_store_score' => 0,
            'visits' => 0,
        ];

        return [
            'targets' => self::TARGETS,
            'weights' => self::WEIGHTS,
            'overview' => $emptyRollup,
            'merchandisers' => collect(),
            'kds' => collect(),
            'regions' => collect(),
            'brands' => collect(),
            'alerts' => collect(),
            'coaching' => collect(),
        ];
    }

    private function scoreVisit(MerchandiserVisit $visit): array
    {
        $metrics = $this->scoreRows($visit->visitSkus);

        $kd = $visit->outlet?->keyDistributor;

        return [
            'visit_id' => $visit->id,
            'user_id' => $visit->user_id,
            'outlet_id' => $visit->outlet_id,
            'kd_id' => $kd?->id ?? $visit->outlet?->kd_id,
            'region_id' => $kd?->region_id,
            ...$metrics,
            'perfect_store_score' => $this->weightedScore($metrics),
        ];
    }

    private function scoreVisitsByBrand(Collection $visits): Collection
    {
        return $visits->flatMap(function (MerchandiserVisit $visit) {
            $kd = $visit->outlet?->keyDistributor;

            return $visit->visitSkus
                ->filter(fn (MerchandiserVisitSku $row) => (int) ($row->sku?->brand_id ?? 0) > 0)
                ->groupBy(fn (MerchandiserVisitSku $row) => (int) $row->sku->brand_id)
                ->map(function (Collection $rows, int $brandId) use ($visit, $kd) {
                    $metrics = $this->scoreRows($rows);

                    return [
                        'visit_id' => $visit->id,
                        'user_id' => $visit->user_id,
                        'outlet_id' => $visit->outlet_id,
                        'kd_id' => $kd?->id ?? $visit->outlet?->kd_id,
                        'region_id' => $kd?->region_id,
                        'brand_id' => $brandId,
                        'brand_name' => $rows->first()?->sku?->brand?->name,
                        ...$metrics,
                        'perfect_store_score' => $this->weightedScore($metrics),
                    ];
                })
                ->values();
        })->values();
    }

    private function scoreRows(Collection $rows): array
    {
        $osa = $this->dropSizeRate(
            $rows->filter(fn (MerchandiserVisitSku $row) => (bool) ($row->sku?->track_osa ?? true)),
            'osa_drop_size'
        );
        $npd = $this->npdRate(
            $rows->filter(fn (MerchandiserVisitSku $row) => (bool) ($row->sku?->track_npd ?? false))
        );
        $mhs = $this->dropSizeRate(
            $rows->filter(fn (MerchandiserVisitSku $row) => (bool) ($row->sku?->track_mhs ?? false)),
            'mhs_drop_size'
        );

        $planogram = $rows->isNotEmpty()
            ? $this->percent($rows->where('planogram_compliant', true)->count(), $rows->count())
            : null;
        $facing = $rows->isNotEmpty()
            ? $this->percent($rows->filter(fn (MerchandiserVisitSku $row) => (int) $row->facing > 0)->count(), $rows->count())
            : null;
        $sos = $rows->isNotEmpty()
            ? round((float) $rows->avg(fn (MerchandiserVisitSku $row) => (float) $row->share_of_shelf), 1)
            : null;

        return compact('osa', 'npd', 'mhs', 'planogram', 'facing', 'sos');
    }

    private function dropSizeRate(Collection $rows, string $dropSizeColumn): ?float
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $passed = $rows->filter(function (MerchandiserVisitSku $row) use ($dropSizeColumn) {
            $dropSize = max(1, (int) ($row->sku?->{$dropSizeColumn} ?? 1));

            return (int) $row->osa_quantity >= $dropSize;
        })->count();

        return $this->percent($passed, $rows->count());
    }

    private function npdRate(Collection $rows): ?float
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $allPassed = $rows->every(function (MerchandiserVisitSku $row) {
            $dropSize = max(1, (int) ($row->sku?->npd_drop_size ?? 1));

            return (bool) $row->npd_present && (int) $row->osa_quantity >= $dropSize;
        });

        return $allPassed ? 100.0 : 0.0;
    }

    private function rollupsBy(
        Collection $assignments,
        Collection $visitScores,
        callable $assignmentKey,
        callable $scoreKey,
        callable $nameResolver
    ): Collection {
        $keys = collect($assignments
            ->map($assignmentKey)
            ->all())
            ->merge($visitScores->map($scoreKey))
            ->filter(fn ($key) => (int) $key > 0)
            ->unique()
            ->values();

        return $keys
            ->map(function ($key) use ($assignments, $visitScores, $assignmentKey, $scoreKey, $nameResolver) {
                $groupAssignments = $assignments->filter(fn ($assignment) => (int) $assignmentKey($assignment) === (int) $key);
                $groupScores = $visitScores->filter(fn ($score) => (int) $scoreKey($score) === (int) $key);
                $rollup = $this->rollup($groupAssignments, $groupScores, $nameResolver($groupAssignments->first(), $key));
                $rollup['id'] = (int) $key;

                return $rollup;
            })
            ->sortByDesc('perfect_store_score')
            ->values();
    }

    private function rollupsByScores(Collection $visitScores, callable $scoreKey, callable $nameResolver): Collection
    {
        return $visitScores
            ->map($scoreKey)
            ->filter(fn ($key) => (int) $key > 0)
            ->unique()
            ->values()
            ->map(function ($key) use ($visitScores, $scoreKey, $nameResolver) {
                $groupScores = $visitScores->filter(fn ($score) => (int) $scoreKey($score) === (int) $key);
                $rollup = $this->rollup(collect(), $groupScores, $nameResolver($groupScores->first(), $key), false);
                $rollup['id'] = (int) $key;

                return $rollup;
            })
            ->sortByDesc('perfect_store_score')
            ->values();
    }

    private function rollup(Collection $assignments, Collection $visitScores, ?string $name, bool $includeCoverage = true): array
    {
        $scheduled = $assignments->count();
        $scored = $assignments
            ->filter(fn ($assignment) => $assignment->visit_id || $assignment->completed_at || strtolower((string) $assignment->status) === 'completed')
            ->count();
        $coverage = $scheduled > 0 ? $this->percent($scored, $scheduled) : ($includeCoverage ? 0.0 : null);

        $metrics = [
            'coverage' => $coverage,
            'osa' => $this->averageMetric($visitScores, 'osa'),
            'npd' => $this->averageMetric($visitScores, 'npd'),
            'mhs' => $this->averageMetric($visitScores, 'mhs'),
            'planogram' => $this->averageMetric($visitScores, 'planogram'),
            'facing' => $this->averageMetric($visitScores, 'facing'),
            'sos' => $this->averageMetric($visitScores, 'sos'),
        ];

        return [
            'name' => $name,
            'scheduled' => $scheduled,
            'scored' => $scored,
            'visits' => $visitScores->count(),
            ...$metrics,
            'perfect_store_score' => $this->weightedScore($metrics),
        ];
    }

    private function alerts(array $overview, Collection $merchandisers, Collection $kds): Collection
    {
        $alerts = collect();

        foreach (self::TARGETS as $metric => $target) {
            $value = $overview[$metric] ?? null;
            if ($value !== null && (float) $value < (float) $target) {
                $alerts->push([
                    'level' => (float) $value < ((float) $target * 0.75) ? 'critical' : 'watch',
                    'title' => strtoupper($metric).' below target',
                    'detail' => 'Current '.$metric.' is '.number_format((float) $value, 1).'% against '.number_format((float) $target, 1).'%.',
                ]);
            }
        }

        $merchandisers
            ->filter(fn ($rollup) => ($rollup['scheduled'] ?? 0) > 0 && (float) ($rollup['coverage'] ?? 0) < 100)
            ->sortBy('coverage')
            ->take(3)
            ->each(fn ($rollup) => $alerts->push([
                'level' => 'watch',
                'title' => 'Coverage gap: '.$rollup['name'],
                'detail' => ($rollup['scored'] ?? 0).' of '.($rollup['scheduled'] ?? 0).' scheduled outlets scored.',
            ]));

        $kds
            ->filter(fn ($rollup) => (float) ($rollup['perfect_store_score'] ?? 0) < 80)
            ->sortBy('perfect_store_score')
            ->take(3)
            ->each(fn ($rollup) => $alerts->push([
                'level' => 'watch',
                'title' => 'KD execution risk: '.$rollup['name'],
                'detail' => 'Perfect Store score is '.number_format((float) $rollup['perfect_store_score'], 1).'%.',
            ]));

        return $alerts->take(8)->values();
    }

    private function coaching(Collection $merchandisers): Collection
    {
        return $merchandisers
            ->filter(fn ($rollup) => ($rollup['visits'] ?? 0) > 0 || ($rollup['scheduled'] ?? 0) > 0)
            ->map(function ($rollup) {
                $weakest = collect(self::TARGETS)
                    ->map(function ($target, $metric) use ($rollup) {
                        $value = $rollup[$metric] ?? null;

                        return $value === null ? null : [
                            'metric' => $metric,
                            'gap' => max(0, (float) $target - (float) $value),
                            'value' => (float) $value,
                            'target' => (float) $target,
                        ];
                    })
                    ->filter()
                    ->sortByDesc('gap')
                    ->first();

                if (! $weakest || $weakest['gap'] <= 0) {
                    return [
                        'name' => $rollup['name'],
                        'title' => 'Maintain execution quality',
                        'detail' => 'Keep the current route rhythm and photo evidence quality consistent.',
                    ];
                }

                $guidance = match ($weakest['metric']) {
                    'coverage' => 'Prioritize all scheduled outlets first, then submit complete visit scores before closing the day.',
                    'osa' => 'Focus on shelf availability and capture quantities against each SKU drop size.',
                    'npd' => 'NPD is all-or-nothing at store level, so every tracked launch SKU must meet its drop size.',
                    'mhs' => 'Check all must-have SKUs and correct gaps before submitting the store visit.',
                    'planogram' => 'Use the planogram reference before evidence capture and flag blockers early.',
                    'facing' => 'Count facings clearly and escalate low visibility where competitor pressure is high.',
                    'sos' => 'Capture shelf share carefully and report opportunities to improve brand block visibility.',
                    default => 'Review the weakest KPI and add corrective notes on the next visit.',
                };

                return [
                    'name' => $rollup['name'],
                    'title' => strtoupper($weakest['metric']).' coaching',
                    'detail' => $guidance,
                ];
            })
            ->take(8)
            ->values();
    }

    private function averageMetric(Collection $scores, string $metric): ?float
    {
        $values = $scores
            ->pluck($metric)
            ->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
    }

    private function weightedScore(array $metrics): float
    {
        $weighted = 0;
        $availableWeight = 0;

        foreach (self::WEIGHTS as $metric => $weight) {
            if (! array_key_exists($metric, $metrics) || $metrics[$metric] === null) {
                continue;
            }

            $weighted += min(100, max(0, (float) $metrics[$metric])) * $weight;
            $availableWeight += $weight;
        }

        return $availableWeight > 0 ? round($weighted / $availableWeight, 1) : 0.0;
    }

    private function percent(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(((float) $part / (float) $total) * 100, 1) : 0.0;
    }
}
