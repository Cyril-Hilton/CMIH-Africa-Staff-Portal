<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,CrmPipelineProject,CrmClientRecord,CrmReportSubmission,Task};
use App\Services\ClientRelationsCentre;
class ClientRelationsSubmissionsTest extends TestCase {
 use RefreshDatabase;
 private function staff($role='staff',$dept='client_relations'){return User::factory()->create(['department'=>$dept,'access_role'=>$role,'status'=>'active']);}
 private function payload($u){return ['owner_id'=>$u->id,'client_name'=>'Dove','project_name'=>'Retail rollout','week_start'=>now()->startOfWeek()->toDateString(),'stage'=>'project_ongoing','actual_amount'=>90,'target_amount'=>100,'shared_next_action'=>'Arrange briefing','reason'=>'Account update for this week'];}
 public function test_staff_entry_waits_for_manager_then_publishes_without_finance_confirmation():void {
  $u=$this->staff();$m=$this->staff('manager');$other=$this->staff();
  $id=$this->actingAs($u)->postJson(route('portal.crm.store'),$this->payload($u))->assertStatus(202)->json('submission_id');
  $this->assertDatabaseCount('crm_pipeline_projects',0);$this->assertDatabaseCount('crm_weekly_progress',0);
  $this->getJson(route('portal.crm.submissions'))->assertJsonCount(1,'entries');
  $this->actingAs($other)->getJson(route('portal.crm.submissions'))->assertJsonCount(0,'entries');
  $this->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertForbidden();
  $this->actingAs($m)->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertOk();
  $p=CrmPipelineProject::firstOrFail();$this->assertEquals($u->id,$p->created_by);$this->assertEquals($u->id,$p->owner_id);
  $this->assertSame('awaiting_finance',$p->revenue_state);$this->assertNull($p->confirmedRevenue());
  $this->assertDatabaseHas('crm_weekly_progress',['project_id'=>$p->id]);
  $this->getJson(route('portal.crm.centre.data'))->assertJsonPath('summary.projects',1)->assertJsonPath('totals.actual_amount.value',null);
  $this->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertStatus(409);
  $this->assertDatabaseCount('crm_pipeline_projects',1);
 }
 public function test_return_notes_resubmission_and_ownership_are_enforced():void {
  $u=$this->staff();$m=$this->staff('manager');$v=$this->payload($u);
  $id=$this->actingAs($u)->postJson(route('portal.crm.store'),$v)->assertStatus(202)->json('submission_id');
  $this->actingAs($m)->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'return'])->assertUnprocessable();
  $this->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'return','note'=>'Please correct the delivery timeline'])->assertOk();
  $this->actingAs($u)->getJson(route('portal.crm.submissions'))->assertJsonPath('entries.0.note','Please correct the delivery timeline')->assertJsonPath('entries.0.can_resubmit',true);
  $v+=['submission_id'=>$id,'submission_revision'=>2];$v['project_name']='Corrected retail rollout';
  $this->postJson(route('portal.crm.store'),array_replace($v,['submission_revision'=>1]))->assertStatus(409);
  $this->actingAs($this->staff())->postJson(route('portal.crm.store'),array_replace($v,['owner_id'=>auth()->id()]))->assertForbidden();
  $this->actingAs($u)->postJson(route('portal.crm.store'),$v)->assertStatus(202);
  $this->actingAs($m)->postJson(route('portal.crm.submissions.review',$id),['revision'=>3,'decision'=>'approve'])->assertOk();
  $this->assertDatabaseCount('crm_report_submissions',1);$this->assertDatabaseHas('crm_pipeline_projects',['project_name'=>'Corrected retail rollout']);
  $this->assertDatabaseHas('performance_outbox',['user_id'=>$u->id,'title'=>'Client Relations corrections requested']);
 }
 public function test_published_version_survives_pending_changes_and_stale_approval_is_rejected():void {
  $u=$this->staff();$m=$this->staff('manager');$v=$this->payload($u);$p=CrmPipelineProject::create($v+['created_by'=>$m->id,'updated_by'=>$m->id]);
  $v['project_name']='Edited project';$v['revision']=$p->revision;
  $id=$this->actingAs($u)->postJson(route('portal.crm.update',$p),$v)->assertStatus(202)->json('submission_id');
  $this->assertSame('Retail rollout',$p->fresh()->project_name);
  $p->update(['revision'=>$p->revision+1]);
  $this->actingAs($m)->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertStatus(409);
  $this->assertSame('pending',CrmReportSubmission::find($id)->status);
 }
 public function test_manager_can_submit_own_entry_and_activity_entries_follow_same_review_flow():void {
  $m=$this->staff('manager');$u=$this->staff();
  $this->actingAs($m)->postJson(route('portal.crm.store'),$this->payload($m)+['submit_for_review'=>true])->assertCreated();
  $this->assertDatabaseCount('crm_report_submissions',0);
  $this->assertDatabaseHas('crm_pipeline_projects',['owner_id'=>$m->id,'created_by'=>$m->id,'revenue_state'=>'awaiting_finance']);
  $id=$this->actingAs($u)->postJson(route('portal.crm.record'),['kind'=>'issue','client_name'=>'Activity only client','reference'=>'ISS-2026-A','owner_id'=>$u->id,'status'=>'open','data'=>['title'=>'Awaiting confirmation','opened_on'=>today()->toDateString()]])->assertStatus(202)->json('submission_id');
  $this->assertDatabaseCount('crm_client_records',0);
  $this->actingAs($m)->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertOk();
  $this->assertDatabaseHas('crm_client_records',['reference'=>'ISS-2026-A','created_by'=>$u->id]);
  $d=app(ClientRelationsCentre::class)->build(['owner'=>$u->id],$m);$this->assertSame(1,$d['summary']['clients']);$this->assertCount(1,$d['actions']);$this->assertSame('Activity only client',$d['clients'][0]['name']);
 }
 public function test_other_departments_and_owner_spoofing_are_blocked():void {
  $u=$this->staff();$other=$this->staff();
  $this->actingAs($u)->postJson(route('portal.crm.store'),$this->payload($other))->assertForbidden();
  $this->actingAs($this->staff('manager','finance'))->postJson(route('portal.crm.store'),$this->payload($u))->assertForbidden();
  $this->getJson(route('portal.crm.submissions'))->assertForbidden();
 }
 public function test_project_range_syncs_across_reporting_views_without_a_source_note(): void {
  $staff=$this->staff();
  $manager=$this->staff('manager');
  $period=['from'=>'2026-11-16','to'=>'2026-11-22'];
  $register=['weekly_from'=>$period['from'],'weekly_to'=>$period['to']];
  $payload=array_diff_key($this->payload($staff),['reason'=>true]);
  $payload=array_replace($payload,['week_start'=>'2026-11-12','week_end'=>'2026-11-19','project_name'=>'Cross-week retail rollout','remarks'=>'Private project note']);

  $this->actingAs($staff)->getJson(route('portal.crm.submissions',['form'=>1]))
   ->assertJsonPath('can_review',false)->assertJsonCount(1,'staff')->assertJsonPath('staff.0.id',$staff->id);
  $this->postJson(route('portal.crm.store'),array_replace($payload,['week_end'=>'2026-11-11']))
   ->assertUnprocessable()->assertJsonValidationErrors('week_end');
  $id=$this->postJson(route('portal.crm.store'),$payload)->assertStatus(202)->json('submission_id');
  $this->getJson(route('portal.crm.centre.data',$period))->assertJsonPath('summary.projects',0);
  $this->get(route('portal.crm.centre.published-register',$register))->assertOk()->assertDontSee('Cross-week retail rollout');

  $this->actingAs($manager)->getJson(route('portal.crm.submissions',['form'=>1]))
   ->assertJsonPath('can_review',true)->assertJsonCount(2,'staff');
  $this->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertOk();
  $this->getJson(route('portal.crm.centre.data',$period))->assertJsonPath('summary.projects',1)
   ->assertJsonPath('clients.0.projects.0.week_end','2026-11-19')->assertJsonPath('totals.actual_amount.value',null)
   ->assertDontSee('Private project note');
  $this->get(route('portal.crm.index',$period))->assertOk()->assertSee('Cross-week retail rollout');
  $this->get(route('portal.crm.centre.published-register',$register))->assertOk()
   ->assertSee('Cross-week retail rollout')->assertSee('19 Nov 2026')->assertDontSee('Private project note');
  $this->getJson(route('portal.crm.centre.data',['from'=>'2026-11-20','to'=>'2026-11-22']))->assertJsonPath('summary.projects',0);
 }
 public function test_supporting_staff_are_kept_through_review_and_publication():void {
  $owner=$this->staff();$manager=$this->staff('manager');$support=$this->staff('staff','creatives');
  $former=$this->staff();$former->update(['status'=>'suspended']);
  $this->actingAs($owner)->getJson(route('portal.crm.submissions',['form'=>1]))->assertOk()
   ->assertJsonFragment(['id'=>$support->id,'name'=>$support->name])->assertDontSee($former->name);
  $payload=$this->payload($owner);
  foreach ([$owner,$former] as $invalid) {
   $this->postJson(route('portal.crm.store'),$payload+['supporting_staff_ids'=>[$invalid->id]])->assertUnprocessable()->assertJsonValidationErrors('supporting_staff_ids');
  }
  $payload['supporting_staff_ids']=[$support->id];
  $id=$this->postJson(route('portal.crm.store'),$payload)->assertStatus(202)->json('submission_id');
  $this->actingAs($manager)->getJson(route('portal.crm.submissions'))->assertOk()->assertSee($support->name);
  $this->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'return','note'=>'Confirm the support assignment.'])->assertOk();
  $this->actingAs($owner)->getJson(route('portal.crm.submissions'))->assertJsonPath('entries.0.payload.supporting_staff_ids.0',$support->id);
  $this->postJson(route('portal.crm.store'),$payload+['submission_id'=>$id,'submission_revision'=>2])->assertStatus(202);
  $this->actingAs($manager)->postJson(route('portal.crm.submissions.review',$id),['revision'=>3,'decision'=>'approve'])->assertOk();
  $project=CrmPipelineProject::sole();
  $this->assertSame([$support->id],$project->supporting_staff_ids);
  $this->getJson(route('portal.crm.centre.data'))->assertJsonPath('clients.0.projects.0.supporting_staff.0',$support->name);
  $this->get(route('portal.crm.centre.published-register',['weekly_from'=>now()->startOfWeek()->toDateString(),'weekly_to'=>now()->endOfWeek()->toDateString()]))
   ->assertOk()->assertSee('Supporting Staff')->assertSee($support->name);
  $this->actingAs($support)->postJson(route('portal.crm.update',$project),$payload+['revision'=>1])->assertForbidden();
  $this->actingAs($manager)->postJson(route('portal.crm.update',$project),array_replace($payload,['revision'=>1,'supporting_staff_ids'=>[]]))->assertOk();
  $this->assertSame([],$project->fresh()->supporting_staff_ids);
  $this->postJson(route('portal.crm.record'),['kind'=>'meeting','client_name'=>'Dove','reference'=>'SUPPORT-1','owner_id'=>$owner->id,'status'=>'completed','supporting_staff_ids'=>[$support->id],'data'=>['title'=>'Joint client meeting','scheduled_at'=>today()->toDateString()]])->assertCreated();
  $this->getJson(route('portal.crm.centre.data'))->assertJsonPath('clients.0.activities.0.supporting_staff.0',$support->name);
 }
 public function test_dashboard_task_delete_keeps_filters_and_rejects_external_redirects():void {
  $m=$this->staff('manager');$t=Task::create(['title'=>'Daily follow up','assigned_to'=>$m->id,'created_by'=>$m->id,'department'=>'client_relations','status'=>'Open','priority'=>'Medium']);
  $return='/dashboard?tab=client_relations&weekly_department=client_relations#mega-table-disclosure';
  $this->actingAs($m)->get(route('portal.tasks.edit',['task'=>$t,'return_to'=>$return]))->assertOk()->assertSee('Back to Mega Table');
  $this->patch(route('portal.tasks.update',$t),['title'=>'Updated daily task','priority'=>'Medium','status'=>'Open','return_to'=>$return])->assertRedirect($return);
  $this->delete(route('portal.tasks.destroy',$t),['return_to'=>$return])->assertRedirect($return);
  $r=\Illuminate\Http\Request::create('/portal/tasks', 'POST',['return_to'=>'https://evil.example/dashboard']);
  $this->assertSame(route('portal.tasks'),\App\Http\Controllers\Portal\TaskController::taskReturnUrl($r));
 }
}
