<?php
namespace Tests\Feature;
use App\Models\{User,Asset,PerformanceRegister,OperationsReportSubmission,HrDashboardActivity};
use App\Services\{OperationsReporting,HrLiveRegisters,HrDashboard};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NativeFormPayload;
use Tests\TestCase;
class DepartmentEntryPopulationAuditTest extends TestCase
{
 use RefreshDatabase,NativeFormPayload;
 protected function setUp():void{parent::setUp();$this->travelTo(now()->setDate(2026,11,24)->setTime(12,0));}
 private function person($department,$role='staff'):User{return User::factory()->create(['department'=>$department,'access_role'=>$role,'status'=>'active','job_level'=>$role,'position_title'=>$department==='hr_admin'?'HR Manager':null]);}
 public function test_all_visible_operations_entry_types_save_review_publish_and_edit_in_both_views():void{
  $hod=$this->person('operations_projects','manager');$staff=$this->person('operations_projects');
  $kinds=array_diff(array_keys(OperationsReporting::schemas()),['milestone']);
  foreach([$staff,$hod] as $actor)foreach($kinds as $kind){
   $tab=collect(config('operations_tracker.groups'))->search(fn($g)=>in_array($kind,$g,true))?:'follow-up';
   $params=['weekly_department'=>'operations_projects','ops_tab'=>$tab,'ops_add'=>$kind,'report_period'=>'week','report_date'=>'2026-11-23'];
   $url=route('dashboard',$params);$html=$this->actingAs($actor)->get($url)->assertOk()->getContent();
   $input=$this->nativeForm($html,route('portal.operations.reporting.save'));$input['title']=$title='AUDIT '.$actor->id.' '.$kind;
   $input['data']['remarks']='Entry flow verification';
   if($kind==='posm_deployment')$input['data']=array_replace($input['data'],['asset_code'=>'POSM-'.$actor->id,'quantity'=>2,'deployed_on'=>'2026-11-23']);
   $saved=$this->post(route('portal.operations.reporting.save'),$input)->assertSessionHasNoErrors();
   $submission=OperationsReportSubmission::where('submitted_by',$actor->id)->where('kind',$kind)->latest('id')->firstOrFail();
   $this->assertSame($actor->id===$hod->id?'published':'pending',$submission->state,$kind);
   $this->get($saved->headers->get('Location'))->assertOk()->assertSee($title);
   if($actor->id!==$hod->id){$this->actingAs($hod)->post(route('portal.operations.reporting.review',$submission),['revision'=>$submission->revision,'decision'=>'approve'])->assertSessionHasNoErrors();}
   $record=PerformanceRegister::findOrFail($submission->fresh()->register_id);
   foreach(['dashboard','portal.operations'] as $page)$this->actingAs($hod)->get(route($page,array_replace($params,['ops_add'=>null])))->assertOk()->assertSee($title);
   $editHtml=$this->get(route('dashboard',array_replace($params,['ops_add'=>null,'ops_edit'=>$record->id])))->assertOk()->getContent();
   $edit=$this->nativeForm($editHtml,route('portal.operations.reporting.save'));$edit['title']=$title.' revised';
   $this->post(route('portal.operations.reporting.save'),$edit)->assertSessionHasNoErrors();
   $this->assertSame($title.' revised',$record->fresh()->title);
   $this->get(route('dashboard',array_replace($params,['ops_add'=>null])))->assertOk()->assertSee($title.' revised');
  }
 }
 public function test_every_hr_register_can_be_created_edited_and_displayed_in_its_table():void{
  $hr=$this->person('hr_admin','manager');$service=app(HrLiveRegisters::class);$this->actingAs($hr);
  foreach(array_merge(['staff','asset'],array_keys(config('hr_workspace.registers'))) as $kind){
   $schema=$service->schema($kind);$params=['hr_tab'=>$schema['tab'],'hr_period'=>'week','hr_from'=>'2026-11-23'];
   $url=route('portal.hr-workspace',$params);$html=$this->get($url)->assertOk()->getContent();
   $input=$this->nativeForm($html,route('portal.hr-registers.store',$kind));$data=[];
   foreach($schema['fields'] as $field=>$f){$type=$f['type'];$data[$field]=match(true){$type==='select'=>$f['options'][0],$type==='staff'=>$hr->id,$type==='staff_multi'=>[$hr->id],$type==='department'=>'hr_admin',$type==='date'=>'2026-11-23',$type==='datetime-local'=>'2026-11-23T10:00',$type==='number'=>1,$type==='checkbox'=>0,str_starts_with($type,'record=')=>array_key_first($service->options(substr($type,7),$hr)),$type==='email'=>'audit-'.$kind.'@example.test',default=>'ZZZ AUDIT '.$kind.' '.$field};}
   $input['data']=$data;$before=$service->records($kind,$hr)->pluck('id')->all();
   $response=$this->post(route('portal.hr-registers.store',$kind),$input)->assertSessionHasNoErrors();
   $row=$service->records($kind,$hr)->first(fn($r)=>!in_array($r['id'],$before,true));$this->assertNotNull($row,'Not created: '.$kind);
   $page=$this->get($response->headers->get('Location'))->assertOk();$page->assertSee(route('portal.hr-registers.update',[$kind,$row['id']]),false);
   $edit=$this->nativeForm($page->getContent(),route('portal.hr-registers.update',[$kind,$row['id']]));$edit['data']=$data;
   $field=collect($schema['fields'])->search(fn($f)=>in_array($f['type'],['text','textarea'],true));if($field)$edit['data'][$field]='ZZZ revised '.$kind;
   $updated=$this->patch(route('portal.hr-registers.update',[$kind,$row['id']]),$edit)->assertSessionHasNoErrors();
   $this->get($updated->headers->get('Location'))->assertOk()->assertSee(route('portal.hr-registers.update',[$kind,$row['id']]),false);
   $fresh=$service->records($kind,$hr)->firstWhere('id',$row['id']);if($field)$this->assertSame('ZZZ revised '.$kind,$fresh['data'][$field]);if($kind==='asset')$this->assertSame($data['brand'],$fresh['data']['brand']);
  }
 }
 public function test_hr_saved_vendor_is_visible_even_when_it_sorts_beyond_the_first_page():void{
  $hr=$this->person('hr_admin','manager');foreach(range(1,12) as $n)\App\Models\ThirdPartyVendor::create(['name'=>'A Vendor '.$n,'category'=>'Production']);
  $this->actingAs($hr);$response=$this->post(route('portal.hr-registers.store','vendor'),['data'=>['name'=>'ZZZ newly saved vendor','service'=>'Fabrication','state'=>'Active']])->assertSessionHasNoErrors();
  $saved=\App\Models\ThirdPartyVendor::where('name','ZZZ newly saved vendor')->sole();
  $this->get($response->headers->get('Location'))->assertOk()->assertSee(route('portal.hr-registers.update',['vendor',$saved->id]),false);
 }
 public function test_hr_completed_activity_remains_visible_after_saving_from_a_not_started_filter():void{
  $hr=$this->person('hr_admin','manager');$this->actingAs($hr);
  $data=['tab'=>'fleet','category'=>HrDashboard::tabs($hr)['fleet'][1][0],'activity'=>'Filtered activity audit','opened_on'=>'2026-11-23','deadline'=>'2026-11-25','assigned_to'=>$hr->id,'priority'=>'High','status'=>'Not Started'];
  $this->post(route('portal.hr-dashboard.activities.store'),$data)->assertSessionHasNoErrors();$row=HrDashboardActivity::sole();
  $response=$this->patch(route('portal.hr-dashboard.activities.update',$row),array_replace($data,['revision'=>$row->revision,'status'=>'Completed','hr_status'=>'Not Started','hr_q'=>'Unrelated search']))->assertSessionHasNoErrors();
  $this->get($response->headers->get('Location'))->assertOk()->assertSee('Filtered activity audit');
 }
 public function test_hr_activities_in_every_tab_populate_and_update_after_completion():void{
  $hr=$this->person('hr_admin','manager');$this->actingAs($hr);
  foreach(HrDashboard::tabs($hr) as $tab=>$details){if(empty($details[1]))continue;
   $input=['tab'=>$tab,'category'=>$details[1][0],'activity'=>'AUDIT activity '.$tab,'opened_on'=>'2026-11-23','deadline'=>'2026-11-25','assigned_to'=>$hr->id,'priority'=>'High','status'=>'Not Started'];
   $this->post(route('portal.hr-dashboard.activities.store'),$input)->assertSessionHasNoErrors();$record=HrDashboardActivity::where('activity',$input['activity'])->sole();
   $this->get(route('portal.hr-workspace',['hr_tab'=>$tab]))->assertOk()->assertSee($input['activity']);
   $this->patch(route('portal.hr-dashboard.activities.update',$record),array_replace($input,['revision'=>$record->revision,'status'=>'Completed']))->assertSessionHasNoErrors();
   $this->assertNotNull($record->fresh()->completed_at);$this->get(route('portal.hr-workspace',['hr_tab'=>$tab,'hr_status'=>'Completed']))->assertOk()->assertSee($input['activity']);
  }
 }
}
