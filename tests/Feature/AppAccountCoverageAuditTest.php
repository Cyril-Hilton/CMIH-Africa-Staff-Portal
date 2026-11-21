<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppAccountCoverageAuditTest extends TestCase
{
 use RefreshDatabase;
 public function test_account_profiles_can_navigate_without_server_errors_and_private_roles_stay_scoped(): void
 {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-21 09:00:00','Africa/Accra'));
  $input=getenv('CMIH_ACCOUNT_AUDIT_INPUT');
  if($input){$profiles=json_decode(file_get_contents($input),true,flags:JSON_THROW_ON_ERROR)['profiles'];}
  else{$profiles=[];foreach(array_keys(config('performance.departments')) as $dept)foreach(['staff','manager'] as $role)$profiles[]=['id'=>count($profiles)+10,'department'=>$dept,'access_role'=>$role,'status'=>'active','is_internal_staff'=>true,'name'=>'Audit user '.count($profiles)];$profiles[]=['id'=>1,'status'=>'active','access_role'=>'super_admin','name'=>'Audit Admin','is_internal_staff'=>true];$profiles[]=['id'=>114,'status'=>'active','access_role'=>'cvo','job_level'=>'CVO','position_title'=>'CVO','name'=>'Audit CVO','is_internal_staff'=>true];$profiles[]=['id'=>117,'status'=>'active','access_role'=>'staff','department'=>'executive','permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION],'name'=>'Audit reviewer','is_internal_staff'=>true];foreach(['merchandiser','merchandiser_supervisor'] as $role)$profiles[]=['id'=>count($profiles)+120,'status'=>'active','access_role'=>$role,'name'=>'Audit field staff','is_internal_staff'=>false];$profiles[]=['id'=>200,'status'=>'archived','access_role'=>'staff','name'=>'Former staff','is_internal_staff'=>true];}
  foreach($profiles as $p){unset($p['expected'],$p['is_internal_staff']);$p['line_manager_id']=null;$p['email']='account-'.$p['id'].'@audit.example.test';$p['password']=bcrypt('Isolated-audit-password');$p['email_verified_at']=now();$u=User::find($p['id'])??new User;$u->forceFill($p)->save();}
  foreach($profiles as $p)if(!empty($p['line_manager_id'])&&User::find($p['line_manager_id']))User::find($p['id'])->update(['line_manager_id'=>$p['line_manager_id']]);
  $checks=[];$errors=[];
  $common=['dashboard','portal.profile','portal.tasks','portal.updates','portal.announcements','portal.people','portal.directory','portal.workspace.index','portal.messages','portal.leaves','portal.fleet-requests','portal.boardroom-requests','portal.appraisals.index','portal.assets','portal.assets.warehouse.index','portal.visitors','portal.surveys.index','portal.printing-requests.index','portal.performance.index','portal.project-journey.tracker','portal.finance','portal.finance.advances.index'];
  $modules=['portal.brands','portal.operations','portal.hr','portal.hr-workspace','portal.creative','portal.crm.index'];
  foreach($profiles as $p){$u=User::findOrFail($p['id']);$this->actingAs($u);
   if($u->status!=='active'){$r=$this->get(route('dashboard'));$r->assertRedirect(route('login'));$checks[]=['id'=>$u->id,'page'=>'inactive gate','status'=>$r->status()];continue;}
   $pages=$p['is_internal_staff']?array_merge($common,$modules):['dashboard','merchandisers.dashboard','merchandisers.admin.dashboard','portal.hr-workspace','portal.finance'];
   foreach($pages as $page){$q=$page==='dashboard'&&$p['is_internal_staff']?['weekly_department'=>'all']:[];$response=$this->get(route($page,$q));$status=$response->status();$checks[]=['id'=>$u->id,'page'=>$page,'status'=>$status];if(in_array($status,[404,405,419])||$status>=500)$errors[]='Account '.$u->id.' '.$page.' => '.$status.' '.($response->exception?->getMessage()??'');
    if($page==='dashboard'&&$p['is_internal_staff']&&$status===200){if(!\App\Services\FinanceDashboard::canRead($u)&&str_contains($response->getContent(),'id="finance-workspace"'))$errors[]='Account '.$u->id.' private Finance panel exposed';if(!\App\Services\HrLiveRegisters::full($u)&&!$u->isLineManager()&&str_contains($response->getContent(),'aria-label="HR and Admin live registers"'))$errors[]='Account '.$u->id.' private HR panel exposed';}
   }
  }
  if($out=getenv('CMIH_ACCOUNT_AUDIT_OUTPUT'))file_put_contents($out,json_encode(['accounts'=>count($profiles),'checks'=>count($checks),'errors'=>$errors,'results'=>$checks],JSON_PRETTY_PRINT));
  $this->assertSame([],$errors,implode("\n",$errors));
 }
}
