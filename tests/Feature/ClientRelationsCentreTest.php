<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,CrmPipelineProject,CrmAccountTarget,CrmAccountRelationship,CrmClientRecord};
use App\Services\ClientRelationsCentre;
class ClientRelationsCentreTest extends TestCase {
 use RefreshDatabase;
 private function staff($department='client_relations',$role='staff'){return User::factory()->create(['department'=>$department,'access_role'=>$role,'status'=>'active']);}
 private function project($owner,$extra=[]){return CrmPipelineProject::create(array_merge(['client_name'=>'Dove','project_name'=>'Campus Experience','week_start'=>now()->startOfWeek(),'stage'=>'proposal_sent','owner_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,'source_period_confirmed'=>true,'proposed_amount'=>200,'po_amount'=>100,'target_amount'=>100,'actual_amount'=>50,'invoiced_amount'=>40,'collected_amount'=>20,'contact_info'=>'PRIVATE-CONTACT','remarks'=>'PRIVATE-NOTE'],$extra));}
 public function test_all_internal_departments_can_see_money_but_not_contacts_or_private_notes():void {
  $owner=$this->staff();$this->project($owner);
  foreach(['finance','creatives','operations_projects','hr_admin','brands_marketing'] as $department){$user=$this->staff($department);$response=$this->actingAs($user)->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonPath('totals.actual_amount.value',null)->assertJsonPath('clients.0.projects.0.reported_revenue','50.00')->assertJsonPath('clients.0.projects.0.revenue_status','Awaiting Finance Confirmation')->assertJsonPath('clients.0.projects.0.money.collected_amount',20)->assertJsonPath('clients.0.projects.0.metrics.outstanding',20);$response->assertDontSee('PRIVATE-CONTACT')->assertDontSee('PRIVATE-NOTE');$this->get(route('portal.crm.index'))->assertForbidden();}
  $this->actingAs($this->staff('finance'))->get(route('dashboard',['weekly_department'=>'client_relations']))->assertOk()->assertSee('Relationship Web')->assertSee('Client Health Radar')->assertSee('Growth & Revenue')->assertSee('Client & Business Performance Centre',false)->assertDontSee('Show Amounts');
 }
 public function test_duplicate_project_snapshots_are_not_added_and_former_owners_remain_visible():void {
  $former=$this->staff();$current=$this->staff();$this->project($former,['week_start'=>now()->subWeek()]);$this->project($current,['week_start'=>now()->startOfWeek(),'actual_amount'=>75]);
  $data=app(ClientRelationsCentre::class)->build([],$current);$this->assertSame(1,$data['summary']['projects']);$this->assertNull($data['totals']['actual_amount']['value']);$this->assertEquals(75,$data['clients'][0]['projects'][0]['reported_revenue']);$this->assertCount(2,$data['clients'][0]['owners']);$this->assertSame('Historical Owner',$data['clients'][0]['owners'][1]['role']);
 }
 public function test_missing_values_are_not_invented_and_unconfirmed_sources_are_excluded():void {
  $owner=$this->staff();$this->project($owner,['actual_amount'=>null,'target_amount'=>null,'proposed_amount'=>null]);$this->project($owner,['project_name'=>'Unconfirmed','actual_amount'=>99999,'source_period_confirmed'=>false]);CrmAccountTarget::create(['owner_id'=>$owner->id,'client_name'=>'Dove','reference'=>'unknown','period_start'=>today(),'period_end'=>today()->addMonth(),'frequency'=>'monthly','target_amount'=>90000,'needs_reconciliation'=>true]);
  $r=$this->actingAs($owner)->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonPath('summary.projects',1)->assertJsonPath('totals.actual_amount.value',null)->assertJsonPath('summary.achievement',null)->assertJsonPath('growth.percent',null)->assertJsonCount(0,'targets')->assertJsonPath('source_pending',1);
 }
 public function test_client_pagination_and_filters_keep_whole_cohort_totals():void {
  $owner=$this->staff();for($i=1;$i<=14;$i++)$this->project($owner,['client_name'=>sprintf('Client %02d',$i),'project_name'=>'Growth Campaign']);
  $this->actingAs($owner)->getJson(route('portal.crm.centre.data',['page'=>2,'per_page'=>6,'q'=>'Growth']))->assertOk()->assertJsonPath('pagination.total',14)->assertJsonPath('pagination.page',2)->assertJsonCount(6,'clients')->assertJsonPath('clients.0.name','Client 07')->assertJsonPath('totals.actual_amount.value',null);
  $this->getJson(route('portal.crm.centre.data',['client'=>'Client 02','owner'=>$owner->id,'stage'=>'proposal_sent']))->assertOk()->assertJsonPath('pagination.total',1)->assertJsonPath('clients.0.name','Client 02');
  $this->getJson(route('portal.crm.centre.data',['from'=>'2026-10-23','to'=>'2020-01-01']))->assertUnprocessable();
 }
 public function test_account_relationships_are_audited_and_only_leads_can_record_them():void {
  $owner=$this->staff();$manager=$this->staff('client_relations','manager');$this->project($owner);$payload=['client_name'=>'Dove','owner_id'=>$owner->id,'relationship'=>'shared','starts_on'=>'2026-10-01','note'=>'Joint account allocation'];
  $this->actingAs($owner)->postJson(route('portal.crm.centre.relationship'),$payload)->assertForbidden();$this->actingAs($manager)->postJson(route('portal.crm.centre.relationship'),$payload)->assertCreated();$this->postJson(route('portal.crm.centre.relationship'),$payload)->assertStatus(409);$this->assertDatabaseCount('crm_account_relationships',1);$this->assertDatabaseHas('performance_events',['entity_type'=>'crm_relationship','action'=>'create']);
  $this->postJson(route('portal.crm.centre.relationship'),array_merge($payload,['relationship'=>'previous','starts_on'=>null]))->assertUnprocessable();
 }
 public function test_exports_use_all_matching_clients_and_keep_private_notes_out():void {
  $owner=$this->staff();$this->project($owner);$this->actingAs($this->staff('finance'))->get(route('portal.crm.centre.export',['format'=>'xlsx']))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');$pdf=$this->get(route('portal.crm.centre.export',['format'=>'pdf']))->assertOk()->assertHeader('Content-Type','application/pdf');$this->assertStringStartsWith('%PDF',$pdf->getContent());
 }
 public function test_guests_cannot_read_shared_business_data():void {$this->getJson(route('portal.crm.centre.data'))->assertUnauthorized();}
}
