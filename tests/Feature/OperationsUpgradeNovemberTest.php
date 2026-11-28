<?php
namespace Tests\Feature;
use App\Models\{User,PerformanceRegister,Asset,Attendance,Task,LeaveApplication};
use App\Services\{AutoClockService,OperationsOverviewCharts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class OperationsUpgradeNovemberTest extends TestCase {
 use RefreshDatabase;
 private function hod(string $department='operations_projects'):User {return User::factory()->create(['name'=>$department==='brands_marketing'?'William Quartey':'Operations Head','department'=>$department,'access_role'=>'manager','job_level'=>'manager','status'=>'active']);}
 public function test_super_admin_sees_weekly_department_navigation_on_the_default_dashboard():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active','department'=>'executive']);$r=$this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Weekly Department Reports')->assertSee('Weekly performance departments',false);
  $doc=new \DOMDocument;@$doc->loadHTML($r->getContent());$xp=new \DOMXPath($doc);$node=$xp->query('//*[@id="weekly-consolidated-live-region"]')->item(0);$this->assertNotNull($node);$this->assertFalse($node->hasAttribute('hidden'));
  foreach(array_keys(config('performance.departments')) as $dept)$this->get(route('dashboard',['weekly_department'=>$dept]))->assertOk()->assertSee('performance-panel-'.$dept,false);
 }
 public function test_william_automatic_clocking_starts_today_stays_open_until_six_and_is_idempotent():void {
  $u=$this->hod('brands_marketing');$this->travelTo(now()->setDate(2026,11,27)->setTime(10,0));AutoClockService::backfillCurrentMonthForUser($u);$a=Attendance::sole();$this->assertSame('2026-11-27',$a->clock_in_at->toDateString());$this->assertNull($a->clock_out_at);$this->assertSame('Coaching, directing and driving teams accountability',$a->daily_objective);$task=Task::sole();$this->assertSame($a->daily_objective,$task->title);$this->assertTrue(AutoClockService::isCompletedAutomaticTask($task,$u));AutoClockService::runAll();$this->assertDatabaseCount('attendance',1);$this->assertDatabaseCount('tasks',1);
  $this->travelTo(now()->setTime(18,1));AutoClockService::handleForUser($u);$this->assertSame('18:00',$a->fresh()->clock_out_at->format('H:i'));
  $this->travelTo(now()->setDate(2026,11,28)->setTime(12,0));AutoClockService::handleForUser($u);$this->assertDatabaseCount('attendance',1);
  $fake=User::factory()->create(['name'=>'William Quartey','department'=>'finance','status'=>'active']);$this->assertFalse(AutoClockService::isAutoClockUser($fake));
 }
 public function test_william_approved_leave_and_nonworking_days_do_not_generate_automatic_work():void {
  $u=$this->hod('brands_marketing');$this->travelTo(now()->setDate(2026,11,30)->setTime(12,0));LeaveApplication::create(['user_id'=>$u->id,'leave_type'=>'Annual','start_date'=>'2026-11-30','end_date'=>'2026-11-30','status'=>'approved','reason'=>'Approved absence']);AutoClockService::handleForUser($u);$this->assertDatabaseCount('attendance',0);$this->assertDatabaseCount('tasks',0);
  $this->travelTo(now()->setDate(2026,12,1));config(['company_calendar.non_working_dates'=>['2026-12-01']]);AutoClockService::handleForUser($u);$this->assertDatabaseCount('attendance',0);
 }
 private function row(User $u,string $kind,string $title,array $data=[]):PerformanceRegister{return PerformanceRegister::create(['reference'=>'TEST-'.\Illuminate\Support\Str::ulid(),'department'=>'operations_projects','kind'=>$kind,'title'=>$title,'owner_id'=>$u->id,'submitted_by'=>$u->id,'approved_by'=>$u->id,'approved_at'=>now(),'period_start'=>'2026-11-23','period_end'=>'2026-11-29','status'=>'planned','data'=>$data]);}
 public function test_independent_table_filters_and_sorting_apply_before_pagination_and_keep_neighbour_selections():void {
  $this->travelTo(now()->setDate(2026,11,27));$u=$this->hod();foreach(range(1,15) as $i)$this->row($u,'activation','Other activation '.$i,['brand'=>'Other']);$one=$this->row($u,'activation','ZZZ needle',['brand'=>'Dove','location'=>'Accra','target_quantity'=>2]);$this->row($u,'activation','AAA needle',['brand'=>'Dove','target_quantity'=>10]);
  $p=['weekly_department'=>'operations_projects','ops_tab'=>'projects','report_date'=>'2026-11-23','ops_tables'=>['activation'=>['field'=>'data->brand','value'=>'Dove','sort'=>'data->target_quantity','direction'=>'desc']]];
  $r=$this->actingAs($u)->get(route('dashboard',$p))->assertOk()->assertSeeInOrder(['AAA needle','ZZZ needle'])->assertDontSee('Other activation 1');$this->assertStringContainsString('ops_tables[activation][sort]',$r->getContent());
  $this->row($u,'procurement','Only procurement',['item_service'=>'Paper']);$this->row($u,'fabrication','Only fabrication',['vendor'=>'Local']);$params=['weekly_department'=>'operations_projects','ops_tab'=>'execution','report_date'=>'2026-11-23','ops_tables'=>['procurement'=>['q'=>'not found'],'fabrication'=>['q'=>'Only fabrication']]];
  $this->get(route('dashboard',$params))->assertOk()->assertDontSee('Only procurement')->assertSee('Only fabrication')->assertSee('name="ops_tables[fabrication][q]" value="Only fabrication"',false);
  $this->get(route('dashboard',$p+['ops_tables'=>['activation'=>['sort'=>'malicious']]]))->assertOk();
  $p['ops_tables']['activation']['sort']='malicious';$this->get(route('dashboard',$p))->assertSessionHasErrors();
 }
 public function test_suppliers_and_production_cannot_return_through_older_configuration_or_direct_table_rendering():void {
  config(['operations_tracker.retired_kinds'=>['milestone'],'operations_tracker.groups.execution'=>['procurement','fabrication','supplier','production']]);
  $u=$this->hod();foreach(['supplier','production'] as $kind){$this->row($u,$kind,'Retired table source '.$kind);$this->assertArrayNotHasKey($kind,\App\Services\OperationsReporting::schemas());$this->assertSame('',trim(view('portal.operations.report-register',['kind'=>$kind])->render()));}
  foreach([$u,User::factory()->create(['department'=>'operations_projects','status'=>'active','access_role'=>'staff'])] as $viewer)foreach(['dashboard','portal.operations'] as $route){
   $html=$this->actingAs($viewer)->get(route($route,['weekly_department'=>'operations_projects','ops_tab'=>'execution']))->assertOk()->getContent();
   foreach(['supplier','production'] as $kind){$this->assertStringNotContainsString('id="ops-table-'.$kind.'"',$html);$this->assertStringNotContainsString('ops_add='.$kind,$html);$this->assertStringNotContainsString('Retired table source '.$kind,$html);}
   $this->assertStringContainsString('id="ops-table-procurement"',$html);$this->assertStringContainsString('id="ops-table-fabrication"',$html);
  }
  $this->assertDatabaseCount('performance_registers',2);
 }
 public function test_assets_can_be_filtered_and_sorted_across_all_inventory_pages():void {
  $u=$this->hod();foreach(range(1,15) as $i)Asset::create(['name'=>'ZZZ unrelated '.$i,'type'=>'Furniture','brand'=>'Other','is_warehouse_tracked'=>true,'added_by'=>$u->id,'status'=>'Available']);foreach(['AAA branded asset','BBB branded asset'] as $name)Asset::create(['name'=>$name,'type'=>'Sound','brand'=>'Dove','location'=>'Accra','is_warehouse_tracked'=>true,'added_by'=>$u->id,'assigned_to'=>$u->id,'status'=>'Available']);
  $r=$this->actingAs($u)->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'assets','asset_brand'=>'Dove','asset_sort'=>'name','asset_direction'=>'desc']))->assertOk()->assertSeeInOrder(['BBB branded asset','AAA branded asset'])->assertDontSee('ZZZ unrelated 1')->assertSee('name="asset_sort"',false);
 }
 public function test_removed_tables_keep_data_but_have_no_forms_or_visible_tables_and_overview_has_four_diagrams():void {
  $this->travelTo(now()->setDate(2026,11,27));$u=$this->hod();foreach(['project','supplier','production'] as $kind)$this->row($u,$kind,'Retired '.$kind.' fixture');
  foreach(['projects','execution'] as $tab)$this->actingAs($u)->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>$tab,'report_date'=>'2026-11-23']))->assertOk()->assertDontSee('Retired project fixture')->assertDontSee('Retired supplier fixture')->assertDontSee('Retired production fixture')->assertDontSee('ops_add=project')->assertDontSee('ops_add=supplier');
  $this->assertDatabaseCount('performance_registers',3);foreach(['project','supplier','production'] as $kind)$this->post(route('portal.operations.reporting.save'),['kind'=>$kind])->assertSessionHasErrors('kind');
  $this->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'overview','report_date'=>'2026-11-23']))->assertOk()->assertSee('data-ops-chart="execution-trend"',false)->assertSee('data-ops-chart="activation-status"',false)->assertSee('data-ops-chart="brand-activity"',false)->assertSee('data-ops-chart="posm-flow"',false);
 }
 public function test_chart_totals_follow_approved_sources_and_invalid_posm_is_not_counted_as_zero():void {
  $u=$this->hod();$a=$this->row($u,'activation','Launch',['brand'=>'Dove','target_quantity'=>10,'actual_quantity'=>12,'activation_status'=>'Completed']);$b=$this->row($u,'posm_deployment','Deployed',['quantity'=>20,'returned_quantity'=>7]);$bad=$this->row($u,'posm_deployment','Unmeasured',['quantity'=>9,'return_status'=>'Returned']);$out=app(OperationsOverviewCharts::class)->build(collect([$a,$b,$bad]),['points'=>[],'current'=>1,'previous'=>0]);$this->assertSame(['Dove'=>1],$out['brands']);$this->assertSame(20.0,$out['posm']['deployed']);$this->assertSame(13.0,$out['posm']['outstanding']);$this->assertSame(1,$out['posm']['needs_correction']);$this->assertSame(1,$out['targets_met']);
 }
}
