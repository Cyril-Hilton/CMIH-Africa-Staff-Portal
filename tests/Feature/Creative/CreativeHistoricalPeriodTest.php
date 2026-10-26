<?php
namespace Tests\Feature\Creative;
use Tests\TestCase;
use App\Models\{User,WeeklyConsolidatedItem};
use App\Services\Creative\{CreativeLegacyImport,CreativeReport};
use Illuminate\Foundation\Testing\RefreshDatabase;
class CreativeHistoricalPeriodTest extends TestCase {
 use RefreshDatabase;
 public function test_original_weekly_dates_include_imported_work_without_inventing_milestones():void {
  $this->travelTo(\Carbon\Carbon::parse('2026-10-26 10:00:00','Africa/Accra'));
  $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  WeeklyConsolidatedItem::create(['department'=>'creatives','week_start'=>'2026-10-19','week_end'=>'2026-10-25','campaign_name'=>'Original last-week design','deliverables'=>'Original saved creative work','status'=>'In Progress','created_by'=>$admin->id,'lead_staff_id'=>$admin->id]);
  app(CreativeLegacyImport::class)->run();
  $report=app(CreativeReport::class)->build($admin,'weekly','2026-10-19');
  $this->assertSame(1,$report['total']);$this->assertSame(1,$report['weekly_snapshot']['entries']);$this->assertSame(1,$report['weekly_snapshot']['reported_statuses']['In Progress']);$this->assertSame(0,$report['received']);$this->assertSame(0,$report['submitted']);$this->assertSame(0,$report['verified']);
  $this->assertSame(0,app(CreativeReport::class)->build($admin,'weekly','2026-10-26')['total']);
  $this->actingAs($admin)->getJson('/portal/creative-studio/data?from=2026-10-19&to=2026-10-25')->assertOk()->assertJsonPath('pagination.total',1);
 }
 public function test_historical_completed_without_approval_date_is_not_counted_as_verified():void {
  $u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  $item=\App\Models\CreativeRequest::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'reference'=>'CR-OLD-DONE','title'=>'Historical completed work','stage'=>'completed','legacy'=>true,'source_recorded_at'=>now(),'visibility'=>'shared']);
  $this->actingAs($u)->getJson('/portal/creative-studio/data')->assertOk()->assertJsonPath('completion_summary.verified',0)->assertJsonPath('completion_summary.historical_reported',1)->assertJsonPath('items.0.stage_label','Historically Reported Complete');
  $this->assertNull($item->fresh()->completed_at);
 }
 public function test_creative_email_is_queued_while_in_app_alert_is_immediate():void {
  \Illuminate\Support\Facades\Bus::fake();
  config(['creative.email_enabled'=>true]);$u=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  $item=app(\App\Services\Creative\CreativeWorkflow::class)->create($u,['title'=>'Queue delivery audit','brief'=>'This brief verifies that email does not block a board stage response.','creative_type'=>'Presentation','requested_deadline'=>now()->addWeek(),'priority'=>'High']);
  \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\DeliverCreativeNotifications::class,fn($job)=>$job->requestId===$item->id && $job->connection==='database' && $job->afterCommit===true);
  $this->assertNotNull(\App\Models\PerformanceOutbox::where('url',route('portal.creative',['request'=>$item->id]))->firstOrFail()->dashboard_at);
 }
}
