<?php
namespace Tests\Feature;
use App\Models\{Attendance,Task,User,WeeklyConsolidatedItem};
use App\Services\{RankingAttention,RankingAttentionActions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RankingAttentionNavigationTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 12:00:00'));$this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);}
 private function staff($department='creatives'): User {return User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>$department,'start_date'=>'2026-11-23']);}
 private function actions(User $viewer,User $person): array {$row=app(RankingAttention::class)->forPerson($person,today()->startOfMonth(),today()->endOfMonth());return app(RankingAttentionActions::class)->forPerson($viewer,$row,today()->startOfMonth(),today()->endOfMonth());}
 public function test_unverified_work_warning_opens_the_actual_editable_task_and_undated_work_opens_its_deadline_field(): void {
  $staff=$this->staff();$task=Task::create(['title'=>'Unverified actual work','details'=>'Original task details','assigned_to'=>$staff->id,'assigned_by'=>$staff->id,'department'=>'creatives','status'=>'In Progress','priority'=>'medium','due_on'=>'2026-11-23']);
  $links=$this->actions($staff,$staff);$this->assertSame(route('portal.tasks.edit',$task),$links['unverified_delivery'][0]['url']);
  $this->actingAs($staff)->get($links['unverified_delivery'][0]['url'])->assertOk()->assertSee('Original task details');
  $task->update(['due_on'=>null]);$links=$this->actions($staff,$staff);$this->assertSame(route('portal.tasks.edit',$task),$links['missing_deadlines'][0]['url']);
  $other=$this->staff();$links=$this->actions($other,$staff);$this->assertEmpty($links['unverified_delivery']??[]);$this->actingAs($other)->get(route('portal.tasks.edit',$task))->assertForbidden();
 }
 public function test_a_report_warning_filters_to_that_record_and_keeps_owner_and_dates_without_exposing_it_to_another_user(): void {
  $staff=$this->staff('operations_projects');$item=WeeklyConsolidatedItem::create(['department'=>'operations_projects','campaign_name'=>'Exact pending report','lead_staff_id'=>$staff->id,'created_by'=>$staff->id,'week_start'=>'2026-11-23','week_end'=>'2026-11-29','workflow_state'=>'submitted','status'=>'In Progress','deliverables'=>'Actual recorded work']);
  $links=$this->actions($staff,$staff);$url=$links['awaiting_verification'][0]['url'];parse_str(parse_url($url,PHP_URL_QUERY),$query);$this->assertSame((string)$item->id,$query['item']);$this->assertSame((string)$staff->id,$query['owner']);$this->assertSame('2026-11-01',$query['from']);
  $this->actingAs($staff)->get($url)->assertOk()->assertSee('Exact pending report')->assertSee('id="commitment-'.$item->id.'"',false);
  $this->actingAs($this->staff('operations_projects'))->get($url)->assertOk()->assertDontSee('Exact pending report');
 }
 public function test_attendance_navigation_is_scoped_to_the_signed_in_person_even_with_another_name_in_the_query(): void {
  $staff=$this->staff();$other=$this->staff();Attendance::create(['user_id'=>$staff->id,'clock_in_at'=>'2026-11-23 09:30:00','daily_objective'=>'Own attendance','status'=>'Late']);Attendance::create(['user_id'=>$other->id,'clock_in_at'=>'2026-11-23 08:00:00','daily_objective'=>'Private other attendance','status'=>'On Time']);
  $response=$this->actingAs($staff)->get(route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>'2026-11-23']));$response->assertOk()->assertSee($staff->name)->assertDontSee($other->name)->assertSee('Historical corrections require HR review')->assertSee('My Attendance')->assertDontSee('Back to HR Reporting');
  $this->get(route('portal.hr-reporting.attendance',['q'=>$other->name]))->assertOk()->assertViewHas('summary',fn($rows)=>$rows->isEmpty())->assertViewHas('logs',fn($logs)=>$logs->isEmpty());
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00'));$this->get(route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>'2026-11-26']))->assertOk()->assertViewHas('summary',fn($rows)=>$rows->first()['expected']===4);
 }
 public function test_brand_gaps_link_to_the_matching_reporting_tab(): void {
  $staff=$this->staff('brands_marketing');$links=$this->actions($staff,$staff);$tabs=[];foreach($links['kpi_evidence']??[] as $link){parse_str(parse_url($link['url'],PHP_URL_QUERY),$q);$tabs[]=$q['brands_tab']??null;$this->assertSame((string)$staff->id,$q['brands_owner']);}
  $this->assertSame(['people','perfect_store','activation','workstream'],$tabs);
 }
 public function test_completed_automatic_task_has_delivery_credit_without_manager_review_but_normal_tasks_still_require_it(): void {
  $staff=$this->staff();$staff->update(['name'=>'Cyril Hilton']);
  \App\Services\AutoClockService::handleForUser($staff,today());
  $task=Task::where('assigned_to',$staff->id)->where('title','CMIH Portal Maintainance and Feature Upgrade')->firstOrFail();
  $this->assertSame(100,$task->progress);$this->assertNull($task->completion_review_status);
  $row=app(RankingAttention::class)->forPerson($staff,today()->startOfMonth(),today()->endOfMonth());
  $this->assertSame(1,$row['score']['completion_verified']);$this->assertSame(100.0,$row['score']['task_rate']);
  $this->assertEmpty($row['score']['unverified_task_ids']);$this->assertNotContains('unverified_delivery',array_column($row['issues'],'code'));
  $this->assertNotContains('missing_clockins',array_column($row['issues'],'code'));$this->assertNotContains('recorded_lateness',array_column($row['issues'],'code'));
  $manual=Task::create(['title'=>'Ordinary completed work','assigned_to'=>$staff->id,'assigned_by'=>$staff->id,'department'=>'creatives','status'=>'Completed','progress'=>100,'priority'=>'High','due_on'=>today()]);
  $row=app(RankingAttention::class)->forPerson($staff,today()->startOfMonth(),today()->endOfMonth());
  $this->assertSame([$manual->id],$row['score']['unverified_task_ids']);$this->assertSame(1,$row['score']['completion_verified']);
  $task->update(['status'=>'In Progress','progress'=>90]);$this->assertFalse(\App\Services\AutoClockService::isCompletedAutomaticTask($task->fresh(),$staff));
  $other=$this->staff();$task->update(['assigned_to'=>$other->id,'assigned_by'=>$other->id,'status'=>'Completed','progress'=>100]);
  $this->assertFalse(\App\Services\AutoClockService::isCompletedAutomaticTask($task->fresh(),$other));
 }

}
