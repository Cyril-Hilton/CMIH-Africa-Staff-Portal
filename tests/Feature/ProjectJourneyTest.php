<?php

namespace Tests\Feature;

use App\Models\CreativeRequest;
use App\Models\CrmPipelineProject;
use App\Models\OperationsHandover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_journey_follows_recorded_milestones_and_separates_read_access_from_linking(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 12:00:00'));
        $auditor = User::factory()->create(['department' => 'executive', 'status' => 'active', 'access_role' => 'staff',
            'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION, User::HR_FINANCE_AUDIT_PERMISSION]]);
        $owner = User::factory()->create(['department' => 'client_relations', 'status' => 'active', 'access_role' => 'staff']);
        $other = User::factory()->create(['department' => 'client_relations', 'status' => 'active', 'access_role' => 'staff']);
        $project = CrmPipelineProject::create(['client_name' => 'Sample Customer', 'project_name' => 'Linked Launch',
            'stage' => 'won', 'week_start' => '2026-11-16', 'source_period_confirmed' => true, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $brief = CreativeRequest::create(['uuid' => (string) Str::uuid(), 'reference' => 'CR-TEST-JOURNEY', 'title' => 'Shared Creative Brief',
            'requester_id' => $owner->id, 'stage' => 'brief_received', 'visibility' => 'shared', 'received_at' => now()]);
        $focus = route('portal.project-journey', ['project' => $project->id]);
        $this->actingAs($other)->get($focus)->assertNotFound();
        $this->actingAs($auditor)->get($focus)->assertOk()->assertDontSee('Link an existing creative brief');
        $this->post(route('portal.project-journey.link'), ['project_id' => $project->id, 'brief_id' => $brief->id])->assertForbidden();
        $this->actingAs($owner)->get($focus)->assertOk()->assertSee('Link an existing creative brief');
        $this->post(route('portal.project-journey.link'), ['project_id' => $project->id, 'brief_id' => $brief->id])->assertRedirect($focus);
        $this->assertSame($project->id, $brief->fresh()->crm_project_id);

        $stage = function (string $expected) use ($auditor, $focus) {
            $response = $this->actingAs($auditor)->get($focus)->assertOk();
            $this->assertSame($expected, $response->viewData('records')->first()['stage']);
        };
        $stage('creative');
        $brief->update(['stage' => 'delivered', 'delivered_at' => now()]);
        $stage('approval');
        $brief->update(['stage' => 'completed', 'completed_at' => now()]);
        $stage('handover');
        $handover = OperationsHandover::create(['project_id' => $project->id, 'requester_id' => $owner->id, 'title' => 'Setup handover', 'plan' => 'Coordinate the launch',
            'acceptance_criteria' => 'Setup signed off', 'location' => 'Accra', 'priority' => 'High', 'event_at' => '2026-11-20 10:00:00', 'setup_due_at' => '2026-11-20 09:00:00', 'stage' => 'planning',
            'cr_signoff_at' => now(), 'ops_signoff_at' => now(), 'handover_approved_at' => now()]);
        $stage('execution');
        $handover->update(['stage' => 'awaiting_acceptance', 'submitted_at' => now()]);
        $stage('closeout');
        $handover->update(['stage' => 'planning']);
        $stage('execution');
        $handover->update(['stage' => 'completed', 'verified_at' => now()]);
        $stage('complete');
        $this->get(route('portal.project-journey', ['view' => 'insights', 'fragment' => 1]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Project stage chart');
    }
}
