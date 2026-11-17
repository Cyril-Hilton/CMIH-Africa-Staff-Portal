<?php

namespace Tests\Feature;

use App\Models\{Asset, AssetWarehouseRequest, CrmAccountRelationship, CrmPipelineProject, PerformanceRegister, User};
use App\Services\ExecutiveDepartmentRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDepartmentRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_use_saved_department_records_and_activation_links_with_staff_filters(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 12:00:00'));
        $auditor = User::factory()->create(['department'=>'executive', 'status'=>'active', 'access_role'=>'staff',
            'permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION]]);
        $ops = User::factory()->create(['department'=>'operations_projects', 'status'=>'active']);
        $support = User::factory()->create(['department'=>'creatives', 'status'=>'active']);
        $cr = User::factory()->create(['department'=>'client_relations', 'status'=>'active']);
        $this->actingAs($auditor);

        CrmAccountRelationship::create(['client_name'=>'Sample Beverage', 'owner_id'=>$cr->id, 'created_by'=>$cr->id, 'relationship'=>'primary']);
        $pipeline = CrmPipelineProject::create(['client_name'=>'Sample Beverage','brand'=>'Original Cola','project_name'=>'Retail Growth Plan',
            'owner_id'=>$cr->id,'created_by'=>$cr->id,'updated_by'=>$cr->id,'stage'=>'proposal','week_start'=>'2026-11-16',
            'source_period_confirmed'=>true,'target_amount'=>1000,'actual_amount'=>800,'bottlenecks'=>'Retail execution gap']);
        $base = ['department'=>'operations_projects', 'owner_id'=>$ops->id, 'submitted_by'=>$ops->id, 'approved_by'=>$ops->id,
            'kind'=>'activation', 'status'=>'in_progress', 'approved_at'=>now(), 'period_start'=>'2026-11-16', 'period_end'=>'2026-11-22'];
        $activation = PerformanceRegister::create($base + [
            'title'=>'Retail Launch Test', 'client'=>'Sample Beverage', 'reference'=>'EXEC-TEST-01',
            'data'=>['collaborator_ids'=>[$support->id],'starts_on'=>'2026-11-17','location'=>'Osu','target_quantity'=>120,'actual_quantity'=>90,'target_measure'=>'Store visits','challenges'=>'Rain affected setup'],
        ]);
        $other = PerformanceRegister::create($base + ['title'=>'Different Activation', 'reference'=>'EXEC-TEST-02', 'data'=>[]]);
        $asset = Asset::create(['name'=>'Warehouse Demo Stand','is_warehouse_tracked'=>true,'warehouse_quantity'=>4,'type'=>'Equipment','status'=>'available']);
        AssetWarehouseRequest::create(['request_code'=>'WR-EXEC-QA','asset_id'=>$asset->id,'operations_activation_id'=>$activation->id,
            'requested_by'=>$ops->id,'requested_quantity'=>2,'purpose'=>'Store display','requested_for'=>'2026-11-17','destination_location'=>'Osu','status'=>AssetWarehouseRequest::STATUS_ISSUED]);
        PerformanceRegister::create(array_replace($base, ['kind'=>'risk_issue', 'title'=>'Activation Access Risk', 'reference'=>'EXEC-TEST-RISK',
            'data'=>['activation_id'=>$activation->id,'description'=>'Restricted entrance']]));

        $filter = ['activity_department'=>'operations_projects','activity_staff'=>$support->id,'report_date'=>'2026-11-19'];
        $response = $this->get(route('dashboard', $filter))->assertOk()->assertSee('Department staff')
            ->assertSee('Retail Launch Test')->assertSee('Warehouse Demo Stand')->assertSee('Activation Access Risk')->assertSee($cr->name)
            ->assertSee('Rain affected setup')->assertDontSee('Different Activation');
        $row = $response->viewData('executiveActivity')['records']['groups'][0]['rows']->first();
        $this->assertSame(30.0, $row['fields']['Shortfall (target minus actual)']);
        $this->assertSame($support->name, $row['fields']['Supporting staff']);
        $this->assertSame($cr->name, $row['fields']['Client Relations owner']);

        $revenue = $this->get(route('dashboard', ['activity_department'=>'client_relations','activity_section'=>'revenue','activity_staff'=>$cr->id]))
            ->assertOk()->assertSee('Retail Growth Plan')->assertSee('Retail execution gap');
        $row = $revenue->viewData('executiveActivity')['records']['groups'][1]['rows']->first();
        $this->assertSame('800.00', $row['fields']['Reported (GH₵)']);
        $this->assertNull($row['fields']['Confirmed (GH₵)']);
        $this->assertNull($row['fields']['Shortfall vs. confirmed (GH₵)']);

        $observer = User::factory()->create(['department'=>'executive','status'=>'active','access_role'=>'staff','permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        $this->actingAs($observer)->get(route('dashboard', ['activity_department'=>'client_relations','activity_section'=>'revenue']))
            ->assertOk()->assertDontSee('Reported (GH₵)')->assertDontSee('Retail execution gap');
        $other->refresh();
        $this->assertSame('Different Activation', $other->title);
    }

    public function test_each_department_source_tab_renders_for_the_auditor(): void
    {
        $user = User::factory()->create(['department'=>'executive','status'=>'active','access_role'=>'staff',
            'permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION]]);
        foreach (ExecutiveDepartmentRecords::SECTIONS as $department => $sections) {
            foreach (array_keys($sections) as $section) {
                $this->actingAs($user)->get(route('dashboard', ['activity_department'=>$department,'activity_section'=>$section]))
                    ->assertOk()->assertSee('Department source reports');
            }
        }
    }
}
