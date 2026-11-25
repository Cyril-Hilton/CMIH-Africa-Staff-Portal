<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ExecutiveDepartmentNavigationTest extends TestCase {
 use RefreshDatabase;
 public function test_executive_source_department_tabs_and_dashboard_links_load_their_target():void {
  $u=User::factory()->create(['access_role'=>'super_admin','department'=>'executive','status'=>'active']);$this->actingAs($u);
  $person=User::factory()->create(['department'=>'creatives','status'=>'active']);
  $response=$this->get(route('dashboard',['exec_view'=>'reports','exec_department'=>'creatives','exec_person'=>$person->id,'activity_section'=>'briefs'] ))->assertOk();
  $doc=new \DOMDocument;@$doc->loadHTML($response->getContent());$xp=new \DOMXPath($doc);
  $links=$xp->query('//nav[@aria-label="Report departments"]/a');$this->assertGreaterThanOrEqual(6,$links->length);
  foreach($links as $a){$url=$a->getAttribute('href');parse_str(parse_url($url,PHP_URL_QUERY)??'',$p);$this->assertSame('reports',$p['exec_view']);$this->assertArrayNotHasKey('exec_person',$p);$this->assertArrayNotHasKey('activity_section',$p);$this->get($url)->assertOk();}
  $open=$xp->query('//a[contains(text(),"Open department dashboard")]')->item(0);$this->assertNotNull($open);$url=$open->getAttribute('href');$this->assertSame(1,substr_count($url,'#'));$this->assertSame('staff-weekly-performance',parse_url($url,PHP_URL_FRAGMENT));$this->get($url)->assertOk()->assertSee('performance-panel-creatives',false);
  $weekly=$this->get(route('dashboard',['weekly_department'=>'all','exec_person'=>$person->id,'activity_section'=>'briefs']))->assertOk();$doc=new \DOMDocument;@$doc->loadHTML($weekly->getContent());$xp=new \DOMXPath($doc);
  foreach($xp->query('//nav[@aria-label="Weekly performance departments"]/a') as $a){$this->assertTrue($a->hasAttribute('data-no-silent'));$this->assertFalse($a->hasAttribute('data-weekly-department-tab'));$this->assertStringNotContainsString('exec_person',$a->getAttribute('href'));$this->get($a->getAttribute('href'))->assertOk();}
 }
 public function test_retired_operations_tabs_and_entry_tables_are_not_exposed():void {
  $u=User::factory()->create(['department'=>'operations_projects','access_role'=>'manager','job_level'=>'manager','status'=>'active']);$this->actingAs($u);
  foreach(['weekly','overview','follow-up','kpis'] as $tab)$this->get(route('dashboard',['weekly_department'=>'operations_projects','ops_tab'=>$tab]))->assertOk()->assertDontSee('Approvals, Reports &amp; Risks',false)->assertDontSee('KPI Results')->assertDontSee('ops_add=client_approval')->assertDontSee('ops_add=project_report')->assertDontSee('ops_add=risk_issue');
  foreach(['client_approval','project_report','risk_issue','risk'] as $kind)$this->post(route('portal.operations.reporting.save'),['kind'=>$kind])->assertSessionHasErrors('kind');
 }
}
