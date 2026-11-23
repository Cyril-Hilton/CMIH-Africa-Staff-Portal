<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\{Asset,AssetWarehouseRequest,CrmPipelineProject,OperationsHandover,OperationsReportSubmission,PerformanceRegister,User,WarehouseAssetSuggestion};
class OperationsWarehouseConnectionTest extends TestCase {
 use RefreshDatabase;
 private function staff($hod=false){return User::factory()->create(['department'=>'operations_projects','status'=>'active','access_role'=>$hod?'manager':'staff','job_title'=>$hod?'Operations HOD':'Operations Executive']);}
 private function inventory($qty=5){return Asset::create(['name'=>'Event stage','type'=>'Equipment','status'=>'Available','condition'=>'Good','warehouse_quantity'=>$qty,'is_warehouse_tracked'=>true,'warehouse_location'=>'Main warehouse']);}
 public function test_operations_staff_inventory_entries_wait_for_hod_review():void {
  $staff=$this->staff();$hod=$this->staff(true);$payload=['name'=>'New light','type'=>'Equipment','warehouse_quantity'=>4,'status'=>'Available','condition'=>'Good','warehouse_location'=>'Accra'];
  $this->actingAs($staff)->get(route('portal.assets.warehouse.index'))->assertOk()->assertSee('Submit Warehouse Inventory Entry')->assertSee('Submit for HOD Review');
  $this->post(route('portal.assets.warehouse.store'),$payload)->assertSessionHasNoErrors();$this->post(route('portal.assets.warehouse.store'),$payload)->assertSessionHasNoErrors();$this->assertDatabaseCount('assets',0);$this->assertDatabaseCount('warehouse_asset_suggestions',1);
  $row=WarehouseAssetSuggestion::sole();$this->post(route('portal.assets.warehouse.intake.review',$row),['revision'=>1,'decision'=>'approve'])->assertForbidden();
  $this->actingAs($hod)->post(route('portal.assets.warehouse.intake.review',$row),$payload+['decision'=>'approve','revision'=>1])->assertSessionHasNoErrors();$asset=Asset::sole();$this->assertSame(4,$asset->warehouse_quantity);$this->assertSame($staff->id,$asset->added_by);$this->assertSame($hod->id,$row->fresh()->reviewed_by);
 }
 public function test_project_checklist_is_reviewed_then_tracks_actual_custody_and_never_double_deducts_stock():void {
  Storage::fake('public');$owner=$this->staff();$hod=$this->staff(true);$client=User::factory()->create(['department'=>'client_relations','status'=>'active']);$p=CrmPipelineProject::create(['client_name'=>'Dove','project_name'=>'Connected launch','owner_id'=>$client->id,'created_by'=>$client->id,'updated_by'=>$client->id,'stage'=>'project_ongoing','week_start'=>today()->startOfWeek()]);
  $h=OperationsHandover::create(['project_id'=>$p->id,'requester_id'=>$client->id,'lead_id'=>$owner->id,'title'=>$p->project_name,'plan'=>'Install the event stage at the venue','acceptance_criteria'=>'Installed stage meets requirements','event_at'=>now()->addDays(3),'setup_due_at'=>now()->addDays(2),'location'=>'Accra','stage'=>'planning','priority'=>'High','handover_approved_at'=>now(),'handover_approved_by'=>$hod->id,'cr_signoff_at'=>now(),'ops_signoff_at'=>now()]);$asset=$this->inventory();
  $payload=['kind'=>'activation','handover_id'=>$h->id,'title'=>'Owner operational details','week'=>today()->startOfWeek()->toDateString(),'status'=>'planned','warehouse_assets_present'=>1,'warehouse_assets'=>[['asset_id'=>$asset->id,'selected'=>1,'quantity'=>2]],'data'=>['location'=>'Accra']];
  $this->actingAs($owner)->post(route('portal.operations.reporting.save'),$payload)->assertSessionHasNoErrors();$this->assertDatabaseCount('asset_warehouse_requests',0);$submission=OperationsReportSubmission::sole();
  $this->actingAs($hod)->post(route('portal.operations.reporting.review',$submission),['revision'=>$submission->revision,'decision'=>'approve'])->assertSessionHasNoErrors();$this->post(route('portal.operations.reporting.review',$submission),['revision'=>$submission->revision,'decision'=>'approve'])->assertSessionHasNoErrors();
  $movement=AssetWarehouseRequest::sole();$this->assertSame($owner->id,$movement->custodian_id);$this->assertSame($p->id,$movement->crm_project_id);$this->assertSame(5,$asset->fresh()->warehouse_quantity);$this->assertNull($asset->fresh()->assigned_to);
  $action=fn($name)=>$this->actingAs($hod)->post(route('portal.assets.warehouse.action',$movement),['action'=>$name,'note'=>'HOD warehouse review'])->assertSessionHasNoErrors();
  $action('approve_check');$this->actingAs($owner)->post(route('portal.assets.warehouse.evidence',$movement),['stage'=>'pre_use','evidence_image'=>UploadedFile::fake()->image('before.jpg')])->assertSessionHasNoErrors();$action('approve_use');$action('issue');
  $this->assertSame(3,$asset->fresh()->warehouse_quantity);$this->assertSame($owner->id,(int)$asset->fresh()->assigned_to);$this->assertSame($owner->id,(int)$asset->fresh()->last_handled_by);$this->assertSame($hod->id,(int)$movement->fresh()->issued_by);$this->assertNotNull($movement->fresh()->issued_at);
  $this->post(route('portal.assets.warehouse.action',$movement),['action'=>'issue'])->assertForbidden();$this->assertSame(3,$asset->fresh()->warehouse_quantity);
  $response=$this->actingAs($client)->getJson(route('portal.project-journey.accountability',$p))->assertOk();$this->assertStringContainsString('Project assets & custody',$response->json('html'));$this->assertStringContainsString(e($owner->name),$response->json('html'));$this->assertStringContainsString('Event stage',$response->json('html'));
  $other=$this->staff();AssetWarehouseRequest::create(['request_code'=>'SECOND-ISSUE','asset_id'=>$asset->id,'requested_by'=>$other->id,'custodian_id'=>$other->id,'requested_quantity'=>1,'purpose'=>'Other event','status'=>AssetWarehouseRequest::STATUS_ISSUED,'issued_at'=>now()]);$asset->decrement('warehouse_quantity');
  $this->actingAs($owner)->post(route('portal.assets.warehouse.evidence',$movement),['stage'=>'return','evidence_image'=>UploadedFile::fake()->image('returned.jpg')])->assertSessionHasNoErrors();$action('close');$this->assertSame(4,$asset->fresh()->warehouse_quantity);$this->assertSame($other->id,(int)$asset->fresh()->assigned_to);$this->assertSame($owner->id,(int)$asset->fresh()->last_handled_by);
 }
 public function test_zero_stock_and_nonwarehouse_checklist_entries_do_not_create_phantom_requests():void {
  $staff=$this->staff();$asset=$this->inventory(0);$this->actingAs($staff)->post(route('portal.assets.warehouse.request',$asset),['requested_quantity'=>1,'destination_location'=>'Accra','purpose'=>'Event use'])->assertSessionHasErrors('requested_quantity');$this->assertDatabaseCount('asset_warehouse_requests',0);
  $asset->update(['is_warehouse_tracked'=>false]);$this->post(route('portal.operations.reporting.save'),['kind'=>'activation','title'=>'Invalid checklist','week'=>today()->startOfWeek()->toDateString(),'status'=>'planned','warehouse_assets_present'=>1,'warehouse_assets'=>[['asset_id'=>$asset->id,'selected'=>1,'quantity'=>1]]])->assertSessionHasErrors('warehouse_assets');$this->assertDatabaseCount('operations_report_submissions',0);
 }
}
