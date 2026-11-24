<?php
namespace Tests\Feature;

use App\Models\{User,Attendance,Task,CreativeRequest,PerformanceObjective,PerformanceKpi};
use App\Services\{RankingAttention,RankingAttentionActions,AutoClockService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignedWorkAttentionTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 12:00:00'));$this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);}
 private function staff(string $dept='creatives'):User{$u=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>$dept,'start_date'=>today()]);Attendance::create(['user_id'=>$u->id,'clock_in_at'=>today()->setTime(8,0),'daily_objective'=>'Assigned work','status'=>'On Time']);return $u;}
 private function row(User $u):array{foreach(array_keys(request()->attributes->all()) as $key)if(str_starts_with($key,'department_source_performance.'))request()->attributes->remove($key);return app(RankingAttention::class)->forPerson($u,today()->startOfMonth(),today()->endOfMonth());}
 private function brief(User $u,array $extra=[]):CreativeRequest{return CreativeRequest::create($extra+['uuid'=>(string)\Illuminate\Support\Str::uuid(),'reference'=>'TEST-'.fake()->uuid(),'title'=>'Assigned artwork','requester_id'=>$u->id,'assignee_id'=>$u->id,'brief'=>'Artwork','creative_type'=>'Flyer / Poster','received_at'=>now(),'planned_start'=>now(),'internal_eta'=>now()->addDays(3),'requested_deadline'=>now()->addDays(3),'stage'=>'scheduled']);}
 public function test_cyril_without_assigned_briefs_has_no_false_kpi_flags_or_free_kpi_credit():void{
  $u=$this->staff();$u->update(['name'=>'Cyril Hilton']);AutoClockService::handleForUser($u,today());$other=$this->staff();$this->brief($other);
  $row=$this->row($u);$this->assertSame([],$row['issues']);$this->assertSame(0,$row['score']['kpi_expected']);$this->assertNull($row['score']['kpi_rate']);$this->assertSame(100.0,$row['score']['task_rate']);$this->assertFalse($row['score']['complete']);
  $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertDontSee('Your ranking attention');
 }
 public function test_empty_department_categories_do_not_assign_personal_reporting_obligations():void{
  foreach(array_keys(config('performance.departments')) as $dept){$row=$this->row($this->staff($dept));$this->assertSame([],$row['issues'],$dept);if($dept!=='hr_admin')$this->assertSame(0,$row['score']['kpi_expected'],$dept);}
 }
 public function test_future_assigned_work_is_not_flagged_but_overdue_work_remains_actionable():void{
  foreach(array_keys(config('performance.departments')) as $dept){$person=$this->staff($dept);Task::create(['title'=>'Upcoming assigned work','assigned_to'=>$person->id,'assigned_by'=>$person->id,'department'=>$dept,'status'=>'In Progress','due_on'=>today()->addDays(3)]);$this->assertSame([],$this->row($person)['issues'],$dept);}
  $u=$this->staff();$brief=$this->brief($u);$task=Task::create(['title'=>'Future assigned task','assigned_to'=>$u->id,'assigned_by'=>$u->id,'department'=>'creatives','status'=>'In Progress','due_on'=>today()->addDays(3)]);
  $row=$this->row($u);$this->assertSame([],$row['issues']);$links=app(RankingAttentionActions::class)->forPerson($u,$row,today()->startOfMonth(),today()->endOfMonth());$this->assertEmpty($links['unverified_delivery']??[]);
  $brief->update(['internal_eta'=>now()->subHour()]);$task->update(['due_on'=>today()->subDay()]);$row=$this->row($u);$codes=array_column($row['issues'],'code');$this->assertContains('kpi_evidence',$codes);$this->assertContains('unverified_delivery',$codes);$this->assertContains('Delivery & review',$row['score']['kpi_gaps']);$this->assertNotContains('Quality & revisions',$row['score']['kpi_gaps']);
 }
 public function test_missing_manager_quality_grade_is_not_a_staff_reporting_failure():void{
  $u=$this->staff();$this->brief($u,['stage'=>'completed','completed_at'=>now(),'first_submitted_at'=>now(),'submitted_at'=>now(),'quality_score'=>null]);
  $this->assertNotContains('Quality & revisions',$this->row($u)['score']['kpi_gaps']);
 }
 public function test_assigned_monthly_kpi_is_not_missing_before_its_reporting_deadline():void{
  $u=$this->staff();$objective=PerformanceObjective::create(['department'=>'creatives','title'=>'Assigned objective','outcome'=>'Artwork','owner_id'=>$u->id,'starts_on'=>'2026-11-01','ends_on'=>'2026-11-30']);
  PerformanceKpi::create(['objective_id'=>$objective->id,'department'=>'creatives','name'=>'Assigned monthly quality result','definition'=>'Quality','formula'=>'actual / target','unit'=>'score','direction'=>'higher','aggregation'=>'sum','frequency'=>'monthly','source'=>'Review','reviewer_id'=>User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active'])->id,'owner_id'=>$u->id,'weight'=>100,'green_threshold'=>100,'amber_threshold'=>80,'escalation_days'=>2,'required_action'=>'Review result','dimension'=>'target_achievement','evidence_required'=>false]);
  $this->assertSame([],$this->row($u)['score']['kpi_gaps']);$this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 09:00'));
  $row=app(RankingAttention::class)->forPerson($u,\Illuminate\Support\Carbon::parse('2026-11-01'),\Illuminate\Support\Carbon::parse('2026-11-30'));$this->assertContains('Assigned monthly quality result',$row['score']['kpi_gaps']);
 }
 public function test_each_department_workspace_precedes_its_kpi_cards():void{
  $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin','department'=>'executive']);
  foreach(['brands_marketing'=>'data-brands-reports','client_relations'=>'data-crm-centre','operations_projects'=>'data-operations-reporting','creatives'=>'data-creative-studio','finance'=>'fd-grid','hr_admin'=>'aria-label="HR and Admin live registers"'] as $dept=>$marker){
   $response=$this->actingAs($admin)->get(route('dashboard',['weekly_department'=>$dept]));$response->assertOk();$html=$response->getContent();$panel=strpos($html,'id="performance-panel-'.$dept.'"');$this->assertNotFalse($panel);$html=substr($html,$panel);$workspace=strpos($html,$marker);$cards=strpos($html,'data-source-department="'.$dept.'"');$this->assertNotFalse($workspace,$dept);$this->assertNotFalse($cards,$dept);$this->assertTrue($workspace<$cards,$dept.' KPI cards must follow the workspace');
  }
 }
 public function test_pending_manager_approval_does_not_flag_the_submitter_in_any_department():void{
  foreach(array_keys(config('performance.departments')) as $dept){
   $u=$this->staff($dept);$manager=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>$dept]);$u->update(['line_manager_id'=>$manager->id]);
   Task::create(['title'=>'Submitted responsibility','assigned_to'=>$u->id,'assigned_by'=>$manager->id,'department'=>$dept,'status'=>'Completed','progress'=>100,'due_on'=>today(),'completion_review_status'=>'pending','completion_review_requested_at'=>now()]);
   $this->assertSame([],$this->row($u)['issues'],$dept);
  }
 }

}
