<?php

namespace Tests\Feature;

use App\Models\PerformanceEvent;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceWorkflow;
use App\Services\StaffPerformanceScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffScoreGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_requires_real_manager_signoff_and_cannot_be_self_awarded(): void
    {
        $manager = User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
        $owner = User::factory()->create(['department'=>'creatives','status'=>'active','line_manager_id'=>$manager->id]);
        $peer = User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active']);
        $task = Task::create(['title'=>'Daily artwork','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'department'=>'creatives','status'=>'Done','priority'=>'Medium','completion_review_status'=>'approved','completion_reviewed_by'=>$owner->id,'completion_reviewed_at'=>now(),'created_at'=>'2026-10-05 10:00:00']);
        $score = fn()=>app(StaffPerformanceScore::class)->user($owner,Carbon::parse('2026-10-01'),Carbon::parse('2026-10-31'));
        $this->assertSame(0,$score()['completion_verified']);
        $task->update(['completion_reviewed_by'=>$peer->id,'copied_manager_ids'=>[$peer->id]]);
        $this->assertSame(0,$score()['completion_verified']);
        $task->update(['completion_reviewed_by'=>$manager->id]);
        $this->assertSame(1,$score()['completion_verified']);
        $this->assertSame(65.0,$score()['components']['completion']['points']);
        $task->update(['completion_review_status'=>'pending']);
        $this->assertSame(0,$score()['completion_verified']);
    }

    public function test_verified_label_without_manager_audit_does_not_earn_points_and_reopening_removes_credit(): void
    {
        $manager=User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
        $owner=User::factory()->create(['department'=>'creatives','status'=>'active','line_manager_id'=>$manager->id]);
        $item=WeeklyConsolidatedItem::create(['department'=>'creatives','workflow_state'=>'verified','week_start'=>'2026-10-05','week_end'=>'2026-10-09','campaign_name'=>'Daily deliverable','deliverables'=>'Approved artwork','lead_staff_id'=>$owner->id,'reviewer_id'=>$manager->id,'status'=>'Done','verified_at'=>now(),'created_by'=>$owner->id,'updated_by'=>$owner->id]);
        $score=fn()=>app(StaffPerformanceScore::class)->user($owner,Carbon::parse('2026-10-01'),Carbon::parse('2026-10-31'));
        $this->assertSame(0,$score()['completion_verified']);
        PerformanceWorkflow::record('commitment',$item->id,$owner->id,'verify',[],$item->getAttributes(),'Self approval must not count');
        $this->assertSame(0,$score()['completion_verified']);
        PerformanceWorkflow::record('commitment',$item->id,$manager->id,'verify',[],$item->getAttributes(),'Manager reviewed evidence');
        $this->assertSame(1,$score()['completion_verified']);
        $item->update(['workflow_state'=>'assigned','verified_at'=>null]);
        $this->assertSame(0,$score()['completion_verified']);
    }

    public function test_a_peer_reviewer_cannot_perform_final_verification(): void
    {
        $owner=User::factory()->create(['department'=>'creatives','status'=>'active']);
        $peer=User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active']);
        $item=WeeklyConsolidatedItem::create(['department'=>'creatives','workflow_state'=>'completed','week_start'=>'2026-10-05','week_end'=>'2026-10-09','campaign_name'=>'Output','deliverables'=>'Artwork','lead_staff_id'=>$owner->id,'reviewer_id'=>$peer->id,'status'=>'Done','created_by'=>$owner->id,'updated_by'=>$owner->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PerformanceWorkflow::class)->transition($item,$peer,'verify',(int)$item->revision,'Peer trying final signoff',90);
    }

    public function test_weights_and_formula_are_consistent_everywhere(): void
    {
        $this->assertSame(config('performance.score_weights'),StaffPerformanceScore::WEIGHTS);
        $result=app(StaffPerformanceScore::class)->combine(100,0,100);
        $this->assertSame(35.0,$result['score']);
        $this->assertSame('10-65-25-v3-impact',$result['formula_version']);
    }
    public function test_dashboard_exposes_a_total_card_without_inventing_a_complete_score(): void
    {
        $owner=User::factory()->create(['department'=>'creatives','status'=>'active']);
        $this->actingAs($owner)->get('/dashboard')->assertOk()
            ->assertSee('System Attendance')->assertSee('Verified Task Completion')
            ->assertSee('KPI Achievement')->assertSee('Total Score / 100%')
            ->assertSee('Incomplete')->assertSee('10% system attendance')
            ->assertDontSee('30% punctuality');
    }

    public function test_foreign_department_kpis_cannot_be_used_to_inflate_a_personal_score(): void
    {
        $owner=User::factory()->create(['department'=>'creatives','status'=>'active']);
        $reviewer=User::factory()->create(['department'=>'finance','status'=>'active','access_role'=>'manager']);
        $objective=\App\Models\PerformanceObjective::create(['department'=>'finance','title'=>'Finance objective','outcome'=>'Collections','owner_id'=>$reviewer->id,'starts_on'=>'2026-01-01','ends_on'=>'2026-12-31']);
        $kpi=\App\Models\PerformanceKpi::create(['objective_id'=>$objective->id,'department'=>'finance','name'=>'Foreign KPI','definition'=>'Collections','formula'=>'actual / target','unit'=>'GHS','direction'=>'higher','aggregation'=>'sum','frequency'=>'monthly','source'=>'Approved source','owner_id'=>$owner->id,'reviewer_id'=>$reviewer->id,'green_threshold'=>100,'amber_threshold'=>80,'escalation_days'=>2,'required_action'=>'Review','dimension'=>'financial','weight'=>100,'evidence_required'=>false]);
        \App\Models\PerformanceObservation::create(['kpi_id'=>$kpi->id,'owner_id'=>$owner->id,'period_start'=>'2026-10-01','period_end'=>'2026-10-31','target'=>100,'actual'=>100,'approved_at'=>now(),'approved_by'=>$reviewer->id,'source_reference'=>'Reviewed source','scope_key'=>'foreign-test']);
        $result=app(StaffPerformanceScore::class)->user($owner,Carbon::parse('2026-10-01'),Carbon::parse('2026-10-31'));
        $this->assertSame(0,$result['kpi_expected']);
        $this->assertNull($result['kpi_rate']);
        $this->assertNull($result['score']);
    }
    public function test_employee_and_department_of_month_standings_use_the_new_formula(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $manager=User::factory()->create(['department'=>'creatives','access_role'=>'super_admin','name'=>'Test Review Executive','status'=>'active']);
        $owner=User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active','line_manager_id'=>$manager->id,'start_date'=>'2026-10-01']);
        for($date=Carbon::parse('2026-10-01');$date->lte(today());$date->addDay()){
            if($date->isWeekday())\App\Models\Attendance::create(['user_id'=>$owner->id,'clock_in_at'=>$date->copy()->setTime(8,0),'daily_objective'=>'Synthetic test work','status'=>'On Time']);
        }
        foreach([true,false] as $approved)Task::create(['title'=>'Synthetic output '.($approved?'approved':'pending'),'assigned_to'=>$owner->id,'assigned_by'=>$manager->id,'department'=>'creatives','status'=>$approved?'Done':'In Progress','priority'=>'Medium','completion_review_status'=>$approved?'approved':'pending','completion_reviewed_by'=>$approved?$manager->id:null,'completion_reviewed_at'=>$approved?now():null,'created_at'=>'2026-10-05 10:00:00']);
        $objective=\App\Models\PerformanceObjective::create(['department'=>'creatives','title'=>'Creative outcomes','outcome'=>'Reviewed artworks','owner_id'=>$manager->id,'starts_on'=>'2026-01-01','ends_on'=>'2026-12-31']);
        $kpi=\App\Models\PerformanceKpi::create(['objective_id'=>$objective->id,'department'=>'creatives','name'=>'Artwork delivery','definition'=>'Reviewed final outputs','formula'=>'actual / target','unit'=>'jobs','direction'=>'higher','aggregation'=>'sum','frequency'=>'monthly','source'=>'Approved source','owner_id'=>$owner->id,'reviewer_id'=>$manager->id,'green_threshold'=>100,'amber_threshold'=>80,'escalation_days'=>2,'required_action'=>'Review','dimension'=>'target_achievement','weight'=>1,'evidence_required'=>false]);
        \App\Models\PerformanceObservation::create(['kpi_id'=>$kpi->id,'owner_id'=>$owner->id,'period_start'=>'2026-10-01','period_end'=>'2026-10-31','target'=>10,'actual'=>8,'approved_at'=>now(),'approved_by'=>$manager->id,'source_reference'=>'Reviewed source','scope_key'=>'award-formula-test']);
        $response=$this->actingAs($manager)->getJson('/portal/awards/standings?period=2026-10')->assertOk();
        $employee=collect($response->json('calculated.employees'))->firstWhere('user_id',$owner->id);
        $department=collect($response->json('calculated.departments'))->firstWhere('key','creatives');
        // 100% attendance × 10 + 50% verified tasks × 65 + 80% KPI × 25.
        $this->assertEquals(62.5,$employee['score']);
        $this->assertEquals(62.5,$department['score']);
        $this->assertSame('10-65-25-v3-impact',$employee['formula_version']);
        $this->assertSame(65,$employee['score_components']['completion']['weight']);
        $this->assertSame(25,$department['score_components']['kpi']['weight']);
        $this->assertTrue($employee['score_complete']);
        $this->travelBack();
    }
}
