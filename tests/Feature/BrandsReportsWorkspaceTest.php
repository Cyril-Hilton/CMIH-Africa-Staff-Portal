<?php
namespace Tests\Feature;
use App\Models\{User,BrandsReportSubmission,PerformanceRegister,WeeklyConsolidatedItem,Task,Attendance};
use App\Services\{BrandsDashboard,BrandsReports};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BrandsReportsWorkspaceTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);$this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-04 12:00:00','Africa/Accra'));}
 private function staff($role='staff',$department='brands_marketing'){return User::factory()->create(['department'=>$department,'status'=>'active','access_role'=>$role]);}
 private function payload($u,$category='workstream'){return ['category'=>$category,'title'=>'Retail week update','owner_id'=>$u->id,'week'=>'2026-11-02','reason'=>'Weekly reporting update for review','data'=>match($category){'workstream'=>['project'=>'Shopper Marketing','brand'=>'Unilever','task_in_week'=>'<p>Review the retail store data.</p><table><tr><td>Coverage</td><td>80%</td></tr></table>','status'=>'In Progress','priority'=>'High','kpis'=>'<p>Coverage target: 90%</p>'],'people'=>['cohort'=>'unilever','planned_count'=>120,'active_count'=>118,'attrition_count'=>2,'average_headcount'=>119,'leave_count'=>0,'absent_count'=>1],'perfect_store'=>['partner'=>'unilever','breakdown'=>'region','location'=>'Accra','osa_target_percent'=>95,'osa_previous_percent'=>80,'osa_actual_percent'=>88],'activation'=>['partner'=>'castel','brief_status'=>'closed','outcome'=>'won','brief_date'=>'2026-11-02','revert_date'=>'2026-11-03','target_sales'=>100,'total_sales'=>120,'sales_unit'=>'cases','target_reach'=>200,'total_reach'=>180,'conversions'=>45,'conversion_base'=>90],'visibility'=>['partner'=>'unilever','posm_type'=>'Shelf panel','scheduled'=>2500,'deployed'=>500,'planned_scope'=>5,'active_scope'=>3] }];}
 private function approve($manager,$id,$revision=1){return $this->actingAs($manager)->postJson(route('portal.brands.reports.review',$id),['revision'=>$revision,'decision'=>'approve']);}
 public function test_staff_submit_manager_return_correct_and_publish_without_overwriting_an_earlier_week():void {
  $u=$this->staff();$manager=$this->staff('manager');$other=$this->staff();$v=$this->payload($u);
  $id=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202)->json('id');$this->assertDatabaseCount('weekly_consolidated_items',0);
  $this->actingAs($other)->getJson(route('portal.brands.reports.submissions'))->assertJsonCount(0,'entries');$this->approve($other,$id)->assertForbidden();
  $this->actingAs($manager)->postJson(route('portal.brands.reports.review',$id),['revision'=>1,'decision'=>'return'])->assertUnprocessable();
  $this->postJson(route('portal.brands.reports.review',$id),['revision'=>1,'decision'=>'return','note'=>'Add the completed outlet names.'])->assertOk();
  $this->actingAs($u)->getJson(route('portal.brands.reports.submissions'))->assertJsonPath('entries.0.note','Add the completed outlet names.');
  $v['data']['task_in_week']='<p>Review complete for the Accra outlets.</p>';$v+=['submission_id'=>$id,'revision'=>2,'source_type'=>'weekly'];
  $this->postJson(route('portal.brands.reports.submit'),array_replace($v,['revision'=>1]))->assertStatus(409);
  $this->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202);$this->approve($manager,$id,3)->assertOk();
  $this->assertDatabaseHas('weekly_consolidated_items',['department'=>'brands_marketing','lead_staff_id'=>$u->id,'week_start'=>'2026-11-02 00:00:00','report_status'=>'reviewed','created_by'=>$u->id]);
  $this->assertDatabaseHas('performance_outbox',['user_id'=>$u->id,'title'=>'Brands report corrections requested']);
  $row=WeeklyConsolidatedItem::sole();$before=$row->deliverables;
  $v=$this->payload($u)+['source_type'=>'weekly','source_id'=>$row->id,'source_token'=>BrandsReports::token($row)];$v['data']['task_in_week']='Corrected weekly progress';
  $id2=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202)->json('id');$this->assertSame($before,$row->fresh()->deliverables);
  $row->update(['notes'=>'Another manager changed the report']);$this->approve($manager,$id2)->assertStatus(409);$this->assertSame($before,$row->fresh()->deliverables);
  $this->actingAs($u)->postJson(route('portal.brands.reports.submit'),array_replace($v,['source_token'=>BrandsReports::token($row->fresh()),'week'=>'2026-11-09']))->assertUnprocessable();
 }
 public function test_all_report_categories_publish_and_calculate_only_from_recorded_values():void {
  $u=$this->staff();$manager=$this->staff('manager');
  foreach(['people','perfect_store','activation','visibility'] as $category){$id=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$this->payload($u,$category))->assertStatus(202)->json('id');$this->assertDatabaseMissing('performance_registers',['kind'=>$category]);$this->approve($manager,$id)->assertOk();}
  $this->assertDatabaseCount('performance_registers',4);
  $data=app(BrandsDashboard::class)->build(['tab'=>'perfect_store','partner'=>'unilever','date'=>'2026-11-04'],$manager);$row=$data['tables'][0]['rows']->first();$this->assertEquals(88,$row['actual']);$this->assertEquals(8,$row['change']);
  $data=app(BrandsDashboard::class)->build(['tab'=>'activation','date'=>'2026-11-04'],$manager);$row=$data['tables'][1]['rows']->first();$this->assertEquals(120,$row['sales_achieved']);$this->assertEquals(90,$row['reach_achieved']);$this->assertEquals(50,$row['conversion_percent']);
  $data=app(BrandsDashboard::class)->build(['tab'=>'visibility','date'=>'2026-11-04'],$manager);$this->assertEquals(2000,$data['tables'][0]['rows']->first()['gap']);
  $data=app(BrandsDashboard::class)->build(['tab'=>'people','date'=>'2026-11-04'],$manager);$this->assertEquals(1.7,$data['tables'][0]['rows']->first()['attrition_rate']);
  $this->assertDatabaseCount('performance_observations',0);
 }
 public function test_owner_month_year_search_pagination_and_suspended_staff_history():void {
  $u=$this->staff();$other=$this->staff();$former=$this->staff();$former->update(['status'=>'suspended']);
  foreach(range(1,12) as $n)WeeklyConsolidatedItem::create(['department'=>'brands_marketing','workflow_state'=>'legacy','client_name'=>'Action '.$n,'campaign_name'=>'Analytics','week_start'=>'2026-10-19','week_end'=>'2026-10-25','lead_staff_id'=>$u->id,'created_by'=>$u->id,'deliverables'=>'<table><tr><td>Preserved KPI table</td></tr></table>','target_breakdown'=>'<p>Existing commitment</p>','status'=>'In Progress']);
  WeeklyConsolidatedItem::create(['department'=>'brands_marketing','client_name'=>'Historical action','campaign_name'=>'Historic work','week_start'=>'2026-09-14','week_end'=>'2026-09-20','lead_staff_id'=>$former->id,'created_by'=>$former->id,'deliverables'=>'Saved original entry']);
  $service=app(BrandsDashboard::class);$v=['tab'=>'workstream','period'=>'month','date'=>'2026-10-20','q'=>'Analytics','owner'=>$u->id,'page'=>2,'per_page'=>5];$d=$service->build($v,$u);
  $this->assertSame(12,$d['tables'][0]['total']);$this->assertCount(5,$d['tables'][0]['rows']);$this->assertSame('2026-10-01',$d['period']['from']);
  $this->assertStringContainsString('<table>',$d['tables'][0]['rows']->first()['task_in_week']);
  $this->assertSame(0,$service->build(array_replace($v,['owner'=>$other->id]),$u)['tables'][0]['total']);
  $old=$service->build(['tab'=>'workstream','date'=>'2026-09-14'],$u);$this->assertTrue($old['staff']->contains('id',$former->id));
  $current=$service->build(['tab'=>'workstream','date'=>'2026-11-04'],$u);$this->assertFalse($current['staff']->contains('id',$former->id));
  $year=$service->build(['tab'=>'workstream','period'=>'year','date'=>'2026-11-04'],$u);$this->assertSame(13,$year['tables'][0]['total']);$this->assertDatabaseCount('weekly_consolidated_items',13);
 }
 public function test_department_and_owner_spoofing_bad_measurements_and_unconfirmed_partner_are_blocked():void {
  $u=$this->staff();$other=$this->staff();$v=$this->payload($other);$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertForbidden();
  $this->actingAs($this->staff('manager','finance'))->postJson(route('portal.brands.reports.submit'),$this->payload($u))->assertForbidden();$this->getJson(route('portal.brands.reports.submissions'))->assertForbidden();
  $v=$this->payload($u,'perfect_store');$v['data']['osa_actual_percent']=180;$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertUnprocessable();
  $private=PerformanceRegister::create(['department'=>'finance','kind'=>'expense','reference'=>'PRIVATE','title'=>'Private payroll','owner_id'=>$u->id,'submitted_by'=>$u->id,'period_start'=>'2026-11-02','period_end'=>'2026-11-08','status'=>'submitted','data'=>['actual'=>9999]]);
  $this->getJson(route('portal.brands.reports.detail',['type'=>'register','id'=>$private->id]))->assertNotFound();
  $unknown=PerformanceRegister::create(['department'=>'brands_marketing','kind'=>'campaign','reference'=>'OLD','title'=>'Old activity','owner_id'=>$u->id,'submitted_by'=>$u->id,'approved_by'=>$other->id,'approved_at'=>now(),'period_start'=>'2026-11-02','period_end'=>'2026-11-08','status'=>'completed','data'=>['osa_actual_percent'=>75,'budget'=>9999]]);
  $p=app(BrandsReports::class)->payload($unknown,'register');$this->assertNull($p['data']['partner']);$this->assertArrayNotHasKey('budget',$p['data']);
 }
 public function test_manager_can_submit_own_operational_report_and_dashboard_replaces_old_workspace():void {
  $manager=$this->staff('manager');$id=$this->actingAs($manager)->postJson(route('portal.brands.reports.submit'),$this->payload($manager))->assertStatus(202)->json('id');$this->approve($manager,$id)->assertOk();
  $this->get(route('dashboard',['weekly_department'=>'brands_marketing']))->assertOk()->assertSee('data-brands-reports',false)->assertSee('Activations &amp; Volume Depletion Initiatives',false)->assertDontSee('Brand insight. Retail impact.')->assertDontSee('Earlier Weekly Entries & Row Editing');
  $this->get(route('portal.brands'))->assertOk()->assertSee('data-brands-reports',false)->assertSee('POSM &amp; Stock',false);
 }
 public function test_perfect_store_previous_week_comparison_keeps_owner_and_partner_separate():void {
  $u=$this->staff();$other=$this->staff();$manager=$this->staff('manager');
  foreach([[$u,'unilever',75],[$other,'unilever',20],[$u,'castel',40]] as [$owner,$partner,$value])PerformanceRegister::create(['department'=>'brands_marketing','kind'=>'perfect_store','reference'=>'P-'.$owner->id.$partner,'title'=>'Accra store result','owner_id'=>$owner->id,'submitted_by'=>$owner->id,'approved_by'=>$manager->id,'approved_at'=>now(),'period_start'=>'2026-10-26','period_end'=>'2026-11-01','status'=>'submitted','data'=>['partner'=>$partner,'breakdown'=>'region','location'=>'Accra','osa_actual_percent'=>$value]]);
  $v=$this->payload($u,'perfect_store');unset($v['data']['osa_previous_percent']);$id=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202)->json('id');$this->approve($manager,$id)->assertOk();
  $d=app(BrandsDashboard::class)->build(['tab'=>'perfect_store','date'=>'2026-11-04','partner'=>'unilever','owner'=>$u->id],$manager);$r=$d['tables'][0]['rows']->first();$this->assertEquals(75,$r['previous']);$this->assertEquals(13,$r['change']);
  $d=app(BrandsDashboard::class)->build(['tab'=>'perfect_store','date'=>'2026-11-04','partner'=>'castel'],$manager);$this->assertSame(0,$d['tables'][0]['total']);
 }
 public function test_approved_correction_keeps_original_week_custom_fields_and_version():void {
  $u=$this->staff();$manager=$this->staff('manager');$v=$this->payload($u);$id=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202)->json('id');$this->approve($manager,$id)->assertOk();$row=WeeklyConsolidatedItem::sole();$row->update(['custom_fields'=>['brand'=>'Unilever','retained'=>'Keep this saved value']]);
  $v+=['source_type'=>'weekly','source_id'=>$row->id,'source_token'=>BrandsReports::token($row)];$v['data']['task_in_week']='Corrected report with completed outlet checks';$id=$this->actingAs($u)->postJson(route('portal.brands.reports.submit'),$v)->assertStatus(202)->json('id');$this->approve($manager,$id)->assertOk();
  $this->assertSame('2026-11-02',$row->fresh()->week_start->toDateString());$this->assertSame('Keep this saved value',$row->fresh()->custom_fields['retained']);$this->assertDatabaseCount('weekly_consolidated_items',1);$this->assertDatabaseCount('weekly_report_versions',1);
 }
 public function test_productivity_uses_real_sessions_tasks_and_unmeasured_scores():void {
  $u=$this->staff();Attendance::create(['user_id'=>$u->id,'daily_objective'=>'QA retailer reporting','clock_in_at'=>'2026-11-02 09:00:00','clock_out_at'=>'2026-11-02 18:00:00','status'=>'On Time']);
  Task::create(['department'=>'brands_marketing','title'=>'A real assigned task','assigned_to'=>$u->id,'assigned_by'=>$u->id,'status'=>'Open','priority'=>'High','due_on'=>'2026-11-06']);
  $d=app(BrandsDashboard::class)->build(['tab'=>'productivity','date'=>'2026-11-04','owner'=>$u->id],$u);$this->assertEquals(8,$d['summary'][0][1]);$this->assertSame(1,$d['summary'][1][1]);$this->assertSame(0,$d['summary'][2][1]);$this->assertNull($d['summary'][5][1]);
 }
 public function test_a_week_crossing_month_end_is_not_counted_in_two_months():void {
  $u=$this->staff();WeeklyConsolidatedItem::create(['department'=>'brands_marketing','workflow_state'=>'legacy','client_name'=>'Month boundary report','campaign_name'=>'Analytics','week_start'=>'2026-10-26','week_end'=>'2026-11-01','lead_staff_id'=>$u->id,'created_by'=>$u->id,'deliverables'=>'Saved October reporting week','status'=>'Done']);
  $service=app(BrandsDashboard::class);
  $october=$service->build(['tab'=>'workstream','period'=>'month','date'=>'2026-10-26'],$u);$november=$service->build(['tab'=>'workstream','period'=>'month','date'=>'2026-11-04'],$u);
  $this->assertSame(1,$october['tables'][0]['total']);$this->assertSame(0,$november['tables'][0]['total']);
 }

}
