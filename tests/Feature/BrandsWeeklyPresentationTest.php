<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\BrandsWeeklyPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BrandsWeeklyPresentationTest extends TestCase {
 use RefreshDatabase;
 public function test_rich_brief_retains_reported_tables_without_executing_html():void {
  $result=app(BrandsWeeklyPresentation::class)->content('<p>Progress &amp; insights</p><script>alert(1)</script><table><tr><th>KPI</th><th>Target</th><th>Week 42</th></tr><tr><td>OSA</td><td>95%</td><td>92%</td></tr></table><p>Next action: verify stock.</p>');
  $this->assertStringContainsString('Progress & insights',$result['text']);
  $this->assertStringContainsString('Next action',$result['text']);
  $this->assertStringNotContainsString('alert',$result['text']);
  $this->assertSame(['OSA','95%','92%'],$result['tables'][0][1]);
 }
 public function test_existing_weekly_work_keeps_owner_roles_and_filters_without_becoming_verified():void {
  $owner=User::factory()->create();$support=User::factory()->create();
  $base=['department'=>'brands_marketing','workflow_state'=>'legacy','campaign_name'=>'Shopper Marketing','client_name'=>'Retail Availability Review','week_start'=>'2026-10-19','week_end'=>'2026-10-25','lead_staff_id'=>$owner->id,'created_by'=>$owner->id,'supporting_staff_ids'=>[$support->id],'supporting_roles'=>[$support->id=>'Perfect Store report'],'deliverables'=>'<p>Compare coverage and OSA.</p>','status'=>'In Progress'];
  WeeklyConsolidatedItem::create($base);
  WeeklyConsolidatedItem::create(array_replace($base,['department'=>'finance','client_name'=>'Private finance task']));
  WeeklyConsolidatedItem::create(array_replace($base,['week_start'=>'2026-09-01','week_end'=>'2026-09-05']));
  $rows=app(BrandsWeeklyPresentation::class)->build('2026-10-19','2026-10-25',['q'=>'Availability','status'=>'In Progress','sort'=>'deadline']);
  $this->assertSame(1,$rows->total());$row=$rows->items()[0];
  $this->assertSame('Shopper Marketing',$row['workstream']);
  $this->assertSame('Perfect Store report',$row['support'][0]['role']);
  $this->assertSame('In Progress',$row['status']);
  $this->assertArrayNotHasKey('verified_at',$row);
  $this->assertSame(0,app(BrandsWeeklyPresentation::class)->build('2026-10-19','2026-10-25',['status'=>'Done'])->total());
 }
 public function test_dashboard_opens_latest_saved_week_but_respects_explicit_current_week():void {
  $this->travelTo(\Carbon\Carbon::parse('2026-10-26 10:00:00','Africa/Accra'));
  $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  foreach(['brands_marketing','operations_projects','finance','hr_admin'] as $dept){
   WeeklyConsolidatedItem::create(['department'=>$dept,'workflow_state'=>'legacy','campaign_name'=>'Preserved work '.$dept,'client_name'=>'Original activity','week_start'=>'2026-10-19','week_end'=>'2026-10-25','created_by'=>$admin->id,'deliverables'=>'Original report remains available','status'=>'In Progress']);
   $this->actingAs($admin)->get(route('dashboard',['weekly_department'=>$dept]))->assertOk()->assertSee('Showing the latest saved week:')->assertSee('Original report remains available');
   $this->get(route('dashboard',['weekly_department'=>$dept,'weekly_from'=>'2026-10-26','weekly_to'=>'2026-11-01']))->assertOk()->assertDontSee('Showing the latest saved week:');
  }
  $this->assertDatabaseCount('weekly_consolidated_items',4);
 }
 public function test_existing_crm_weekly_reports_remain_available_without_creating_sales_figures():void {
  $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  WeeklyConsolidatedItem::create(['department'=>'client_relations','workflow_state'=>'legacy','client_name'=>'Original CRM Client','campaign_name'=>'Original client project','week_start'=>'2026-10-12','week_end'=>'2026-10-18','deliverables'=>'Original client service narrative','created_by'=>$admin->id,'status'=>'Planned']);
  $this->actingAs($admin)->get(route('dashboard',['weekly_department'=>'client_relations']))->assertOk()->assertSee('Original client service narrative');
  $this->assertDatabaseCount('crm_pipeline_projects',0);
 }
 public function test_old_hr_and_finance_narratives_stay_out_of_other_departments_dashboards():void {
  $staff=User::factory()->create(['access_role'=>'staff','status'=>'active','department'=>'brands_marketing']);
  foreach(['hr_admin','finance'] as $dept){
   WeeklyConsolidatedItem::create(['department'=>$dept,'workflow_state'=>'legacy','campaign_name'=>'Restricted source','week_start'=>now()->startOfWeek(),'week_end'=>now()->endOfWeek(),'deliverables'=>'PRIVATE NARRATIVE '.$dept,'created_by'=>$staff->id,'status'=>'Planned']);
  }
  $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($staff)->get(route('dashboard',['weekly_department'=>'all']))->assertOk()->assertDontSee('PRIVATE NARRATIVE');
 }
 public function test_department_weekly_entry_round_trip_preserves_rich_content():void {
  $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  $html='<h3 style="text-align:center">Weekly report</h3><ol><li><strong>Priority</strong> H<sub>2</sub>O</li></ol><table><tr><td>Target</td><td>95%</td></tr></table><img src="https://example.com/evidence.jpg">';
  foreach(['brands_marketing','operations_projects','finance','hr_admin','creatives'] as $dept){
   $this->actingAs($admin)->post(route('portal.dashboard.weekly-consolidated.store'),['department'=>$dept,'week_start'=>'2026-10-19','week_end'=>'2026-10-25','campaign_name'=>'Rich report '.$dept,'deliverables'=>$html,'target_breakdown'=>$html,'status'=>'Planned'])->assertSessionHasNoErrors();
   $row=WeeklyConsolidatedItem::where('department',$dept)->firstOrFail();
   $this->assertSame($html,$row->deliverables);$this->assertSame($html,$row->target_breakdown);
   $this->actingAs($admin)->get(route('dashboard',['weekly_department'=>$dept,'weekly_from'=>'2026-10-19','weekly_to'=>'2026-10-25']))->assertOk()->assertSee('<table>',false)->assertSee('text-align:center',false);
  }
 }
}
