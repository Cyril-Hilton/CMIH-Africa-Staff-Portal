<?php

namespace App\Services;

use App\Models\KeyDistributor;
use App\Models\MerchandiserVisit;
use App\Models\MerchandiserVisitSku;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Collection;

class PerfectStoreCalculator
{
    public const FACING_TARGET_PCT = 95.0;
    public const PLANOGRAM_TARGET_PCT = 100.0;

    /**
     * Calculate SKU-level Facing Percentage.
     * SKU Facing % = (Actual Facings Recorded ÷ SKU Facing Target) × 100
     */
    public static function calculateSkuFacingPct(int $actualFacings, int $facingTarget): float
    {
        if ($facingTarget <= 0) {
            return 0.0;
        }

        return min(100.0, round(($actualFacings / $facingTarget) * 100, 2));
    }

    /**
     * Calculate Store-level Facing Performance from visit SKUs.
     * Store Facing % = (Total Actual Facings ÷ Total Facing Target) × 100
     */
    public static function calculateStoreFacingPct(Collection $visitSkus): float
    {
        $totalActual = 0;
        $totalTarget = 0;

        foreach ($visitSkus as $skuRecord) {
            $actual = (int) ($skuRecord->facing ?? 0);
            $target = (int) ($skuRecord->facing_target_snapshot ?? $skuRecord->sku?->facing_target ?? 0);

            if ($target > 0) {
                $totalActual += $actual;
                $totalTarget += $target;
            }
        }

        if ($totalTarget <= 0) {
            return 100.0;
        }

        return min(100.0, round(($totalActual / $totalTarget) * 100, 2));
    }

    /**
     * Calculate Store-level Planogram Performance.
     * Store Planogram % = (Number of Compliant SKUs ÷ Total SKUs Tracked for Planogram) × 100
     */
    public static function calculateStorePlanogramPct(Collection $visitSkus): float
    {
        $trackedCount = 0;
        $compliantCount = 0;

        foreach ($visitSkus as $skuRecord) {
            $isTracked = (bool) ($skuRecord->sku?->track_planogram ?? true);
            if ($isTracked) {
                $trackedCount++;
                if (! empty($skuRecord->planogram_compliant)) {
                    $compliantCount++;
                }
            }
        }

        if ($trackedCount <= 0) {
            return 100.0;
        }

        return round(($compliantCount / $trackedCount) * 100, 2);
    }

    /**
     * Calculate Category-level SOS % for a visit.
     * SOS % = (Unilever Facings ÷ Total Category Facings) × 100
     */
    public static function calculateCategorySosPct(int $unileverFacings, int $totalCategoryFacings, ?float $fallbackSos = null): float
    {
        if ($totalCategoryFacings > 0) {
            return round(($unileverFacings / $totalCategoryFacings) * 100, 2);
        }

        if ($fallbackSos !== null) {
            return round((float) $fallbackSos, 2);
        }

        return 0.0;
    }

    /**
     * Calculate Store-level SOS Performance.
     * Store SOS Performance = Average of Category SOS % across tracked categories in the visit.
     */
    public static function calculateStoreSosPct(Collection $visitSkus): float
    {
        $categoryScores = [];

        foreach ($visitSkus as $skuRecord) {
            $category = $skuRecord->sku?->category ?: 'General';
            $unileverFacings = (int) ($skuRecord->category_unilever_facings ?? $skuRecord->facing ?? 0);
            $totalCategoryFacings = (int) ($skuRecord->category_total_facings ?? $skuRecord->visit?->total_category_facings ?? 0);
            if ($totalCategoryFacings === 0 && ! empty($skuRecord->visit_id)) {
                $totalCategoryFacings = (int) (\App\Models\MerchandiserVisit::where('id', $skuRecord->visit_id)->value('total_category_facings') ?? 0);
            }
            $fallbackSos = $skuRecord->share_of_shelf !== null ? (float) $skuRecord->share_of_shelf : null;

            $sos = self::calculateCategorySosPct($unileverFacings, $totalCategoryFacings, $fallbackSos);
            $categoryScores[$category][] = $sos;
        }

        if (empty($categoryScores)) {
            return 0.0;
        }

        $categoryAverages = [];
        foreach ($categoryScores as $cat => $scores) {
            $categoryAverages[] = array_sum($scores) / count($scores);
        }

        return round(array_sum($categoryAverages) / count($categoryAverages), 2);
    }

    /**
     * Compute full Store Performance Metrics for a visit.
     */
    public static function computeStoreVisitMetrics(MerchandiserVisit $visit): array
    {
        $visitSkus = $visit->visitSkus->loadMissing('sku');

        $facingPct = self::calculateStoreFacingPct($visitSkus);
        $planogramPct = self::calculateStorePlanogramPct($visitSkus);
        $sosPct = self::calculateStoreSosPct($visitSkus);

        // Overall Perfect Store Score = Average of Facing %, Planogram %, SOS %
        $overallScore = round(($facingPct + $planogramPct + $sosPct) / 3, 2);

        $status = 'Needs Attention';
        if ($overallScore >= 95.0 && $facingPct >= 95.0 && $planogramPct >= 100.0) {
            $status = 'Perfect Store';
        } elseif ($overallScore >= 75.0) {
            $status = 'On Track';
        }

        return [
            'facing_pct' => $facingPct,
            'facing_target_pct' => self::FACING_TARGET_PCT,
            'facing_compliant' => $facingPct >= self::FACING_TARGET_PCT,
            'planogram_pct' => $planogramPct,
            'planogram_target_pct' => self::PLANOGRAM_TARGET_PCT,
            'planogram_compliant' => $planogramPct >= self::PLANOGRAM_TARGET_PCT,
            'sos_pct' => $sosPct,
            'overall_score' => $overallScore,
            'status' => $status,
            'total_skus' => $visitSkus->count(),
            'actual_facings' => $visitSkus->sum('facing'),
            'target_facings' => $visitSkus->sum(fn ($s) => $s->facing_target_snapshot ?? $s->sku?->facing_target ?? 0),
        ];
    }

    /**
     * Compute Merchandiser Facing, Planogram & SOS Performance.
     * Merchandiser Performance = Average of Store Performance % across assigned stores.
     */
    public static function computeMerchandiserMetrics(User $merchandiser, Collection $latestVisitsByStore): array
    {
        if ($latestVisitsByStore->isEmpty()) {
            return [
                'user_id' => $merchandiser->id,
                'user_name' => $merchandiser->name,
                'facing_pct' => 0.0,
                'planogram_pct' => 0.0,
                'sos_pct' => 0.0,
                'overall_score' => 0.0,
                'status' => 'Needs Attention',
                'store_count' => 0,
            ];
        }

        $facingScores = [];
        $planogramScores = [];
        $sosScores = [];
        $overallScores = [];

        foreach ($latestVisitsByStore as $item) {
            $visit = $item instanceof Collection ? $item->first() : (is_array($item) ? reset($item) : $item);
            if (! $visit instanceof MerchandiserVisit) {
                continue;
            }
            $m = self::computeStoreVisitMetrics($visit);
            $facingScores[] = $m['facing_pct'];
            $planogramScores[] = $m['planogram_pct'];
            $sosScores[] = $m['sos_pct'];
            $overallScores[] = $m['overall_score'];
        }

        $count = count($facingScores);
        if ($count === 0) {
            return [
                'user_id' => $merchandiser->id,
                'user_name' => $merchandiser->name,
                'facing_pct' => 0.0,
                'planogram_pct' => 0.0,
                'sos_pct' => 0.0,
                'overall_score' => 0.0,
                'status' => 'Needs Attention',
                'store_count' => 0,
            ];
        }

        $avgFacing = round(array_sum($facingScores) / $count, 2);
        $avgPlanogram = round(array_sum($planogramScores) / $count, 2);
        $avgSos = round(array_sum($sosScores) / $count, 2);
        $avgOverall = round(array_sum($overallScores) / $count, 2);

        $status = 'Needs Attention';
        if ($avgOverall >= 95.0 && $avgFacing >= 95.0 && $avgPlanogram >= 100.0) {
            $status = 'Perfect Store';
        } elseif ($avgOverall >= 75.0) {
            $status = 'On Track';
        }

        return [
            'user_id' => $merchandiser->id,
            'user_name' => $merchandiser->name,
            'facing_pct' => $avgFacing,
            'planogram_pct' => $avgPlanogram,
            'sos_pct' => $avgSos,
            'overall_score' => $avgOverall,
            'status' => $status,
            'store_count' => $count,
        ];
    }

    /**
     * Compute Key Distributor (KD) Performance.
     * KD Performance = Average of Store Performance % under the KD.
     */
    public static function computeKdMetrics(KeyDistributor $kd, Collection $latestVisitsByStore): array
    {
        if ($latestVisitsByStore->isEmpty()) {
            return [
                'kd_id' => $kd->id,
                'kd_name' => $kd->name,
                'facing_pct' => 0.0,
                'planogram_pct' => 0.0,
                'sos_pct' => 0.0,
                'overall_score' => 0.0,
                'status' => 'Needs Attention',
                'store_count' => 0,
            ];
        }

        $facingScores = [];
        $planogramScores = [];
        $sosScores = [];
        $overallScores = [];

        foreach ($latestVisitsByStore as $item) {
            $visit = $item instanceof Collection ? $item->first() : (is_array($item) ? reset($item) : $item);
            if (! $visit instanceof MerchandiserVisit) {
                continue;
            }
            $m = self::computeStoreVisitMetrics($visit);
            $facingScores[] = $m['facing_pct'];
            $planogramScores[] = $m['planogram_pct'];
            $sosScores[] = $m['sos_pct'];
            $overallScores[] = $m['overall_score'];
        }

        $count = count($facingScores);
        if ($count === 0) {
            return [
                'kd_id' => $kd->id,
                'kd_name' => $kd->name,
                'facing_pct' => 0.0,
                'planogram_pct' => 0.0,
                'sos_pct' => 0.0,
                'overall_score' => 0.0,
                'status' => 'Needs Attention',
                'store_count' => 0,
            ];
        }

        $avgFacing = round(array_sum($facingScores) / $count, 2);
        $avgPlanogram = round(array_sum($planogramScores) / $count, 2);
        $avgSos = round(array_sum($sosScores) / $count, 2);
        $avgOverall = round(array_sum($overallScores) / $count, 2);

        $status = 'Needs Attention';
        if ($avgOverall >= 95.0 && $avgFacing >= 95.0 && $avgPlanogram >= 100.0) {
            $status = 'Perfect Store';
        } elseif ($avgOverall >= 75.0) {
            $status = 'On Track';
        }

        return [
            'kd_id' => $kd->id,
            'kd_name' => $kd->name,
            'facing_pct' => $avgFacing,
            'planogram_pct' => $avgPlanogram,
            'sos_pct' => $avgSos,
            'overall_score' => $avgOverall,
            'status' => $status,
            'store_count' => $count,
        ];
    }
}
