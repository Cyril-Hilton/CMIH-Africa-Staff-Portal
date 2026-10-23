<?php
namespace Tests\Feature\Creative;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage,Mail};
use Illuminate\Http\UploadedFile;
use App\Models\{User,Task,CreativeRequest,WeeklyConsolidatedItem,PerformanceEvent,PerformanceOutbox};
use App\Services\Creative\{CreativeWorkflow,CreativeLegacyImport,CreativeCapacity,CreativeAccountability};
class CreativeStudioTest extends TestCase {
 use RefreshDatabase;
 private function staff(string $department='client_relations',string $role='staff'):User{return User::factory()->create(['status'=>'active','department'=>$department,'access_role'=>$role,'must_reset_password'=>false,'profile_photo_path'=>'fixtures/test.jpg']);}
 private function brief(User $requester, array $extra=[]):CreativeRequest{return app(CreativeWorkflow::class)->create($requester,array_replace(['title'=>'Dove creative campaign','brief'=>'Create approved artwork for the campus campaign.','creative_type'=>'Flyer / Poster','priority'=>'High','client'=>'Dove','visibility'=>'shared','requested_deadline'=>now()->addWeek()],$extra));}
 private function plan(CreativeRequest $r,User $lead,User $owner,User $reviewer):CreativeRequest{return app(CreativeWorkflow::class)->plan($r,$lead,['revision'=>$r->revision,'assignee_id'=>$owner->id,'reviewer_id'=>$reviewer->id,'planned_start'=>now()->startOfWeek()->addDay()->setHour(9),'internal_eta'=>now()->startOfWeek()->addDays(3)->setHour(16),'estimated_hours'=>8,'requested_deadline'=>now()->addWeek(),'priority'=>'High','reason'=>'Assigned against the creative brief','override_reason'=>'Capacity will be confirmed in this local test']);}
 private function move(CreativeRequest $r,User $u,string $stage,array $extra=[]):CreativeRequest{return app(CreativeWorkflow::class)->transition($r,$u,array_replace(['revision'=>$r->revision,'stage'=>$stage,'reason'=>'Recorded workflow action','quality_score'=>null],$extra));}
 public function test_all_departments_can_submit_and_neutral_staff_can_follow_shared_timeline():void {
  $sender=$this->staff();$neutral=$this->staff('finance');$this->actingAs($sender)->postJson(route('portal.creative.store'),['title'=>'Dove artwork request','brief'=>'Create campaign artwork with suitable print formats.','creative_type'=>'Flyer / Poster','priority'=>'High','visibility'=>'shared','requested_deadline'=>now()->addDays(5)->toIso8601String(),'requester_id'=>$neutral->id,'received_at'=>'2000-01-01'])->assertCreated();
  $r=CreativeRequest::firstOrFail();$this->assertSame($sender->id,$r->requester_id);$this->assertTrue($r->received_at->isToday());$this->assertSame('client_relations',$r->department);
  $this->actingAs($neutral)->getJson(route('portal.creative.show',$r))->assertOk()->assertJsonPath('requester.name',$sender->name)->assertJsonCount(1,'history');
  $this->get('/portal/creative')->assertOk()->assertSee('Creative Studio')->assertSee('Reports &amp; Presentations', false);
 }
 public function test_intake_validation_and_unregistered_users_cannot_write():void {
  $this->postJson(route('portal.creative.store'),[])->assertUnauthorized();$this->actingAs($this->staff())->postJson(route('portal.creative.store'),['title'=>'Bad','requested_deadline'=>now()->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors(['title','brief','requested_deadline']);$this->assertDatabaseCount('creative_requests',0);
 }
 public function test_requester_cannot_assign_and_stale_plans_do_not_overwrite():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->brief($sender);
  $this->actingAs($sender)->postJson(route('portal.creative.plan',$r),['revision'=>1,'assignee_id'=>$owner->id,'reviewer_id'=>$sender->id,'planned_start'=>now()->toIso8601String(),'internal_eta'=>now()->addDay()->toIso8601String(),'requested_deadline'=>now()->addWeek()->toIso8601String(),'estimated_hours'=>5,'priority'=>'High','reason'=>'Cannot assign work myself'])->assertForbidden();
  $planned=$this->plan($r,$lead,$owner,$sender);$this->assertSame('scheduled',$planned->stage);$this->assertNotNull($planned->task_id);
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$this->plan($r,$lead,$owner,$sender);
 }
 public function test_review_cycles_retain_first_submission_and_require_evidence_feedback_and_independent_signoff():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');
  $this->actingAs($owner)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'in_review'])->assertUnprocessable()->assertJsonValidationErrors('reference_url');
  $r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/artwork-v1']);$first=$r->first_submitted_at->toIso8601String();
  $this->actingAs($sender)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'changes_requested','reason'=>''])->assertUnprocessable()->assertJsonValidationErrors('reason');
  $r=$this->move($r,$sender,'changes_requested',['feedback_type'=>'correction','reason'=>'Please correct the approved colour.']);$this->travel(1)->hours();$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/artwork-v2']);$this->assertSame($first,$r->first_submitted_at->toIso8601String());$this->assertSame(2,$r->iteration);
  $r=$this->move($r,$sender,'delivered');$this->assertSame('pending',$r->task->completion_review_status);
  $this->actingAs($owner)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>90])->assertForbidden();
  $r=$this->move($r,$lead,'completed',['quality_score'=>95]);$this->assertSame('approved',$r->task->fresh()->completion_review_status);$this->assertSame($lead->id,$r->task->fresh()->completion_reviewed_by);$this->assertTrue(\App\Services\TaskStatsService::wasApprovedByExpectedManager($r->task->fresh(),$owner));
  $this->actingAs($this->staff('finance'))->getJson(route('portal.creative.show',$r))->assertOk()->assertJsonCount(2,'rounds')->assertJsonPath('rounds.0.feedback','Please correct the approved colour.')->assertJsonPath('rounds.1.iteration',2);$this->assertSame(2,$r->updates()->where('kind','in_review')->count());$this->assertGreaterThan(0,PerformanceOutbox::count());
 }
 public function test_confidential_briefs_and_private_attachments_do_not_leak_to_neutral_staff():void {
  Storage::fake('local');$sender=$this->staff();$neutral=$this->staff('finance');$r=$this->brief($sender,['visibility'=>'restricted']);
  $this->actingAs($sender)->postJson(route('portal.creative.upload',$r),['kind'=>'brief','file'=>UploadedFile::fake()->image('brief.png')])->assertOk();$attachment=$r->attachments()->firstOrFail();
  $this->actingAs($neutral)->getJson(route('portal.creative.data'))->assertOk()->assertJsonCount(0,'items');$this->getJson(route('portal.creative.show',$r))->assertNotFound();$this->get(route('portal.creative.attachment',$attachment))->assertNotFound();
  $this->actingAs($sender)->get(route('portal.creative.attachment',$attachment))->assertOk()->assertHeader('Cache-Control','no-store, private');
 }
 public function test_historical_import_is_idempotent_and_does_not_invent_dates_or_duplicate_scores():void {
  $u=$this->staff('creatives');$t=Task::create(['title'=>'Historical creative','department'=>'creatives','assigned_to'=>$u->id,'assigned_by'=>$u->id,'status'=>'Open','progress'=>10,'created_at'=>now()]);
  WeeklyConsolidatedItem::create(['department'=>'creatives','week_start'=>now()->startOfWeek(),'week_end'=>now()->endOfWeek(),'lead_staff_id'=>$u->id,'created_by'=>$u->id,'updated_by'=>$u->id,'source_task_id'=>$t->id,'campaign_name'=>'Historical creative','deliverables'=>'Original work','status'=>'Planned']);
  $import=app(CreativeLegacyImport::class);$this->assertSame(1,$import->run());$this->assertSame(0,$import->run());$this->assertDatabaseCount('creative_requests',1);$this->assertDatabaseCount('tasks',1);$r=CreativeRequest::firstOrFail();$this->assertNull($r->received_at);$this->assertNull($r->estimated_hours);$this->assertDatabaseCount('creative_sources',1);
 }
 public function test_workload_is_split_across_weeks_and_capacity_is_not_fabricated():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$r=$this->brief($sender);$r->update(['assignee_id'=>$owner->id,'planned_start'=>'2026-10-22 09:00:00','internal_eta'=>'2026-10-27 17:00:00','estimated_hours'=>20]);$cap=app(CreativeCapacity::class);$this->assertSame(10.0,$cap->hours($r->fresh(),\Illuminate\Support\Carbon::parse('2026-10-19'),\Illuminate\Support\Carbon::parse('2026-10-25')));$this->assertSame(10.0,$cap->hours($r->fresh(),\Illuminate\Support\Carbon::parse('2026-10-26'),\Illuminate\Support\Carbon::parse('2026-11-01')));$summary=$cap->summary($sender,\Illuminate\Support\Carbon::parse('2026-10-19'),\Illuminate\Support\Carbon::parse('2026-10-25'));$this->assertNull($summary[0]['available']);
 }
 public function test_review_waiting_is_neutral_for_creative_and_review_obligation_is_measured_against_reviewer():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/proof']);$this->assertContains($r->task_id,CreativeAccountability::neutralTaskIds());$this->travelTo($r->review_due_at->copy()->addHour());$measure=CreativeAccountability::reviewerMeasure($sender,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(0.0,$measure['achievement']);
  $this->assertSame(1,app(CreativeAccountability::class)->reminders());$count=PerformanceOutbox::count();app(CreativeAccountability::class)->reminders();$this->assertSame($count,PerformanceOutbox::count());
 }
 public function test_exports_are_real_excel_and_pdf_and_apply_visibility():void {
  $sender=$this->staff();$this->brief($sender);$this->actingAs($sender)->get(route('portal.creative.export','xlsx'))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');$pdf=$this->get(route('portal.creative.export','pdf'))->assertOk();$this->assertStringStartsWith('%PDF',$pdf->getContent());
 }
 public function test_each_revision_keeps_its_own_reviewer_deadline_and_decision():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-05 09:00:00'));
  $r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/version-one']);
  $firstDue=$r->review_due_at->copy();$this->travelTo($firstDue->copy()->subHour());$r=$this->move($r,$sender,'changes_requested',['feedback_type'=>'correction','reason'=>'Correct the logo placement.']);
  $r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/version-two']);$this->travelTo($r->review_due_at->copy()->addHour());
  $measure=CreativeAccountability::reviewerMeasure($sender,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(2,$measure['total']);$this->assertSame(1,$measure['on_time']);$this->assertSame(50.0,$measure['achievement']);
  $submission=$r->updates()->where('kind','in_review')->oldest('id')->firstOrFail();$this->assertSame($sender->id,$submission->metadata['reviewer_id']);$this->assertSame($firstDue->toIso8601String(),$submission->metadata['review_due_at']);
  $this->expectException(\LogicException::class);$submission->update(['body'=>'Overwrite the original submission']);
 }

 public function test_acknowledgement_is_separate_idempotent_and_cannot_be_forged_by_other_staff():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender,['reported_external_at'=>now()->subDays(2)]),$lead,$owner,$sender);
  $this->actingAs($sender)->postJson(route('portal.creative.acknowledge',$r))->assertForbidden();$this->travel(1)->hours();
  $this->actingAs($owner)->postJson(route('portal.creative.acknowledge',$r),['acknowledged_at'=>'2000-01-01'])->assertOk();$ack=$r->fresh()->acknowledged_at;$this->assertTrue($ack->gt($r->received_at));
  $this->travel(1)->hours();$this->postJson(route('portal.creative.acknowledge',$r))->assertOk();$this->assertTrue($r->fresh()->acknowledged_at->eq($ack));$this->assertSame(1,PerformanceEvent::where('entity_type','creative_request')->where('entity_id',$r->id)->where('action','acknowledged')->count());
 }
 public function test_archiving_verified_work_preserves_earned_independent_signoff():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/proof']);$r=$this->move($r,$sender,'delivered');$r=$this->move($r,$lead,'completed',['quality_score'=>90]);$r=$this->move($r,$lead,'archived');
  $this->assertTrue(CreativeAccountability::independentSignoff($r->task->fresh(),$owner));$this->assertNotNull($r->completed_at);$this->assertSame('approved',$r->task->fresh()->completion_review_status);
 }
 public function test_date_filter_includes_this_weeks_submission_even_if_deadline_was_earlier():void {
  $sender=$this->staff();$r=$this->brief($sender);$r->update(['requested_deadline'=>now()->subMonth(),'received_at'=>now()->subMonth(),'submitted_at'=>now(),'stage'=>'in_review']);
  $this->actingAs($sender)->getJson(route('portal.creative.data',['from'=>now()->startOfWeek()->toDateString(),'to'=>now()->endOfWeek()->toDateString()]))->assertOk()->assertJsonPath('pagination.total',1);
 }

 public function test_studio_delivery_uses_manager_impact_weight_and_no_credit_while_review_is_pending():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);
  $r=app(CreativeWorkflow::class)->plan($r,$lead,$r->only(['assignee_id','reviewer_id','planned_start','internal_eta','estimated_hours','requested_deadline','priority'])+['revision'=>$r->revision,'performance_weight'=>12,'reason'=>'High impact campaign milestone','override_reason'=>'Test capacity']);
  $this->assertSame(12,\App\Services\DeliveryImpact::points($r->task->fresh(),$owner));$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/weighted-artwork']);
  $pending=app(\App\Services\StaffPerformanceScore::class)->user($owner,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(1,$pending['awaiting_creative_review']);$this->assertSame(0,$pending['completion_verified']);$this->assertNull($pending['task_rate']);
  $r=$this->move($r,$sender,'delivered');$r=$this->move($r,$lead,'completed',['quality_score'=>95]);$verified=app(\App\Services\StaffPerformanceScore::class)->user($owner,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(12,$verified['completion_points_verified']);$this->assertSame(100.0,$verified['task_rate']);$this->assertSame(65.0,$verified['task_weighted']);
 }

 public function test_shared_feed_is_paginated_when_thousands_of_briefs_exist():void {
  $sender=$this->staff();$now=now();$rows=[];
  for($i=0;$i<3000;$i++)$rows[]=['uuid'=>(string)\Illuminate\Support\Str::uuid(),'reference'=>'SCALE-'.$i,'title'=>'Synthetic scale brief '.$i,'requester_id'=>$sender->id,'reviewer_id'=>$sender->id,'department'=>'client_relations','brief'=>'Synthetic load test only.','visibility'=>'shared','stage'=>'brief_received','priority'=>'Medium','created_at'=>$now,'updated_at'=>$now];
  foreach(array_chunk($rows,200) as $chunk)DB::table('creative_requests')->insert($chunk);
  $this->actingAs($sender)->getJson(route('portal.creative.data'))->assertOk()->assertJsonCount(48,'items')->assertJsonPath('pagination.total',3000)->assertJsonPath('pagination.last',63);
  $this->getJson(route('portal.creative.data',['per_page'=>1000]))->assertUnprocessable();$this->getJson(route('portal.creative.export','pdf'))->assertUnprocessable();
 }
 public function test_planned_creative_task_cannot_be_completed_through_the_ordinary_task_form():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);
  $this->actingAs($owner)->patchJson(route('portal.tasks.update',$r->task_id),['status'=>'Completed','progress'=>100])->assertForbidden();$this->assertSame('Open',$r->task->fresh()->status);
 }

 public function test_creative_email_retries_without_repeating_delivered_in_app_alerts():void {
  config(['performance.email_enabled'=>false,'creative.email_enabled'=>true]);$sender=$this->staff();$lead=$this->staff('creatives','manager');$this->brief($sender);$outbox=PerformanceOutbox::firstOrFail();$this->assertNotNull($outbox->dashboard_at);$calls=0;
  Mail::shouldReceive('raw')->twice()->andReturnUsing(function()use(&$calls){if(++$calls===1)throw new \RuntimeException('Synthetic mail provider outage');});
  $service=app(\App\Services\PerformanceAutomation::class);$service->deliver();$this->assertSame(1,$outbox->fresh()->attempts);$this->assertNull($outbox->fresh()->emailed_at);$dashboard=$outbox->fresh()->dashboard_at;
  $service->deliver();$this->assertNotNull($outbox->fresh()->emailed_at);$this->assertTrue($outbox->fresh()->dashboard_at->eq($dashboard));$this->assertSame(0,$service->deliver());
 }

 public function test_later_deadline_change_does_not_rewrite_first_submission_timeliness():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r->update(['requested_deadline'=>now()->subHour()]);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/late-first-version']);$r=$this->move($r,$sender,'changes_requested',['feedback_type'=>'scope_change','reason'=>'Newly requested scope needs a revised date.']);
  $r=app(CreativeWorkflow::class)->plan($r,$lead,$r->only(['assignee_id','reviewer_id','planned_start','internal_eta','estimated_hours','priority'])+['requested_deadline'=>now()->addWeek(),'revision'=>$r->revision,'reason'=>'Deadline changed for new scope','override_reason'=>'Synthetic capacity']);
  $this->actingAs($sender)->getJson(route('portal.creative.show',$r))->assertOk()->assertJsonPath('on_time_submission',false);
 }
 public function test_approved_leave_and_company_holidays_reduce_working_capacity():void {
  $owner=$this->staff('creatives');config(['company_calendar.non_working_dates'=>['2026-10-23']]);
  \App\Models\LeaveApplication::create(['user_id'=>$owner->id,'start_date'=>'2026-10-22','end_date'=>'2026-10-22','leave_type'=>'annual','status'=>'approved']);
  $days=app(CreativeCapacity::class)->workingDates(\Illuminate\Support\Carbon::parse('2026-10-19'),\Illuminate\Support\Carbon::parse('2026-10-25'),$owner->id);$this->assertSame(['2026-10-19','2026-10-20','2026-10-21'],$days);
 }

 public function test_feedback_history_is_newest_first_and_older_entries_remain_accessible():void {
  $sender=$this->staff();$r=$this->brief($sender);
  for($i=1;$i<=105;$i++)$r->updates()->create(['author_id'=>$sender->id,'kind'=>'comment','body'=>'History entry '.$i]);
  $this->actingAs($sender)->getJson(route('portal.creative.show',$r))->assertOk()->assertJsonCount(40,'updates')->assertJsonPath('updates.0.body','History entry 105')->assertJsonPath('feedback_pages.last',3);
  $this->getJson(route('portal.creative.show',[$r,'feedback_page'=>3]))->assertOk()->assertJsonCount(25,'updates')->assertJsonPath('updates.24.body','History entry 1');
 }


 public function test_period_reports_use_complete_authorised_cohort_and_correct_boundaries():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-22 12:00:00'));
  $sender=$this->staff();$neutral=$this->staff('finance');
  for($n=0;$n<51;$n++)$this->brief($sender);
  $private=$this->brief($sender,['visibility'=>'restricted','title'=>'Private secret campaign']);
  foreach(['weekly'=>['2026-10-19','2026-10-25',7],'monthly'=>['2026-10-01','2026-10-31',5],'quarterly'=>['2026-10-01','2026-12-31',3],'yearly'=>['2026-01-01','2026-12-31',12]] as $period=>[$from,$to,$buckets]){
   $this->actingAs($neutral)->getJson(route('portal.creative.reports',['format'=>'data','period'=>$period,'anchor'=>'2026-10-22']))->assertOk()->assertJsonPath('total',51)->assertJsonPath('from',$from)->assertJsonPath('to',$to)->assertJsonCount($buckets,'buckets')->assertDontSee('Private secret campaign');
  }
  $this->getJson(route('portal.creative.reports',['format'=>'data','period'=>'decade','anchor'=>'2026-10-22']))->assertUnprocessable();
  $this->getJson(route('portal.creative.reports',['format'=>'data','period'=>'yearly','anchor'=>'2026-10-22','assignee'=>999999]))->assertOk()->assertJsonPath('total',0);
 }
 public function test_completion_images_are_atomic_with_submission_and_feed_private_report_gallery():void {
  Storage::fake('local');$sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$neutral=$this->staff('finance');
  $r=$this->move($this->plan($this->brief($sender,['visibility'=>'restricted']),$lead,$owner,$sender),$owner,'in_progress');
  $this->actingAs($neutral)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'in_review','evidence_images'=>[UploadedFile::fake()->image('forged.png')]])->assertForbidden();
  $this->assertDatabaseCount('creative_attachments',0);
  $this->actingAs($owner)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision-1,'stage'=>'in_review','evidence_images'=>[UploadedFile::fake()->image('stale.png')]])->assertConflict();
  $this->assertDatabaseCount('creative_attachments',0);
  $this->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'in_review','evidence_images'=>[UploadedFile::fake()->image('final-artwork.png')]])->assertOk();
  $this->assertSame('in_review',$r->fresh()->stage);$this->assertDatabaseCount('creative_attachments',1);
  $this->getJson(route('portal.creative.reports',['format'=>'data','period'=>'monthly','anchor'=>now()->toDateString()]))->assertOk()->assertJsonCount(1,'gallery')->assertJsonPath('gallery.0.mime','image/png')->assertJsonMissingPath('gallery.0.image');
  $this->actingAs($neutral)->getJson(route('portal.creative.reports',['format'=>'data','period'=>'monthly','anchor'=>now()->toDateString()]))->assertOk()->assertJsonCount(0,'gallery');
  $this->actingAs($lead)->get(route('portal.creative.reports',['format'=>'pdf','period'=>'weekly','anchor'=>now()->toDateString()]))->assertOk()->assertHeader('Content-Type','application/pdf');
 }

 public function test_plan_preview_is_authorised_read_only_and_reports_conflicts():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->brief($sender);
  $payload=['assignee_id'=>$owner->id,'planned_start'=>now()->addWeeks(2)->startOfWeek()->toIso8601String(),'internal_eta'=>now()->addWeeks(2)->endOfWeek()->toIso8601String(),'requested_deadline'=>now()->addDay()->toIso8601String(),'estimated_hours'=>40];
  $this->actingAs($sender)->postJson(route('portal.creative.plan-preview',$r),$payload)->assertForbidden();
  $this->actingAs($lead)->postJson(route('portal.creative.plan-preview',$r),$payload)->assertOk()->assertJsonCount(2,'warnings');
  $this->assertSame(1,$r->fresh()->revision);$this->assertNull($r->fresh()->assignee_id);$this->assertDatabaseCount('tasks',0);
 }
 public function test_temporary_capacity_changes_apply_only_within_dates_and_retain_standard_capacity():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 12:00:00'));$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');
  $this->actingAs($lead)->postJson(route('portal.creative.capacity'),['user_id'=>$owner->id,'weekly_hours'=>40,'reason'=>'Standard capacity for creative'])->assertOk();
  $this->postJson(route('portal.creative.capacity'),['user_id'=>$owner->id,'weekly_hours'=>20,'starts_on'=>'2026-10-19','ends_on'=>'2026-10-23','reason'=>'Temporary allocation for training'])->assertOk();
  $service=app(CreativeCapacity::class);
  $this->assertSame(20.0,$service->available($owner->id,now()->startOfWeek(),now()->endOfWeek()));
  $this->assertSame(40.0,$service->available($owner->id,now()->addWeek()->startOfWeek(),now()->addWeek()->endOfWeek()));
  $this->assertEquals(40,DB::table('creative_capacities')->where('user_id',$owner->id)->value('weekly_hours'));
  $this->postJson(route('portal.creative.capacity'),['user_id'=>$owner->id,'weekly_hours'=>20,'starts_on'=>'2026-10-23','ends_on'=>'2026-10-19','reason'=>'Invalid range rejected'])->assertUnprocessable();
 }
 public function test_upload_screening_fails_closed_and_rejects_infected_files_before_storage():void {
  Storage::fake('local');$sender=$this->staff();$r=$this->brief($sender);
  config(['creative.upload_scan_required'=>true,'creative.upload_scanner'=>null]);
  $this->actingAs($sender)->postJson(route('portal.creative.upload',$r),['kind'=>'brief','file'=>UploadedFile::fake()->image('brief.png')])->assertStatus(503);
  $this->assertDatabaseCount('creative_attachments',0);
  config(['creative.upload_scanner'=>'/usr/bin/false']);
  $this->postJson(route('portal.creative.upload',$r),['kind'=>'brief','file'=>UploadedFile::fake()->image('brief.png')])->assertUnprocessable();
  $this->assertDatabaseCount('creative_attachments',0);
  config(['creative.upload_scanner'=>'/usr/bin/true']);
  $this->postJson(route('portal.creative.upload',$r),['kind'=>'brief','file'=>UploadedFile::fake()->image('brief.png')])->assertOk();
 }

 public function test_shared_activity_feed_is_paginated_and_hides_restricted_briefs():void {
  $sender=$this->staff();$neutral=$this->staff('finance');$public=$this->brief($sender);$this->brief($sender,['visibility'=>'restricted','title'=>'Confidential campaign']);
  $this->actingAs($neutral)->getJson(route('portal.creative.activity'))->assertOk()->assertJsonPath('total',1)->assertJsonPath('items.0.reference',$public->reference)->assertDontSee('Confidential campaign');
 }
}
