<?php
namespace Tests\Feature;
use App\Models\{User,PerformanceRegister,CrmPipelineProject};
use App\Services\FinanceDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
 use RefreshDatabase;
 private function person(bool $manager=false):User{return User::factory()->create(['department'=>'finance','status'=>'active','job_title'=>$manager?'Finance HOD':'Accountant','access_role'=>$manager?'manager':'staff']);}
 private function row(User $owner,User $reviewer,string $kind,array $data,array $extra=[]):PerformanceRegister{return PerformanceRegister::create($extra+['department'=>'finance','kind'=>$kind,'reference'=>fake()->uuid(),'title'=>'Finance source '.$kind,'owner_id'=>$owner->id,'submitted_by'=>$owner->id,'approved_by'=>$reviewer->id,'approved_at'=>now(),'period_start'=>'2026-11-01','period_end'=>'2026-11-30','status'=>'in_progress','data'=>$data]);}
 private function projection(User $u,array $v=[]):array{$r=Request::create('/portal/finance','GET',$v+['finance_date'=>'2026-11-19']);$r->setUserResolver(fn()=>$u);return app(FinanceDashboard::class)->build($r)['finance'];}
 public function test_reviewed_totals_do_not_mix_revenue_cash_or_invoice_balances_and_filters_apply():void{
  $owner=$this->person();$other=$this->person();$reviewer=$this->person(true);
  $this->row($owner,$reviewer,'revenue',['target'=>1000,'actual'=>900]);$this->row($owner,$reviewer,'invoice',['value'=>1000,'credited_amount'=>100,'paid_amount'=>300]);
  $this->row($owner,$reviewer,'receipt',['value'=>300,'paid_amount'=>300,'paid_on'=>'2026-11-18']);$this->row($owner,$reviewer,'payment',['value'=>200,'paid_amount'=>100,'paid_on'=>'2026-11-18']);$this->row($owner,$reviewer,'expense',['budget'=>400,'actual'=>200]);
  $this->row($other,$reviewer,'revenue',['target'=>10000,'actual'=>9999],['approved_at'=>null]);$this->row($other,$other,'revenue',['target'=>50,'actual'=>50]);
  $s=$this->projection($owner)['summary'];$this->assertEquals(900,$s['revenue']);$this->assertEquals(90,$s['achievement']);$this->assertEquals(300,$s['receipts']);$this->assertEquals(100,$s['payments']);$this->assertEquals(600,$s['receivable']);$this->assertEquals(200,$s['expenses']);
  $s=$this->projection($owner,['finance_period'=>'week','finance_date'=>'2026-11-18'])['summary'];$this->assertEquals(0,$s['revenue']);$this->assertEquals(300,$s['receipts']);$this->assertGreaterThan(0,$s['partial']);
  $s=$this->projection($owner,['finance_status'=>'pending'])['summary'];$this->assertEquals(0,$s['revenue']);$this->assertEquals(1,$s['pending']);
  $s=$this->projection($owner,['finance_owner'=>$other->id])['summary'];$this->assertEquals(0,$s['revenue']);$s=$this->projection($owner,['finance_q'=>'does not match'])['summary'];$this->assertEquals(0,$s['receipts']);
 }
 public function test_all_sections_render_with_shared_filters_and_private_figures_stay_restricted():void{
  $owner=$this->person();$hod=$this->person(true);$this->row($owner,$hod,'invoice',['value'=>80,'paid_amount'=>10]);
  foreach(array_keys(FinanceDashboard::TABS) as $tab)$this->actingAs($owner)->get(route('portal.finance',['finance_tab'=>$tab,'finance_date'=>'2026-11-19']))->assertOk()->assertSee('Finance data filters')->assertSee('Commercial Risks')->assertSee('Finance team members');
  $outside=User::factory()->create(['department'=>'creatives','status'=>'active']);$this->actingAs($outside)->post(route('portal.finance.reporting.save'),[])->assertForbidden();
  $this->actingAs($outside)->get(route('dashboard',['weekly_department'=>'finance']))->assertOk()->assertDontSee('Every figure. A clear picture.');
 }
 public function test_entries_require_independent_review_and_updates_do_not_duplicate_sources():void{
  $owner=$this->person();$hod=$this->person(true);$payload=['kind'=>'revenue','reference'=>'NOV-2026','title'=>'November revenue','owner_id'=>$owner->id,'period_start'=>'2026-11-01','period_end'=>'2026-11-30','status'=>'in_progress','data'=>['target'=>1000,'actual'=>900],'reason'=>'Monthly source verified by submitter','actions'=>[['task'=>'Collect outstanding invoices','owner'=>'Finance','due'=>'2026-11-30','status'=>'planned']]];
  $this->actingAs($owner)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();$row=PerformanceRegister::sole();$this->assertNull($row->approved_at);$this->assertEquals(0,$this->projection($owner,['finance_basis'=>'ledger'])['summary']['revenue']);
  $this->actingAs($owner)->post(route('portal.performance.approveRegister',$row),['reason'=>'Self review'])->assertForbidden();
  $this->actingAs($hod)->post(route('portal.performance.approveRegister',$row),['reason'=>'Source independently reviewed'])->assertSessionHasNoErrors();$this->assertEquals(900,$this->projection($owner)['summary']['revenue']);
  $this->actingAs($owner)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('reference');$this->assertDatabaseCount('performance_registers',1);
  $row->refresh();$payload['record_id']=$row->id;$payload['version']=hash('sha256',json_encode($row->getAttributes()));$payload['data']['actual']=850;
  $this->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();$this->assertNull($row->fresh()->approved_at);$this->assertDatabaseCount('performance_registers',1);$this->assertEquals(0,$this->projection($owner)['summary']['revenue']);
  $this->post(route('portal.finance.reporting.save'),$payload)->assertConflict();
 }
 public function test_invalid_dates_overpayments_and_duplicate_months_cannot_create_false_figures():void{
  $owner=$this->person();$payload=['kind'=>'receipt','reference'=>'BANK-1','title'=>'Receipt','owner_id'=>$owner->id,'period_start'=>'2026-11-19','period_end'=>'2026-11-19','status'=>'in_progress','data'=>['value'=>100,'paid_amount'=>200,'paid_on'=>'2026-11-19'],'reason'=>'Original bank statement'];
  $this->actingAs($owner)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('data.paid_amount');$payload['data']['paid_amount']=50;$payload['data']['paid_on']='2026-13-13';$this->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('data.paid_on');$this->assertDatabaseCount('performance_registers',0);
 }
 public function test_project_connections_preserve_canonical_names_and_publish_only_shared_processing_details():void{
  $finance=$this->person();$reviewer=$this->person(true);$cr=User::factory()->create(['department'=>'client_relations','status'=>'active']);$ops=User::factory()->create(['department'=>'operations_projects','status'=>'active']);
  $project=CrmPipelineProject::create(['owner_id'=>$cr->id,'created_by'=>$cr->id,'updated_by'=>$cr->id,'client_name'=>'Dove','project_name'=>'One shared launch','stage'=>'project_ongoing','source_period_confirmed'=>true,'week_start'=>'2026-11-16']);
  $handover=\App\Models\OperationsHandover::create(['project_id'=>$project->id,'requester_id'=>$cr->id,'lead_id'=>$ops->id,'title'=>$project->project_name,'location'=>'Accra','plan'=>'Execution plan','acceptance_criteria'=>'Stage ready','event_at'=>'2026-11-25','setup_due_at'=>'2026-11-24','stage'=>'planning','priority'=>'High','handover_approved_at'=>now(),'handover_approved_by'=>$reviewer->id]);
  $payload=['kind'=>'invoice','reference'=>'SHARED-INV','title'=>'Project invoice','crm_project_id'=>$project->id,'operations_handover_id'=>$handover->id,'client'=>'Wrong name','project'=>'Wrong project','owner_id'=>$finance->id,'period_start'=>'2026-11-19','period_end'=>'2026-11-19','status'=>'in_progress','data'=>['value'=>987654.32,'paid_amount'=>0,'due_on'=>'2026-11-30','processing_state'=>'Pending reconciliation approval'],'reason'=>'Connected original project'];
  $this->actingAs($finance)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();$row=PerformanceRegister::sole();$this->assertSame('One shared launch',$row->project);$this->assertSame('Dove',$row->client);$this->assertSame($project->id,$row->data['_crm_project_id']);
  $shared=app(\App\Services\ConnectedProjectSummary::class)->build($project,$cr);$this->assertSame('Awaiting Finance review',$shared['finance_work']->first()['state']);$this->assertStringNotContainsString('987654',json_encode($shared['finance_work']));
  $this->actingAs($reviewer)->post(route('portal.performance.approveRegister',$row),['reason'=>'Original invoice checked'])->assertSessionHasNoErrors();$shared=app(\App\Services\ConnectedProjectSummary::class)->build($project,$cr);$this->assertSame('Pending reconciliation approval',$shared['finance_work']->first()['state']);
  $this->actingAs($finance)->get(route('portal.finance',['finance_tab'=>'invoices','finance_date'=>'2026-11-19']))->assertOk()->assertSee($cr->name)->assertSee($ops->name)->assertSee('One shared launch');
  $payload['reference']='WRONG-LINK';$payload['crm_project_id']=null;$this->post(route('portal.finance.reporting.save'),$payload)->assertUnprocessable();
 }
 public function test_repeated_crm_snapshots_count_once_and_are_never_added_to_monthly_ledger():void{
  $finance=$this->person();$reviewer=$this->person(true);$cr=User::factory()->create(['department'=>'client_relations','status'=>'active']);
  foreach(['2026-11-02'=>100,'2026-11-16'=>150] as $week=>$value){$p=new CrmPipelineProject(['owner_id'=>$cr->id,'created_by'=>$cr->id,'updated_by'=>$cr->id,'client_name'=>'Dove','project_name'=>'Same project','stage'=>'project_ongoing','source_period_confirmed'=>true,'week_start'=>$week,'week_end'=>\Carbon\Carbon::parse($week)->endOfWeek(),'actual_amount'=>$value,'revenue_state'=>'confirmed','revenue_submitted_by'=>$cr->id,'revenue_finance_by'=>$finance->id,'revenue_confirmed_at'=>now()]);$p->saveQuietly();}
  $s=$this->projection($finance)['summary'];$this->assertEquals(150,$s['revenue']);$this->assertEquals(150,$this->projection($finance,['finance_owner'=>$finance->id,'finance_basis'=>'crm'])['summary']['revenue']);$this->row($finance,$reviewer,'revenue',['actual'=>200,'target'=>300]);$this->assertEquals(200,$this->projection($finance)['summary']['revenue']);$this->assertEquals(150,$this->projection($finance,['finance_basis'=>'crm'])['summary']['revenue']);
 }

}
