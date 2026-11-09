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
