<?php

namespace Tests\Feature;

use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\WeeklyDepartmentTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDepartmentTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_tables_use_department_columns_and_approved_allowlisted_values(): void
    {
        $owner = User::factory()->create();
        $base = ['department' => 'operations_projects', 'kind' => 'project', 'reference' => 'ART-1', 'title' => 'Artwork', 'owner_id' => $owner->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'status' => 'completed', 'data' => ['rework_count' => 2, 'quality_score' => 90, 'budget' => 987654, 'private_note' => 'Confidential source'], 'approved_at' => now()];
        PerformanceRegister::create($base);
        PerformanceRegister::create(array_replace($base, ['reference' => 'ART-2', 'title' => 'Unreviewed output', 'approved_at' => null]));
        $tables = app(WeeklyDepartmentTables::class)->build('operations_projects');
        $this->assertCount(1, $tables['operations_projects']['rows']);
        $this->assertEquals(2, $tables['operations_projects']['rows'][0]['rework_count']);
        $this->assertArrayNotHasKey('budget', $tables['operations_projects']['rows'][0]);
        $this->assertStringNotContainsString('Confidential source', json_encode($tables));
        $this->assertCount(0, app(WeeklyDepartmentTables::class)->build('operations_projects', '2026-11-01', '2026-11-30')['operations_projects']['rows']);
        $this->assertSame('Completed', $tables['operations_projects']['rows'][0]['status']);
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('hr_admin'));
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('creatives'));
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('brands_marketing')['brands_marketing']['sections']['campaign']['rows']);
        $this->assertStringNotContainsString('987654', json_encode($tables));
        $this->assertSame('Artwork', $tables['operations_projects']['sections']['project']['rows'][0]['title']);
    }
    public function test_brands_presentation_reads_approved_campaigns_without_budget_or_evidence(): void
    {
        $owner=User::factory()->create();
        $base=['department'=>'brands_marketing','kind'=>'campaign','reference'=>'CAM-1','title'=>'Campaign performance','owner_id'=>$owner->id,'period_start'=>'2026-10-19','period_end'=>'2026-10-25','status'=>'completed','approved_at'=>now(),'evidence_url'=>'https://example.test/private-evidence','data'=>['impressions'=>2500,'interactions'=>120,'budget'=>987654,'spend'=>456789,'private_note'=>'PRIVATE SOURCE']];
        PerformanceRegister::create($base);
        PerformanceRegister::create(array_replace($base,['reference'=>'CAM-2','title'=>'Unapproved campaign','approved_at'=>null]));
        $result=app(WeeklyDepartmentTables::class)->build('brands_marketing','2026-10-19','2026-10-25');
        $rows=$result['brands_marketing']['sections']['campaign']['rows'];
        $this->assertCount(1,$rows);
        $this->assertSame(2500,$rows[0]['impressions']);
        foreach(['987654','456789','PRIVATE SOURCE','private-evidence','Unapproved campaign'] as $secret) $this->assertStringNotContainsString($secret,json_encode($result));
    }

    public function test_brands_retail_measures_are_shared_without_private_payload_or_unapproved_rows(): void
    {
        $owner=User::factory()->create();
        $base=['department'=>'brands_marketing','kind'=>'campaign','reference'=>'BRAND-RETAIL','title'=>'Retail execution','owner_id'=>$owner->id,'period_start'=>'2026-10-19','period_end'=>'2026-10-25','status'=>'in_progress','approved_at'=>now(),'data'=>['workstream'=>'Shopper Marketing','coverage_target_percent'=>100,'coverage_actual_percent'=>88,'coverage_previous_percent'=>72,'osa_actual_percent'=>92,'outlets_visited'=>0,'merchandisers_deployed'=>25,'next_action'=>'Resolve shelf gaps','budget'=>123456,'spend'=>654321,'retailer_phone'=>'PRIVATE CONTACT']];
        PerformanceRegister::create($base);
        $result=app(WeeklyDepartmentTables::class)->build('brands_marketing');
        $row=$result['brands_marketing']['sections']['campaign']['rows'][0];
        $this->assertSame(88,$row['coverage_actual_percent']);
        $this->assertSame(0,$row['outlets_visited']);
        $this->assertSame('Shopper Marketing',$row['workstream']);
        foreach(['budget','spend','retailer_phone'] as $private) $this->assertArrayNotHasKey($private,$row);
        $html=view('portal.partials.brands-activity-register',['section'=>$result['brands_marketing']['sections']['campaign']])->render();
        foreach(['Retail KPI Results','On-Shelf Availability','+16.00 pp change','-12.00 pp vs target','reported results','Resolve shelf gaps'] as $expected) $this->assertStringContainsString($expected,$html);
        foreach(['123456','654321','PRIVATE CONTACT'] as $private) $this->assertStringNotContainsString($private,$html);
    }

    public function test_brands_percentages_are_bounded_but_execution_may_exceed_plan(): void
    {
        $validator=app(\App\Services\BrandsActivityValidation::class);
        $validator->validate(['coverage_actual_percent'=>100,'outlets_planned'=>10,'outlets_visited'=>12,'assets_delivered'=>0]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $validator->validate(['osa_actual_percent'=>101]);
    }
    public function test_brands_deployment_counts_cannot_be_fractional(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\BrandsActivityValidation::class)->validate(['promoters_deployed'=>2.5]);
    }

}
