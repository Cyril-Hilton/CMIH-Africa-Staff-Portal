<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User,Task,WeeklyConsolidatedItem,WeeklyConsolidatedColumn};
use App\Services\{WeeklyReportingHistory,DashboardInsights,TaskStatsService};
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardUsabilityRegressionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void { parent::setUp(); $this->travelTo(\Carbon\Carbon::parse('2026-10-27 12:00:00')); }
    private function manager($department='brands_marketing') {return User::factory()->create(['department'=>$department,'access_role'=>'manager','status'=>'active']);}
    private function row(User $owner,int $index=0):WeeklyConsolidatedItem {
        return WeeklyConsolidatedItem::create(['department'=>$owner->department,'week_start'=>'2026-10-19','week_end'=>'2026-10-25','campaign_name'=>'Activity '.$index,'objective'=>'Recurring brief','deliverables'=>'<table><tr><td>Previous work</td></tr></table>','target_breakdown'=>'<ul><li>Coverage target</li></ul>','achieved_breakdown'=>'92%','actual_value'=>92,'status'=>'In Progress','created_by'=>$owner->id,'updated_by'=>$owner->id,'custom_fields'=>['scope'=>'<p>Retail coverage</p>','results'=>'Old actual'],'project_ends_on'=>'2026-11-30']);
    }
    public function test_whole_week_preview_and_copy_include_all_pages_preserve_rich_context_and_are_idempotent():void {
        $manager=$this->manager();$author=$this->manager();
        WeeklyConsolidatedColumn::create(['user_id'=>$author->id,'department'=>$author->department,'column_key'=>'scope','label'=>'Scope','type'=>'rich_text','order'=>1]);
        WeeklyConsolidatedColumn::create(['user_id'=>$author->id,'department'=>$author->department,'column_key'=>'results','label'=>'Actual Results','type'=>'rich_text','order'=>2]);
        $rows=collect(range(1,11))->map(fn($i)=>$this->row($author,$i));
        $payload=['department'=>'brands_marketing','source_week'=>'2026-10-19','target_week'=>'2026-10-26'];
        $this->actingAs($manager)->getJson(route('portal.dashboard.weekly-consolidated.copy-week.preview',$payload))->assertOk()->assertJsonCount(11,'items');
        $this->postJson(route('portal.dashboard.weekly-consolidated.copy-week.store'),$payload+['items'=>$rows->pluck('id')->all()])->assertOk()->assertJsonPath('created',11);
        $draft=WeeklyConsolidatedItem::where('carried_from_id',$rows->first()->id)->firstOrFail();
        $this->assertSame('draft',$draft->report_status);$this->assertSame('',$draft->deliverables);$this->assertNull($draft->actual_value);$this->assertNull($draft->achieved_breakdown);
        $this->assertSame('<ul><li>Coverage target</li></ul>',$draft->target_breakdown);$this->assertSame('<p>Retail coverage</p>',$draft->custom_fields['scope']);$this->assertNull($draft->custom_fields['results']);
        $this->assertStringContainsString('<table>',$draft->previous_week_reference['content']['deliverables']);
        $this->assertSame('92%',$rows->first()->fresh()->achieved_breakdown);
        $draft->update(['deliverables'=>'New week already edited']);
        $this->postJson(route('portal.dashboard.weekly-consolidated.copy-week.store'),$payload+['items'=>$rows->pluck('id')->all()])->assertOk()->assertJsonPath('created',0)->assertJsonPath('existing',11);
        $this->assertSame('New week already edited',$draft->fresh()->deliverables);
    }
    public function test_bulk_copy_is_department_scoped_and_rejects_foreign_items_atomically():void {
        $manager=$this->manager();$other=$this->manager('finance');$own=$this->row($manager);$foreign=$this->row($other);
        $payload=['department'=>'brands_marketing','source_week'=>'2026-10-19','target_week'=>'2026-10-26'];
        $staff=User::factory()->create(['access_role'=>'staff','department'=>'brands_marketing','status'=>'active']);
        $this->actingAs($staff)->getJson(route('portal.dashboard.weekly-consolidated.copy-week.preview',$payload))->assertForbidden();
        $this->actingAs($other)->postJson(route('portal.dashboard.weekly-consolidated.copy-week.store'),$payload+['items'=>[$own->id]])->assertForbidden();
        $this->actingAs($manager)->postJson(route('portal.dashboard.weekly-consolidated.copy-week.store'),$payload+['items'=>[$own->id,$foreign->id]])->assertUnprocessable();
        $this->assertDatabaseCount('weekly_consolidated_items',2);
    }
    public function test_week_copy_is_available_to_the_four_reporting_departments_only():void {
        $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        foreach(['brands_marketing','operations_projects','finance','hr_admin'] as $department){
            $manager=$this->manager($department);$row=$this->row($manager);
            $payload=['department'=>$department,'source_week'=>'2026-10-19','target_week'=>'2026-10-26'];
            $this->actingAs($manager)->getJson(route('portal.dashboard.weekly-consolidated.copy-week.preview',$payload))->assertOk()->assertJsonCount(1,'items');
            $this->postJson(route('portal.dashboard.weekly-consolidated.copy-week.store'),$payload+['items'=>[$row->id]])->assertOk()->assertJsonPath('created',1);
        }
        foreach(['creatives','client_relations'] as $department){
            $payload=['department'=>$department,'source_week'=>'2026-10-19','target_week'=>'2026-10-26'];
            $this->actingAs($admin)->getJson(route('portal.dashboard.weekly-consolidated.copy-week.preview',$payload))->assertUnprocessable();
            $this->get(route('dashboard',['weekly_department'=>$department]))->assertOk()->assertDontSee('data-copy-week data-department',false);
        }
    }

    public function test_chart_categories_are_disjoint_and_due_later_today_is_not_overdue():void {
        $manager=$this->manager();
        foreach([['Open',now()->addHours(3)],['In Progress',now()->subHour()],['Completed',now()->subHour()]] as [$status,$due])Task::create(['title'=>$status,'department'=>'brands_marketing','assigned_to'=>$manager->id,'assigned_by'=>$manager->id,'status'=>$status,'priority'=>'Medium','due_on'=>$due]);
        $row=app(DashboardInsights::class)->departments(['brands_marketing'=>'Brands'],[],now()->startOfMonth(),now())[0];
        $this->assertSame(1,$row['completed']);$this->assertSame(1,$row['in_progress']);$this->assertSame(1,$row['delayed']);$this->assertSame(3,array_sum(array_intersect_key($row,array_flip(['completed','in_progress','delayed','review_waiting']))));
    }
    public function test_later_edits_do_not_change_the_week_of_a_recorded_completion():void {
        $manager=$this->manager();$task=Task::create(['title'=>'Completed last week','department'=>'brands_marketing','assigned_to'=>$manager->id,'assigned_by'=>$manager->id,'status'=>'Completed','priority'=>'Medium','completion_review_status'=>'approved','completion_reviewed_at'=>'2026-10-20 12:00:00']);
        $task->update(['details'=>'Edited this week']);$rows=app(DashboardInsights::class)->completionTrend();
        $this->assertSame(1,$rows[2]['completed']);$this->assertSame(0,$rows[3]['completed']);
    }
    public function test_historical_overdue_totals_use_the_selected_period_end():void {
        $manager=$this->manager();$row=$this->row($manager);$row->update(['project_ends_on'=>null]);
        $this->assertTrue($row->isOverdue());
        $cutoff=\Illuminate\Support\Carbon::parse('2026-10-25')->endOfDay();
        $this->assertFalse($row->isOverdue($cutoff));
        $summary=app(\App\Services\WeeklyAccountabilityService::class)->summarize(collect([$row]),$cutoff);
        $this->assertSame(0,$summary['overdue']);
    }

    public function test_current_week_is_explicit_and_history_is_accessible_without_fallback():void {
        $manager=$this->manager();$this->row($manager);
        $this->actingAs($manager)->get(route('dashboard',['weekly_department'=>'brands_marketing']))->assertOk()->assertSee('Current week')->assertViewHas('weeklyConsolidatedItems',fn($rows)=>$rows->total()===0);
        $this->get(route('dashboard',['weekly_department'=>'brands_marketing','weekly_from'=>'2026-10-19','weekly_to'=>'2026-10-25']))->assertOk()->assertSee('Historical week')->assertSee('Activity 0')->assertSee('Copy Entire Week');
    }
}
