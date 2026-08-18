<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\KeyDistributor;
use App\Models\Region;
use App\Models\Outlet;
use App\Models\Sku;
use App\Models\MerchandiserVisit;
use App\Models\MerchandiserVisitSku;
use App\Services\PerfectStoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfectStoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_facing_percentage_calculation_math()
    {
        $this->assertEquals(75.0, PerfectStoreCalculator::calculateSkuFacingPct(3, 4));
        $this->assertEquals(100.0, PerfectStoreCalculator::calculateSkuFacingPct(5, 4));
        $this->assertEquals(0.0, PerfectStoreCalculator::calculateSkuFacingPct(0, 4));
    }

    public function test_category_share_of_shelf_calculation_math()
    {
        $this->assertEquals(60.0, PerfectStoreCalculator::calculateCategorySosPct(30, 50));
        $this->assertEquals(100.0, PerfectStoreCalculator::calculateCategorySosPct(50, 50));
        $this->assertEquals(0.0, PerfectStoreCalculator::calculateCategorySosPct(0, 50));
    }

    public function test_store_visit_kpi_metrics_computation()
    {
        $region = Region::create(['name' => 'Greater Accra', 'code' => 'ACC', 'country' => 'Ghana', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Accra KD', 'code' => 'KD-ACC-01', 'region_id' => $region->id]);
        $user = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $outlet = Outlet::create([
            'name' => 'Superstore Osu',
            'code' => 'OSU-001',
            'channel_type' => 'GT',
            'kd_id' => $kd->id,
            'latitude' => 5.5500,
            'longitude' => -0.2000,
        ]);

        $sku1 = Sku::create(['name' => 'Geffen Soap 100g', 'code' => 'SKU-01', 'facing_target' => 4, 'category' => 'Home Care']);
        $sku2 = Sku::create(['name' => 'Geffen Detergent 500g', 'code' => 'SKU-02', 'facing_target' => 2, 'category' => 'Home Care']);

        $visit = MerchandiserVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'visited_at' => now(),
            'notes' => 'Audit visit',
            'total_category_facings' => 10,
        ]);

        // SKU 1: 4 actual (target 4) -> 100% facing, planogram compliant
        MerchandiserVisitSku::create([
            'visit_id' => $visit->id,
            'sku_id' => $sku1->id,
            'facing' => 4,
            'category_unilever_facings' => 4,
            'category_total_facings' => 10,
            'is_in_stock' => true,
            'planogram_compliant' => true,
        ]);

        // SKU 2: 2 actual (target 2) -> 100% facing, planogram compliant
        MerchandiserVisitSku::create([
            'visit_id' => $visit->id,
            'sku_id' => $sku2->id,
            'facing' => 2,
            'category_unilever_facings' => 2,
            'category_total_facings' => 10,
            'is_in_stock' => true,
            'planogram_compliant' => true,
        ]);

        $visit->load(['visitSkus.sku', 'outlet.keyDistributor']);

        $metrics = PerfectStoreCalculator::computeStoreVisitMetrics($visit);

        $this->assertEquals(100.0, $metrics['facing_pct']);
        $this->assertEquals(100.0, $metrics['planogram_pct']);
        $this->assertEquals(30.0, $metrics['sos_pct']);
    }

    public function test_merchandiser_and_kd_rollup_metrics()
    {
        $region = Region::create(['name' => 'Ashanti', 'code' => 'ASH', 'country' => 'Ghana', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Kumasi KD', 'code' => 'KD-KUM-01', 'region_id' => $region->id]);
        $user = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $merchData = PerfectStoreCalculator::computeMerchandiserMetrics($user, collect());
        $this->assertEquals($user->id, $merchData['user_id']);
        $this->assertEquals(0, $merchData['store_count']);

        $kdData = PerfectStoreCalculator::computeKdMetrics($kd, collect());
        $this->assertEquals($kd->id, $kdData['kd_id']);
        $this->assertEquals(0, $kdData['store_count']);
    }
}
