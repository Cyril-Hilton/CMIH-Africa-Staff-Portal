<?php
namespace Tests\Feature;

use App\Models\{CreativeRequest,PerformanceRegister,Task,User,WeeklyConsolidatedItem};
use App\Services\{BrandsDashboard,DepartmentMetricCalculator,ExecutivePerformanceSummary,FinanceDashboard,FinanceReportingMath,HrLiveRegisters,OperationsOverviewCharts,PerformanceHubReport,StaffPerformanceScore,TaskStatsService};
use App\Services\Creative\{CreativeDeliveryStats,CreativeKpiSnapshot};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatisticsAccuracyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();$this->travelTo(Carbon::parse('2026-11-27 12:00:00'));
    }

    public function test_every_catalog_sla_retains_pending_due_items_and_excludes_future_deadlines(): void
    {
        foreach(config('performance_catalog') as $department=>$definitions)foreach($definitions as $definition){
            if($definition['operation']!=='on_time')continue;
            $rows=collect();
            foreach(range(1,5) as $i)$rows->push($this->row($department,$definition['kind'],[$definition['other']=>$i===5?'2026-12-01':'2026-11-25']+($i<=3?[$definition['field']=>'2026-11-24']:[])));
            $result=collect(app(DepartmentMetricCalculator::class)->calculate($rows))->where('department',$department)->firstWhere('name',$definition['name']);
            $this->assertEquals(75,$result['value'],$department.': '.$definition['name']);
            $this->assertSame(3,$result['coverage']);
            $this->assertCount(4,$result['source_ids']);
            $historic=collect(app(DepartmentMetricCalculator::class)->calculate($rows,'2026-11-22'))->where('department',$department)->firstWhere('name',$definition['name']);$this->assertNull($historic['value']);
        }
    }

    public function test_percentage_ratios_keep_missing_results_in_the_recorded_target_denominator(): void
    {
        foreach(config('performance_catalog') as $department=>$definitions)foreach($definitions as $definition){
            if($definition['operation']!=='ratio')continue;
            $rows=collect();foreach(range(1,4) as $i)$rows->push($this->row($department,$definition['kind'],[$definition['other']=>10]+($i<=3?[$definition['field']=>10]:[])));
            $result=collect(app(DepartmentMetricCalculator::class)->calculate($rows))->where('department',$department)->firstWhere('name',$definition['name']);
            $this->assertEquals(75,$result['value'],$department.': '.$definition['name']);
            $this->assertSame(3,$result['coverage']);
        }
    }

    public function test_finance_totals_distinguish_unknown_from_zero_and_never_assume_missing_payments(): void
    {
        $u=User::factory()->create(['department'=>'finance','status'=>'active']);
        $project=function()use($u){$r=Request::create('/portal/finance','GET',['finance_period'=>'month','finance_date'=>'2026-11-27']);$r->setUserResolver(fn()=>$u);return app(FinanceDashboard::class)->build($r)['finance'];};
        $invoice=$this->row('finance','invoice',['value'=>100],$u);
        $revenue=$this->row('finance','revenue',['target'=>100,'actual'=>null,'revenue_type'=>'monthly_total'],$u);
        $data=$project();$this->assertNull($data['summary']['revenue']);$this->assertNull($data['summary']['achievement']);$this->assertNull($data['summary']['receivable']);
        $this->assertNull($data['months'][0]['revenue']);$this->assertEquals(2,$data['summary']['incomplete_amounts']);
        $invoice->update(['data'=>['value'=>100,'paid_amount'=>0]]);$revenue->update(['data'=>['target'=>100,'actual'=>0,'revenue_type'=>'monthly_total']]);
        $data=$project();$this->assertSame(0.0,$data['summary']['revenue']);$this->assertSame(0.0,$data['summary']['achievement']);$this->assertSame(100.0,$data['summary']['receivable']);$this->assertSame(0.0,$data['months'][0]['revenue']);
    }

    public function test_foreign_currency_and_unpublished_finance_rows_never_enter_ghs_totals(): void
    {
        $u=User::factory()->create(['department'=>'finance','status'=>'active']);config(['finance_reporting.manager_user_id'=>$u->id]);
        $ghs=$this->row('finance','revenue',['actual'=>100],$u);$foreign=$this->row('finance','revenue',['actual'=>500,'_finance_currency'=>'USD','_finance_workflow'=>'manager_review'],$u);$foreign->update(['approved_by'=>$u->id]);
        $this->assertFalse(FinanceDashboard::published($foreign));
        $cards=collect(app(ExecutivePerformanceSummary::class)->build(collect([$ghs,$foreign]),collect()))->keyBy('label');$this->assertEquals(100,$cards['Finance-recorded Revenue']['value']);
    }

    public function test_finance_chart_keeps_gaps_instead_of_drawing_invented_zero_points(): void
    {
        $points=[['label'=>'Jan','revenue'=>100,'receipts'=>null,'expenses'=>null],['label'=>'Feb','revenue'=>null,'receipts'=>0,'expenses'=>null],['label'=>'Mar','revenue'=>200,'receipts'=>null,'expenses'=>null]];
        $html=view('portal.finance.chart',['f_months'=>$points,'fMoney'=>fn($value)=>'GHS '.$value])->render();$doc=new \DOMDocument;@$doc->loadHTML($html);$xp=new \DOMXPath($doc);
        $this->assertSame(2,$xp->query('//*[@data-finance-series="revenue"]//*[@data-finance-value]')->length);
        $this->assertSame(0,$xp->query('//*[@data-finance-series="revenue"]//*[local-name()="polyline"]')->length);
        $this->assertSame(1,$xp->query('//*[@data-finance-series="receipts"]//*[@data-finance-value="0"]')->length);
        $this->assertSame(0,$xp->query('//*[@data-finance-series="expenses"]//*[@data-finance-value]')->length);
    }

    public function test_executive_revenue_does_not_add_direct_entries_to_monthly_totals(): void
    {
        $rows=collect([$this->row('finance','revenue',['actual'=>1000,'revenue_type'=>'monthly_total']),$this->row('finance','revenue',['actual'=>300,'revenue_type'=>'direct']),$this->row('finance','revenue',['actual'=>200,'revenue_type'=>'direct'],null,'2026-10-01','2026-10-31')]);
        $cards=collect(app(ExecutivePerformanceSummary::class)->build($rows,collect()))->keyBy('label');$this->assertEquals(1200,$cards['Finance-recorded Revenue']['value']);$this->assertCount(2,$cards['Finance-recorded Revenue']['sources']);
    }

    public function test_gross_profit_matches_the_same_definition_in_tables_catalog_and_executive_cards(): void
    {
        $row=$this->row('finance','profitability',['revenue'=>1000,'direct_cost'=>600,'variable_cost'=>300]);
        $catalog=collect(app(DepartmentMetricCalculator::class)->calculate(collect([$row])))->where('department','finance')->firstWhere('name','Gross profit');
        $cards=collect(app(ExecutivePerformanceSummary::class)->build(collect([$row]),collect()))->keyBy('label');$this->assertEquals(400,$catalog['value']);$this->assertEquals(400,$cards['Gross Profit']['value']);
        $html=view('portal.finance.table-value',['row'=>$row,'field'=>'gross_profit','f_monthlyTargets'=>collect(),'fMoney'=>fn($value)=>'GHS '.$value])->render();$this->assertStringContainsString('GHS 400',$html);
    }

    public function test_operational_metrics_do_not_sum_percentages_identifiers_or_repeated_bank_balances(): void
    {
        $rows=collect([$this->row('finance','cash',['account_ref'=>'BANK','closing_balance'=>100],null,'2026-11-01','2026-11-15'),$this->row('finance','cash',['account_ref'=>'BANK','closing_balance'=>200],null,'2026-11-16','2026-11-30'),$this->row('brands_marketing','campaign',['perfect_store_actual_percent'=>90,'_crm_project_id'=>500]),$this->row('brands_marketing','campaign',['perfect_store_actual_percent'=>80,'_crm_project_id'=>600])]);
        $metrics=app(PerformanceHubReport::class)->operational($rows);
        $this->assertEquals(200,$metrics['finance / cash / closing_balance']['value']);
        $this->assertNull($metrics['brands_marketing / campaign / perfect_store_actual_percent']['value']);
        $this->assertArrayNotHasKey('brands_marketing / campaign / _crm_project_id',$metrics);
        $invoice=$this->row('finance','invoice',['value'=>100,'paid_amount'=>50,'credited_amount'=>25,'due_on'=>'2026-11-20']);
        $catalog=collect(app(DepartmentMetricCalculator::class)->calculate(collect([$invoice])))->where('department','finance')->keyBy('name');$this->assertEquals(25,$catalog['Receivables outstanding']['value']);$this->assertEquals(25,$catalog['Receivables overdue']['value']);
    }

    public function test_operations_delivered_procurement_is_not_still_counted_pending(): void
    {
        $rows=collect([$this->row('operations_projects','procurement',['delivery_status'=>'Delivered','procurement_status'=>'Purchased']),$this->row('operations_projects','procurement',['delivery_status'=>'Pending']),$this->row('operations_projects','procurement',['delivery_status'=>'Partially Delivered'])]);
        $charts=app(OperationsOverviewCharts::class)->build($rows,['points'=>[]]);$this->assertSame(2,$charts['pending_procurements']);
    }

    public function test_pending_creative_review_remains_pending_in_completion_counts_without_a_staff_scoring_penalty(): void
    {
        $u=User::factory()->create(['department'=>'creatives','status'=>'active']);$manager=User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
        foreach(range(1,4) as $i){$task=Task::create(['title'=>'Audit work '.$i,'department'=>'creatives','assigned_to'=>$u->id,'assigned_by'=>$manager->id,'status'=>$i===4?'Completed':'Completed','completion_review_status'=>$i===4?'pending':'approved','completion_reviewed_by'=>$i===4?null:$manager->id,'completion_reviewed_at'=>$i===4?null:now(),'completion_review_requested_at'=>now(),'due_on'=>today()->addDay(),'priority'=>'Medium']);}
        CreativeRequest::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'reference'=>'AUDIT-BRIEF','title'=>'Awaiting review','brief'=>'Submitted work','requester_id'=>$manager->id,'assignee_id'=>$u->id,'planned_by'=>$manager->id,'task_id'=>$task->id,'stage'=>'in_review','submitted_at'=>now(),'first_submitted_at'=>now(),'internal_eta'=>now()->addDay(),'visibility'=>'shared']);
        $from=today()->startOfMonth();$to=today()->endOfMonth();
        foreach([TaskStatsService::forUser($u,$from,$to),TaskStatsService::global($from,$to),TaskStatsService::forDepartments(['creatives'],$from,$to)] as $stats){$this->assertSame(75.0,$stats['completion_rate']);$this->assertSame(3,$stats['completed']);$this->assertSame(1,$stats['awaiting_creative_review']);}
        $score=app(StaffPerformanceScore::class)->user($u,$from,$to);$this->assertSame(1,$score['awaiting_creative_review']);$this->assertEquals(100,$score['task_rate']);
    }

    public function test_missing_creative_measurements_and_no_eligible_hr_days_remain_unmeasured(): void
    {
        $metrics=app(CreativeDeliveryStats::class)->build(collect(),'2026-11-23','2026-11-29');foreach(['on_time_credit','quality_credit','delivery_credit'] as $field)$this->assertNull($metrics[$field]);
        $snapshot=app(CreativeKpiSnapshot::class)->forPeriod(today()->startOfWeek(),today()->endOfWeek());foreach($snapshot['items'] as $metric)$this->assertNull($metric['achievement']);
        $u=User::factory()->create(['department'=>'hr_admin','status'=>'active','access_role'=>'manager','job_level'=>'manager','position_title'=>'HR Manager']);$attendance=app(HrLiveRegisters::class)->attendance($u,'2026-12-01','2026-12-31');$this->assertNull($attendance['rate']);
    }

    public function test_historical_overdue_counts_stop_at_the_selected_report_end(): void
    {
        $u=User::factory()->create(['department'=>'brands_marketing','status'=>'active']);
        $item=WeeklyConsolidatedItem::create(['department'=>'brands_marketing','campaign_name'=>'Historical work','client_name'=>'Brand','lead_staff_id'=>$u->id,'created_by'=>$u->id,'week_start'=>'2026-11-16','week_end'=>'2026-11-22','workflow_state'=>'executing','project_ends_on'=>'2026-11-25','deliverables'=>'Recorded commitment','status'=>'In Progress']);
        $report=app(PerformanceHubReport::class)->build(collect([$item]),collect(),collect(),'2026-11-16','2026-11-22');$this->assertSame(0,$report['overdue']);$this->assertSame(0,$report['departments']['brands_marketing']['overdue']);$this->assertSame(0,$report['staff'][$u->id]['overdue']);
    }

    public function test_client_relations_resolution_rate_includes_pending_due_issues_but_not_future_ones(): void
    {
        $u=User::factory()->create(['department'=>'client_relations','status'=>'active']);
        foreach(range(1,5) as $i)\App\Models\CrmClientRecord::create(['kind'=>'issue','reference'=>'SLA-'.$i,'client_name'=>'Audit client','owner_id'=>$u->id,'created_by'=>$u->id,'updated_by'=>$u->id,'status'=>$i<=3?'resolved':'open','data'=>['opened_on'=>'2026-11-23','sla_due'=>$i===5?'2026-12-01':'2026-11-25']+($i<=3?['resolved_on'=>'2026-11-24']:[])]);
        $result=app(\App\Services\ClientRelationsCentre::class)->build(['period'=>'week','date'=>'2026-11-23'],$u);
        $timely=collect($result['radar'])->firstWhere('name','Timely Resolution');$this->assertEquals(75,$timely['value']);$this->assertSame(4,$timely['coverage']);
    }

    public function test_brands_brief_counts_use_the_latest_update_without_rewriting_earlier_weeks(): void
    {
        $u=User::factory()->create(['department'=>'brands_marketing','status'=>'active']);
        $data=['brief_date'=>'2026-11-09','partner'=>'unilever','brief_status'=>'pending','outcome'=>'ongoing'];
        $first=$this->row('brands_marketing','activation',$data,$u,'2026-11-09','2026-11-15');
        $this->row('brands_marketing','activation',array_replace($data,['brief_status'=>'closed','outcome'=>'won']),$u,'2026-11-16','2026-11-22');
        $summary=fn($period,$date)=>collect(app(BrandsDashboard::class)->build(['tab'=>'activation','period'=>$period,'date'=>$date],$u)['summary'])->mapWithKeys(fn($card)=>[$card[0]=>$card[1]]);
        $month=$summary('month','2026-11-20');$this->assertSame(1,$month['Briefs Received']);$this->assertSame(0,$month['Pending']);$this->assertSame(1,$month['Closed']);$this->assertEquals(100,$month['Success Rate']);
        $week=$summary('week','2026-11-09');$this->assertSame(1,$week['Briefs Received']);$this->assertSame(1,$week['Pending']);$this->assertEquals(0,$week['Success Rate']);
    }

    private function row(string $department,string $kind,array $data,?User $owner=null,string $from='2026-11-01',string $to='2026-11-30'): PerformanceRegister
    {
        $owner??=User::factory()->create(['department'=>$department,'status'=>'active']);
        return PerformanceRegister::create(['reference'=>(string)\Illuminate\Support\Str::ulid(),'department'=>$department,'kind'=>$kind,'owner_id'=>$owner->id,'title'=>'Statistics audit source','period_start'=>$from,'period_end'=>$to,'status'=>'in_progress','approved_at'=>now(),'data'=>$data]);
    }
}
