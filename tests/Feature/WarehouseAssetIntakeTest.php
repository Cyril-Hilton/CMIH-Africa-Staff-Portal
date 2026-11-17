<?php

namespace Tests\Feature;

use App\Models\{Asset, PerformanceRegister, User, WarehouseAssetSuggestion};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseAssetIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_and_new_report_assets_wait_for_explicit_warehouse_review(): void
    {
        $lead = User::factory()->create(['status'=>'active', 'department'=>'operations_projects', 'access_role'=>'manager', 'job_level'=>'manager', 'position_title'=>'Operations Manager']);
        $staff = User::factory()->create(['status'=>'active', 'department'=>'operations_projects', 'access_role'=>'staff']);
        $activation = PerformanceRegister::create(['department'=>'operations_projects','kind'=>'activation','title'=>'Past launch','reference'=>'ACT-HIST-TEST',
            'owner_id'=>$lead->id,'submitted_by'=>$staff->id,'status'=>'completed','approved_at'=>now(),
            'period_start'=>'2026-11-02','period_end'=>'2026-11-08','data'=>['planned_assets'=>'<ul><li>Display Stand</li><li>Table</li></ul>']]);
        $posm = PerformanceRegister::create(['department'=>'operations_projects','kind'=>'posm_deployment','title'=>'Recorded deployment','reference'=>'POSM-HIST-TEST',
            'owner_id'=>$lead->id,'submitted_by'=>$lead->id,'status'=>'in_progress','approved_at'=>now(),
            'period_start'=>'2026-11-02','period_end'=>'2026-11-08','data'=>['asset_name'=>'Display Stand','quantity'=>16,'activation_id'=>$activation->id]]);
        $this->artisan('warehouse:queue-existing-assets')->assertSuccessful();
        $this->artisan('warehouse:queue-existing-assets')->assertSuccessful();
        $this->assertDatabaseCount('warehouse_asset_suggestions', 3);
        $this->assertDatabaseCount('assets', 0);
        $suggestion = WarehouseAssetSuggestion::where('source_id', $posm->id)->firstOrFail();
        $this->assertSame(16, $suggestion->reported['quantity']);

        $review = route('portal.assets.warehouse.intake.review', $suggestion);
        $this->actingAs($staff)->get(route('portal.assets.warehouse.intake'))->assertOk()
            ->assertSee('ACT-HIST-TEST')->assertDontSee('POSM-HIST-TEST');
        $this->post($review, ['decision'=>'approve','revision'=>1])->assertForbidden();
        $this->actingAs($lead)->get(route('portal.assets.warehouse.intake'))->assertOk()
            ->assertSee('POSM-HIST-TEST')->assertSee('Approve new asset');
        $fields = ['decision'=>'approve','revision'=>1,'name'=>'Display Stand','type'=>'POSM','warehouse_location'=>'Main warehouse','condition'=>'Good'];
        $this->post($review, $fields)->assertSessionHasErrors('warehouse_quantity');
        $this->post($review, $fields + ['warehouse_quantity'=>3])->assertSessionHasNoErrors();
        $asset = Asset::sole();
        $this->assertSame(3, $asset->warehouse_quantity);
        $this->assertSame($activation->id, $suggestion->fresh()->activation_id);
        $this->assertSame('approved', $suggestion->fresh()->status);
        $this->post($review, $fields + ['warehouse_quantity'=>3])->assertStatus(409);

        $secondary = WarehouseAssetSuggestion::where('source_id', $activation->id)->get()->first(fn ($row) => $row->reported['name'] === 'Display Stand');
        $this->post(route('portal.assets.warehouse.intake.review', $secondary), ['decision'=>'link','revision'=>1,'asset_id'=>$asset->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assets', 1);
        $this->assertSame(3, $asset->fresh()->warehouse_quantity);

        $this->post(route('portal.operations.reporting.save'), [
            'kind'=>'weekly_update','title'=>'Weekly stock contribution','week'=>'2026-11-16','status'=>'planned',
            'data'=>['planned_assets'=>'Portable counter'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Portable counter', WarehouseAssetSuggestion::latest('id')->firstOrFail()->reported['name']);
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_cross_department_collaborators_show_on_rows_but_not_as_operations_staff_tabs(): void
    {
        $lead = User::factory()->create(['status'=>'active','department'=>'operations_projects','access_role'=>'manager']);
        $collaborator = User::factory()->create(['name'=>'Client Relations Support Fixture','status'=>'active','department'=>'client_relations']);
        PerformanceRegister::create(['department'=>'operations_projects','kind'=>'activation','title'=>'Joint Event','reference'=>'ACT-JOINT-TEST',
            'owner_id'=>$lead->id,'submitted_by'=>$lead->id,'status'=>'in_progress','approved_at'=>now(),
            'period_start'=>'2026-11-02','period_end'=>'2026-11-08','data'=>['collaborator_ids'=>[$collaborator->id]]]);
        $response = $this->actingAs($lead)->get(route('dashboard', ['ops_tab'=>'projects','weekly_department'=>'operations_projects','report_date'=>'2026-11-02']))
            ->assertOk()->assertSee('Client Relations Support Fixture')->assertDontSee('ops_owner='.$collaborator->id, false);
        $this->assertStringContainsString('Joint Event', $response->getContent());
    }
}
