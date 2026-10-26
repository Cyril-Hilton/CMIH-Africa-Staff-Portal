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
 private function plan(CreativeRequest $r,User $lead,User $owner,User $reviewer):CreativeRequest{return app(CreativeWorkflow::class)->plan($r,$lead,['revision'=>$r->revision,'assignee_id'=>$owner->id,'reviewer_id'=>($owner->id===$lead->id ? $this->staff('creatives','manager')->id : $lead->id),'planned_start'=>now()->startOfWeek()->addDay()->setHour(9),'internal_eta'=>now()->startOfWeek()->addDays(3)->setHour(16),'estimated_hours'=>8,'requested_deadline'=>now()->addWeek(),'priority'=>'High','reason'=>'Assigned against the creative brief','override_reason'=>'Capacity will be confirmed in this local test']);}
 private function move(CreativeRequest $r,User $u,string $stage,array $extra=[]):CreativeRequest{return app(CreativeWorkflow::class)->transition($r,$u,array_replace(['revision'=>$r->revision,'stage'=>$stage,'reason'=>'Recorded workflow action','quality_score'=>null,'client_approved'=>$stage==='completed'], $extra));}
 public function test_intake_merge_preserves_existing_dates_ownership_and_feedback_history():void {
  $sender=$this->staff();$r=$this->brief($sender);$r->update(['stage'=>'backlog']);
  $correction=$this->brief($sender);$correction->update(['stage'=>'changes_requested']);
  $correction->updates()->create(['author_id'=>$sender->id,'kind'=>'changes_requested','body'=>'Retain this correction history']);
  $received=$r->received_at->toISOString();
  $migration=require database_path('migrations/2026_10_23_090000_merge_creative_intake_stages.php');$migration->up();$migration->up();
  $this->assertSame('brief_received',$r->fresh()->stage);$this->assertSame($received,$r->fresh()->received_at->toISOString());$this->assertSame($sender->id,$r->fresh()->requester_id);
  $this->assertSame('in_progress',$correction->fresh()->stage);$this->assertSame('Retain this correction history',$correction->updates()->first()->body);
 }
 public function test_creative_who_also_sent_the_brief_cannot_bypass_the_post_review_manager_gate():void {
  $owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($owner),$lead,$owner,$lead);
  $r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/final-artwork']);
  $this->assertSame([],app(CreativeWorkflow::class)->allowed($r,$owner));
  $r=$this->move($r,$lead,'delivered');$this->assertSame([],app(CreativeWorkflow::class)->allowed($r,$owner));
 }
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
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'changes_requested','reason'=>''])->assertUnprocessable()->assertJsonValidationErrors('reason');
  $r=$this->move($r,$lead,'changes_requested',['feedback_type'=>'correction','reason'=>'Please correct the approved colour.']);$this->travel(1)->hours();$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/artwork-v2']);$this->assertSame($first,$r->first_submitted_at->toIso8601String());$this->assertSame(2,$r->iteration);
  $r=$this->move($r,$lead,'delivered');$this->assertSame('pending',$r->task->completion_review_status);
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
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/proof']);$this->assertContains($r->task_id,CreativeAccountability::neutralTaskIds());$this->travelTo($r->review_due_at->copy()->addHour());$measure=CreativeAccountability::reviewerMeasure($lead,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(0.0,$measure['achievement']);
  $this->assertSame(1,app(CreativeAccountability::class)->reminders());$count=PerformanceOutbox::count();app(CreativeAccountability::class)->reminders();$this->assertSame($count,PerformanceOutbox::count());
 }
 public function test_exports_are_real_excel_and_pdf_and_apply_visibility():void {
  $sender=$this->staff();$this->brief($sender);$this->actingAs($sender)->get(route('portal.creative.export','xlsx'))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');$pdf=$this->get(route('portal.creative.export','pdf'))->assertOk();$this->assertStringStartsWith('%PDF',$pdf->getContent());
 }
 public function test_each_revision_keeps_its_own_reviewer_deadline_and_decision():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-05 09:00:00'));
  $r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/version-one']);
  $firstDue=$r->review_due_at->copy();$this->travelTo($firstDue->copy()->subHour());$r=$this->move($r,$lead,'changes_requested',['feedback_type'=>'correction','reason'=>'Correct the logo placement.']);
  $r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/version-two']);$this->travelTo($r->review_due_at->copy()->addHour());
  $measure=CreativeAccountability::reviewerMeasure($lead,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(2,$measure['total']);$this->assertSame(1,$measure['on_time']);$this->assertSame(50.0,$measure['achievement']);
  $submission=$r->updates()->where('kind','in_review')->oldest('id')->firstOrFail();$this->assertSame($lead->id,$submission->metadata['reviewer_id']);$this->assertSame($firstDue->toIso8601String(),$submission->metadata['review_due_at']);
  $this->expectException(\LogicException::class);$submission->update(['body'=>'Overwrite the original submission']);
 }

 public function test_acknowledgement_is_separate_idempotent_and_cannot_be_forged_by_other_staff():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender,['reported_external_at'=>now()->subDays(2)]),$lead,$owner,$sender);
  $this->actingAs($sender)->postJson(route('portal.creative.acknowledge',$r))->assertForbidden();$this->travel(1)->hours();
  $this->actingAs($owner)->postJson(route('portal.creative.acknowledge',$r),['acknowledged_at'=>'2000-01-01'])->assertOk();$ack=$r->fresh()->acknowledged_at;$this->assertTrue($ack->gt($r->received_at));
  $this->travel(1)->hours();$this->postJson(route('portal.creative.acknowledge',$r))->assertOk();$this->assertTrue($r->fresh()->acknowledged_at->eq($ack));$this->assertSame(1,PerformanceEvent::where('entity_type','creative_request')->where('entity_id',$r->id)->where('action','acknowledged')->count());
 }
 public function test_archiving_verified_work_preserves_earned_independent_signoff():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/proof']);$r=$this->move($r,$lead,'delivered');$r=$this->move($r,$lead,'completed',['quality_score'=>90]);$r=$this->move($r,$lead,'archived');
  $this->assertTrue(CreativeAccountability::independentSignoff($r->task->fresh(),$owner));$this->assertNotNull($r->completed_at);$this->assertSame('approved',$r->task->fresh()->completion_review_status);
 }
 public function test_date_filter_includes_this_weeks_submission_even_if_deadline_was_earlier():void {
  $sender=$this->staff();$r=$this->brief($sender);$r->update(['requested_deadline'=>now()->subMonth(),'received_at'=>now()->subMonth(),'submitted_at'=>now(),'stage'=>'in_review']);
  $this->actingAs($sender)->getJson(route('portal.creative.data',['from'=>now()->startOfWeek()->toDateString(),'to'=>now()->endOfWeek()->toDateString()]))->assertOk()->assertJsonPath('pagination.total',1);
 }

 public function test_studio_delivery_uses_manager_impact_weight_and_no_credit_while_review_is_pending():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20 10:00:00', 'Africa/Accra'));
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);
  $r=app(CreativeWorkflow::class)->plan($r,$lead,$r->only(['assignee_id','reviewer_id','planned_start','internal_eta','estimated_hours','requested_deadline','priority'])+['revision'=>$r->revision,'performance_weight'=>12,'reason'=>'High impact campaign milestone','override_reason'=>'Test capacity']);
  $this->assertSame(12,\App\Services\DeliveryImpact::points($r->task->fresh(),$owner));$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/weighted-artwork']);
  $pending=app(\App\Services\StaffPerformanceScore::class)->user($owner,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(1,$pending['awaiting_creative_review']);$this->assertSame(0,$pending['completion_verified']);$this->assertNull($pending['task_rate']);
  $r=$this->move($r,$lead,'delivered');$r=$this->move($r,$lead,'completed',['quality_score'=>95]);$verified=app(\App\Services\StaffPerformanceScore::class)->user($owner,now()->startOfMonth(),now()->endOfMonth());$this->assertSame(12,$verified['completion_points_verified']);$this->assertSame(100.0,$verified['task_rate']);$this->assertSame(65.0,$verified['task_weighted']);
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
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$sender);$r->update(['requested_deadline'=>now()->subHour()]);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/late-first-version']);$r=$this->move($r,$lead,'changes_requested',['feedback_type'=>'scope_change','reason'=>'Newly requested scope needs a revised date.']);
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

 public function test_new_plan_counts_proposed_effort_even_before_scheduling():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20 10:00:00','Africa/Accra'));
  $owner=$this->staff('creatives');$r=$this->brief($this->staff());
  DB::table('creative_capacities')->insert(['user_id'=>$owner->id,'weekly_hours'=>8,'updated_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
  $r->fill(['assignee_id'=>$owner->id,'planned_start'=>now()->addWeek()->startOfWeek(),'internal_eta'=>now()->addWeek()->endOfWeek(),'estimated_hours'=>40]);
  $this->assertEquals(0,app(CreativeCapacity::class)->hours($r,$r->planned_start,$r->internal_eta,true));
  $this->assertStringContainsString('40h remaining against 8h available',implode(' ',app(CreativeCapacity::class)->warnings($r)));
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

 public function test_backlog_can_move_directly_to_brief_received_without_losing_history():void {
  $sender=$this->staff();$lead=$this->staff('creatives','manager');$neutral=$this->staff('finance');$r=$this->brief($sender);$r->update(['stage'=>'backlog']);$received=$r->received_at->toIso8601String();
  $this->actingAs($neutral)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'brief_received'])->assertForbidden();
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'brief_received','reason'=>'Moved on the Creative Kanban board.'])->assertOk();
  $this->assertSame('brief_received',$r->fresh()->stage);$this->assertSame($received,$r->fresh()->received_at->toIso8601String());$this->assertSame(2,$r->fresh()->revision);
  $this->assertDatabaseHas('creative_updates',['creative_request_id'=>$r->id,'kind'=>'brief_received']);
 }

 public function test_hod_can_split_a_client_brief_assign_self_and_team_without_duplicate_parent_scoring():void {
  $sender=$this->staff();$lead=$this->staff('creatives','manager');$owner=$this->staff('creatives');$verifier=$this->staff('creatives','staff');$verifier->forceFill(['job_level'=>'CVO'])->save();$parent=$this->brief($sender,['priority'=>'Urgent']);
  $payload=['revision'=>$parent->revision,'reason'=>'Divide creative work across the team','parts'=>[['title'=>'Design campaign artwork','brief'=>'Design the approved creative artwork and export the print formats.','priority'=>'Urgent'],['title'=>'Prepare motion animation','brief'=>'Produce an animation using the approved brand assets and copy.','priority'=>'High']]];
  $this->actingAs($sender)->postJson(route('portal.creative.split',$parent),$payload)->assertForbidden();
  $this->actingAs($lead)->postJson(route('portal.creative.split',$parent),$payload)->assertOk();
  $this->assertTrue($parent->fresh()->is_group);$this->assertNull($parent->fresh()->task_id);$this->assertSame(2,$parent->parts()->count());
  foreach($parent->parts()->get() as $n=>$part){
   $this->assertSame($sender->id,$part->requester_id);$this->assertTrue($part->received_at->equalTo($parent->received_at));$this->assertSame('brief_received',$part->stage);
   $assignee=$n===0?$lead:$owner;$part=$this->plan($part,$lead,$assignee,$sender);$part=$this->move($part,$assignee,'in_progress');$part=$this->move($part,$assignee,'in_review',['reference_url'=>'https://example.test/deliverable']);$part=$this->move($part,$verifier,'delivered');$this->move($part,$verifier,'completed',['quality_score'=>90]);
  }
  $this->assertSame('completed',$parent->fresh()->stage);$this->assertDatabaseCount('tasks',2);
  $this->actingAs($lead)->postJson(route('portal.creative.split',$parent),$payload)->assertStatus(409);
 }

 public function test_creative_can_pause_and_resume_production_without_losing_assignment_or_deadline():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20 10:00:00', 'Africa/Accra'));
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->move($this->plan($this->brief($sender),$lead,$owner,$sender),$owner,'in_progress');$due=$r->requested_deadline->toIso8601String();
  $r=$this->move($r,$owner,'brief_received');$this->assertSame('brief_received',$r->stage);$this->assertSame($owner->id,$r->assignee_id);$this->assertSame($due,$r->requested_deadline->toIso8601String());$this->assertSame('Open',$r->task->fresh()->status);
  $this->assertEquals(0,app(CreativeCapacity::class)->hours($r,now()->startOfWeek(),now()->endOfWeek(),true));
  $r=$this->move($r,$owner,'scheduled');$r=$this->move($r,$owner,'in_progress');$this->assertSame('in_progress',$r->stage);$this->assertSame('In Progress',$r->task->fresh()->status);
  $r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/current-artwork']);$r=$this->move($r,$lead,'brief_received');$this->assertNotNull($r->first_submitted_at);$this->assertSame('brief_received',$r->stage);
 }

 public function test_mega_creative_daily_entries_are_separate_from_weekly_studio_workflow():void {
  $owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$sender=$this->staff();
  Task::create(['title'=>'Daily clock-in design task','details'=>'Daily standalone task entry.','department'=>'creatives','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'status'=>'Open','priority'=>'High']);
  $brief=$this->plan($this->brief($sender,['title'=>'Studio campaign work package']),$lead,$owner,$sender);
  $response=$this->actingAs($owner)->get(route('dashboard',['tab'=>'creatives','weekly_department'=>'creatives']));$response->assertOk()->assertSee('Daily Creative Work')->assertSee('Daily clock-in design task');
  $html=$response->getContent();$start=strpos($html,'data-creative-daily-tasks');$end=strpos($html,'</section>',$start);$daily=substr($html,$start,$end-$start);
  $this->assertStringNotContainsString('data-cs-view',$daily);$this->assertStringNotContainsString('Studio campaign work package',$daily);
  $this->assertStringContainsString('Task Entry Date',$daily);
 }

 public function test_assigned_creative_can_move_backward_and_forward_between_all_operational_columns():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');
  foreach(['brief_received','scheduled','in_progress','in_review','delivered','completed'] as $source){
   foreach(['brief_received','scheduled','in_progress'] as $destination){
    if($source===$destination)continue;
    $r=$this->plan($this->brief($sender),$lead,$owner,$sender);
    if(in_array($source,['brief_received']))$r=$this->move($r,$owner,$source);
    if(in_array($source,['in_progress','in_review','delivered','completed']))$r=$this->move($r,$owner,'in_progress');
    if(in_array($source,['in_review','delivered','completed']))$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/approved-work']);
    if(in_array($source,['delivered','completed']))$r=$this->move($r,$lead,'delivered');
    if($source==='completed')$r=$this->move($r,$lead,'completed',['quality_score'=>95]);
    $r=$this->move($r,in_array($source,['in_review','delivered','completed'])?$lead:$owner,$destination,['feedback_type'=>'correction']);$this->assertSame($destination,$r->stage,$source.' -> '.$destination);$this->assertNull($r->fresh()->completed_at);$this->assertNotSame('approved',$r->task->fresh()->completion_review_status);
   }
  }
 }
 public function test_daily_search_filters_sort_and_pagination_preserve_the_selected_page():void {
  $owner=$this->staff('creatives');
  for($i=1;$i<=23;$i++)Task::create(['title'=>sprintf('Daily Filter %02d',$i),'department'=>'creatives','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'status'=>'Open','priority'=>'High']);
  Task::create(['title'=>'Daily Filter Wrong Priority','department'=>'creatives','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'status'=>'Open','priority'=>'Low']);
  $params=['tab'=>'creatives','search_mega_creatives'=>'Daily Filter','creative_status'=>'Open','creative_priority'=>'High','creative_assignee'=>$owner->id,'creative_sort'=>'deliverables','creative_dir'=>'asc','creative_per_page'=>10,'mega_creatives_page'=>2];
  $response=$this->actingAs($owner)->get(route('dashboard',$params))->assertOk();
  $page=$response->viewData('departmentTables')['creatives'];
  $this->assertSame(2,$page->currentPage());$this->assertSame(23,$page->total());$this->assertSame('Daily Filter 11',$page->first()->title);$this->assertSame('Daily Filter 20',$page->last()->title);
  $params['creative_per_page']=20;$params['mega_creatives_page']=1;
  $this->get(route('dashboard',$params))->assertOk()->assertViewHas('departmentTables',fn($tables)=>$tables['creatives']->count()===20);
 }
 public function test_weekly_creative_search_sort_and_page_size_work_together():void {
  $sender=$this->staff();
  for($i=1;$i<=15;$i++)$this->brief($sender,['title'=>sprintf('Paged Creative %02d',$i),'priority'=>'High']);
  $this->brief($sender,['title'=>'Paged Creative Wrong Priority','priority'=>'Low']);
  $this->actingAs($sender)->getJson(route('portal.creative.data',['q'=>'Paged Creative','priority'=>'High','sort'=>'title','per_page'=>12,'page'=>2]))->assertOk()->assertJsonPath('pagination.page',2)->assertJsonPath('pagination.total',15)->assertJsonPath('pagination.last',2)->assertJsonCount(3,'items')->assertJsonPath('items.0.title','Paged Creative 13');
 }

 public function test_creative_lead_controls_both_approvals_and_client_approval_is_explicit():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->brief($sender);
  $plan=['revision'=>$r->revision,'assignee_id'=>$owner->id,'reviewer_id'=>$sender->id,'planned_start'=>now()->toIso8601String(),'internal_eta'=>now()->addDay()->toIso8601String(),'requested_deadline'=>now()->addWeek()->toIso8601String(),'estimated_hours'=>5,'priority'=>'High','reason'=>'Assign production','override_reason'=>'Synthetic capacity'];
  $this->actingAs($lead)->postJson(route('portal.creative.plan',$r),$plan)->assertUnprocessable();
  $r=$this->plan($r,$lead,$owner,$lead);$r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/final-artwork']);
  foreach([$sender,$owner] as $actor)$this->actingAs($actor)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'delivered','reason'=>'Attempt approval'])->assertForbidden();
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>95,'client_approved'=>true,'reason'=>'Client approved artwork'])->assertForbidden();
  $r=$this->move($r,$lead,'delivered');
  foreach([$sender,$owner] as $actor)$this->actingAs($actor)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>95,'client_approved'=>true,'reason'=>'Attempt verification'])->assertForbidden();
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>95,'reason'=>'Client approval must be confirmed'])->assertUnprocessable()->assertJsonValidationErrors('client_approved');
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>95,'client_approved'=>true,'reason'=>''])->assertUnprocessable()->assertJsonValidationErrors('client_approved');
  $r=$this->move($r,$lead,'completed',['quality_score'=>95,'reason'=>'Client approved the final artwork by email.','client_approved'=>true]);
  $this->assertSame($lead->id,$r->verified_by);$this->assertSame('approved',$r->task->fresh()->completion_review_status);
  $this->assertTrue($r->updates()->where('kind','completed')->latest('id')->first()->metadata['client_approved']);
 }
 public function test_requester_corrections_after_lead_approval_return_to_production_with_audit():void {
  $sender=$this->staff();$owner=$this->staff('creatives');$lead=$this->staff('creatives','manager');$r=$this->plan($this->brief($sender),$lead,$owner,$lead);
  $r=$this->move($r,$owner,'in_progress');$r=$this->move($r,$owner,'in_review',['reference_url'=>'https://example.test/draft']);$r=$this->move($r,$lead,'delivered');
  $r=$this->move($r,$sender,'changes_requested',['feedback_type'=>'correction','reason'=>'Client requests a revised logo size.']);
  $this->assertSame('in_progress',$r->stage);$this->assertNull($r->completed_at);$this->assertDatabaseHas('creative_updates',['creative_request_id'=>$r->id,'kind'=>'changes_requested','body'=>'Client requests a revised logo size.']);
 }
 public function test_creative_staff_can_submit_brief_but_cannot_self_assign():void {
  $owner=$this->staff('creatives');$r=$this->brief($owner);$this->assertSame('brief_received',$r->stage);$this->assertNull($r->assignee_id);
  $this->actingAs($owner)->postJson(route('portal.creative.plan',$r),['revision'=>$r->revision,'assignee_id'=>$owner->id,'reviewer_id'=>$owner->id,'planned_start'=>now()->toIso8601String(),'internal_eta'=>now()->addDay()->toIso8601String(),'requested_deadline'=>now()->addWeek()->toIso8601String(),'estimated_hours'=>5,'priority'=>'High','reason'=>'Attempt self assignment'])->assertForbidden();
  $this->actingAs($owner)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'scheduled'])->assertForbidden();
 }

 public function test_unassigned_intake_cannot_skip_planning_and_self_assigned_lead_cannot_approve_own_work():void {
  $sender=$this->staff();$lead=$this->staff('creatives','manager');$verifier=$this->staff('creatives','manager');$r=$this->brief($sender);
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'scheduled','reason'=>'Skip assignment'])->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
  $r=$this->plan($r,$lead,$lead,$verifier);$r=$this->move($r,$lead,'in_progress');$r=$this->move($r,$lead,'in_review',['reference_url'=>'https://example.test/lead-artwork']);
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'delivered','reason'=>'Self approval attempt'])->assertForbidden();
  $r=$this->move($r,$verifier,'delivered');
  $this->actingAs($lead)->postJson(route('portal.creative.transition',$r),['revision'=>$r->revision,'stage'=>'completed','quality_score'=>100,'client_approved'=>true,'reason'=>'Self final approval attempt'])->assertForbidden();
  $this->assertSame('pending',$r->task->fresh()->completion_review_status);
 }

}
