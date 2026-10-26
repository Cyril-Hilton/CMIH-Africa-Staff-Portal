<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,WeeklyConsolidatedItem};
use App\Services\WeeklyReportingHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
class WeeklyReportingHistoryTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->travelTo(\Carbon\Carbon::parse('2026-10-26 10:00:00','Africa/Accra'));}
 private function source(User $owner):WeeklyConsolidatedItem{return WeeklyConsolidatedItem::create(['department'=>'brands_marketing','week_start'=>'2026-10-19','week_end'=>'2026-10-25','campaign_name'=>'Ongoing Activation','deliverables'=>'Original weekly results','achieved_breakdown'=>'92%','status'=>'In Progress','progress_percent'=>60,'created_by'=>$owner->id,'updated_by'=>$owner->id,'project_starts_on'=>'2026-10-19','project_ends_on'=>'2026-11-15']);}
 public function test_old_period_cannot_be_repurposed_and_source_content_survives():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$row=$this->source($u);
  $this->actingAs($u)->patch(route('portal.dashboard.weekly-consolidated.update',$row),['department'=>'brands_marketing','week_start'=>'2026-10-26','week_end'=>'2026-11-01','deliverables'=>'Replacement','status'=>'Planned','correction_reason'=>'Reuse for next week'])->assertSessionHasErrors('week_start');
  $this->assertSame('Original weekly results',$row->fresh()->deliverables);
 }
 public function test_carry_forward_is_idempotent_and_does_not_copy_results_or_approvals():void {
  $u=User::factory()->create();$row=$this->source($u);$service=app(WeeklyReportingHistory::class);
  $draft=$service->carry($row,'2026-10-26');$again=$service->carry($row,'2026-10-26');
  $this->assertSame($draft->id,$again->id);$this->assertDatabaseCount('weekly_consolidated_items',2);
  $this->assertSame('draft',$draft->report_status);$this->assertNull($draft->achieved_breakdown);$this->assertSame('',$draft->deliverables);$this->assertNull($draft->report_reviewed_at);$this->assertSame(60,$draft->previous_week_reference['progress_percent']);
  $this->assertSame('92%',$row->fresh()->achieved_breakdown);
 }
 public function test_past_corrections_require_reason_and_keep_previous_version():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$row=$this->source($u);$url=route('portal.dashboard.weekly-consolidated.update',$row);
  $this->actingAs($u)->patch($url,['status'=>'Done'])->assertSessionHasErrors('correction_reason');
  $this->patch($url,['status'=>'Done','correction_reason'=>'Client confirmed completion'])->assertSessionHasNoErrors();
  $this->assertDatabaseHas('weekly_report_versions',['weekly_item_id'=>$row->id,'reason'=>'Client confirmed completion']);$this->assertSame('Done',$row->fresh()->status);
 }
 public function test_weekly_submission_does_not_complete_project_and_self_review_is_blocked():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$other=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$row=$this->source($u);
  $url=route('portal.dashboard.weekly-consolidated.report-state',$row);
  $this->actingAs($u)->post($url,['report_action'=>'submit','reason'=>'Work continues next week'])->assertSessionHasNoErrors();$this->assertSame('In Progress',$row->fresh()->status);
  $this->post($url,['report_action'=>'review','reason'=>'Reviewed weekly account'])->assertForbidden();
  $this->actingAs($other)->post($url,['report_action'=>'review','reason'=>'Reviewed weekly account'])->assertSessionHasNoErrors();$this->assertSame('reviewed',$row->fresh()->report_status);
 }
 public function test_roll_forward_and_monthly_summary_distinguish_entries_from_projects():void {
  $u=User::factory()->create();$this->source($u);$service=app(WeeklyReportingHistory::class);$this->assertSame(1,$service->rollForward());$this->assertSame(0,$service->rollForward());
  $summary=$service->summary('brands_marketing','2026-10-01','2026-10-31');$this->assertSame(2,$summary['entries']);$this->assertSame(1,$summary['projects']);$this->assertSame(1,$summary['reports']['draft']);
 }
 public function test_historical_reports_cannot_be_deleted_and_empty_drafts_cannot_be_submitted():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$row=$this->source($u);
  $this->actingAs($u)->delete(route('portal.dashboard.weekly-consolidated.destroy',$row))->assertStatus(422);
  $draft=app(WeeklyReportingHistory::class)->carry($row,'2026-10-26');
  $this->post(route('portal.dashboard.weekly-consolidated.report-state',$draft),['report_action'=>'submit','reason'=>'Nothing entered yet'])->assertStatus(422);
  $this->assertDatabaseHas('weekly_consolidated_items',['id'=>$row->id,'deliverables'=>'Original weekly results']);
 }
 public function test_missing_weeks_are_reported_without_fabricating_historical_updates():void {
  $u=User::factory()->create();$row=$this->source($u);$row->update(['report_series'=>(string)\Illuminate\Support\Str::uuid(),'project_starts_on'=>'2026-10-05']);
  $summary=app(WeeklyReportingHistory::class)->summary('brands_marketing','2026-10-01','2026-10-31');
  $this->assertSame(3,$summary['missing']);$this->assertDatabaseCount('weekly_consolidated_items',1);
 }
 public function test_the_actual_submitter_cannot_review_another_editors_report():void {
  $author=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$submitter=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$row=$this->source($author);$url=route('portal.dashboard.weekly-consolidated.report-state',$row);
  $this->actingAs($submitter)->post($url,['report_action'=>'submit','reason'=>'Submitted for this week'])->assertSessionHasNoErrors();
  $this->post($url,['report_action'=>'review','reason'=>'Trying to self review'])->assertForbidden();
 }
 public function test_a_boundary_week_is_not_counted_in_two_months():void {
  $u=User::factory()->create();$row=$this->source($u);$service=app(WeeklyReportingHistory::class);$service->carry($row,'2026-10-26');
  $this->assertSame(2,$service->summary('brands_marketing','2026-10-01','2026-10-31')['entries']);
  $this->assertSame(0,$service->summary('brands_marketing','2026-11-01','2026-11-30')['entries']);
  $this->assertSame(2,$service->summary('brands_marketing','2026-01-01','2026-12-31')['entries']);
 }
 public function test_monthly_work_status_uses_the_latest_week_without_erasing_previous_pending_work():void {
  $u=User::factory()->create();$source=$this->source($u);$service=app(WeeklyReportingHistory::class);$draft=$service->carry($source,'2026-10-26');
  $this->assertSame('In Progress',$draft->status);$draft->update(['status'=>'Done']);
  $summary=$service->summary('brands_marketing','2026-10-01','2026-10-31');
  $this->assertSame(1,$summary['work']['Done']);$this->assertArrayNotHasKey('In Progress',$summary['work']);$this->assertSame(1,$summary['weekly_work']['In Progress']);$this->assertSame('In Progress',$source->fresh()->status);
 }
 public function test_dashboard_counters_use_latest_linked_project_state():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$source=$this->source($u);$draft=app(WeeklyReportingHistory::class)->carry($source,'2026-10-26');$draft->update(['status'=>'Done']);
  $this->actingAs($u)->get(route('dashboard',['weekly_department'=>'brands_marketing','weekly_from'=>'2026-10-01','weekly_to'=>'2026-10-31']))->assertOk()->assertViewHas('weeklyConsolidatedMetrics',fn($stats)=>$stats['completed']===1&&$stats['pending']===0&&$stats['planned']===0);
 }
 public function test_every_department_can_navigate_to_a_previous_explicit_week():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  foreach(array_keys(config('performance.departments')) as $department){
   WeeklyConsolidatedItem::create(['department'=>$department,'week_start'=>'2026-10-19','week_end'=>'2026-10-25','campaign_name'=>'Preserved weekly work','deliverables'=>'Original report','status'=>'In Progress','created_by'=>$u->id]);
   $this->actingAs($u)->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>'2026-10-19','weekly_to'=>'2026-10-25']))
    ->assertOk()->assertSee('Department reporting week navigation')->assertSee('Previous Week')->assertSee('weekly_from=2026-10-12',false)->assertSee('weekly_to=2026-10-18',false);
  }
 }
}
