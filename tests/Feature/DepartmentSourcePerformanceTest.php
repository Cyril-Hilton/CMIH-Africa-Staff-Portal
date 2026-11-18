<?php
namespace Tests\Feature;

use App\Models\{CrmPipelineProject,PerformanceRegister,User};
use App\Services\{DepartmentSourcePerformance,SharedWeeklyPerformance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentSourcePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_reporting_alone_covers_one_area_and_missing_revenue_and_growth_reduce_coverage(): void
    {
        $owner=User::factory()->create(['department'=>'client_relations']);
        $p=CrmPipelineProject::create(['client_name'=>'Test Client','project_name'=>'Delivery only','owner_id'=>$owner->id,
            'created_by'=>$owner->id,'updated_by'=>$owner->id,'week_start'=>'2026-11-16','source_period_confirmed'=>true,'stage'=>'project_ongoing']);
        $s=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22');
        $this->assertSame(33.3,$s['coverage']);
        $this->assertSame(1,$s['reported']);
        $this->assertSame(2,$s['missing']);
        $this->assertSame('Missing report',$s['areas'][1]['state']);
        $this->assertNull($s['metrics'][0]['achievement']);
        $p->update(['target_amount'=>100]);
        $s=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22');
        $this->assertSame(0.0,$s['metrics'][0]['achievement']);
        $this->assertNull($s['metrics'][0]['actual']);
        $p->update(['actual_amount'=>50,'proposed_amount'=>200,'new_business'=>true]);
        $s=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22');
        $this->assertSame(100.0,$s['coverage']);
        $this->assertSame(0.0,$s['metrics'][0]['achievement'],'Reported revenue is not confirmed revenue');
        $finance=User::factory()->create(['department'=>'finance']);
        $p->update(['revenue_state'=>'confirmed','revenue_submitted_by'=>$owner->id,'revenue_finance_by'=>$finance->id,'revenue_confirmed_at'=>now()]);
        $s=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22');
        $this->assertSame(50.0,$s['metrics'][0]['achievement']);
        $other=User::factory()->create(['department'=>'client_relations']);
        $this->assertSame(0.0,app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22',$other->id)['coverage']);
        $this->assertSame(0.0,app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-09','2026-11-15')['coverage']);
    }

    public function test_missing_source_actuals_remain_in_the_target_denominator_and_unapproved_reports_do_not_earn_credit(): void
    {
        $owner=User::factory()->create();
        foreach ([['actual'=>50],[],['actual'=>999]] as $i=>$data) {
            PerformanceRegister::create(['department'=>'finance','kind'=>'revenue','reference'=>'FIN-'.$i,'title'=>'Revenue '.$i,
                'owner_id'=>$owner->id,'period_start'=>'2026-11-16','period_end'=>'2026-11-22','status'=>'approved',
                'data'=>$data+['target'=>100],'approved_at'=>$i===2?null:now()]);
        }
        $s=app(DepartmentSourcePerformance::class)->build('finance','2026-11-16','2026-11-22');
        $this->assertEquals(25,$s['metrics'][0]['achievement']);
        $this->assertSame(2,$s['metrics'][0]['expected']);
        $this->assertSame(1,$s['metrics'][0]['reported']);
        $public=app(SharedWeeklyPerformance::class)->build('finance','2026-11-16','2026-11-22');
        $this->assertArrayNotHasKey('actual',$public['finance']['source_performance']['metrics'][0]);
        $this->assertArrayNotHasKey('target',$public['finance']['source_performance']['metrics'][0]);
    }
}
