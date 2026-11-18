<?php

namespace Tests\Feature;

use App\Models\{Attendance, CrmPipelineProject, PerformanceRegister, User};
use App\Services\{ClientRelationsScorecard, DepartmentSourcePerformance, PerformanceHubReport, PerformanceMath, StaffPerformanceScore};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KpiAccuracyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_people_uses_actual_against_plan_even_when_the_report_exists(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $owner=User::factory()->create(['status'=>'active','department'=>'brands_marketing']);
        $this->record($owner,'people',['cohort'=>'internal','scope'=>'internal','planned_count'=>10,'active_count'=>9]);
        $source=app(DepartmentSourcePerformance::class)->build('brands_marketing','2026-11-16','2026-11-22');
        $metric=collect($source['metrics'])->firstWhere('name','Active staff coverage');
        $this->assertSame(90.0,$metric['achievement']);
        $this->assertSame(90.0,$source['areas'][0]['achievement_credit']);
        $this->assertSame(25.0,$source['coverage']);
        $this->assertSame(22.5,$source['achievement_credit']);
        $score=app(StaffPerformanceScore::class)->user($owner,Carbon::parse('2026-11-16'),Carbon::parse('2026-11-22'));
        $this->assertSame(22.5,$score['kpi_rate']);
        $this->assertFalse($score['complete']);
        $this->assertNotNull($score['credited_score']);
        $this->assertSame(0,app(StaffPerformanceScore::class)->user(User::factory()->create(['department'=>'brands_marketing']),Carbon::parse('2026-11-16'),Carbon::parse('2026-11-22'))['kpi_measured']);
    }

    public function test_people_snapshots_are_not_added_across_weeks_and_pending_entries_are_excluded(): void
    {
        $owner=User::factory()->create(['department'=>'brands_marketing']);
        $this->record($owner,'people',['cohort'=>'internal','scope'=>'internal','planned_count'=>10,'active_count'=>10],'2026-11-09');
        $this->record($owner,'people',['cohort'=>'internal','scope'=>'internal','planned_count'=>10,'active_count'=>9]);
        $this->record($owner,'people',['cohort'=>'internal','scope'=>'internal','planned_count'=>1,'active_count'=>1],approved:false);
        $source=app(DepartmentSourcePerformance::class)->build('brands_marketing','2026-11-01','2026-11-30');
        $metric=collect($source['metrics'])->firstWhere('name','Active staff coverage');
        $this->assertEquals(10,$metric['target']);
        $this->assertEquals(9,$metric['actual']);
        $this->assertSame(90.0,$metric['achievement']);
    }

    public function test_late_and_missing_clock_ins_reduce_real_punctuality_separately_from_reporting(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $owner=User::factory()->create(['status'=>'active','department'=>'brands_marketing','start_date'=>'2026-11-16']);
        foreach(['2026-11-16 08:30:00','2026-11-17 09:30:00'] as $at) Attendance::create(['user_id'=>$owner->id,'clock_in_at'=>$at,'status'=>'On Time','daily_objective'=>'Work']);
        $source=app(DepartmentSourcePerformance::class)->build('brands_marketing','2026-11-16','2026-11-22',$owner->id);
        $this->assertSame(3,$source['attendance']['expected']);
        $this->assertSame(2,$source['attendance']['clocked']);
        $this->assertSame(1,$source['attendance']['late']);
        $this->assertSame(1,$source['attendance']['missing']);
        $this->assertEqualsWithDelta(33.33,$source['attendance']['punctuality_credit'],0.01);
    }

    public function test_revenue_scorecard_retains_missing_results_in_denominator_without_fabricating_actuals(): void
    {
        $owner=User::factory()->create();
        $rows=collect();
        foreach([50,null] as $actual) {
            $p=new CrmPipelineProject(['client_name'=>'Customer','project_name'=>'Project','owner_id'=>$owner->id,'stage'=>'won','target_amount'=>100,'actual_amount'=>$actual]);
            $p->revenue_state='confirmed';$p->revenue_submitted_by=$owner->id;$p->revenue_finance_by=$owner->id+1;$p->revenue_confirmed_at=now();$rows->push($p);
        }
        $score=app(ClientRelationsScorecard::class)->build($rows,collect());
        $this->assertSame(25.0,$score['achievement']);
        $this->assertSame(2,$score['target_expected']);
        $this->assertSame(1,$score['target_coverage']);
        $empty=app(ClientRelationsScorecard::class)->build($rows->map(fn($p)=>tap(clone $p,fn($r)=>$r->actual_amount=null)),collect());
        $this->assertSame(0.0,$empty['achievement']);
        $this->assertNull($empty['actual']);
    }

    public function test_missing_weight_is_not_redistributed_and_zero_is_a_valid_lower_target(): void
    {
        $this->assertSame(25.0,PerformanceMath::creditedWeightedScore([['weight'=>1,'achievement'=>100],['weight'=>3,'achievement'=>null]]));
        $this->assertSame(100.0,PerformanceMath::achievement(0,0,'lower'));
        $this->assertSame(0.0,PerformanceMath::achievement(0,1,'lower'));
        $this->assertNull(PerformanceMath::achievement(0,null,'lower'));
        $this->assertNull(PerformanceMath::achievement(0,1,'higher'));
        $this->assertEquals(5,app(PerformanceHubReport::class)->aggregate(collect([(object)['actual'=>4],(object)['actual'=>6]]),'mean'));
    }

    public function test_unreported_cost_is_not_misrepresented_as_a_favourable_underspend(): void
    {
        $owner=User::factory()->create(['department'=>'finance']);
        $this->record($owner,'expense',['budget'=>100,'actual'=>50]);
        $this->record($owner,'expense',['budget'=>100]);
        $source=app(DepartmentSourcePerformance::class)->build('finance','2026-11-16','2026-11-22');
        $metric=collect($source['metrics'])->firstWhere('name','Operating expense budget compliance');
        $this->assertSame(50.0,$metric['achievement']);$this->assertSame(1,$metric['reported']);$this->assertSame(2,$metric['expected']);
    }

    public function test_multiweek_project_reporting_matches_the_dashboard_date_range(): void
    {
        $owner=User::factory()->create(['department'=>'client_relations']);
        CrmPipelineProject::create(['client_name'=>'Range client','project_name'=>'Cross-week project','owner_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,'stage'=>'project_ongoing','week_start'=>'2026-11-12','week_end'=>'2026-11-19','source_period_confirmed'=>true]);
        $source=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-16','2026-11-22');
        $this->assertTrue($source['areas'][0]['reported']);
        $later=app(DepartmentSourcePerformance::class)->build('client_relations','2026-11-23','2026-11-29');
        $this->assertFalse($later['areas'][0]['reported']);
    }

    public function test_expected_staff_who_have_not_reported_still_appear_with_zero_credited_points(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $staff=User::factory()->create(['name'=>'Unreported expected staff','department'=>'finance','status'=>'active','access_role'=>'staff','start_date'=>'2026-11-01']);
        $standings=app(\App\Http\Controllers\Portal\PerformanceAwardController::class)->standingsPayload('2026-11')['calculated'];
        $row=collect($standings['measured_employees'])->firstWhere('label',$staff->name);
        $this->assertNotNull($row);$this->assertSame(0.0,$row['credited_score']);$this->assertFalse($row['score_complete']);
        $department=collect($standings['measured_departments'])->firstWhere('label','Finance');
        $this->assertNotNull($department);$this->assertSame(0.0,$department['credited_score']);
    }

    private function record(User $owner,string $kind,array $data,string $week='2026-11-16',bool $approved=true): PerformanceRegister
    {
        return PerformanceRegister::create(['department'=>$owner->department,'kind'=>$kind,'reference'=>uniqid('AUDIT-'),'title'=>'Audit entry','owner_id'=>$owner->id,'period_start'=>$week,'period_end'=>Carbon::parse($week)->endOfWeek(),'status'=>'submitted','data'=>$data,'approved_at'=>$approved?now():null]);
    }
}
