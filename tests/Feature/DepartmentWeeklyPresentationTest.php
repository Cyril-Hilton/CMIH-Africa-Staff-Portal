<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,WeeklyConsolidatedItem};
use App\Services\DepartmentWeeklyPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
class DepartmentWeeklyPresentationTest extends TestCase {
 use RefreshDatabase;
 private function row(string $department,User $u,string $title):WeeklyConsolidatedItem{return WeeklyConsolidatedItem::create(['department'=>$department,'week_start'=>'2026-10-12','week_end'=>'2026-10-18','campaign_name'=>$title,'deliverables'=>'<p>Original progress</p><table><tr><td>Reported result</td><td>72%</td></tr></table>','target_breakdown'=>'Original target narrative','achieved_breakdown'=>'Original result narrative','status'=>'In Progress','created_by'=>$u->id,'lead_staff_id'=>$u->id]);}
 public function test_obsolete_reporting_cards_are_absent_while_source_records_are_retained():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  foreach(['operations_projects','finance','hr_admin','client_relations'] as $department){$row=$this->row($department,$u,'Saved '.$department.' report');$this->actingAs($u)->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18']))->assertOk()->assertDontSee('data-saved-weekly=',false)->assertDontSee('Earlier Saved Weekly Reports')->assertDontSee('Weekly Team Updates (');$this->assertDatabaseHas('weekly_consolidated_items',['id'=>$row->id,'deliverables'=>$row->deliverables]);$this->assertSame('In Progress',$row->fresh()->status);$this->assertNull($row->fresh()->verified_at);}
 }
 public function test_search_status_and_pagination_select_source_records_without_copying_them():void {
  $u=User::factory()->create();for($n=0;$n<10;$n++)$this->row('operations_projects',$u,'Project '.$n);
  $service=app(DepartmentWeeklyPresentation::class);$page=$service->build('operations_projects','2026-10-12','2026-10-18');$this->assertSame(10,$page->total());$this->assertCount(8,$page->items());$this->assertSame(1,$service->build('operations_projects','2026-10-12','2026-10-18',['q'=>'Project 9'])->total());$this->assertSame(0,$service->build('operations_projects','2026-10-12','2026-10-18',['status'=>'Done'])->total());$this->assertDatabaseCount('weekly_consolidated_items',10);
 }
 public function test_confidential_hr_and_finance_narratives_are_not_exposed_to_neutral_staff():void {
  $owner=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$viewer=User::factory()->create(['department'=>'brands_marketing','access_role'=>'staff','status'=>'active']);
  foreach(['finance','hr_admin'] as $department){$row=$this->row($department,$owner,'Private department report');$row->update(['deliverables'=>'Sensitive-only-narrative-'.$department]);$this->actingAs($viewer)->get(route('dashboard',['weekly_department'=>$department,'weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18']))->assertOk()->assertDontSee('Sensitive-only-narrative-'.$department)->assertDontSee('data-saved-weekly="'.$row->id.'"',false);}
 }
 public function test_old_weekly_updates_query_does_not_reintroduce_the_retired_client_relations_card():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);$this->row('client_relations',$u,'Client report');
  $this->actingAs($u)->get(route('dashboard',['weekly_department'=>'client_relations','weekly_from'=>'2026-10-12','weekly_to'=>'2026-10-18','client_reporting_view'=>'updates','department_entry_search'=>'Client']))->assertOk()->assertDontSee('cr-report-updates-tab',false)->assertSee("clientReportingView:'business'",false);
 }
}
