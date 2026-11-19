<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,CrmPipelineProject,OperationsHandover,Task,PerformanceOutbox};
use App\Services\OperationsHandoverWorkflow;
class OperationsHandoverTest extends TestCase {
 use RefreshDatabase;
 private function staff($d='client_relations',$role='staff'){return User::factory()->create(['department'=>$d,'access_role'=>$role,'status'=>'active']);}
 private function project($owner){return CrmPipelineProject::create(['client_name'=>'Dove','project_name'=>'Campus activation','owner_id'=>$owner->id,'week_start'=>now()->startOfWeek(),'stage'=>'project_ongoing','created_by'=>$owner->id,'updated_by'=>$owner->id]);}
 private function payload($project){return ['project_id'=>$project->id,'title'=>'Dove event setup','plan'=>'Set up the agreed booth, sampling stations and welcome area.','acceptance_criteria'=>'All agreed stations are installed and safe.','event_at'=>now()->addDays(5)->toIso8601String(),'setup_due_at'=>now()->addDays(4)->toIso8601String(),'location'=>'University of Ghana','priority'=>'High','lead_id'=>999];}
 private function action($h,$actor,$action,$extra=[]){$h->refresh();$this->actingAs($actor)->postJson(route('portal.operations.handovers.action',$h),array_merge(['action'=>$action,'revision'=>$h->revision,'reason'=>'Recorded handover decision'],$extra))->assertOk();return $h->refresh();}
 public function test_signed_handover_enters_reviews_and_hod_assignment_publishes_one_activation_preserving_operational_edits():void {
  $sender=$this->staff();$hod=$this->staff('operations_projects','manager');$owner=$this->staff('operations_projects');$support=$this->staff('operations_projects');$p=$this->project($sender);
  $this->actingAs($sender)->postJson(route('portal.operations.handovers.store'),$this->payload($p)+['execution_due_at'=>now()->addDays(6)->toIso8601String()])->assertCreated();$h=OperationsHandover::firstOrFail();
  $h=$this->action($h,$sender,'meeting',['meeting_scheduled_at'=>now()->subHour()->toIso8601String(),'meeting_held_at'=>now()->subMinutes(30)->toIso8601String(),'meeting_location'=>'Boardroom','meeting_minutes'=>'Both teams agreed requirements and delivery dates.','attendees'=>[$sender->id,$hod->id,$support->id]]);
  $this->assertTrue(PerformanceOutbox::where('user_id',$support->id)->whereNotNull('dashboard_at')->exists());
  $h=$this->action($h,$sender,'signoff_cr');$h=$this->action($h,$hod,'signoff_ops');$this->assertNotNull($h->handover_approved_at);$this->assertNull($h->operations_register_id);
  $this->actingAs($hod)->get(route('dashboard',['weekly_department'=>'operations_projects','ops_review'=>0]))->assertOk()->assertSee('Signed handovers')->assertSee($p->project_name);
  $h=$this->action($h,$hod,'assign',['lead_id'=>$owner->id,'agreed_setup_due_at'=>now()->addDays(3)->toIso8601String(),'agreed_execution_due_at'=>now()->addDays(6)->toIso8601String()]);
  $row=\App\Models\PerformanceRegister::findOrFail($h->operations_register_id);$this->assertSame($p->id,$row->data['crm_project_id']);$this->assertSame($owner->id,$row->owner_id);$this->assertNotNull($row->approved_at);
  $this->actingAs($hod)->post(route('portal.operations.reporting.save'),['kind'=>'activation','source'=>$row->id,'source_version'=>\App\Services\OperationsReporting::version($row),'title'=>$row->title,'client'=>'Wrong client','project'=>'Wrong project','owner_id'=>$hod->id,'crm_project_id'=>null,'week'=>$row->period_start->toDateString(),'status'=>'completed','data'=>['activation_status'=>'Completed','vendors'=>'Added operational vendor']])->assertSessionHasNoErrors();
  $row->refresh();$this->assertSame($p->id,$row->data['crm_project_id']);$this->assertSame($p->project_name,$row->project);$this->assertSame($owner->id,$row->owner_id);$this->assertNotSame('completed',$row->status);$this->assertSame('Planned',$row->data['activation_status']);$this->assertSame('Added operational vendor',$row->data['vendors']);
  $data=$row->data;$data['vendors']='Confirmed vendor';$data['remarks']='Operations detail added';$row->update(['data'=>$data]);
  $h=$this->action($h,$hod,'assign',['lead_id'=>$hod->id]);$this->assertSame(1,\App\Models\PerformanceRegister::where('data->operations_handover_id',$h->id)->count());$this->assertSame('Confirmed vendor',$row->fresh()->data['vendors']);$this->assertSame('Operations detail added',$row->fresh()->data['remarks']);
  $this->assertSame($hod->id,$row->fresh()->owner_id);
  $h=$this->action($h,$sender,'meeting',['meeting_scheduled_at'=>now()->subHour()->toIso8601String(),'meeting_held_at'=>now()->subMinutes(20)->toIso8601String(),'meeting_location'=>'Boardroom','meeting_minutes'=>'Changed requirements require new approvals.','attendees'=>[$sender->id,$hod->id]]);
  $this->assertNull($h->handover_approved_at);$this->assertTrue($row->fresh()->data['handover_review_required']);
 }
 public function test_end_to_end_handover_requires_both_meeting_signoffs_resources_acceptance_and_independent_verification():void {
  $sender=$this->staff();$lead=$this->staff('operations_projects','manager');$owner=$this->staff('operations_projects');$p=$this->project($sender);
  $this->actingAs($sender)->postJson(route('portal.operations.handovers.store'),$this->payload($p))->assertCreated();$h=OperationsHandover::firstOrFail();$this->assertNull($h->lead_id);$this->assertNull($h->task_id);$this->assertDatabaseHas('performance_events',['entity_type'=>'operations_handover','action'=>'submitted']);$this->assertTrue(PerformanceOutbox::where('user_id',$lead->id)->whereNotNull('dashboard_at')->exists());
  $h=$this->action($h,$lead,'assign',['lead_id'=>$owner->id]);$taskId=$h->task_id;$h=$this->action($h,$owner,'acknowledge');
  $this->actingAs($owner)->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'setup','reason'=>'Attempting to skip meeting'])->assertForbidden();
  $h=$this->action($h,$sender,'meeting',['meeting_scheduled_at'=>now()->subHour()->toIso8601String(),'meeting_held_at'=>now()->subMinutes(30)->toIso8601String(),'meeting_location'=>'Office meeting room','meeting_minutes'=>'Both teams agreed to the booth layout and asset checklist.','attendees'=>[$sender->id,$owner->id]]);
  $h=$this->action($h,$sender,'signoff_cr');$this->assertNull($h->ops_signoff_at);$h=$this->action($h,$owner,'signoff_ops');$this->assertSame('planning',$h->stage);
  $h=$this->action($h,$owner,'resource',['resource'=>['category'=>'asset','name'=>'Sampling booth','quantity'=>1,'owner_id'=>$owner->id,'due_on'=>today()->addDays(3)->toDateString(),'status'=>'needed']]);
  $this->actingAs($owner)->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'submit','reason'=>'Work supposedly ready','evidence_url'=>'https://example.test/photos'])->assertUnprocessable();
  $resource=$h->resources[0];$resource['status']='ready';$h=$this->action($h,$owner,'resource',['resource'=>$resource]);$h=$this->action($h,$owner,'setup');$h=$this->action($h,$owner,'submit',['evidence_url'=>'https://example.test/photos']);$this->assertSame('pending',$h->task->fresh()->completion_review_status);
  $h=$this->action($h,$sender,'return');$this->assertSame('planning',$h->stage);$h=$this->action($h,$owner,'submit',['evidence_url'=>'https://example.test/corrected-photos']);$h=$this->action($h,$sender,'accept');$this->assertSame('accepted',$h->stage);$this->assertSame('pending',$h->task->fresh()->completion_review_status);
  $this->actingAs($owner)->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'verify','reason'=>'Cannot verify my own work','quality_score'=>100])->assertForbidden();
  $h=$this->action($h,$lead,'verify',['quality_score'=>93]);$this->assertSame('completed',$h->stage);$this->assertSame('approved',$h->task->fresh()->completion_review_status);$this->assertSame($taskId,$h->task_id);$this->assertNotNull($h->accepted_at);$this->assertNotNull($h->verified_at);
 }
 public function test_neutral_staff_read_but_cannot_assign_or_change_handover():void {
  $sender=$this->staff();$p=$this->project($sender);$this->actingAs($sender)->postJson(route('portal.operations.handovers.store'),$this->payload($p))->assertCreated();$h=OperationsHandover::first();
  $this->actingAs($this->staff('finance'))->getJson(route('portal.operations.handovers.data'))->assertOk()->assertJsonPath('items.0.client','Dove')->assertJsonCount(0,'items.0.actions');
  $this->postJson(route('portal.operations.handovers.action',$h),['action'=>'assign','revision'=>1,'reason'=>'Unauthorised assignment','lead_id'=>$sender->id])->assertForbidden();
  $this->actingAs($sender)->postJson(route('portal.operations.handovers.action',$h),['action'=>'assign','revision'=>1,'reason'=>'Client cannot assign Operations','lead_id'=>$sender->id])->assertForbidden();
 }
 public function test_duplicate_stale_and_invalid_meetings_are_rejected():void {
  $sender=$this->staff();$lead=$this->staff('operations_projects','manager');$owner=$this->staff('operations_projects');$p=$this->project($sender);$payload=$this->payload($p);$this->actingAs($sender)->postJson(route('portal.operations.handovers.store'),$payload)->assertCreated();$this->postJson(route('portal.operations.handovers.store'),$payload)->assertStatus(409);$h=OperationsHandover::first();$h=$this->action($h,$lead,'assign',['lead_id'=>$owner->id]);
  $this->actingAs($lead)->postJson(route('portal.operations.handovers.action',$h),['action'=>'acknowledge','revision'=>1,'reason'=>'Stale tab update'])->assertStatus(409);
  $this->actingAs($sender)->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'meeting','reason'=>'Meeting without Operations','meeting_scheduled_at'=>now()->subHour()->toIso8601String(),'meeting_held_at'=>now()->subMinutes(20)->toIso8601String(),'meeting_location'=>'Office','meeting_minutes'=>'Agreed plan without operations present','attendees'=>[$sender->id]])->assertUnprocessable();
 }
 public function test_guests_and_unrelated_creators_cannot_create_handover():void {$this->getJson(route('portal.operations.handovers.data'))->assertUnauthorized();$creator=$this->staff('creatives');$this->actingAs($creator)->postJson(route('portal.operations.handovers.store'),[])->assertForbidden();}
}
