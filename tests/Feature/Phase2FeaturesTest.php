<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPhoto;
use App\Models\Asset;
use App\Models\User;
use App\Models\Task;
use App\Models\FreelancePromoter;
use App\Models\AppraisalMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2FeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
            'department' => 'operations',
        ]);

        $this->staffUser = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'operations',
        ]);

        // Autoclock them in
        $this->actingAs($this->adminUser);
        $this->post(route('portal.attendance.clock-in'), [
            'daily_objective' => 'Core operations and campaign setup',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
    }

    /**
     * Test export table endpoint returns correct download headers
     */
    public function test_export_endpoint_returns_csv_download_headers(): void
    {
        $this->actingAs($this->adminUser);

        FreelancePromoter::create([
            'name' => 'Rose Addo',
            'city' => 'Accra',
            'contact' => '0241112222',
        ]);

        $response = $this->get(route('portal.export', ['table' => 'freelance_promoters']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="freelance_promoters_export_' . date('Y-m-d') . '_' . date('H-i') . '-' . date('s') . '.csv"');
    }

    public function test_staff_task_export_is_scoped_to_owned_and_collaborator_records(): void
    {
        $otherStaff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        Task::create([
            'title' => 'Owned Export Task',
            'assigned_to' => $this->staffUser->id,
            'assigned_by' => $this->staffUser->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Task::create([
            'title' => 'Collaborator Export Task',
            'assigned_to' => $otherStaff->id,
            'assigned_by' => $otherStaff->id,
            'supporting_staff_ids' => [$this->staffUser->id],
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        Task::create([
            'title' => 'Unrelated Export Task',
            'assigned_to' => $otherStaff->id,
            'assigned_by' => $otherStaff->id,
            'department' => 'creatives',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $response = $this->actingAs($this->staffUser)->get(route('portal.export', ['table' => 'tasks']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Owned Export Task', $content);
        $this->assertStringContainsString('Collaborator Export Task', $content);
        $this->assertStringNotContainsString('Unrelated Export Task', $content);
    }

    /**
     * Test CSV import process and mapping execution
     */
    public function test_csv_import_column_mapping_and_insertion(): void
    {
        $this->actingAs($this->adminUser);

        Storage::fake('local');

        // 1. Process Upload
        $csvContent = "Name,Contact,City\nGrace Mensah,0555999000,Kumasi\n";
        $file = UploadedFile::fake()->createWithContent('promoters.csv', $csvContent);

        $response = $this->post(route('portal.import.process', ['table' => 'freelance_promoters']), [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('portal.import');
        $response->assertViewHas('temp_file');

        $tempFile = $response->viewData('temp_file');

        // 2. Execute Import
        // Map Name -> index 0, Contact -> index 1, City -> index 2
        $mappings = [
            'name' => '0',
            'contact' => '1',
            'city' => '2',
        ];

        $execResponse = $this->post(route('portal.import.execute', ['table' => 'freelance_promoters']), [
            'temp_file' => $tempFile,
            'mappings' => $mappings,
        ]);

        $execResponse->assertRedirect();
        $this->assertDatabaseHas('freelance_promoters', [
            'name' => 'Grace Mensah',
            'city' => 'Kumasi',
            'contact' => '0555999000',
        ]);
    }

    public function test_staff_task_import_marks_importer_as_cross_department_collaborator(): void
    {
        Storage::fake('local');

        $assignee = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $csvContent = "Title,Assigned To,Details\nImported Cross Dept Task,{$assignee->id},Needs operations support\n";
        $file = UploadedFile::fake()->createWithContent('tasks.csv', $csvContent);

        $response = $this->actingAs($this->staffUser)->post(route('portal.import.process', ['table' => 'tasks']), [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $tempFile = $response->viewData('temp_file');

        $execResponse = $this->actingAs($this->staffUser)->post(route('portal.import.execute', ['table' => 'tasks']), [
            'temp_file' => $tempFile,
            'mappings' => [
                'title' => '0',
                'assigned_to' => '1',
                'details' => '2',
            ],
        ]);

        $execResponse->assertRedirect();

        $task = Task::where('title', 'Imported Cross Dept Task')->firstOrFail();
        $this->assertSame($assignee->id, (int) $task->assigned_to);
        $this->assertSame($this->staffUser->id, (int) $task->assigned_by);
        $this->assertContains($this->staffUser->id, array_map('intval', $task->supporting_staff_ids ?? []));
    }

    /**
     * Test campaign creation, share token generation, and read-only live feed
     */
    public function test_campaign_creation_and_sharing_flow(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create campaign
        $response = $this->post(route('portal.campaigns.store'), [
            'name' => 'Nestle Milo Activation',
            'client_name' => 'Nestle West Africa',
            'duration' => 21,
            'status_update' => 'Done',
            'project_lead_id' => $this->staffUser->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Nestle Milo Activation',
            'client_name' => 'Nestle West Africa',
            'duration' => 21,
            'status_update' => 'Done',
            'project_lead_id' => $this->staffUser->id,
        ]);

        $campaign = Campaign::where('name', 'Nestle Milo Activation')->first();

        // 2. Generate share token
        $shareResponse = $this->post(route('portal.campaigns.generate-share', $campaign));
        $shareResponse->assertRedirect();

        $campaign->refresh();
        $this->assertNotNull($campaign->share_token);

        // 3. View shared campaign publicly (no authentication required)
        auth()->logout();

        $publicResponse = $this->get(route('campaign.share.view', $campaign->share_token));
        $publicResponse->assertStatus(200);
        $publicResponse->assertSee('Nestle Milo Activation');
        $publicResponse->assertSee('Nestle West Africa');
        $publicResponse->assertSee('Duration');
        $publicResponse->assertSee('Done');
        $publicResponse->assertSee($this->staffUser->name);
    }

    public function test_operations_staff_can_import_and_export_campaigns(): void
    {
        Campaign::create([
            'name' => 'Exportable Operations Campaign',
            'client_name' => 'Operations Client',
            'duration' => 14,
            'status_update' => 'In Progress',
            'created_by' => $this->staffUser->id,
            'status' => 'active',
        ]);

        $importResponse = $this->actingAs($this->staffUser)->get(route('portal.import.show', ['table' => 'campaigns']));
        $importResponse->assertOk();
        $importResponse->assertSee('campaigns', false);

        $exportResponse = $this->actingAs($this->staffUser)->get(route('portal.export', ['table' => 'campaigns']));
        $exportResponse->assertOk();
        $exportResponse->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('Exportable Operations Campaign', $exportResponse->streamedContent());
    }

    public function test_operations_staff_can_update_campaign_week_on_week_brief(): void
    {
        $campaign = Campaign::create([
            'name' => 'Editable Activation',
            'client_name' => 'Original Client',
            'location_brief' => '<p>Old brief.</p>',
            'duration' => 7,
            'status_update' => 'Pending',
            'created_by' => $this->staffUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->staffUser)->patch(route('portal.campaigns.update', $campaign), [
            'name' => 'Editable Activation Updated',
            'client_name' => 'Updated Client',
            'duration' => 14,
            'status_update' => 'In Progress',
            'project_lead_id' => $this->adminUser->id,
            'status' => 'active',
            'location_brief' => '<p>Week one accounts activated in Osu and Spintex.</p>',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'Editable Activation Updated',
            'client_name' => 'Updated Client',
            'duration' => 14,
            'status_update' => 'In Progress',
            'project_lead_id' => $this->adminUser->id,
            'location_brief' => '<p>Week one accounts activated in Osu and Spintex.</p>',
        ]);
    }

    public function test_hr_can_update_staff_leave_balance_and_export_assets(): void
    {
        Asset::create([
            'name' => 'Conference Projector',
            'description' => 'Meeting room display asset',
            'type' => 'Hardware',
            'status' => 'Available',
            'condition' => 'Good',
            'added_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('portal.hr.leave-balance.update', $this->staffUser), [
            'leave_balance' => 18,
        ]);

        $response->assertRedirect();
        $this->assertSame(18, (int) $this->staffUser->fresh()->leave_balance);

        $exportResponse = $this->actingAs($this->adminUser)->get(route('portal.export', ['table' => 'assets']));
        $exportResponse->assertOk();
        $exportResponse->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('Conference Projector', $exportResponse->streamedContent());
    }

    public function test_operations_campaign_activity_hides_sensitive_finance_tasks_from_non_collaborators(): void
    {
        $financeUser = User::factory()->create([
            'name' => 'Chinecherem Precious Ikechukwu',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $campaign = Campaign::create([
            'name' => 'Sensitive Campaign Activity',
            'client_name' => 'Guinness',
            'location_brief' => '<p>Accra and Kumasi activation routes.</p>',
            'created_by' => $this->staffUser->id,
            'status' => 'active',
        ]);

        Task::create([
            'campaign_id' => $campaign->id,
            'title' => 'Operations Visible Report',
            'assigned_to' => $this->staffUser->id,
            'assigned_by' => $this->staffUser->id,
            'department' => 'operations_projects',
            'status' => 'In Progress',
            'priority' => 'Medium',
        ]);

        Task::create([
            'campaign_id' => $campaign->id,
            'title' => 'Confidential Invoice For Guinness Matchday',
            'assigned_to' => $financeUser->id,
            'assigned_by' => $financeUser->id,
            'department' => 'finance',
            'status' => 'In Progress',
            'priority' => 'High',
        ]);

        $operationsView = $this->actingAs($this->staffUser)->get(route('portal.operations'));
        $operationsView->assertOk();
        $operationsView->assertSee('Operations Visible Report');
        $operationsView->assertSee('Accra and Kumasi activation routes');
        $operationsView->assertDontSee('Confidential Invoice For Guinness Matchday');

        $adminView = $this->actingAs($this->adminUser)->get(route('portal.operations'));
        $adminView->assertOk();
        $adminView->assertSee('Confidential Invoice For Guinness Matchday');
    }

    public function test_campaign_can_be_deleted_without_deleting_linked_task_history(): void
    {
        $this->actingAs($this->adminUser);

        $campaign = Campaign::create([
            'name' => 'Delete Me Activation',
            'client_name' => 'Operations Client',
            'created_by' => $this->adminUser->id,
            'status' => 'active',
        ]);

        $task = Task::create([
            'campaign_id' => $campaign->id,
            'client_name' => 'Operations Client',
            'title' => 'Report field work',
            'details' => 'Capture account-level activity.',
            'assigned_to' => $this->staffUser->id,
            'assigned_by' => $this->adminUser->id,
            'department' => 'operations',
            'status' => 'In Progress',
            'priority' => 'medium',
        ]);

        CampaignPhoto::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->staffUser->id,
            'image_path' => 'storage/campaigns/delete-me.jpg',
            'caption' => 'Live update',
        ]);

        $response = $this->delete(route('portal.campaigns.destroy', $campaign));

        $response->assertRedirect();
        $response->assertSessionHas('status', '"Delete Me Activation" campaign deleted successfully.');
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('campaign_photos', ['campaign_id' => $campaign->id]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'campaign_id' => null,
        ]);
    }

    /**
     * Test anonymous photo upload to campaign feed
     */
    public function test_anonymous_upload_to_campaign_live_feed(): void
    {
        Storage::fake('public');

        $campaign = Campaign::create([
            'name' => 'Guinness Bright House',
            'client_name' => 'Guinness Ghana',
            'share_token' => 'test-token-guinness-123',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('setup.jpg', 100, 'image/jpeg');

        $response = $this->post(route('campaign.share.upload', $campaign->share_token), [
            'photo' => $file,
            'caption' => 'Accra Sports Stadium Stage setup',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('campaign_photos', [
            'campaign_id' => $campaign->id,
            'caption' => 'Accra Sports Stadium Stage setup',
        ]);
    }
}
