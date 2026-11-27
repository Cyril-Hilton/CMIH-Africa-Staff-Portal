<?php
namespace Tests\Feature;
use App\Models\{User,OperationsDataTransfer,OperationsReportSubmission,PerformanceRegister};
use App\Services\{BrandedSpreadsheet,OperationsReporting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class OperationsActivationTransferTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->travelTo(now()->setDate(2026,11,27)->setTime(12,0));}
 private function person(string $role='staff'):User{return User::factory()->create(['status'=>'active','department'=>'operations_projects','access_role'=>$role,'job_level'=>$role]);}
 private function workbook(array $rows):UploadedFile {$bytes=app(BrandedSpreadsheet::class)->xlsx($rows);return UploadedFile::fake()->createWithContent('activation-import.xlsx',$bytes);}
 public function test_excel_preview_submission_hod_review_and_dashboard_population_are_end_to_end():void {
  $staff=$this->person();$hod=$this->person('manager');$this->actingAs($staff);
  $file=fn()=>$this->workbook([['Activation','Client','Project','Brand','Location','Start date','End date','Status','Remarks'],['Workbook launch','Client A','Launch','Dove','Accra','27/11/2026','28/11/2026','planned','Ready for review']]);
  $this->post(route('portal.operations.activation-transfers.preview'),['file'=>$file(),'week'=>'2026-11-23','date_format'=>'d/m/Y'])->assertSessionHasNoErrors()->assertRedirect();
  $transfer=OperationsDataTransfer::sole();$this->assertSame('preview',$transfer->state);$this->assertDatabaseCount('operations_report_submissions',0);$this->assertDatabaseCount('performance_registers',0);
  $this->get(route('portal.operations.activation-transfers.index',['transfer'=>$transfer->id]))->assertOk()->assertSee('Workbook launch')->assertSee('2026-11-27');
  $this->post(route('portal.operations.activation-transfers.preview'),['file'=>$file(),'week'=>'2026-11-23','date_format'=>'d/m/Y'])->assertRedirect();$this->assertDatabaseCount('operations_data_transfers',1);
  $submit=route('portal.operations.activation-transfers.submit',$transfer);$this->post($submit,['revision'=>1])->assertSessionHasNoErrors()->assertRedirect();$this->post($submit,['revision'=>1])->assertRedirect();$this->assertDatabaseCount('operations_report_submissions',1);
  $entry=OperationsReportSubmission::sole();$this->assertSame('pending',$entry->state);$this->assertSame($staff->id,$entry->payload['owner_id']);
  $dash=route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'projects','report_date'=>'2026-11-23']);$this->get($dash)->assertOk()->assertDontSee('Workbook launch');
  $this->post(route('portal.operations.reporting.review',$entry),['revision'=>1,'decision'=>'approve'])->assertForbidden();
  $this->actingAs($hod)->post(route('portal.operations.reporting.review',$entry),['revision'=>1,'decision'=>'approve'])->assertSessionHasNoErrors()->assertRedirect();
  $this->get($dash)->assertOk()->assertSee('Workbook launch');$this->assertDatabaseCount('performance_registers',1);
  $this->get(route('portal.operations.activation-transfers.index',['transfer'=>$transfer->id]))->assertOk()->assertSee('1 / 1 rows approved');
 }
 public function test_manager_imports_also_require_hod_review_and_invalid_files_never_partially_submit():void {
  $u=$this->person('manager');$this->actingAs($u);
  $this->post(route('portal.operations.activation-transfers.preview'),['file'=>$this->workbook([['Activation','Start date'],['Bad row','31/02/2026']]),'week'=>'2026-11-23','date_format'=>'d/m/Y'])->assertSessionHasErrors('file');$this->assertDatabaseCount('operations_data_transfers',0);
  $this->post(route('portal.operations.activation-transfers.preview'),['file'=>$this->workbook([['Activation','Unsupported detail'],['HOD imported launch','Unknown value']]),'week'=>'2026-11-23','date_format'=>'d/m/Y'])->assertSessionHasNoErrors();$t=OperationsDataTransfer::sole();
  $this->post(route('portal.operations.activation-transfers.submit',$t),['revision'=>1])->assertSessionHasErrors('accept_unmapped');
  $this->post(route('portal.operations.activation-transfers.submit',$t),['revision'=>1,'accept_unmapped'=>1])->assertSessionHasNoErrors();$this->assertSame('pending',OperationsReportSubmission::sole()->state);$this->assertDatabaseCount('performance_registers',0);
 }
 public function test_export_requires_hod_approval_preserves_filter_scope_and_freezes_the_reviewed_data():void {
  $staff=$this->person();$hod=$this->person('manager');$other=$this->person();$base=['department'=>'operations_projects','kind'=>'activation','owner_id'=>$staff->id,'submitted_by'=>$hod->id,'approved_by'=>$hod->id,'approved_at'=>now(),'period_start'=>'2026-11-23','period_end'=>'2026-11-29','status'=>'completed'];
  $record=PerformanceRegister::create($base+['reference'=>'TEST-'.\Illuminate\Support\Str::ulid(),'title'=>'Exported activation','data'=>['brand'=>'Dove','location'=>'Accra']]);PerformanceRegister::create($base+['reference'=>'TEST-'.\Illuminate\Support\Str::ulid(),'title'=>'Excluded activation','data'=>['brand'=>'Other']]);
  $this->actingAs($staff)->post(route('portal.operations.activation-transfers.export'),['format'=>'xlsx','columns'=>['title','brand','owner'],'report_date'=>'2026-11-23','report_period'=>'week','ops_tables'=>['activation'=>['field'=>'data->brand','value'=>'Dove']]])->assertSessionHasNoErrors()->assertRedirect();$t=OperationsDataTransfer::sole();$this->assertCount(1,$t->payload['rows']);
  $download=route('portal.operations.activation-transfers.download',$t);$this->get($download)->assertForbidden();$this->post(route('portal.operations.activation-transfers.review',$t),['revision'=>1,'decision'=>'approve'])->assertForbidden();
  $this->actingAs($hod)->get(route('portal.operations.activation-transfers.index',['transfer'=>$t->id]))->assertOk()->assertSee('Exported activation')->assertDontSee('Excluded activation');
  $this->post(route('portal.operations.activation-transfers.review',$t),['revision'=>1,'decision'=>'approve'])->assertRedirect()->assertSessionHasNoErrors();
  $record->update(['title'=>'Changed after approval']);$r=$this->actingAs($staff)->get($download)->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');$this->assertStringStartsWith('PK',$r->getContent());
  $path=tempnam(sys_get_temp_dir(),'ops-export');file_put_contents($path,$r->getContent());$csv=app(BrandedSpreadsheet::class)->csvForImport($path);unlink($path);$this->assertStringContainsString('Exported activation',$csv);$this->assertStringNotContainsString('Changed after approval',$csv);
  $this->actingAs($other)->get($download)->assertForbidden();$this->get(route('portal.operations.activation-transfers.index',['transfer'=>$t->id]))->assertNotFound();
  $this->actingAs($staff)->post(route('portal.operations.activation-transfers.export'),['format'=>'pdf','columns'=>['title','brand'],'report_date'=>'2026-11-23'])->assertRedirect()->assertSessionHasNoErrors();$pdf=OperationsDataTransfer::latest('id')->first();$this->actingAs($hod)->post(route('portal.operations.activation-transfers.review',$pdf),['revision'=>1,'decision'=>'approve'])->assertRedirect();$r=$this->actingAs($staff)->get(route('portal.operations.activation-transfers.download',$pdf))->assertOk()->assertHeader('Content-Type','application/pdf');$this->assertStringStartsWith('%PDF-',$r->getContent());
 }
 public function test_template_and_transfer_routes_are_restricted_to_operations_and_executives():void {
  $this->actingAs($this->person())->get(route('portal.operations.activation-transfers.template'))->assertOk();
  $this->actingAs(User::factory()->create(['status'=>'active','department'=>'creatives']))->get(route('portal.operations.activation-transfers.index'))->assertForbidden();
 }
}
