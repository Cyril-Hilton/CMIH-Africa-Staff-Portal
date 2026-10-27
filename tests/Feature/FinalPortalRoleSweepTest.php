<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalPortalRoleSweepTest extends TestCase
{
    use RefreshDatabase;
    public function test_general_pages_and_department_dashboards_for_all_office_roles(): void
    {
        $personas=[];
        foreach(array_keys(config('performance.departments')) as $department)foreach(['staff','manager'] as $role)$personas[]=['department'=>$department,'access_role'=>$role];
        $personas[]=['department'=>'finance','access_role'=>'super_admin'];
        $personas[]=['department'=>'creatives','access_role'=>'staff','job_level'=>'CVO','position_title'=>'CVO'];
        $pages=['portal.tasks','portal.updates','portal.announcements','portal.people','portal.workspace.index','portal.messages','portal.leaves','portal.fleet-requests','portal.profile','portal.finance','portal.finance.advances.index','portal.appraisals.index'];
        $checks=[];
        foreach($personas as $persona){
            $user=User::factory()->create($persona+['status'=>'active']);$this->actingAs($user);
            foreach($pages as $page){$response=$this->get(route($page));$response->assertOk();$checks[]=[$persona['department'],$persona['access_role'],$page,$response->status()];}
            foreach(array_keys(config('performance.departments')) as $department)foreach([now()->startOfWeek(),now()->subWeek()->startOfWeek()] as $week){
                $response=$this->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>$week->toDateString(),'weekly_to'=>$week->copy()->endOfWeek()->toDateString()]));$response->assertOk();$checks[]=[$persona['department'],$persona['access_role'],'dashboard/'.$department,$response->status()];
            }
            $ownRoute=['brands_marketing'=>'portal.brands','operations_projects'=>'portal.operations','finance'=>'portal.finance','hr_admin'=>'portal.hr','creatives'=>'portal.creative','client_relations'=>'portal.crm.index'][$persona['department']];
            $this->get(route($ownRoute))->assertOk();
        }
        if($path=getenv('CMIH_SWEEP_OUTPUT'))file_put_contents($path,json_encode(['personas'=>count($personas),'checks'=>count($checks),'results'=>$checks],JSON_PRETTY_PRINT));
    }
    public function test_department_boundaries_and_clock_in_gate(): void
    {
        foreach(array_keys(config('performance.departments')) as $department){
            $user=User::factory()->create(['department'=>$department,'access_role'=>'staff','status'=>'active']);$this->actingAs($user);
            foreach(['portal.brands'=>'brands_marketing','portal.operations'=>'operations_projects','portal.hr'=>'hr_admin','portal.crm.index'=>'client_relations'] as $route=>$allowed){
                $response=$this->get(route($route));$department===$allowed?$response->assertOk():$response->assertForbidden();
            }
            $this->withHeaders(['X-Test-Enforce-ClockIn'=>'1'])->get(route('portal.messages'))->assertRedirect(route('dashboard'));
            $this->flushHeaders();
        }
    }

    public function test_anonymous_and_inactive_accounts_do_not_enter_private_pages(): void
    {
        foreach(['dashboard','portal.brands','portal.operations','portal.finance','portal.hr','portal.creative','portal.crm.index','portal.messages'] as $route)$this->get(route($route))->assertRedirect(route('login'));
        $inactive=User::factory()->create(['status'=>'suspended']);$this->actingAs($inactive)->get(route('dashboard'))->assertRedirect(route('login'));
    }
    public function test_executive_static_page_inventory_has_no_server_errors_or_missing_routes(): void
    {
        $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$this->actingAs($admin);$checked=[];
        foreach(\Illuminate\Support\Facades\Route::getRoutes() as $route){
            $name=$route->getName();$uri=$route->uri();
            if(!$name||!in_array('GET',$route->methods())||!str_starts_with($uri,'portal/')||str_contains($uri,'{'))continue;
            // Data feeds, exports, integrations and stateful GET links have dedicated tests.
            if(preg_match('~poll|export|callback|dropbox|/data$|/live$|/refresh|/read|/sync|/download|/template|/id-card|/cvo$|/suggest~',$uri))continue;
            $response=$this->get(route($name));
            $this->assertNotContains($response->status(),[404,405,419,500,502,503],$name.' returned '.$response->status());$checked[]=[$name,$response->status()];
        }
        $this->assertGreaterThan(25,count($checked));
        if($path=getenv('CMIH_STATIC_SWEEP_OUTPUT'))file_put_contents($path,json_encode($checked,JSON_PRETTY_PRINT));
    }
}
