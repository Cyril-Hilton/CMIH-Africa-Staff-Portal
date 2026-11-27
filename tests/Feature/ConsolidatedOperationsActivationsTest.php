<?php
namespace Tests\Feature;
use App\Models\{User,PerformanceRegister,OperationsDataTransfer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ConsolidatedOperationsActivationsTest extends TestCase {
 use RefreshDatabase;
 public function test_overview_has_no_duplicate_breakdown_tables_and_projects_has_one_combined_activation_table():void {
  $u=User::factory()->create(['status'=>'active','department'=>'operations_projects','access_role'=>'manager','job_level'=>'manager']);$this->actingAs($u);
  foreach(['dashboard','portal.operations'] as $route){$params=['weekly_department'=>'operations_projects','ops_tab'=>'overview'];$html=$this->get(route($route,$params))->assertOk()->getContent();$this->assertStringNotContainsString('ops-table-breakdown_',$html);$this->assertStringContainsString('data-ops-chart="brand-activity"',$html);
   $html=$this->get(route($route,array_replace($params,['ops_tab'=>'projects'])))->assertOk()->getContent();$this->assertSame(1,substr_count($html,'id="ops-table-activation"'));foreach(['brand','location','lead','sort'] as $key)$this->assertStringContainsString('name="ops_tables[activation]['.$key.']"',$html);
  }
 }
 public function test_combined_brand_location_lead_filters_sorting_pagination_and_export_use_one_query():void {
  $this->travelTo(now()->setDate(2026,11,27));$hod=User::factory()->create(['name'=>'Lead Alpha','status'=>'active','department'=>'operations_projects','access_role'=>'manager','job_level'=>'manager']);$other=User::factory()->create(['name'=>'Lead Beta','status'=>'active','department'=>'operations_projects']);
  $create=function($owner,$title,$brand,$location)use($hod){return PerformanceRegister::create(['reference'=>'TEST-'.\Illuminate\Support\Str::ulid(),'department'=>'operations_projects','kind'=>'activation','title'=>$title,'owner_id'=>$owner->id,'period_start'=>'2026-11-23','period_end'=>'2026-11-29','status'=>'planned','approved_at'=>now(),'approved_by'=>$hod->id,'data'=>['brand'=>$brand,'location'=>$location]]);};
  foreach(range(1,15) as $i)$create($other,'Outside selection '.$i,'Other','Tema');$create($hod,'ZZZ selected launch','Dove','Accra');$create($hod,'AAA selected launch','Dove','Accra');$create($other,'Wrong project lead','Dove','Accra');$create($hod,'Wrong location','Dove','Tema');
  $filters=['brand'=>'Dove','location'=>'Accra','lead'=>'Alpha','sort'=>'title','direction'=>'asc'];$params=['weekly_department'=>'operations_projects','ops_tab'=>'projects','report_date'=>'2026-11-23','ops_tables'=>['activation'=>$filters]];
  $this->actingAs($hod)->get(route('dashboard',$params))->assertOk()->assertSeeInOrder(['AAA selected launch','ZZZ selected launch'])->assertDontSee('Outside selection 1')->assertDontSee('Wrong location')->assertDontSee('Wrong project lead');
  $this->post(route('portal.operations.activation-transfers.export'),$params+['format'=>'xlsx','columns'=>['title','brand','owner']])->assertRedirect()->assertSessionHasNoErrors();$this->assertCount(2,OperationsDataTransfer::sole()->payload['rows']);$this->assertSame('AAA selected launch',OperationsDataTransfer::sole()->payload['rows'][0][0]);
  $filters['brand']='No such brand';$params['ops_tables']['activation']=$filters;$this->get(route('dashboard',$params))->assertOk()->assertDontSee('AAA selected launch')->assertDontSee('ZZZ selected launch');
 }
}
