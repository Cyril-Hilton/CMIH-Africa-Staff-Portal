<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,WeeklyConsolidatedItem};
use App\Services\DepartmentWeeklyPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
class DepartmentWeeklyPresentationTest extends TestCase {
 use RefreshDatabase;
 private function row(string $department,User $u,string $title):WeeklyConsolidatedItem{return WeeklyConsolidatedItem::create(['department'=>$department,'week_start'=>'2026-10-12','week_end'=>'2026-10-18','campaign_name'=>$title,'deliverables'=>'<p>Original progress</p><table><tr><td>Reported result</td><td>72%</td></tr></table>','target_breakdown'=>'Original target narrative','achieved_breakdown'=>'Original result narrative','status'=>'In Progress','created_by'=>$u->id,'lead_staff_id'=>$u->id]);}
 public function test_original_finance_operations_and_hr_reports_render_inside_primary_weekly_views():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  foreach(['operations_projects','finance','hr_admin','client_relations'] as $department){$row=$this->row($department,$u,'Saved '.$department.' report');$this->actingAs($u)->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18']))->assertOk()->assertSee('data-saved-weekly="'.$row->id.'"',false)->assertSee('Original target narrative')->assertSee('Original result narrative')->assertSee('<td>72%</td>',false)->assertSee($department==='client_relations'?'Weekly Team Updates':'Weekly Updates')->assertSee('Edit Weekly Update')->assertSee('data-weekly-entry-actions="'.$row->id.'"',false)->assertDontSee('Earlier Weekly Entries &amp; Row Editing',false)->assertDontSee('Existing Weekly Reporting');$this->assertSame('In Progress',$row->fresh()->status);$this->assertNull($row->fresh()->verified_at);}
 }
 public function test_search_status_and_pagination_select_source_records_without_copying_them():void {
  $u=User::factory()->create();for($n=0;$n<10;$n++)$this->row('operations_projects',$u,'Project '.$n);
  $service=app(DepartmentWeeklyPresentation::class);$page=$service->build('operations_projects','2026-10-12','2026-10-18');$this->assertSame(10,$page->total());$this->assertCount(8,$page->items());$this->assertSame(1,$service->build('operations_projects','2026-10-12','2026-10-18',['q'=>'Project 9'])->total());$this->assertSame(0,$service->build('operations_projects','2026-10-12','2026-10-18',['status'=>'Done'])->total());$this->assertDatabaseCount('weekly_consolidated_items',10);
 }
 public function test_confidential_hr_and_finance_narratives_are_not_exposed_to_neutral_staff():void {
  $owner=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$viewer=User::factory()->create(['department'=>'brands_marketing','access_role'=>'staff','status'=>'active']);
  foreach(['finance','hr_admin'] as $department){$row=$this->row($department,$owner,'Private department report');$row->update(['deliverables'=>'Sensitive-only-narrative-'.$department]);$this->actingAs($viewer)->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18']))->assertOk()->assertDontSee('Sensitive-only-narrative-'.$department)->assertDontSee('data-saved-weekly="'.$row->id.'"',false);}
 }
 public function test_client_relations_search_keeps_the_weekly_updates_tab_selected():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$this->row('client_relations',$u,'Client report');
  $this->actingAs($u)->get(route('dashboard',['weekly_department'=>'client_relations','weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18','client_reporting_view'=>'updates','department_entry_search'=>'Client']))->assertOk()->assertSee('name="client_reporting_view" value="updates"',false)->assertSee('Client report')->assertSee("clientReportingView:'updates'",false);
 }
}
