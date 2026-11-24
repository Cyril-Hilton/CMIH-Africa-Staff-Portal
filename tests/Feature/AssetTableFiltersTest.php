<?php
namespace Tests\Feature;
use App\Models\{Asset,AssetWarehouseRequest,Campaign,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AssetTableFiltersTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->travelTo(now()->setDate(2026,11,24)->setTime(12,0));}
 private function person($dept,$role='manager'){return User::factory()->create(['status'=>'active','department'=>$dept,'access_role'=>$role,'job_level'=>$role,'position_title'=>$dept==='hr_admin'?'HR Manager':'Department Head']);}
 private function tableText($html){$dom=new \DOMDocument;@$dom->loadHTML($html);$text='';foreach($dom->getElementsByTagName('table') as $table)$text.=$table->textContent;return $text;}
 private function asset($owner,$name,$warehouse){return Asset::create(['name'=>$name,'type'=>'AV equipment','category'=>'Audio','brand'=>'Dove','condition'=>'Good','status'=>'Available','asset_tag'=>str_replace(' ','-',$name),'location'=>'Accra event floor','warehouse_location'=>'Tema Rack 2','is_warehouse_tracked'=>$warehouse,'warehouse_quantity'=>2,'assigned_to'=>$owner->id,'added_by'=>$owner->id]);}
 public function test_filters_combine_before_pagination_in_office_hr_operations_and_warehouse_tables():void{
  $hr=$this->person('hr_admin');$ops=$this->person('operations_projects');
  foreach(range(1,15) as $n)foreach([true,false] as $warehouse)Asset::create(['name'=>'A unrelated '.$n.($warehouse?' W':' O'),'type'=>'Furniture','brand'=>'Other','condition'=>'Fair','status'=>'Available','is_warehouse_tracked'=>$warehouse,'added_by'=>$hr->id]);
  $office=$this->asset($hr,'ZZZ matched office',false);$warehouse=$this->asset($ops,'ZZZ matched warehouse',true);
  $filter=['asset_brand'=>'Dove','asset_location'=>'Accra','asset_type'=>'AV','asset_condition'=>'Good','asset_status'=>'Available'];
  foreach([['portal.assets',$hr,[],$office],['portal.hr-workspace',$hr,['hr_tab'=>'assets'],$office],['portal.assets.warehouse.index',$ops,[],$warehouse],['dashboard',$ops,['weekly_department'=>'operations_projects','ops_tab'=>'assets'],$warehouse]] as [$route,$viewer,$params,$asset]){
   $response=$this->actingAs($viewer)->get(route($route,$params+$filter+['asset_handler'=>$viewer->id,'asset_department'=>$viewer->department]))->assertOk()->assertSee($asset->name)->assertSee('asset_project',false);$this->assertStringNotContainsString('A unrelated 1',$this->tableText($response->getContent()));
   $empty=$this->get(route($route,$params+['asset_item'=>'does-not-exist']))->assertOk();$this->assertStringNotContainsString('ZZZ matched',$this->tableText($empty->getContent()));
   $single=$this->get(route($route,$params+['asset_item'=>$asset->name]))->assertOk()->assertSee($asset->name);$this->assertStringNotContainsString('A unrelated 1',$this->tableText($single->getContent()));
  }
 }
 public function test_generated_sticker_code_finds_an_asset_without_a_custom_tag():void{
  $hr=$this->person('hr_admin');$asset=$this->asset($hr,'Generated sticker asset',false);$asset->update(['asset_tag'=>null]);
  $this->actingAs($hr)->get(route('portal.assets',['asset_item'=>$asset->fresh()->asset_tag_or_code]))->assertOk()->assertSee($asset->name);
  $this->get(route('portal.assets',['asset_item'=>'CMIH-AST-99999999']))->assertOk()->assertDontSee($asset->name);
 }
 public function test_asset_project_filter_uses_real_linked_allocations_and_custody_not_pending_requests():void{
  $ops=$this->person('operations_projects');$handler=$this->person('operations_projects','staff');$asset=$this->asset($ops,'Connected sampling booth',true);
  $campaign=Campaign::create(['name'=>'Red Bull launch','client_name'=>'Red Bull','status'=>'active','created_by'=>$ops->id]);
  $request=AssetWarehouseRequest::create(['asset_id'=>$asset->id,'request_code'=>'FILTER-REQ','requested_by'=>$handler->id,'custodian_id'=>$handler->id,'campaign_id'=>$campaign->id,'requested_quantity'=>1,'purpose'=>'Launch','status'=>AssetWarehouseRequest::STATUS_PENDING_CHECK]);
  $url=fn($f)=>route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'assets']+$f);
  $this->actingAs($ops)->get($url(['asset_project'=>'Red Bull']))->assertOk()->assertSee($asset->name);
  $this->get($url(['asset_handler'=>$handler->id]))->assertOk()->assertDontSee($asset->name);
  $request->update(['status'=>AssetWarehouseRequest::STATUS_ISSUED,'issued_at'=>now()]);
  $this->get($url(['asset_handler'=>$handler->id]))->assertOk()->assertSee($asset->name);
  $request->update(['returned_at'=>now(),'closed_at'=>now(),'status'=>AssetWarehouseRequest::STATUS_CLOSED]);
  $this->get($url(['asset_handler'=>$handler->id]))->assertOk()->assertDontSee($asset->name);
 }
 public function test_filter_links_keep_period_and_selection_and_literal_search_is_not_a_wildcard():void{
  $ops=$this->person('operations_projects');$asset=$this->asset($ops,'Literal % booth',true);$this->asset($ops,'Plain booth',true);
  $this->actingAs($ops)->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'assets','report_period'=>'month','report_date'=>'2026-11-01','asset_item'=>'%']))->assertOk()->assertSee('Literal % booth')->assertDontSee('Plain booth')->assertSee('name="report_period" value="month"',false)->assertSee('Clear asset filters');
  $this->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>'assets','asset_handler'=>'invalid']))->assertSessionHasErrors('asset_handler');
 }
}
