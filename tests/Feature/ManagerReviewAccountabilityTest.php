<?php
namespace Tests\Feature;
use App\Models\{User,Task,PerformanceEvent,Attendance,LeaveApplication};
use App\Services\{ReviewAccountability,PerformanceWorkflow,RankingAttention,StaffPerformanceScore};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManagerReviewAccountabilityTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->travelTo(Carbon::parse('2026-11-23 12:00:00'));$this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);}
 private function person(string $dept,string $role='staff'):User{return User::factory()->create(['status'=>'active','department'=>$dept,'access_role'=>$role,'job_level'=>$role==='manager'?'manager':'staff','start_date'=>'2026-11-23']);}
 public function test_on_time_submission_is_credited_and_manager_delay_is_scored_in_every_department():void {
  foreach(array_keys(config('performance.departments')) as $dept){
   $manager=$this->person($dept,'manager');if($dept==='finance')config(['finance_reporting.manager_user_id'=>$manager->id]);
   $staff=$this->person($dept);$staff->update(['line_manager_id'=>$manager->id]);
   $task=Task::create(['title'=>'Review responsibility '.$dept,'department'=>$dept,'assigned_to'=>$staff->id,'assigned_by'=>$manager->id,'status'=>'Awaiting Approval','progress'=>95,'priority'=>'High','due_on'=>today(),'completion_review_status'=>'pending','completion_review_requested_at'=>now()]);
   PerformanceWorkflow::record('task',$task->id,$staff->id,'submit_completion',[],$task->getAttributes(),'Submitted with evidence.');
   $event=PerformanceEvent::where('entity_type','task')->where('entity_id',$task->id)->firstOrFail();$meta=$event->after['_review_accountability'];$this->assertSame($manager->id,$meta['reviewer_id']);$this->assertSame('2026-11-24 23:59:59',$meta['due_at']);
   $score=app(StaffPerformanceScore::class)->user($staff,today()->startOfMonth(),today()->endOfMonth());$this->assertSame(100.0,$score['task_rate']);$this->assertSame(0,$score['completion_verified']);$this->assertSame(1,$score['completion_submitted']);
   $this->travelTo(Carbon::parse('2026-11-25 09:00:00'));request()->attributes->remove('manager_review_accountability');
   $measure=app(ReviewAccountability::class)->measure($manager,today()->startOfMonth(),today()->endOfMonth());$this->assertSame(0.0,$measure['achievement']);
   $flags=app(RankingAttention::class)->forPerson($manager,today()->startOfMonth(),today()->endOfMonth());$this->assertContains('manager_review_overdue',array_column($flags['issues'],'code'));
   PerformanceWorkflow::record('task',$task->id,$manager->id,'return',[],$task->getAttributes(),'Provide correction.');
   $this->assertSame(0.0,app(ReviewAccountability::class)->measure($manager,today()->startOfMonth(),today()->endOfMonth())['achievement']);
   $this->travelTo(Carbon::parse('2026-11-23 12:00:00'));request()->attributes->remove('manager_review_accountability');
  }
 }
 public function test_deadline_skips_weekends_holidays_and_approved_reviewer_leave():void {
  $manager=$this->person('creatives','manager');config(['company_calendar.non_working_dates'=>['2026-11-30']]);
  LeaveApplication::create(['user_id'=>$manager->id,'leave_type'=>'annual','start_date'=>'2026-12-01','end_date'=>'2026-12-01','status'=>'approved','reason'=>'Approved absence']);
  $this->assertSame('2026-12-02 23:59:59',ReviewAccountability::deadline(Carbon::parse('2026-11-27 13:00:00'),$manager)->toDateTimeString());
 }
 public function test_late_submission_is_not_granted_on_time_provisional_delivery_credit():void {
  $manager=$this->person('operations_projects','manager');$staff=$this->person('operations_projects');$staff->update(['line_manager_id'=>$manager->id]);
  $task=Task::create(['title'=>'Late submission','department'=>'operations_projects','assigned_to'=>$staff->id,'assigned_by'=>$manager->id,'status'=>'Awaiting Approval','progress'=>95,'due_on'=>'2026-11-23','completion_review_status'=>'pending','completion_review_requested_at'=>'2026-11-24 10:00:00']);
  $this->travelTo(Carbon::parse('2026-11-24 12:00:00'));$score=app(StaffPerformanceScore::class)->user($staff,today()->startOfMonth(),today()->endOfMonth());$this->assertSame(0.0,$score['task_rate']);$this->assertSame(0.0,collect($score['kpis'])->firstWhere('id','staff-submission-timeliness')['achievement']);
 }
 public function test_review_queue_is_private_and_creative_gap_links_show_an_actionable_empty_state():void {
  $staff=$this->person('creatives');$manager=$this->person('creatives','manager');$staff->update(['line_manager_id'=>$manager->id]);$task=Task::create(['title'=>'Private review item','department'=>'creatives','assigned_to'=>$staff->id,'assigned_by'=>$manager->id,'status'=>'Awaiting Approval','due_on'=>today(),'completion_review_status'=>'pending','completion_review_requested_at'=>now()]);
  PerformanceWorkflow::record('task',$task->id,$staff->id,'submit_completion',[],$task->getAttributes(),'Review now.');
  $this->actingAs($manager)->get(route('portal.performance.index',['view'=>'reviews']))->assertOk()->assertSee('Private review item')->assertSee('24 Nov 2026');
  $other=$this->person('creatives');$this->actingAs($other)->get(route('portal.performance.index',['view'=>'reviews']))->assertOk()->assertDontSee('Private review item');
  $this->actingAs($staff)->get(route('portal.performance.index',['department'=>'creatives','view'=>'kpis','source_gap'=>'Quality & revisions']))->assertOk()->assertSee('There are no active Creative briefs assigned')->assertSee('Open Targets & Responsibilities',false);
 }
 public function test_finance_entry_review_is_separate_from_staff_reporting_and_pending_money_is_not_published():void {
  $manager=$this->person('finance','manager');config(['finance_reporting.manager_user_id'=>$manager->id]);$staff=$this->person('finance');$staff->update(['line_manager_id'=>$manager->id]);
  $payload=['kind'=>'revenue','reference'=>'DIRECT-TEST','title'=>'Own Finance revenue','owner_id'=>$staff->id,'period_start'=>'2026-11-23','period_end'=>'2026-11-23','status'=>'completed','data'=>['revenue_type'=>'direct','actual'=>700,'target'=>900,'earned_on'=>'2026-11-23','source'=>'Direct income'],'reason'=>'Received additional revenue outside CRM.','outside_crm'=>'1'];
  $this->actingAs($staff)->post(route('portal.finance.reporting.save'),$payload)->assertRedirect();
  $row=\App\Models\PerformanceRegister::where('reference','DIRECT-TEST')->firstOrFail();$this->assertNull($row->approved_at);$this->assertFalse(\App\Services\FinanceDashboard::published($row));
  $review=app(ReviewAccountability::class)->rows()->firstWhere('submitter_id',$staff->id);$this->assertSame($manager->id,$review['reviewer_id']);$this->assertSame('2026-11-24 23:59:59',$review['due_at']->toDateTimeString());
  $source=app(\App\Services\DepartmentSourcePerformance::class)->build('finance','2026-11-23','2026-11-29',$staff->id);$area=collect($source['areas'])->firstWhere('name','Revenue');$this->assertTrue($area['awaiting_review']);$this->assertSame('Awaiting manager review',$area['state']);
  $this->actingAs($manager)->post(route('portal.performance.approveRegister',$row),['reason'=>'Reviewed against evidence.'])->assertRedirect();
  $this->assertTrue(\App\Services\FinanceDashboard::published($row->fresh()));$this->assertTrue(app(ReviewAccountability::class)->rows()->firstWhere('submitter_id',$staff->id)['timely']);
 }

 public function test_return_and_resubmission_preserve_the_original_staff_submission_time():void {
  $manager=$this->person('creatives','manager');$staff=$this->person('creatives');$staff->update(['line_manager_id'=>$manager->id]);
  $task=Task::create(['title'=>'Returned after timely submission','department'=>'creatives','assigned_to'=>$staff->id,'assigned_by'=>$manager->id,'status'=>'Awaiting Approval','progress'=>95,'due_on'=>today(),'completion_review_status'=>'pending','completion_review_requested_at'=>now()]);
  PerformanceWorkflow::record('task',$task->id,$staff->id,'submit_completion',[],$task->getAttributes(),'First on-time submission.');
  $this->travelTo(Carbon::parse('2026-11-25 09:00:00'));PerformanceWorkflow::record('task',$task->id,$manager->id,'return',[],$task->getAttributes(),'Correction requested after the review deadline.');
  $task->update(['completion_review_requested_at'=>now()]);PerformanceWorkflow::record('task',$task->id,$staff->id,'submit_completion',[],$task->getAttributes(),'Corrected version submitted.');
  $this->assertTrue(ReviewAccountability::timelyTaskSubmission($task->fresh()));$this->assertSame(0.0,app(ReviewAccountability::class)->measure($manager,today()->startOfMonth(),today()->endOfMonth())['achievement']);
 }

}
