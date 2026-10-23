<?php

namespace Tests\Feature;

use App\Models\CrmPipelineProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmPipelineImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_traceable_idempotent_and_does_not_treat_forecast_as_actual_revenue(): void
    {
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $actor = $owner;
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'staff', 'status' => 'suspended']);
        $row = ['key' => 'source-row-1', 'sheet' => '2026 Example', 'row' => 5, 'period_label' => '2026 · September', 'confirmed' => true, 'period_start' => '2026-09-01', 'source_forecast' => 75000, 'client' => 'Source client', 'project' => 'Source project', 'stage' => 'proposal_sent', 'proposed_amount' => 150000, 'po_amount' => null, 'bottlenecks' => 'Source private note', 'next_action' => 'Await decision', 'source_values' => ['B' => 'Source client', 'K' => 150000]];
        $data = ['source_id' => '1ydsMLK86iDG5XPp1PcC3_qjh_2CjeJ_-', 'projects' => [$row], 'targets' => [['sheet' => '2026 Example', 'row' => 20, 'confirmed' => true, 'amount' => 100000, 'year' => 2026, 'month' => 9, 'kind' => 'monthly'], ['sheet' => '2026 Example', 'row' => 200, 'confirmed' => false, 'amount' => 90000, 'year' => null, 'month' => null, 'kind' => 'monthly']]];
        $file = tempnam(sys_get_temp_dir(), 'crm-import-');
        $map = tempnam(sys_get_temp_dir(), 'crm-map-');
        file_put_contents($file, json_encode($data));
        file_put_contents($map, json_encode(['2026 Example' => $owner->id]));
        try {
            $args = ['file' => $file, '--owner-map' => $map, '--actor' => $actor->id];
            $this->artisan('crm:import-pipeline', $args)->assertSuccessful();
            $project = CrmPipelineProject::firstOrFail();
            $this->assertNull($project->actual_amount);
            $this->assertEquals(75000, $project->source_forecast);
            $this->assertSame('Source client', $project->source_values['source_values']['B']);
            $project->update(['next_action' => 'Updated by staff after import']);
            $this->artisan('crm:import-pipeline', $args)->assertSuccessful();
            $this->assertDatabaseCount('crm_pipeline_projects', 1);
            $this->assertDatabaseCount('crm_account_targets', 2);
            $this->assertSame('Updated by staff after import', $project->fresh()->next_action);
            $this->assertSame('suspended', $owner->fresh()->status);
            $this->assertFalse(\App\Services\ClientRelationsPipeline::member($owner));
            $this->assertSame($owner->id, $project->owner_id);
            $this->actingAs($actor)->get(route('portal.crm.index'))->assertOk()->assertSee('Source project')->assertSee('2026 · September');
            $outsider = User::factory()->create(['department' => 'creatives', 'status' => 'active']);
            $this->actingAs($outsider)->get(route('portal.crm.history'))->assertForbidden();
        } finally {
            unlink($file);
            unlink($map);
        }
    }

    public function test_imported_project_date_requires_explicit_manager_confirmation_before_publication(): void
    {
        $owner = User::factory()->create(['department' => 'client_relations', 'access_role' => 'staff', 'status' => 'active']);
        $manager = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $project = CrmPipelineProject::create(['owner_id' => $owner->id, 'client_name' => 'Imported client', 'project_name' => 'Undated source', 'stage' => 'proposal_sent', 'week_start' => '1900-01-01', 'source_period_confirmed' => false, 'import_key' => 'date-qa', 'source_reference' => 'Original sheet / row 9', 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        app(\App\Services\ClientRelationsPipeline::class)->publish($project);
        $this->assertDatabaseCount('crm_weekly_progress', 0);
        $payload = ['owner_id' => $owner->id, 'client_name' => 'Imported client', 'project_name' => 'Undated source', 'stage' => 'proposal_sent', 'week_start' => '2026-09-07', 'revision' => 1, 'reason' => 'Checked source date', 'confirm_source_period' => 1, 'source_reference' => 'Changed reference'];
        $this->actingAs($owner)->post(route('portal.crm.update', $project), $payload)->assertForbidden();
        $this->actingAs($manager)->post(route('portal.crm.update', $project), $payload)->assertRedirect();
        $this->assertTrue($project->fresh()->source_period_confirmed);
        $this->assertSame('Original sheet / row 9', $project->fresh()->source_reference);
        $this->assertDatabaseCount('crm_weekly_progress', 1);
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'crm_project', 'entity_id' => $project->id, 'action' => 'update']);
    }
}
