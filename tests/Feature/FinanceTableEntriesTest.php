<?php

namespace Tests\Feature;

use App\Models\{User,PerformanceRegister};
use App\Services\FinanceDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FinanceTableEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $staff=User::factory()->create(['department'=>'finance','status'=>'active','access_role'=>'staff']);
        $manager=User::factory()->create(['department'=>'finance','status'=>'active','access_role'=>'manager']);
        config(['finance_reporting.manager_user_id'=>$manager->id]);
        return [$staff,$manager];
    }

    private function payload(User $staff,string $kind,array $data): array
    {
        return ['kind'=>$kind,'title'=>'Manual '.$kind,'owner_id'=>$staff->id,'client'=>'Typed client','project'=>'Typed project','period_start'=>'2026-11-01','period_end'=>'2026-11-30','status'=>'in_progress','data'=>$data+['notes'=>'Finance comment'],'reason'=>'Original source checked'];
    }

    public function test_each_native_table_has_its_own_form_and_separate_pagination(): void
    {
        [$staff,$manager]=$this->team();
        foreach(FinanceDashboard::GROUPS as $tab=>$kinds){
            $response=$this->actingAs($staff)->get(route('portal.finance',['finance_tab'=>$tab,'finance_date'=>'2026-11-23']));
            $response->assertOk();
            foreach($kinds as $kind){$response->assertSee('data-finance-table="'.$kind.'"',false)->assertSee('id="finance-entry-title-'.$kind.'"',false)->assertSee('name="kind" value="'.$kind.'"',false);}
        }
        for($i=0;$i<12;$i++) foreach(['receipt','payment'] as $kind) PerformanceRegister::create(['department'=>'finance','kind'=>$kind,'reference'=>$kind.'-'.$i,'title'=>$kind.' '.$i,'owner_id'=>$staff->id,'submitted_by'=>$staff->id,'period_start'=>'2026-11-01','period_end'=>'2026-11-30','status'=>'in_progress','data'=>['value'=>100,'paid_amount'=>0]]);
        $request=Request::create('/portal/finance','GET',['finance_tab'=>'cashflow','finance_date'=>'2026-11-23','finance_receipt_page'=>2]);$request->setUserResolver(fn()=>$staff);
        $data=app(FinanceDashboard::class)->build($request)['finance'];
        $this->assertSame(12,$data['tables']['receipt']->total());$this->assertSame(2,$data['tables']['receipt']->currentPage());$this->assertCount(2,$data['tables']['receipt']->items());
        $this->assertSame(1,$data['tables']['payment']->currentPage());$this->assertCount(10,$data['tables']['payment']->items());
    }

    public function test_manual_entries_for_every_table_keep_typed_fields_and_require_manager_review(): void
    {
        [$staff,$manager]=$this->team();
        $inputs=['revenue'=>['actual'=>900,'target'=>1000,'revenue_type'=>'monthly_total'],'revenue_target'=>['target'=>1000],'profitability'=>['revenue'=>900,'direct_cost'=>300],'invoice'=>['event_date'=>'2026-11-20','account_lead'=>'Typed lead','processing_state'=>'Pending reconciliation approval'],'payable'=>['value'=>100,'paid_amount'=>0],'receipt'=>['value'=>100,'paid_amount'=>0,'expected_on'=>'2026-11-28'],'payment'=>['value'=>80,'paid_amount'=>0,'expected_on'=>'2026-11-29'],'cash'=>['closing_balance'=>300],'expense'=>['actual'=>40],'commercial_risk'=>['risk_type'=>'Executed without PO','account_lead'=>'Typed lead'],'payroll'=>['staff_group'=>'Team total','headcount'=>2,'value'=>500,'paid_amount'=>0],'tax'=>['tax_type'=>'SSNIT','value'=>100,'paid_amount'=>0]];
        foreach($inputs as $kind=>$data){
            $this->actingAs($staff)->post(route('portal.finance.reporting.save'),$this->payload($staff,$kind,$data))->assertSessionHasNoErrors();
            $row=PerformanceRegister::where('kind',$kind)->sole();
            $this->assertNull($row->approved_at);$this->assertSame('Typed client',$row->client);$this->assertSame('Typed project',$row->project);$this->assertSame('Finance comment',$row->data['notes']);$this->assertStringStartsWith('FIN-',$row->reference);$this->assertArrayNotHasKey('_crm_project_id',$row->data);
            $this->actingAs($manager)->post(route('portal.performance.approveRegister',$row),['reason'=>'Precious reviewed source'])->assertSessionHasNoErrors();$this->assertNotNull($row->fresh()->approved_at);
        }
        $this->actingAs($staff)->get(route('portal.finance',['finance_tab'=>'revenue','finance_date'=>'2026-11-23']))->assertOk()->assertSee('90.00%')->assertSee('Below target')->assertSee('Finance comment');
        $this->get(route('portal.finance',['finance_tab'=>'invoices','finance_date'=>'2026-11-23']))->assertOk()->assertSee('Typed lead')->assertSee('20 Nov 2026')->assertSee('Pending reconciliation approval');
    }

    public function test_unknown_invoice_value_cannot_be_used_to_record_settlement(): void
    {
        [$staff]=$this->team();
        $payload=$this->payload($staff,'invoice',['processing_state'=>'Pending reconciliation approval','paid_amount'=>5,'paid_on'=>'2026-11-20']);
        $this->actingAs($staff)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('data.value');$this->assertDatabaseCount('performance_registers',0);
    }

    public function test_validation_reopens_correct_form_and_edit_preserves_notes(): void
    {
        [$staff,$manager]=$this->team();
        $url=route('portal.finance',['finance_tab'=>'invoices','finance_date'=>'2026-11-23']);
        $payload=$this->payload($staff,'invoice',['processing_state'=>'Sent']);
        $this->actingAs($staff)->from($url)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('data.value');
        $this->get($url)->assertOk()->assertSee('$refs.entry.showModal()',false)->assertSee('Typed client');
        $payload['data']=['value'=>100,'paid_amount'=>0,'notes'=>'Saved note','account_lead'=>'Typed lead'];
        $this->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();$row=PerformanceRegister::sole();
        $this->get($url.'&finance_edit='.$row->id)->assertOk()->assertSee('Saved note')->assertSee('name="record_id" value="'.$row->id.'"',false);
        $payload['record_id']=$row->id;$payload['version']=hash('sha256',json_encode($row->getAttributes()));$payload['reference']=$row->reference;$payload['data']['notes']='Corrected note';
        $this->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();$this->assertDatabaseCount('performance_registers',1);$this->assertSame('Corrected note',$row->fresh()->data['notes']);
    }
    public function test_repeated_form_submission_does_not_duplicate_a_generated_reference(): void
    {
        [$staff]=$this->team();
        $payload=$this->payload($staff,'expense',['actual'=>20]);$payload['entry_key']=(string)\Illuminate\Support\Str::ulid();
        $this->actingAs($staff)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();
        $this->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasErrors('reference');$this->assertDatabaseCount('performance_registers',1);
    }

    public function test_cash_tables_distinguish_project_client_from_the_actual_payer_or_payee(): void
    {
        [$staff,$manager]=$this->team();
        foreach(['receipt'=>'Original payer','payment'=>'Original supplier'] as $kind=>$party){
            $payload=$this->payload($staff,$kind,['value'=>100,'paid_amount'=>0,'counterparty'=>$party]);
            $this->actingAs($staff)->post(route('portal.finance.reporting.save'),$payload)->assertSessionHasNoErrors();
        }
        $this->get(route('portal.finance',['finance_tab'=>'cashflow','finance_date'=>'2026-11-23']))->assertOk()->assertSee('Original payer')->assertSee('Original supplier')->assertSee('Project client / brand')->assertSee('Payee / supplier')->assertSee('Received from / payer');
    }

}
