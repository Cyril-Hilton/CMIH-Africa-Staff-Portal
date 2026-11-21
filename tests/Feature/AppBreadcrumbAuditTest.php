<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AppBreadcrumbAuditTest extends TestCase {
 use RefreshDatabase;
 public function test_department_tabs_have_a_labelled_trail_back_to_their_overview(): void {
  $u=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);$this->actingAs($u);
  foreach([
   ['portal.finance',['finance_tab'=>'risks'],'Commercial Risks'],['portal.creative',['creative_view'=>'calendar'],'Calendar'],['portal.operations',['ops_tab'=>'assets'],'Assets &amp; POSM'],['portal.brands',['brands_tab'=>'visibility'],'Visibility Deployment'],['portal.hr-workspace',['hr_tab'=>'supplies'],'Supplies, Vendors &amp; Communications'],['dashboard',['weekly_department'=>'all'],'All Departments'],['dashboard',['weekly_department'=>'creatives','creative_view'=>'calendar'],'Calendar'],
  ] as [$route,$q,$label]){
   $r=$this->get(route($route,$q))->assertOk();$dom=new \DOMDocument();@$dom->loadHTML($r->getContent());$xp=new \DOMXPath($dom);$nav=$xp->query('//nav[@aria-label="Breadcrumbs"]')->item(0);$this->assertNotNull($nav,$route);$this->assertStringContainsString(html_entity_decode($label),$nav->textContent);$this->assertStringContainsString('Back to',$nav->textContent);
   foreach($nav->getElementsByTagName('a') as $a){$matched=app('router')->getRoutes()->match(\Illuminate\Http\Request::create($a->getAttribute('href'),'GET'));$this->assertNotNull($matched);}
  }
 }
}
