<?php
namespace Tests\Feature;

use App\Models\{User, PosmLedger, Campaign, ThirdPartyVendor, PettyCashClaim, PerformanceRegister, WeeklyConsolidatedItem};
use App\Services\SharedModuleActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentModuleAlignmentTest extends TestCase
{
    use RefreshDatabase;
    private function actor(string $department, string $role='manager'): User {
        return User::factory()->create(['department'=>$department,'access_role'=>$role,'status'=>'active']);
    }
    public function test_department_modules_expose_the_same_reporting_records_without_fake_inventory(): void {
        $admin=$this->actor('finance','super_admin');
        foreach (['brands'=>'Retail KPI &amp; Insights','operations'=>'Execution Trackers','finance'=>'Revenue'] as $route=>$label) {
            $page=$this->actingAs($admin)->get(route('portal.'.$route));
            $page->assertOk()->assertSee('Weekly Presentation')->assertSee('Add Weekly Activity')->assertSee($label,false)->assertSee('Copy Entire Week');
        }
        $this->get(route('portal.brands'))->assertSee('No POSM stock items recorded')->assertDontSee('Promotional Flyers Bundle');
    }
    public function test_module_movement_is_visible_on_dashboard_without_private_notes_or_invented_verification(): void {
        $brands=$this->actor('brands_marketing');
        $this->actingAs($brands)->post(route('portal.brands.posm.store'),['item_name'=>'Retail visibility kit','item_type'=>'POSM','client_brand'=>'Shared Brand','quantity_in'=>12,'quantity_out'=>4,'notes'=>'CONFIDENTIAL-VENDOR-RATE'])->assertSessionHasNoErrors();
        $viewer=$this->actor('creatives','staff');
        $page=$this->actingAs($viewer)->get(route('dashboard',['weekly_department'=>'brands_marketing']));
        $page->assertOk()->assertSee('Retail visibility kit')->assertSee('Shared Brand')->assertSee('Recorded movement')->assertDontSee('CONFIDENTIAL-VENDOR-RATE');
        $this->assertDatabaseCount('performance_registers',0);
        $this->assertDatabaseCount('weekly_consolidated_items',0);
    }
    public function test_finance_activity_projection_never_contains_financial_or_personal_payloads(): void {
        $person=$this->actor('brands_marketing','staff');
        PettyCashClaim::create(['user_id'=>$person->id,'amount'=>987654.32,'currency'=>'GHC','description'=>'CONFIDENTIAL-MEDICAL-CLAIM','receipt_path'=>'secret/receipt.pdf','status'=>'Submitted to Finance']);
        $sources=app(SharedModuleActivity::class)->build('finance',today()->startOfWeek()->toDateString(),today()->endOfWeek()->toDateString());
        $payload=json_encode($sources['claims']['rows']->items());
        $this->assertStringContainsString('Submitted to Finance',$payload);
        foreach (['987654','CONFIDENTIAL-MEDICAL-CLAIM','secret/receipt.pdf',$person->name] as $secret) $this->assertStringNotContainsString($secret,$payload);
    }
    public function test_pending_register_is_visible_as_recorded_but_private_content_is_not_published(): void {
        $owner=$this->actor('finance');
        PerformanceRegister::create(['department'=>'finance','kind'=>'cash','reference'=>'PRIVATE-BANK-REFERENCE','title'=>'CONFIDENTIAL-BANK-NAME','owner_id'=>$owner->id,'submitted_by'=>$owner->id,'period_start'=>today()->startOfWeek(),'period_end'=>today()->endOfWeek(),'status'=>'in_progress','data'=>['account_ref'=>'SECRET-ACCOUNT','closing_balance'=>7654321]]);
        $sources=app(SharedModuleActivity::class)->build('finance',today()->startOfWeek()->toDateString(),today()->endOfWeek()->toDateString());
        $payload=json_encode($sources['pending']['rows']->items());
        $this->assertStringContainsString('Awaiting Independent Review',$payload);
        foreach (['SECRET-ACCOUNT','7654321','CONFIDENTIAL-BANK-NAME','PRIVATE-BANK-REFERENCE'] as $secret)$this->assertStringNotContainsString($secret,$payload);
    }
    public function test_cvo_can_see_financial_review_queue_but_other_staff_only_see_own_claims(): void {
        $requester=$this->actor('brands_marketing','staff');
        PettyCashClaim::create(['user_id'=>$requester->id,'amount'=>125,'currency'=>'GHC','description'=>'CVO queue item','status'=>'Submitted to CVO']);
        $cvo=$this->actor('executive','cvo');$cvo->update(['position_title'=>'CVO','job_level'=>'cvo']);
        $this->actingAs($cvo)->get(route('portal.finance'))->assertOk()->assertSee('CVO queue item')->assertSee('Confirmed Revenue');
        $other=$this->actor('creatives','staff');
        $this->actingAs($other)->get(route('portal.finance'))->assertOk()->assertDontSee('CVO queue item')->assertDontSee('module-panel-registers');
    }
    public function test_vendor_links_to_real_campaign_and_existing_task_link_is_preserved(): void {
        $manager=$this->actor('operations_projects');
        $campaign=Campaign::create(['name'=>'Actual Activation','client_name'=>'Brand','created_by'=>$manager->id,'status'=>'active','start_date'=>today()]);
        $this->actingAs($manager)->post(route('portal.operations.vendors.store'),['name'=>'Print Supplier','category'=>'Projects','campaign_id'=>$campaign->id])->assertSessionHasNoErrors();
        $this->assertSame($campaign->id,ThirdPartyVendor::first()->campaign_id);
        $this->get(route('portal.operations'))->assertOk()->assertSee('Actual Activation');
        $this->post(route('portal.operations.vendors.store'),['name'=>'Wrong Link','category'=>'Projects','assigned_project_id'=>999])->assertSessionHasErrors('assigned_project_id');
    }
    public function test_out_of_period_records_are_not_silently_added_and_weekly_snapshots_are_shared(): void {
        $owner=$this->actor('brands_marketing');
        PosmLedger::create(['created_by'=>$owner->id,'item_name'=>'OLD-MOVEMENT','item_type'=>'POSM','quantity_in'=>1,'quantity_out'=>0])->forceFill(['created_at'=>now()->subYear()])->save();
        WeeklyConsolidatedItem::create(['department'=>'brands_marketing','created_by'=>$owner->id,'updated_by'=>$owner->id,'lead_staff_id'=>$owner->id,'week_start'=>now()->startOfWeek(),'week_end'=>now()->endOfWeek(),'campaign_name'=>'Shared Workstream','client_name'=>'Shared Activity','deliverables'=>'<table><tr><td>Shared rich detail</td></tr></table>','status'=>'In Progress']);
        $this->actingAs($owner)->get(route('portal.brands'))->assertOk()->assertSee('Shared rich detail');
        $sources=app(SharedModuleActivity::class)->build('brands_marketing',now()->startOfWeek()->toDateString(),now()->endOfWeek()->toDateString());
        $this->assertSame(0,$sources['posm']['rows']->total());
        $this->get(route('dashboard',['weekly_department'=>'brands_marketing']))->assertOk()->assertSee('Shared rich detail');
        $this->assertDatabaseCount('weekly_consolidated_items',1);
    }

    public function test_strategy_documents_are_private_and_department_authorised(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $owner=$this->actor('brands_marketing');
        $this->actingAs($owner)->post(route('portal.brands.strategy.store'),['name'=>'Restricted strategy','description'=>'Brand strategy notes','file'=>\Illuminate\Http\UploadedFile::fake()->create('strategy.pdf',10,'application/pdf')])->assertSessionHasNoErrors();
        $asset=\App\Models\Asset::where('type','blueprint')->firstOrFail();
        $this->assertStringStartsWith('private:',$asset->image_path);
        $this->get(route('portal.brands.strategy.file',$asset))->assertOk();
        $this->actingAs($this->actor('creatives','staff'))->get(route('portal.brands.strategy.file',$asset))->assertForbidden();
    }
    public function test_register_categories_paginate_without_losing_older_records(): void {
        $owner=$this->actor('brands_marketing');
        foreach(range(1,112) as $id)PerformanceRegister::create(['department'=>'brands_marketing','kind'=>'campaign','reference'=>'ROW-'.$id,'title'=>'Retail activity '.$id,'owner_id'=>$owner->id,'submitted_by'=>$owner->id,'approved_by'=>$owner->id,'approved_at'=>now(),'period_start'=>today()->startOfWeek(),'period_end'=>today()->endOfWeek(),'status'=>'completed','data'=>['outlets_planned'=>10,'outlets_visited'=>8]]);
        $this->actingAs($owner);
        request()->query->set('register_brands_marketing_campaign_page',12);
        $tables=app(\App\Services\WeeklyDepartmentTables::class)->build('brands_marketing',today()->startOfWeek()->toDateString(),today()->endOfWeek()->toDateString());
        $section=$tables['brands_marketing']['sections']['campaign'];
        $this->assertSame(112,$section['total']);$this->assertCount(2,$section['rows']);
        $this->assertSame('ROW-1',$section['rows'][1]['reference']);
    }
    public function test_private_kpi_workspace_rejects_other_departments(): void {
        $user=$this->actor('creatives','staff');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\DepartmentKpiWorkspace::class)->build($user,'finance',today()->startOfMonth()->toDateString(),today()->endOfMonth()->toDateString());
    }
    public function test_approved_retail_counts_produce_correct_ratio_without_scoring_unapproved_records(): void {
        $rows=collect([new PerformanceRegister(['department'=>'brands_marketing','kind'=>'campaign','approved_at'=>now(),'status'=>'completed','data'=>['outlets_planned'=>10,'outlets_visited'=>8]]),new PerformanceRegister(['department'=>'brands_marketing','kind'=>'campaign','approved_at'=>null,'status'=>'completed','data'=>['outlets_planned'=>1000,'outlets_visited'=>1000]])]);
        $metric=collect(app(\App\Services\DepartmentMetricCalculator::class)->calculate($rows))->firstWhere('name','Reported outlet visit completion');
        $this->assertEquals(80,$metric['value']);$this->assertSame(1,$metric['coverage']);
    }

    public function test_posm_links_real_asset_and_uses_event_date_without_adjusting_warehouse_twice(): void {
        $manager=$this->actor('brands_marketing');
        $asset=\App\Models\Asset::create(['name'=>'Registered retail stand','type'=>'POSM','status'=>'available','warehouse_quantity'=>25,'is_warehouse_tracked'=>true,'asset_value'=>998877,'notes'=>'PRIVATE-ASSET-NOTE']);
        $project=Campaign::create(['name'=>'Linked retail project','client_name'=>'Brand','created_by'=>$manager->id,'status'=>'active']);
        $this->actingAs($manager)->post(route('portal.brands.posm.store'),['asset_id'=>$asset->id,'campaign_id'=>$project->id,'occurred_on'=>now()->subMonth()->toDateString(),'item_name'=>'Typed alternate label','item_type'=>'POSM','quantity_in'=>0,'quantity_out'=>5])->assertSessionHasNoErrors();
        $row=PosmLedger::firstOrFail();$this->assertSame('Registered retail stand',$row->item_name);$this->assertSame($asset->id,$row->asset_id);$this->assertSame(25,$asset->fresh()->warehouse_quantity);
        $current=app(SharedModuleActivity::class)->build('brands_marketing',now()->startOfWeek()->toDateString(),now()->endOfWeek()->toDateString());$this->assertSame(0,$current['posm']['rows']->total());
        $prior=app(SharedModuleActivity::class)->build('brands_marketing',now()->subMonth()->startOfMonth()->toDateString(),now()->subMonth()->endOfMonth()->toDateString());$this->assertSame(1,$prior['posm']['rows']->total());
        $this->assertStringNotContainsString('998877',json_encode($prior));$this->assertStringNotContainsString('PRIVATE-ASSET-NOTE',json_encode($prior));
    }
}
