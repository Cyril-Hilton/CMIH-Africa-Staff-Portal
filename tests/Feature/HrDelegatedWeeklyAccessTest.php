<?php
namespace Tests\Feature;
use App\Models\{User,HrRegisterRecord,HrDashboardActivity};
use App\Services\{HrLiveRegisters,HrDashboard};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NativeFormPayload;
use Tests\TestCase;
class HrDelegatedWeeklyAccessTest extends TestCase {
 use RefreshDatabase,NativeFormPayload;
 private function contributor():User {return User::factory()->create(['id'=>123,'name'=>'Maxwell Addo Opoku','status'=>'active','department'=>'hr_admin','access_role'=>'staff','job_level'=>'executive','position_title'=>null]);}
 public function test_delegated_hr_and_admin_native_forms_save_and_display_without_full_hr_access():void {
  $this->travelTo(now()->setDate(2026,11,25)->setTime(12,0));$u=$this->contributor();$service=app(HrLiveRegisters::class);$this->actingAs($u);
  $this->assertFalse($u->hasFullHrAccess());$this->assertFalse($u->canViewAllPayroll());
  foreach(config('hr_weekly_access.registers') as $kind){
   $schema=$service->schema($kind);$html=$this->get(route('portal.hr-workspace',['hr_tab'=>$schema['tab']]))->assertOk()->getContent();$input=$this->nativeForm($html,route('portal.hr-registers.store',$kind));$data=[];
   foreach($schema['fields'] as $field=>$f){$type=$f['type'];$data[$field]=match(true){$type==='select'=>$f['options'][0],$type==='staff'=>$u->id,$type==='staff_multi'=>[$u->id],$type==='department'=>'hr_admin',$type==='date'=>'2026-11-25',$type==='datetime-local'=>'2026-11-25T10:00',$type==='number'=>1,$type==='checkbox'=>0,str_starts_with($type,'record=')=>array_key_first($service->options(substr($type,7),$u)),$type==='email'=>'audit@example.test',default=>'MAXWELL '.$kind.' '.$field};}
   $input['data']=$data;$saved=$this->post(route('portal.hr-registers.store',$kind),$input)->assertSessionHasNoErrors()->assertRedirect();$this->get($saved->headers->get('Location'))->assertOk()->assertSee('MAXWELL');
  }
  foreach(config('hr_weekly_access.tabs') as $tab){$html=$this->get(route('dashboard',['weekly_department'=>'hr_admin','hr_tab'=>$tab]))->assertOk()->assertDontSee('Payroll &amp; Payslips',false)->getContent();$data=$this->nativeForm($html,route('portal.hr-dashboard.activities.store'));$data=array_replace($data,['category'=>HrDashboard::tabs($u)[$tab][1][0],'activity'=>'NSS '.$tab.' entry','assigned_to'=>$u->id,'opened_on'=>'2026-11-25','deadline'=>'2026-11-26','status'=>'Not Started']);$saved=$this->post(route('portal.hr-dashboard.activities.store'),$data)->assertSessionHasNoErrors()->assertRedirect();$this->get($saved->headers->get('Location'))->assertOk()->assertSee('NSS '.$tab.' entry');}
 }
 public function test_sensitive_records_and_mutations_are_restricted_even_with_direct_urls():void {
  $u=$this->contributor();$this->actingAs($u);
  foreach(['staff','staff_document','candidate','probation','appraisal_record','improvement_plan','employee_case','welfare_case','feedback','exit'] as $kind)$this->post(route('portal.hr-registers.store',$kind),['data'=>['name'=>'Forbidden fixture']])->assertForbidden();
  $secret=HrRegisterRecord::create(['kind'=>'employee_case','label'=>'CONFIDENTIAL CASE','data'=>['staff_id'=>$u->id],'revision'=>1,'created_by'=>$u->id]);
  $this->get(route('portal.hr-workspace',['hr_tab'=>'relations']))->assertForbidden();
  $this->get(route('portal.hr-registers.attachment',$secret))->assertNotFound();
  $this->get(route('portal.hr-workspace',['hr_tab'=>'people']))->assertOk()->assertDontSee('CONFIDENTIAL CASE')->assertDontSee('Staff document checklist')->assertDontSee('Add record');
  $this->post(route('portal.payroll.export-register'))->assertStatus(405);
  $this->get(route('portal.payroll.export-register'))->assertForbidden();
  $this->get(route('portal.hr.staff.show',$u))->assertForbidden();
  $other=User::factory()->create(['department'=>'hr_admin','status'=>'active']);
  $a=HrDashboardActivity::create(['tab'=>'people','category'=>'Update staff file','activity'=>'PRIVATE MANAGER ENTRY','assigned_to'=>$other->id,'created_by'=>$other->id,'opened_on'=>today(),'deadline'=>today()->addDay(),'status'=>'Not Started']);
  $this->get(route('portal.hr-workspace',['hr_tab'=>'people']))->assertOk()->assertDontSee('PRIVATE MANAGER ENTRY');
  $this->get(route('portal.hr-dashboard.activities.show',$a))->assertForbidden();
  $this->get(route('portal.hr-workspace',['hr_tab'=>'people','hr_edit'=>$a->id]))->assertForbidden();
  $this->delete(route('portal.hr-dashboard.activities.destroy',$a),['revision'=>1])->assertForbidden();
  $this->get(route('portal.hr-workspace.export',['hr_tab'=>'people','format'=>'xlsx']))->assertOk();
  $u->update(['department'=>'finance']);$this->assertFalse(HrLiveRegisters::contributor($u->fresh()));
 }
 public function test_hr_manager_has_a_working_payroll_entry_point():void {
  $u=User::factory()->create(['department'=>'hr_admin','status'=>'active','access_role'=>'manager','position_title'=>'Department Head','job_level'=>'manager']);
  $this->actingAs($u)->get(route('portal.hr-workspace'))->assertOk()->assertSee('Payroll &amp; Payslips',false)->assertSee(route('portal.payroll'),false);
  $this->get(route('portal.payroll'))->assertOk();
 }
}
