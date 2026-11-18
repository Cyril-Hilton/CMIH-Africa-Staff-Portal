<?php
namespace Tests\Feature;
use App\Models\{User,Asset,HrRegisterRecord,HrDashboardActivity,PerformanceEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class HrLiveWorkspaceTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);$this->travelTo(now()->setDate(2026,11,18)->setTime(10,0));}
 private function hr(): User {return User::factory()->create(['name'=>'Peggy Test','status'=>'active','department'=>'hr_admin','access_role'=>'manager','job_level'=>'manager','position_title'=>'HR Manager']);}
 public function test_all_function_tabs_render_shared_records_and_activity_layout(): void {
  $u=$this->hr();$this->actingAs($u)->get(route('portal.hr-workspace'))->assertOk()->assertSee('Automatic alerts')->assertSee('Next 10 activity deadlines');
  foreach(config('hr_workspace.tabs') as $key=>$tab)$this->get(route('portal.hr-workspace',['hr_tab'=>$key]))->assertOk()->assertSee($tab['label'])->assertSee('Records / Registers')->assertSee('Add Activity');
  $this->get(route('portal.hr'))->assertOk()->assertSee('Live registers');
 }
 public function test_register_totals_share_native_assets_and_exits_show_recovery(): void {
  $u=$this->hr();$staff=User::factory()->create(['status'=>'active','department'=>'creatives']);
  $data=['asset_tag'=>'HR-LAP-001','name'=>'Presentation laptops','category'=>'Laptop','quantity_procured'=>3,'condition'=>'Good','location'=>'Office','assigned_to'=>$staff->id];
  $this->actingAs($u)->post(route('portal.hr-registers.store','asset'),['data'=>$data])->assertSessionHasNoErrors();
  $asset=Asset::sole();$this->assertSame(3,$asset->quantity_procured);$this->assertEquals($staff->id,$asset->assigned_to);
  $stats=app(\App\Services\HrLiveRegisters::class)->stats('assets',$u,'2026-11-16','2026-11-22');$this->assertSame(3,$stats['Total assets']);$this->assertSame(3,$stats['Assigned']);
  $this->post(route('portal.hr-registers.store','exit'),['data'=>['staff_id'=>$staff->id,'exit_type'=>'Resignation','notice_on'=>'2026-11-18','last_day'=>'2026-12-18']])->assertSessionHasNoErrors();
  $this->get(route('portal.hr-workspace',['hr_tab'=>'compliance']))->assertOk()->assertSee('HR-LAP-001')->assertSee('Assets to recover');
 }
 public function test_activity_deadline_automatic_overdue_completion_history_and_cvo_approval(): void {
  $u=$this->hr();$data=['tab'=>'fleet','category'=>'Vehicle servicing','activity'=>'Service pool vehicle','opened_on'=>'2026-11-16','deadline'=>'2026-11-17','assigned_to'=>$u->id,'priority'=>'High','status'=>'Not Started'];
  $this->actingAs($u)->post(route('portal.hr-dashboard.activities.store'),array_diff_key($data,['deadline'=>1]))->assertSessionHasErrors('deadline');
  $this->post(route('portal.hr-dashboard.activities.store'),array_replace($data,['status'=>'Overdue']))->assertSessionHasErrors('status');
  $this->post(route('portal.hr-dashboard.activities.store'),$data)->assertSessionHasNoErrors();$a=HrDashboardActivity::sole();$this->assertTrue($a->isOverdue());
  $this->get(route('portal.hr-workspace',['hr_tab'=>'fleet','hr_status'=>'Overdue']))->assertOk()->assertSee('Service pool vehicle');
  $this->patch(route('portal.hr-dashboard.activities.update',$a),array_replace($data,['revision'=>1,'status'=>'Completed']))->assertSessionHasNoErrors();
  $this->assertNotNull($a->fresh()->completed_at);$this->assertFalse($a->fresh()->isOverdue());$this->assertDatabaseCount('performance_events',2);
  $this->patch(route('portal.hr-dashboard.activities.update',$a),array_replace($data,['revision'=>2,'status'=>'Pending Approval']))->assertSessionHasNoErrors();
  $cvo=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
  $this->actingAs($cvo)->post(route('portal.hr-dashboard.activities.approve',$a),['revision'=>3])->assertSessionHasNoErrors();$this->assertNotNull($a->fresh()->approved_at);$this->assertSame('In Progress',$a->fresh()->status);
 }
 public function test_hod_scope_and_auditor_read_only_confidential_access(): void {
  $hr=$this->hr();$designer=User::factory()->create(['name'=>'Own staff fixture','status'=>'active','department'=>'creatives']);$finance=User::factory()->create(['name'=>'Other department fixture','status'=>'active','department'=>'finance']);
  $hod=User::factory()->create(['status'=>'active','department'=>'creatives','job_level'=>'manager','access_role'=>'manager']);
  $this->actingAs($hr)->post(route('portal.hr-registers.store','employee_case'),['data'=>['staff_id'=>$finance->id,'case_type'=>'Grievance','opened_on'=>'2026-11-18','state'=>'Open','summary'=>'Sensitive case fixture']])->assertSessionHasNoErrors();
  $this->actingAs($hod)->get(route('portal.hr-workspace',['hr_tab'=>'people']))->assertOk()->assertSee('Own staff fixture')->assertDontSee('Other department fixture')->assertDontSee('Emergency contact');
  $this->get(route('portal.hr-workspace',['hr_tab'=>'relations']))->assertForbidden();
  $audit=User::factory()->create(['status'=>'active','department'=>'executive','access_role'=>'staff','position_title'=>'Traffic Manager']);
  // The existing explicit executive audit capability is retained.
  $audit->forceFill(['permissions_matrix'=>[User::HR_FINANCE_AUDIT_PERMISSION,User::EXECUTIVE_ACTIVITY_PERMISSION]])->save();
  $this->actingAs($audit)->get(route('portal.hr-workspace',['hr_tab'=>'relations']))->assertOk()->assertSee('Sensitive case fixture')->assertDontSee('Add record');
  $this->post(route('portal.hr-registers.store','employee_case'),['data'=>[]])->assertForbidden();
  $regular=User::factory()->create(['status'=>'active','department'=>'creatives','job_level'=>'executive','access_role'=>'staff']);
  $this->actingAs($regular)->get(route('portal.hr-workspace'))->assertForbidden();
  $this->post(route('portal.hr-registers.store','vehicle'),['data'=>[]])->assertForbidden();
 }
 public function test_vehicle_import_preview_is_atomic_deduplicates_and_checks_revision(): void {
  $u=$this->hr();$sheet=app(\App\Services\BrandedSpreadsheet::class)->xlsx([
   ['name','registration_number','state','mileage'],['Pool vehicle','GT-1234','Available',12000],
  ]);
  $this->actingAs($u)->post(route('portal.hr-registers.import.preview','vehicle'),['file'=>UploadedFile::fake()->createWithContent('vehicles.xlsx',$sheet)])->assertSessionHasNoErrors();
  $preview=session('hr_register_import.vehicle');$this->assertSame([],$preview['errors']);$this->assertDatabaseCount('hr_register_records',0);
  $this->post(route('portal.hr-registers.import.confirm','vehicle'),['token'=>$preview['token']])->assertSessionHasNoErrors();$this->assertDatabaseCount('hr_register_records',1);
  $this->post(route('portal.hr-registers.import.confirm','vehicle'),['token'=>$preview['token']])->assertUnprocessable();
  $this->post(route('portal.hr-registers.import.preview','vehicle'),['file'=>UploadedFile::fake()->createWithContent('vehicles.xlsx',$sheet)])->assertSessionHasNoErrors();
  $preview=session('hr_register_import.vehicle');$row=HrRegisterRecord::sole();$row->update(['revision'=>2]);
  $this->post(route('portal.hr-registers.import.confirm','vehicle'),['token'=>$preview['token']])->assertConflict();
  $this->assertDatabaseCount('hr_register_records',1);
 }
 public function test_reports_export_pdf_and_excel_and_hod_cannot_export_cases(): void {
  $u=$this->hr();$data=['staff_id'=>$u->id,'case_type'=>'Query','opened_on'=>'2026-11-18','state'=>'Open','summary'=>'Private export fixture'];
  $this->actingAs($u)->post(route('portal.hr-registers.store','employee_case'),['data'=>$data])->assertSessionHasNoErrors();
  $this->get(route('portal.hr-workspace.export',['format'=>'xlsx']))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  $this->get(route('portal.hr-workspace.export',['format'=>'pdf']))->assertOk()->assertHeader('Content-Type','application/pdf');
  $hod=User::factory()->create(['status'=>'active','department'=>'creatives','job_level'=>'manager']);
  $this->actingAs($hod)->get(route('portal.hr-workspace.export',['hr_tab'=>'relations','format'=>'xlsx']))->assertForbidden();
 }
 public function test_public_holidays_apply_to_attendance_and_register_metrics_never_reward_presence_alone(): void {
  $u=$this->hr();$staff=User::factory()->create(['status'=>'active','department'=>'creatives','start_date'=>'2026-11-16']);
  $this->actingAs($u)->post(route('portal.hr-registers.store','holiday'),['data'=>['name'=>'Company public holiday','date'=>'2026-11-17']])->assertSessionHasNoErrors();
  request()->attributes->remove('hr_public_holidays');
  $attendance=\App\Services\PerformanceScoringService::attendanceSummary($staff,\Illuminate\Support\Carbon::parse('2026-11-16'),\Illuminate\Support\Carbon::parse('2026-11-18'));
  $this->assertSame(2,$attendance['expected_workdays']);
  $this->post(route('portal.hr-registers.store','vehicle'),['data'=>['name'=>'Van','registration_number'=>'GT-9000','state'=>'Under maintenance','insurance_expiry'=>'2026-11-30','roadworthy_expiry'=>'2026-11-01']])->assertSessionHasNoErrors();
  $source=app(\App\Services\DepartmentSourcePerformance::class)->build('hr_admin','2026-11-16','2026-11-22');
  $this->assertSame(11,$source['expected']);
  $metric=collect($source['metrics'])->firstWhere('name','Fleet document validity');$this->assertSame(50.0,$metric['achievement']);
  $this->assertLessThan(100,$source['achievement_credit']);
 }

 public function test_existing_vendor_is_updated_in_place_without_a_second_register_entry(): void {
  $u=$this->hr();$vendor=\App\Models\ThirdPartyVendor::create(['name'=>'Existing printing partner','category'=>'Printing']);
  $this->actingAs($u)->get(route('portal.hr-workspace',['hr_tab'=>'supplies']))->assertOk()->assertSee('Existing printing partner');
  $record=app(\App\Services\HrLiveRegisters::class)->records('vendor',$u)->sole();
  $this->patch(route('portal.hr-registers.update',['vendor',$vendor->id]),['revision'=>$record['revision'],'data'=>['name'=>$vendor->name,'service'=>'Printing','state'=>'Active','contact'=>'Office contact','contract_end'=>'2027-01-01']])->assertSessionHasNoErrors();
  $this->assertDatabaseCount('third_party_vendors',1);$this->assertDatabaseMissing('hr_register_records',['kind'=>'vendor']);$this->assertSame('Active',$vendor->fresh()->hr_details['state']);
  $stats=app(\App\Services\HrLiveRegisters::class)->stats('supplies',$u,'2026-11-16','2026-11-22');$this->assertSame(1,$stats['Active vendors']);
 }
 public function test_acknowledgements_and_appraisals_feed_totals_without_duplicate_entry(): void {
  $u=$this->hr();$staff=User::factory()->create(['status'=>'active','department'=>'creatives','start_date'=>'2026-01-01']);
  $this->actingAs($u)->post(route('portal.hr-registers.store','policy'),['data'=>['name'=>'Staff Handbook','version'=>'2026','state'=>'Approved','approved_on'=>'2026-11-01']])->assertSessionHasNoErrors();
  $policy=HrRegisterRecord::where('kind','policy')->sole();
  $this->post(route('portal.hr-registers.store','acknowledgement'),['data'=>['staff_id'=>$staff->id,'policy_id'=>$policy->id,'signed_on'=>'2026-11-18']])->assertSessionHasNoErrors();
  $service=app(\App\Services\HrLiveRegisters::class);$this->assertSame(1,$service->handbookSigned(collect([$u->id,$staff->id])));
  \App\Models\Appraisal::create(['user_id'=>$staff->id,'year'=>2026,'quarter'=>'Q4','status'=>'approved']);
  $this->post(route('portal.hr-registers.store','appraisal_record'),['data'=>['staff_id'=>$staff->id,'period_start'=>'2026-10-01','period_end'=>'2026-12-31','due_on'=>'2026-11-18','completed_on'=>'2026-11-18','score'=>8]])->assertSessionHasNoErrors();
  $stats=$service->stats('performance',$u,'2026-11-16','2026-11-22');$this->assertSame(1,$stats['Appraisals completed']);
 }

 public function test_historical_company_attendance_is_scored_without_manual_hr_report(): void {
  $hr=$this->hr();$hr->update(['start_date'=>'2026-11-18']);$person=User::factory()->create(['status'=>'active','department'=>'client_relations','start_date'=>'2026-11-02']);
  foreach(['2026-11-02','2026-11-03','2026-11-04','2026-11-05'] as $day)\App\Models\Attendance::create(['user_id'=>$person->id,'clock_in_at'=>$day.' 08:30:00','status'=>'On Time','daily_objective'=>'Client work']);
  $source=app(\App\Services\DepartmentSourcePerformance::class)->build('hr_admin','2026-11-02','2026-11-06');
  $area=collect($source['areas'])->firstWhere('name','Attendance & Leave');
  $this->assertTrue($area['reported']);$this->assertGreaterThan(0,$area['achievement_credit']);$this->assertGreaterThanOrEqual(4,$area['records']);
  $metric=collect($source['metrics'])->firstWhere('name','Company attendance coverage');$this->assertSame(4.0,(float)$metric['actual']);$this->assertSame(5.0,(float)$metric['target']);$this->assertSame(80.0,$metric['achievement']);
 }

 public function test_facility_asset_links_and_driver_assignments_do_not_disagree_or_double_count(): void {
  $u=$this->hr();$asset=Asset::create(['name'=>'Office AC','asset_tag'=>'AC-TEST','type'=>'AC','category'=>'AC','quantity_procured'=>2,'condition'=>'Good','status'=>'available','is_warehouse_tracked'=>false]);
  $this->actingAs($u)->post(route('portal.hr-registers.store','facility'),['data'=>['name'=>'Office AC servicing record','category'=>'AC','quantity'=>2,'condition'=>'Good','asset_id'=>$asset->id]])->assertSessionHasNoErrors();
  $stats=app(\App\Services\HrLiveRegisters::class)->stats('facilities',$u,'2026-11-16','2026-11-22');$this->assertSame(2,$stats['AC']);
  $this->post(route('portal.hr-registers.store','driver'),['data'=>['name'=>'Driver fixture']])->assertSessionHasNoErrors();$driver=HrRegisterRecord::where('kind','driver')->sole();
  $this->post(route('portal.hr-registers.store','vehicle'),['data'=>['name'=>'Fleet car','registration_number'=>'TEST-234','state'=>'Available','driver_id'=>$driver->id]])->assertSessionHasNoErrors();
  $car=HrRegisterRecord::where('kind','vehicle')->sole();$this->assertSame($car->id,$driver->fresh()->data['vehicle_id']);
  $this->post(route('portal.hr-registers.store','vehicle'),['data'=>['name'=>'Second car','registration_number'=>'TEST-235','state'=>'Available','driver_id'=>$driver->id]])->assertSessionHasErrors('driver_id');
  $this->assertSame(1,HrRegisterRecord::where('kind','vehicle')->count());
 }

 public function test_confidential_activity_is_not_visible_to_subject_or_support_staff(): void {
  $hr=$this->hr();$staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives']);
  $activity=HrDashboardActivity::create(['tab'=>'relations','category'=>'Issue query','activity'=>'Confidential discipline','staff_id'=>$staff->id,'supporting_staff_ids'=>[$staff->id],'assigned_to'=>$hr->id,'created_by'=>$hr->id,'opened_on'=>'2026-11-18','deadline'=>'2026-11-20','status'=>'Not Started']);
  $this->actingAs($staff)->get(route('portal.hr-dashboard.activities.show',$activity))->assertForbidden();
  $this->get(route('portal.hr-dashboard.activities.attachment',$activity))->assertNotFound();
 }
 public function test_legacy_office_assets_count_individual_items_and_hr_pages_have_return_navigation(): void {
  $u=$this->hr();Asset::create(['name'=>'Existing laptop','type'=>'Hardware','quantity_procured'=>0,'condition'=>'Fair','is_warehouse_tracked'=>false,'added_by'=>$u->id]);
  $this->assertSame(1,app(\App\Services\HrLiveRegisters::class)->stats('assets',$u,'2026-11-16','2026-11-22')['Total assets']);
  $this->actingAs($u)->get(route('portal.hr-workspace',['hr_tab'=>'assets']))->assertOk()->assertSee('Breadcrumbs')->assertSee('Back to HR &amp; Admin',false)->assertSee('Assets &amp; Equipment',false);
  $this->get(route('portal.hr-registers.import',['kind'=>'asset']))->assertOk()->assertSee('Back to HR &amp; Admin',false)->assertSee('Import');
 }
}
