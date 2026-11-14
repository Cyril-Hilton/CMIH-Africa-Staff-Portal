<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsClockedIn;
use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\Campaign;
use App\Models\OperationsReportSubmission;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\OperationsReporting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsReportingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureUserIsClockedIn::class);
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));
    }

    public function test_team_review_workflow_publishes_to_both_views_and_preserves_week_on_edit(): void
    {
        $staff = $this->person();
        $peer = $this->person();
        $manager = $this->person('manager');
        $report = [
            'kind' => 'activation', 'title' => 'Mall product launch', 'week' => '2026-11-10', 'status' => 'in_progress',
            'data' => ['location' => 'Accra Mall', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-13'],
        ];
        $dashboard = route('dashboard', ['weekly_department' => 'operations_projects', 'ops_tab' => 'projects']);
        $module = route('portal.operations', ['ops_tab' => 'projects']);
        $save = route('portal.operations.reporting.save');
        $this->actingAs($staff)->post($save, $report)->assertRedirect();
        $entry = OperationsReportSubmission::sole();
        $review = route('portal.operations.reporting.review', $entry);
        $this->assertDatabaseCount('performance_registers', 0);
        $this->actingAs($peer)->get($dashboard)->assertOk()->assertDontSee('Mall product launch');
        $this->get(route('portal.operations', ['ops_review' => $entry->id]))->assertOk()->assertDontSee('Mall product launch');
        $this->postJson($review, ['revision' => 1, 'decision' => 'approve'])->assertForbidden();

        $this->actingAs($manager)->get(route('portal.operations', ['ops_review' => $entry->id]))
            ->assertOk()->assertSee('Mall product launch')->assertSee('Return for Correction');
        $this->postJson($review, ['revision' => 1, 'decision' => 'return'])->assertUnprocessable();
        $this->post($review, ['revision' => 1, 'decision' => 'return', 'note' => 'Add the outlet name.'])->assertRedirect();
        $entry->refresh();
        $this->actingAs($staff)->get(route('portal.operations', ['ops_review' => $entry->id]))
            ->assertOk()->assertSee('Add the outlet name.')->assertSee('Resubmit for Review');
        $report['data']['outlet'] = 'Main gate';
        $this->post($save, $report + ['submission' => $entry->id, 'revision' => $entry->revision])->assertRedirect();
        $this->actingAs($manager)->post($review, ['revision' => $entry->fresh()->revision, 'decision' => 'approve'])->assertRedirect();
        $this->get($dashboard)->assertOk()->assertSee('Mall product launch')->assertSee('Main gate');
        $this->get($module)->assertOk()->assertSee('Mall product launch')->assertSee('Main gate');
        $register = PerformanceRegister::sole();

        $report['title'] = 'Mall launch completed';
        $report['week'] = '2026-11-23';
        $this->post($save, $report + ['source' => $register->id, 'source_version' => OperationsReporting::version($register)])->assertRedirect();
        $this->assertSame('2026-11-09', $register->fresh()->period_start->toDateString());
        $this->assertSame('Mall launch completed', $register->fresh()->title);
        $this->assertDatabaseCount('performance_registers', 1);
        $this->post($save, $report + ['owner_id' => $manager->id])->assertRedirect();
        $this->assertDatabaseHas('performance_registers', ['owner_id' => $manager->id, 'approved_by' => $manager->id]);
    }

    public function test_manager_can_correct_and_publish_a_staff_submission_without_taking_ownership(): void
    {
        $staff = $this->person();
        $manager = $this->person('manager');
        $report = ['kind' => 'weekly_update', 'title' => 'Original entry', 'week' => '2026-11-09', 'status' => 'planned'];
        $save = route('portal.operations.reporting.save');
        $this->actingAs($staff)->post($save, $report)->assertRedirect();
        $entry = OperationsReportSubmission::sole();
        $report['title'] = 'Manager corrected entry';
        $report['week'] = '2026-11-16';
        $this->actingAs($manager)->post($save, $report + ['submission' => $entry->id, 'revision' => $entry->revision])->assertRedirect();
        $register = PerformanceRegister::sole();
        $this->assertSame($staff->id, $register->owner_id);
        $this->assertSame($staff->id, $register->submitted_by);
        $this->assertSame('2026-11-09', $register->period_start->toDateString());
        $this->assertSame('Manager corrected entry', $register->title);
        $this->assertSame('published', $entry->fresh()->state);
    }

    public function test_historical_filters_and_shared_access_keep_private_fields_out(): void
    {
        $owner = $this->person('manager');
        $other = $this->person('staff', 'finance');
        $this->actingAs($owner)->post(route('portal.operations.reporting.save'), [
            'kind' => 'supplier', 'title' => 'Historic supplier audit', 'week' => '2026-10-19',
            'status' => 'completed', 'module_entry' => 1,
            'data' => ['supplier_name' => 'Test supplier', 'contact_person' => 'Private contact marker', 'cost' => 912345],
        ])->assertRedirect();
        WeeklyConsolidatedItem::create([
            'department' => 'operations_projects', 'week_start' => '2026-10-19', 'week_end' => '2026-10-25',
            'campaign_name' => 'Historical workstream marker', 'lead_staff_id' => $owner->id, 'created_by' => $owner->id,
            'deliverables' => '<p>Retained weekly content</p>', 'status' => 'Done',
        ]);
        $selection = ['weekly_department' => 'operations_projects', 'ops_tab' => 'execution', 'ops_owner' => $owner->id, 'ops_q' => 'Historic', 'report_date' => '2026-10-19'];
        $this->actingAs($other)->get(route('dashboard', $selection + ['report_period' => 'year']))
            ->assertOk()->assertSee('Historic supplier audit')->assertDontSee('Private contact marker')->assertDontSee('912345');
        $this->get(route('portal.operations'))->assertForbidden();
        $this->actingAs($owner)->get(route('portal.operations', $selection + ['report_period' => 'month']))
            ->assertOk()->assertSee('Private contact marker');
        $this->get(route('dashboard', array_replace($selection, ['ops_tab' => 'weekly', 'report_period' => 'week'])))
            ->assertOk()->assertSee('Historical workstream marker')->assertSee('Retained weekly content');
        $this->get(route('dashboard', array_replace($selection, ['report_date' => '2026-11-14'])))
            ->assertOk()->assertDontSee('Historic supplier audit');
    }

    public function test_inventory_and_activation_rows_show_scoped_warehouse_movements_without_changing_stock(): void
    {
        $manager = $this->person('manager', 'operations_projects', ['position_title' => 'Department Head']);
        $staff = $this->person();
        $this->actingAs($manager)->post(route('portal.operations.reporting.save'), [
            'kind' => 'activation', 'title' => 'Pop-up activation', 'week' => '2026-11-09', 'status' => 'planned',
        ])->assertRedirect();
        $activation = PerformanceRegister::sole();
        $asset = Asset::create(['name' => 'Warehouse sampling booth', 'type' => 'Equipment', 'condition' => 'Good',
            'status' => 'Available', 'warehouse_quantity' => 5, 'is_warehouse_tracked' => true,
            'warehouse_location' => 'Rack A4', 'added_by' => $manager->id]);
        $requestUrl = route('portal.assets.warehouse.request', $asset);
        $this->actingAs($staff)->post($requestUrl, ['activation_link' => 'activation:'.$activation->id, 'requested_quantity' => 2,
            'destination_location' => 'East gate loading area', 'purpose' => 'Set up the booth.'])->assertRedirect();
        $movement = AssetWarehouseRequest::sole();
        $this->assertEquals($activation->id, $movement->operations_activation_id);
        $movement->update(['issued_at' => now(), 'status' => AssetWarehouseRequest::STATUS_ISSUED]);
        $this->assertSame(5, $asset->fresh()->warehouse_quantity);
        $this->actingAs($manager)->get(route('portal.operations', ['ops_tab'=>'projects']))->assertOk()
            ->assertSee('Warehouse sampling booth')->assertSee('East gate loading area')->assertSee('Issued / In Use');
        $this->get(route('portal.operations', ['ops_tab'=>'assets', 'ops_warehouse'=>'movements']))->assertOk()->assertSee('East gate loading area');
        $this->get(route('portal.operations', ['ops_tab'=>'assets']))->assertOk()->assertSee('Rack A4');
        $this->actingAs($this->person())->get(route('portal.operations', ['ops_tab'=>'projects']))
            ->assertOk()->assertDontSee('East gate loading area');
        $this->get(route('portal.assets.warehouse.index', ['operations_activation_id' => $activation->id]))->assertOk()
            ->assertSee('value="activation:'.$activation->id.'" selected', false);
        $this->patch(route('portal.assets.warehouse.activation', $movement), ['activation_link' => null])->assertForbidden();
        $campaign = Campaign::create(['name'=>'Previously saved campaign', 'client_name'=>'Client', 'status'=>'active',
            'start_date'=>'2026-11-09', 'end_date'=>'2026-11-15', 'created_by'=>$manager->id, 'project_lead_id'=>$manager->id]);
        $this->actingAs($manager)->patch(route('portal.assets.warehouse.activation', $movement), ['activation_link' => 'campaign:'.$campaign->id])->assertRedirect();
        $this->assertEquals($campaign->id, $movement->fresh()->campaign_id);
        $this->assertNull($movement->fresh()->operations_activation_id);
        $this->get(route('portal.operations', ['ops_tab'=>'projects']))->assertOk()->assertSee('Previously saved campaign')->assertSee('East gate loading area');
    }

    public function test_supporting_staff_survive_review_and_appear_under_their_staff_filter(): void
    {
        $lead = $this->person();
        $support = $this->person('staff', 'creatives');
        $manager = $this->person('manager');
        $save = route('portal.operations.reporting.save');
        $input = [
            'kind' => 'activation', 'title' => 'Collaborative execution', 'week' => '2026-11-09', 'status' => 'completed',
            'collaborators_present' => 1, 'collaborator_ids' => [$lead->id, $support->id],
        ];
        $this->actingAs($lead)->postJson($save, array_replace($input, ['collaborator_ids' => [99999]]))->assertUnprocessable();
        $this->post($save, $input)->assertSessionHasNoErrors();
        $entry = OperationsReportSubmission::sole();
        $this->assertSame([$support->id], $entry->payload['data']['collaborator_ids']);
        $this->actingAs($manager)->post(route('portal.operations.reporting.review', $entry), ['revision' => 1, 'decision' => 'approve'])->assertSessionHasNoErrors();
        $params = ['weekly_department' => 'operations_projects', 'ops_tab' => 'projects', 'ops_owner' => $support->id];
        $this->actingAs($support)->get(route('dashboard', $params))->assertOk()->assertSee('Collaborative execution');
        $this->postJson($save, $input)->assertForbidden();

        $published = PerformanceRegister::sole();
        $this->actingAs($manager)->post($save, array_replace($input, ['collaborator_ids' => [], 'source' => $published->id, 'source_version' => OperationsReporting::version($published)]))->assertSessionHasNoErrors();
        $this->assertSame([], $published->fresh()->data['collaborator_ids']);
        $this->get(route('dashboard', $params))->assertOk()->assertDontSee('Collaborative execution');
    }

    public function test_execution_charts_compare_periods_and_exclude_incomplete_or_unapproved_rows(): void
    {
        $manager = $this->person('manager');
        foreach ([
            ['2026-11-09', 'completed', [], true],
            ['2026-11-09', 'in_progress', ['activation_status' => 'Completed'], true],
            ['2026-11-09', 'completed', ['activation_status' => 'Cancelled'], true],
            ['2026-11-09', 'completed', [], false],
            ['2026-11-02', 'completed', [], true],
            ['2026-10-19', 'completed', [], true],
        ] as $index => [$week, $status, $data, $approved]) {
            PerformanceRegister::create([
                'department' => 'operations_projects', 'kind' => 'activation', 'reference' => 'TREND-'.$index,
                'title' => 'Retail activation', 'owner_id' => $manager->id, 'period_start' => $week,
                'period_end' => \Illuminate\Support\Carbon::parse($week)->endOfWeek(), 'status' => $status,
                'approved_at' => $approved ? now() : null, 'data' => $data,
            ]);
        }
        $this->actingAs($manager);
        foreach (['week' => '↑ 1 more', 'month' => '↑ 2 more', 'year' => '↑ 4 more'] as $period => $change) {
            $this->get(route('portal.operations', ['ops_tab' => 'overview', 'report_period' => $period, 'report_date' => '2026-11-14']))
                ->assertOk()->assertSee('Activation Executions')->assertSee($change);
        }
    }

    public function test_office_asset_checkout_cannot_change_warehouse_stock_or_return_a_stale_checkout(): void
    {
        $staff = $this->person();
        $params = ['name' => 'Test stand', 'type' => 'Equipment', 'status' => 'available', 'condition' => 'Good'];
        $warehouse = Asset::create($params + ['is_warehouse_tracked' => true, 'warehouse_quantity' => 4]);
        $office = Asset::create($params + ['is_warehouse_tracked' => false]);
        $checkout = route('portal.operations.assets.checkout');

        $this->actingAs($staff)->postJson($checkout, ['asset_id' => $warehouse->id, 'user_id' => $staff->id])->assertUnprocessable();
        $this->assertSame(4, $warehouse->fresh()->warehouse_quantity);
        $this->assertNull($warehouse->fresh()->assigned_to);
        $this->post($checkout, ['asset_id' => $office->id, 'user_id' => $staff->id])->assertSessionHasNoErrors();
        $log = $office->assetLogs()->firstOrFail();
        $return = route('portal.operations.assets.checkin', $log);
        $this->post($return, ['reported_condition' => 'Good'])->assertSessionHasNoErrors();
        $this->post($checkout, ['asset_id' => $office->id, 'user_id' => $staff->id])->assertSessionHasNoErrors();
        $this->postJson($return, ['reported_condition' => 'Good'])->assertUnprocessable();
        $this->assertSame('checked_out', $office->fresh()->status);
    }

    private function person(string $role = 'staff', string $department = 'operations_projects', array $attributes = []): User
    {
        return User::factory()->create($attributes + ['status' => 'active', 'department' => $department, 'access_role' => $role]);
    }
}
