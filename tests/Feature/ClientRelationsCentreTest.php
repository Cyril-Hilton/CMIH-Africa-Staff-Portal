<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,CrmPipelineProject,CrmAccountTarget,CrmAccountRelationship,CrmClientRecord};
use App\Services\ClientRelationsCentre;
class ClientRelationsCentreTest extends TestCase {
 use RefreshDatabase;
 public function test_focus_rate_counts_unique_active_accounts_against_the_full_portfolio_before_pagination():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-24 12:00:00'));$owner=$this->staff();$other=$this->staff();
  $url=fn($extra=[])=>route('portal.crm.centre.data',array_replace(['period'=>'week','date'=>'2026-11-23'],$extra));
  $this->actingAs($owner)->getJson($url())->assertOk()->assertJsonPath('summary.focus_rate',null);
  foreach(range(1,81) as $n){$name=sprintf('Account %02d',$n);\Illuminate\Support\Facades\DB::table('crm_account_directory')->insert(['client_name'=>$name,'name_key'=>ClientRelationsCentre::key($name)]);}
  foreach(range(1,6) as $n)$this->project($n===6?$other:$owner,['client_name'=>sprintf('Account %02d',$n),'project_name'=>'Current '.$n,'week_start'=>'2026-11-23']);
  $this->project($owner,['client_name'=>' account 01 ','project_name'=>'Second project','week_start'=>'2026-11-23']);
  $this->project($owner,['client_name'=>'Account 07','project_name'=>'Earlier week','week_start'=>'2026-11-09']);
  foreach([['account_scope'=>'engaged'],['account_scope'=>'all','per_page'=>6,'page'=>2]] as $filters)$this->getJson($url($filters))->assertOk()->assertJsonPath('summary.portfolio_clients',81)->assertJsonPath('summary.engaged_clients',6)->assertJsonPath('summary.focus_rate',7.4);
  $this->getJson($url(['owner'=>$owner->id]))->assertOk()->assertJsonPath('summary.engaged_clients',5)->assertJsonPath('summary.focus_rate',6.2);
  $this->getJson($url(['q'=>'Account 01']))->assertOk()->assertJsonPath('summary.portfolio_clients',1)->assertJsonPath('summary.focus_rate',100);
  $this->getJson($url(['period'=>'month']))->assertOk()->assertJsonPath('summary.engaged_clients',7)->assertJsonPath('summary.focus_rate',8.6);
  $this->getJson($url(['date'=>'2026-11-30']))->assertOk()->assertJsonPath('summary.engaged_clients',0)->assertJsonPath('summary.focus_rate',0);
 }
 private function staff($department='client_relations',$role='staff'){return User::factory()->create(['department'=>$department,'access_role'=>$role,'status'=>'active']);}
 private function project($owner,$extra=[]){return CrmPipelineProject::create(array_merge(['client_name'=>'Dove','project_name'=>'Campus Experience','week_start'=>now()->startOfWeek(),'stage'=>'proposal_sent','owner_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,'source_period_confirmed'=>true,'proposed_amount'=>200,'po_amount'=>100,'target_amount'=>100,'actual_amount'=>50,'invoiced_amount'=>40,'collected_amount'=>20,'contact_info'=>'PRIVATE-CONTACT','remarks'=>'PRIVATE-NOTE'],$extra));}
 public function test_owner_tabs_show_photos_and_keep_suspended_staff_only_in_historical_periods():void {
  $active=$this->staff();$former=$this->staff();$unrelated=$this->staff();
  $former->update(['status'=>'suspended','profile_photo_path'=>'profiles/former.png']);$unrelated->update(['status'=>'suspended']);
  $past=now()->startOfWeek()->subWeek();
  $this->project($former,['week_start'=>$past,'project_name'=>'Historical delivery']);
  $this->project($former,['week_start'=>now()->startOfWeek(),'project_name'=>'Existing carried snapshot']);
  $service=app(ClientRelationsCentre::class);
  $old=$service->build(['from'=>$past->toDateString(),'to'=>$past->copy()->endOfWeek()->toDateString()],$active);
  $this->assertContains($former->id,$old['staff']->pluck('id')->all());
  $this->assertNotContains($unrelated->id,$old['staff']->pluck('id')->all());
  $this->assertSame(route('profile.photo',$former),$old['staff']->firstWhere('id',$former->id)['photo_url']);
  $this->assertFalse($old['staff']->firstWhere('id',$former->id)['active']);
  $this->assertSame($former->id,$old['clients'][0]['projects'][0]['owner']['id']);
  foreach([now()->startOfWeek(),now()->startOfWeek()->addWeek()] as $week){
   $current=$service->build(['from'=>$week->toDateString(),'to'=>$week->copy()->endOfWeek()->toDateString()],$active);
   $this->assertNotContains($former->id,$current['staff']->pluck('id')->all());
   $this->assertContains($active->id,$current['staff']->pluck('id')->all());
  }
  $this->assertDatabaseCount('crm_pipeline_projects',2);
 }
 public function test_suspended_staff_with_only_legacy_weekly_entries_remain_in_historical_owner_tabs():void {
  $active=$this->staff();$former=$this->staff();$past=now()->startOfWeek()->subWeeks(2);
  $former->update(['status'=>'suspended']);
  \App\Models\WeeklyConsolidatedItem::create(['department'=>'client_relations','week_start'=>$past,'week_end'=>$past->copy()->endOfWeek(),'lead_staff_id'=>$former->id,'client_name'=>'Legacy account','campaign_name'=>'Weekly follow-up','deliverables'=>'Legacy weekly account update','status'=>'Done','created_by'=>$active->id]);
  $old=app(ClientRelationsCentre::class)->build(['from'=>$past->toDateString(),'to'=>$past->copy()->endOfWeek()->toDateString()],$active);
  $this->assertContains($former->id,$old['staff']->pluck('id')->all());
  $this->assertDatabaseCount('weekly_consolidated_items',1);
 }
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
 public function test_periods_keep_portfolio_only_accounts_out_of_weekly_metrics():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 12:00:00'));
  $owner=$this->staff();
  $this->project($owner,['client_name'=>'Engaged project','week_start'=>'2026-11-16']);
  $this->project($owner,['client_name'=>'Previous week','week_start'=>'2026-11-12']);
  $this->project($owner,['client_name'=>'Previous month','week_start'=>'2026-10-25']);
  $this->project($owner,['client_name'=>'Running project','week_start'=>'2026-11-12','week_end'=>'2026-11-21']);
  CrmClientRecord::create(['kind'=>'meeting','reference'=>'MEET-A','client_name'=>'Engaged meeting','owner_id'=>$owner->id,'status'=>'completed','data'=>['scheduled_at'=>'2026-11-16 10:00:00'],'created_by'=>$owner->id,'updated_by'=>$owner->id]);
  CrmAccountRelationship::create(['client_name'=>'Portfolio only','owner_id'=>$owner->id,'relationship'=>'current','created_by'=>$owner->id]);
  $url=fn($filters=[])=>route('portal.crm.centre.data',$filters);
  $this->actingAs($owner)->getJson($url())->assertOk()->assertJsonPath('period.from','2026-11-16')
   ->assertJsonPath('summary.engaged_clients',3)->assertJsonPath('summary.portfolio_clients',6)
   ->assertJsonPath('summary.projects',2)->assertJsonCount(3,'clients')->assertDontSee('Previous week');
  $this->getJson($url(['account_scope'=>'all']))->assertOk()->assertJsonPath('summary.engaged_clients',3)
   ->assertJsonCount(6,'clients')->assertJsonPath('clients.3.engaged',false);
  $this->getJson($url(['period'=>'week','date'=>'2026-11-12']))->assertOk()->assertJsonPath('summary.projects',2);
  $this->getJson($url(['period'=>'month']))->assertOk()->assertJsonPath('summary.projects',3);
  $this->getJson($url(['period'=>'year']))->assertOk()->assertJsonPath('summary.projects',4);
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 12:00:00'));
  $this->getJson($url())->assertOk()->assertJsonPath('period.from','2026-11-23')->assertJsonPath('summary.engaged_clients',0);
  $this->travelBack();
 }
 public function test_weekly_account_tabs_use_period_and_owner_filters_across_client_pages():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 12:00:00'));
  $owner=$this->staff();$other=$this->staff();
  $this->project($owner,['client_name'=>'Z Active account','week_start'=>'2026-11-16']);
  $this->project($other,['client_name'=>'Other owner','week_start'=>'2026-11-16']);
  foreach(range(1,8) as $number){
   $this->project($owner,['client_name'=>'Historical '.$number,'week_start'=>'2026-11-10']);
  }
  $filters=['account_scope'=>'all','owner'=>$owner->id,'period'=>'week','date'=>'2026-11-16','per_page'=>6,'page'=>2];
  $this->actingAs($owner)->getJson(route('portal.crm.centre.data',$filters))->assertOk()
   ->assertJsonPath('summary.engaged_clients',1)->assertJsonPath('pagination.total',9)
   ->assertJsonPath('client_options.0',['name'=>'Z Active account','key'=>'z active account','engaged'=>true])
   ->assertJsonPath('client_options.6.engaged',false)
   ->assertJsonPath('client_options.6.key','historical 6')->assertJsonPath('clients.0.key','historical 6');
  $this->getJson(route('portal.crm.centre.data',array_replace($filters,['period'=>'month','page'=>1])))->assertOk()
   ->assertJsonPath('summary.engaged_clients',9)->assertJsonPath('client_options.6.engaged',true);
  $this->travelBack();
 }
 public function test_former_staff_allocations_remain_historical_in_all_accounts():void {
  $owner=$this->staff();$former=$this->staff();$former->update(['status'=>'suspended']);
  CrmAccountRelationship::create(['client_name'=>'GB Food','owner_id'=>$former->id,'relationship'=>'current','created_by'=>$owner->id]);
  $this->actingAs($owner)->getJson(route('portal.crm.centre.data'))->assertOk()->assertJsonCount(0,'clients');
  $this->getJson(route('portal.crm.centre.data',['account_scope'=>'all','client'=>'GB Food']))->assertOk()
   ->assertJsonPath('clients.0.engaged',false)->assertJsonPath('clients.0.owners.0.role','Former Account Owner')
   ->assertJsonPath('clients.0.owners.0.former_staff',true);
 }
 public function test_guests_cannot_read_shared_business_data():void {$this->getJson(route('portal.crm.centre.data'))->assertUnauthorized();}
}
