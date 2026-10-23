<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\{User,CrmFinancialRegister,CrmAccountTarget};
class CrmFinancialRegisterTest extends TestCase {
 use RefreshDatabase;
 private function owner(){return User::factory()->create(['department'=>'client_relations','access_role'=>'manager','status'=>'active']);}
 public function test_source_import_is_idempotent_retains_negative_balance_and_never_infers_revenue():void {
  $actor=$this->owner();$owner=User::factory()->create(['department'=>'client_relations','access_role'=>'staff','status'=>'suspended']);$row=['sheet'=>'2026 Example','row'=>2,'client'=>'Dove UI','project'=>'Experience Centre','po_amount'=>100,'po_balance'=>-10,'invoiced_amount'=>100,'po_status'=>'Issued','invoice_status'=>'GRed','source_year'=>2026,'as_of'=>null,'source_values'=>['A'=>'Source value'],'checksum'=>str_repeat('a',64)];$file=tempnam(sys_get_temp_dir(),'finance-');$map=tempnam(sys_get_temp_dir(),'owners-');file_put_contents($file,json_encode(['source_id'=>'1ydsMLK86iDG5XPp1PcC3_qjh_2CjeJ_-','rows'=>[$row]]));file_put_contents($map,json_encode(['2026 Example'=>$owner->id]));
  try{for($i=0;$i<2;$i++)$this->artisan('crm:import-financial-register',['file'=>$file,'--owner-map'=>$map,'--actor'=>$actor->id])->assertSuccessful();$this->assertDatabaseCount('crm_financial_register',1);$this->assertEquals(-10,CrmFinancialRegister::first()->po_balance);$this->assertNull(CrmFinancialRegister::first()->as_of);$this->assertSame('suspended',$owner->fresh()->status);$this->assertSame($owner->id,CrmFinancialRegister::first()->owner_id);$this->assertFalse(\App\Services\ClientRelationsPipeline::member($owner));
   $staff=User::factory()->create(['department'=>'finance','status'=>'active']);$this->actingAs($staff)->getJson(route('portal.crm.centre.data',['source'=>'workbook']))->assertOk()->assertJsonPath('ledger_summary.totals.po_amount.value',100)->assertJsonPath('ledger_summary.totals.invoiced_amount.value',100)->assertJsonPath('totals.actual_amount.value',null)->assertJsonPath('totals.collected_amount.value',null)->assertJsonPath('ledger_summary.undated',1)->assertJsonPath('clients.0.finance.0.discrepancy',true)->assertJsonPath('clients.0.owners.0.role','Workbook Register Owner');
  }finally{unlink($file);unlink($map);}
 }
 public function test_source_only_mode_excludes_manual_preview_figures_and_can_filter_finance_only_projects():void {
  $owner=$this->owner();\App\Models\CrmPipelineProject::create(['client_name'=>'DEMO','project_name'=>'Not workbook','week_start'=>today(),'owner_id'=>$owner->id,'stage'=>'proposal_sent','actual_amount'=>99999,'created_by'=>$owner->id,'updated_by'=>$owner->id]);CrmFinancialRegister::create(['import_key'=>'source','client_name'=>'Dove UI','project_name'=>'Experience Centre','owner_id'=>$owner->id,'po_amount'=>515587.97,'invoiced_amount'=>257793.99,'source_year'=>2026,'source_sheet'=>'Maintained finance block','source_row'=>2,'source_values'=>[],'source_checksum'=>str_repeat('a',64)]);
  $this->actingAs($owner)->getJson(route('portal.crm.centre.data',['source'=>'workbook','q'=>'Experience']))->assertOk()->assertJsonPath('summary.clients',1)->assertJsonPath('clients.0.name','Dove UI')->assertJsonPath('summary.projects',0)->assertJsonPath('totals.actual_amount.value',null)->assertJsonPath('ledger_summary.totals.invoiced_amount.value',257793.99);
 }
 public function test_period_result_requires_manager_revision_and_a_confirmed_source_target():void {
  $owner=$this->owner();$target=CrmAccountTarget::create(['client_name'=>'Dove','owner_id'=>$owner->id,'period_start'=>today()->startOfMonth(),'period_end'=>today()->endOfMonth(),'frequency'=>'monthly','target_amount'=>100,'reference'=>'confirmed']);$payload=['revision'=>1,'actual_amount'=>65,'reason'=>'Checked against the revenue ledger'];
  $this->actingAs(User::factory()->create(['department'=>'client_relations','status'=>'active','access_role'=>'staff']))->postJson(route('portal.crm.centre.target-result',$target),$payload)->assertForbidden();$this->actingAs($owner)->postJson(route('portal.crm.centre.target-result',$target),$payload)->assertOk();$this->postJson(route('portal.crm.centre.target-result',$target),$payload)->assertStatus(409);$this->assertEquals(65,$target->fresh()->actual_amount);$target->update(['needs_reconciliation'=>true]);$this->postJson(route('portal.crm.centre.target-result',$target),array_merge($payload,['revision'=>2]))->assertUnprocessable();
 }
 public function test_register_updates_preserve_source_and_reject_stale_or_unauthorised_changes():void {
  $manager=$this->owner();$staff=User::factory()->create(['department'=>'client_relations','access_role'=>'staff','status'=>'active']);
  $entry=CrmFinancialRegister::create(['import_key'=>'update-source','client_name'=>'Finance Only','project_name'=>'Activation','owner_id'=>$staff->id,'po_amount'=>100,'source_year'=>2026,'source_sheet'=>'Original','source_row'=>2,'source_values'=>['PO'=>100],'source_checksum'=>str_repeat('a',64)]);
  $payload=['revision'=>1,'po_amount'=>120,'po_balance'=>20,'invoiced_amount'=>100,'as_of'=>today()->toDateString(),'reason'=>'Confirmed against the issued PO'];
  $this->actingAs(User::factory()->create(['department'=>'finance','status'=>'active']))->postJson(route('portal.crm.centre.financial-update',$entry),$payload)->assertForbidden();
  $this->actingAs($staff)->postJson(route('portal.crm.centre.financial-update',$entry),$payload)->assertOk();
  $this->postJson(route('portal.crm.centre.financial-update',$entry),$payload)->assertStatus(409);
  $this->assertEquals(120,$entry->fresh()->po_amount);$this->assertSame(['PO'=>100],$entry->fresh()->source_values);
  $allocation=\App\Models\CrmAccountAllocation::create(['import_key'=>'allocation-update','client_name'=>'Finance Only','owner_id'=>$staff->id,'budget_target'=>500,'source_year'=>2026,'source_sheet'=>'Original','source_row'=>3,'source_values'=>['Budget'=>500],'source_checksum'=>str_repeat('b',64)]);
  $values=['revision'=>1,'budget_target'=>600,'reason'=>'Confirmed budget allocation'];
  $this->postJson(route('portal.crm.centre.allocation-update',$allocation),$values+['owner_id'=>null])->assertForbidden();
  $this->postJson(route('portal.crm.centre.allocation-update',$allocation),$values)->assertOk();
  $this->assertSame($staff->id,$allocation->fresh()->owner_id);$this->assertSame(['Budget'=>500],$allocation->fresh()->source_values);
  $this->actingAs($manager)->postJson(route('portal.crm.centre.relationship'),['client_name'=>'Finance Only','owner_id'=>$staff->id,'relationship'=>'shared','note'=>'Confirmed account coverage'])->assertCreated();
 }
}
