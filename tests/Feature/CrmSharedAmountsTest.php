<?php

namespace Tests\Feature;

use App\Models\CrmPipelineProject;
use App\Models\CrmWeeklyProgress;
use App\Models\User;
use App\Services\ClientRelationsPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSharedAmountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_page_displays_business_amounts_to_internal_staff_and_keeps_private_notes_out(): void
    {
        $manager = User::factory()->create(['department' => 'client_relations', 'access_role' => 'manager', 'status' => 'active']);
        $project = CrmPipelineProject::create([
            'client_name' => 'Shared Client', 'project_name' => 'Shared Project', 'owner_id' => $manager->id,
            'week_start' => now()->startOfWeek(), 'stage' => 'proposal_sent', 'proposed_amount' => 987654.32,
            'po_amount' => 543210.98, 'target_amount' => 100, 'actual_amount' => 50,
            'description' => 'Shared description', 'shared_bottlenecks' => 'Awaiting brief',
            'shared_next_action' => 'Follow up tomorrow', 'comments' => 'Weekly update',
            'bottlenecks' => 'PRIVATE-CONFIDENTIAL-NOTE', 'po_state' => 'received',
            'created_by' => $manager->id, 'updated_by' => $manager->id,
        ]);
        app(ClientRelationsPipeline::class)->publish($project);
        $progress = CrmWeeklyProgress::firstOrFail();
        foreach (['creatives', 'finance', 'client_relations'] as $department) {
            $staff = User::factory()->create(['department' => $department, 'access_role' => 'staff', 'status' => 'active']);
            $this->actingAs($staff)->get(route('dashboard', ['weekly_department' => 'client_relations']))
                ->assertOk()->assertSee('GH₵ 987,654.32')->assertSee('Shared description')->assertSee('Awaiting brief')
                ->assertSee('543,210.98')->assertDontSee('PRIVATE-CONFIDENTIAL-NOTE')
                ->assertDontSee('Show Amounts');
            $this->getJson(route('portal.crm.reveal', $progress))->assertOk();
        }
        $outsider = User::factory()->create(['department' => 'finance', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($outsider)->getJson(route('portal.crm.reveal', $progress))->assertOk();
        $this->actingAs($manager)->get(route('dashboard', ['weekly_department' => 'client_relations']))
            ->assertOk()->assertDontSee('Show Amounts')->assertSee('987,654.32');
        $response = $this->getJson(route('portal.crm.reveal', $progress))->assertOk()
            ->assertJsonPath('proposed_amount', 'GH₵ 987,654.32')->assertJsonPath('po_amount', 'GH₵ 543,210.98');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('portal.crm.index'))->assertOk()->assertSee('GH₵ 987,654.32')->assertDontSee('Show Amounts');
        $project->update(['po_amount' => null]);
        app(ClientRelationsPipeline::class)->publish($project);
        $this->assertFalse((bool) $progress->fresh()->has_po_amount);
    }
}
