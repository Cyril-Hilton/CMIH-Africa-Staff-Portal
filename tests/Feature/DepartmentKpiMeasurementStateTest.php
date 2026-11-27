<?php
namespace Tests\Feature;

use App\Models\{PerformanceRegister, User};
use App\Services\{DepartmentSourcePerformance, SharedWeeklyPerformance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DepartmentKpiMeasurementStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_department_cards_distinguish_missing_measurement_from_a_measured_zero(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 12:00:00'));
        foreach (array_keys(config('performance.departments')) as $department) {
            $source=app(DepartmentSourcePerformance::class)->build($department,'2026-11-23','2026-11-29');
            $html=view('portal.partials.source-performance',['sourcePerformance'=>$source])->render();
            $doc=new \DOMDocument(); @$doc->loadHTML($html); $xp=new \DOMXPath($doc);
            foreach ($source['areas'] as $area) {
                $this->assertSame('no_records',$area['measurement_state'],$department.': '.$area['name']);
            }
            $this->assertSame(0,$xp->query('//article[@data-kpi-area]//*[@role="meter"]')->length,$department);
            $this->assertStringContainsString('Not measured',$html);
            $this->assertFalse($source['has_measured_result']);
        }
    }

    public function test_known_target_without_actual_is_unmeasured_but_actual_zero_is_a_real_zero(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 12:00:00'));
        foreach ([['finance','revenue','target','actual'],['brands_marketing','people','planned_count','active_count'],['operations_projects','activation','target_quantity','actual_quantity']] as [$dept,$kind,$target,$actual]) {
            $owner=User::factory()->create(['department'=>$dept]);
            $row=$this->record($owner,$kind,[$target=>10]);
            $source=app(DepartmentSourcePerformance::class)->build($dept,'2026-11-23','2026-11-29');
            $area=collect($source['areas'])->firstWhere('records',1);
            $this->assertSame('not_measured',$area['measurement_state']);
            $this->assertSame(0.0,$area['achievement_credit'],'Missing actuals still receive zero credit, without being called measured outcomes.');
            $this->assertSame(0,$area['measured']);
            $row->update(['data'=>[$target=>10,$actual=>0]]);
            $source=app(DepartmentSourcePerformance::class)->build($dept,'2026-11-23','2026-11-29');
            $area=collect($source['areas'])->firstWhere('records',1);
            $this->assertSame('measured',$area['measurement_state']);
            $this->assertSame(0.0,$area['achievement_credit']);
            $this->assertSame(1,$area['measured']);
            $shared=app(SharedWeeklyPerformance::class)->build($dept,'2026-11-23','2026-11-29')[$dept]['source_performance'];
            $this->assertTrue($shared['metrics'][0]['has_measured_result']);
            $this->assertArrayNotHasKey('actual',$shared['metrics'][0]);
            $this->assertStringContainsString('data-measurement-state="measured"',view('portal.partials.source-performance',['sourcePerformance'=>$shared])->render());
        }
    }

    public function test_operations_weekly_updates_are_evidence_without_fabricated_achievement_and_retired_tables_do_not_score(): void
    {
        $owner=User::factory()->create(['department'=>'operations_projects']);
        for($i=0;$i<9;$i++) $this->record($owner,'weekly_update',[]);
        foreach(['project','supplier','production','milestone','project_report','risk_issue','risk','client_approval'] as $kind) $this->record($owner,$kind,['ends_on'=>'2026-11-24','due_on'=>'2026-11-24','project_status'=>'completed','budget'=>10,'actual_cost'=>0]);
        $source=app(DepartmentSourcePerformance::class)->build('operations_projects','2026-11-23','2026-11-29');
        $this->assertCount(3,$source['areas']);
        $this->assertSame(9,$source['areas'][0]['records']);
        $this->assertSame('not_measured',$source['areas'][0]['measurement_state']);
        $this->assertEquals(0,$source['achievement_credit']);
        $this->assertCount(1,$source['metrics']);
    }

    public function test_operations_current_activation_and_fabrication_outcomes_use_only_due_records(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 12:00:00'));
        $owner=User::factory()->create(['department'=>'operations_projects']);
        foreach([['ends_on'=>'2026-11-24','activation_status'=>'Completed'],['ends_on'=>'2026-11-25','activation_status'=>'Ongoing'],['ends_on'=>'2026-11-29','activation_status'=>'Upcoming'],['ends_on'=>'2026-11-24','activation_status'=>'Cancelled'],[]] as $data) $this->record($owner,'activation',$data);
        foreach([['due_on'=>'2026-11-24','delivered_on'=>'2026-11-24'],['due_on'=>'2026-11-25','delivered_on'=>'2026-11-26'],['due_on'=>'2026-11-29'],[]] as $data) $this->record($owner,'fabrication',$data);
        $source=app(DepartmentSourcePerformance::class)->build('operations_projects','2026-11-23','2026-11-29');
        foreach(['Due activation completion','On-time fabrication delivery'] as $name) {
            $metric=collect($source['metrics'])->firstWhere('name',$name);
            $this->assertSame(2,$metric['expected']);
            $this->assertSame(50.0,$metric['achievement']);
            $this->assertTrue($metric['has_measured_result']);
        }
    }

    public function test_finance_separate_targets_match_direct_revenue_and_monthly_totals_replace_underlying_entries(): void
    {
        $owner=User::factory()->create(['department'=>'finance']);
        $this->record($owner,'revenue_target',['target'=>200]);
        $this->record($owner,'revenue',['revenue_type'=>'direct','actual'=>150]);
        $source=app(DepartmentSourcePerformance::class)->build('finance','2026-11-23','2026-11-29');
        $metric=collect($source['metrics'])->firstWhere('name','Revenue target achievement');
        $this->assertSame(75.0,$metric['achievement']);
        $this->assertTrue($metric['has_measured_result']);
        $this->record($owner,'revenue',['revenue_type'=>'monthly_total','target'=>999,'actual'=>100]);
        $source=app(DepartmentSourcePerformance::class)->build('finance','2026-11-23','2026-11-29');
        $metric=collect($source['metrics'])->firstWhere('name','Revenue target achievement');
        $this->assertEquals(200,$metric['target']);
        $this->assertEquals(100,$metric['actual']);
        $this->assertSame(50.0,$metric['achievement']);
    }

    private function record(User $owner,string $kind,array $data): PerformanceRegister
    {
        return PerformanceRegister::create(['department'=>$owner->department,'kind'=>$kind,'reference'=>uniqid('AUDIT-'),'title'=>'Audit source','owner_id'=>$owner->id,'period_start'=>'2026-11-23','period_end'=>'2026-11-29','status'=>'approved','approved_at'=>now(),'data'=>$data]);
    }
}
