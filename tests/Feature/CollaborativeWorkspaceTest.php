<?php

namespace Tests\Feature;

use App\Models\CollaborativeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborativeWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected User $lineManager;
    protected User $collaboratorView;
    protected User $collaboratorEdit;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->lineManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'position_title' => 'Manager',
            'department' => 'creatives',
        ]);

        $this->superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->author = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'position_title' => 'Executive',
            'department' => 'creatives',
            'line_manager_id' => $this->lineManager->id,
        ]);

        $this->collaboratorView = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'position_title' => 'Executive',
            'department' => 'creatives',
        ]);

        $this->collaboratorEdit = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'position_title' => 'Executive',
            'department' => 'creatives',
        ]);
    }

    /** Test document creation and file import */
    public function test_document_creation_and_import(): void
    {
        $this->actingAs($this->author);

        $fakeFile = UploadedFile::fake()->create('brief_q3.docx', 100);

        $response = $this->post(route('portal.workspace.store'), [
            'title' => 'Test Brief Q3',
            'content' => '<p>Initial brief layout</p>',
            'file' => $fakeFile,
            'collaborators' => [$this->collaboratorView->id]
        ]);

        $document = CollaborativeDocument::first();
        $this->assertNotNull($document);

        $response->assertRedirect(route('portal.workspace.show', $document));

        $this->assertEquals('Test Brief Q3', $document->title);
        $this->assertEquals('document', $document->doc_type);
        $this->assertEquals('<p>Initial brief layout</p>', $document->content);
        $this->assertEquals('brief_q3.docx', $document->file_name);
        Storage::disk('local')->assertExists($document->file_path);

        // Verify collaborator default view permission
        $this->assertTrue($document->collaborators()->where('user_id', $this->collaboratorView->id)->exists());
        $collab = $document->collaborators()->where('user_id', $this->collaboratorView->id)->first();
        $this->assertEquals('view', $collab->pivot->permission);
    }

    /** View-only collaborator cannot edit, but edit-enabled collaborator can */
    public function test_collaborator_permissions_enforced(): void
    {
        $document = CollaborativeDocument::create([
            'title' => 'Shared Document',
            'doc_type' => 'document',
            'content' => 'Lorem ipsum',
            'created_by' => $this->author->id,
            'current_holder_id' => $this->author->id,
            'status' => 'draft',
        ]);

        $document->collaborators()->attach([
            $this->collaboratorView->id => ['permission' => 'view'],
            $this->collaboratorEdit->id => ['permission' => 'edit'],
        ]);

        // 1. View-only collaborator attempts to edit
        $this->actingAs($this->collaboratorView);
        $responseEditView = $this->get(route('portal.workspace.edit', $document));
        $responseEditView->assertStatus(403);

        $responseUpdateView = $this->put(route('portal.workspace.update', $document), [
            'title' => 'Hacked Title',
        ]);
        $responseUpdateView->assertStatus(403);

        // 2. Edit-enabled collaborator attempts to edit
        $this->actingAs($this->collaboratorEdit);
        $responseEditEdit = $this->get(route('portal.workspace.edit', $document));
        $responseEditEdit->assertStatus(200);

        $responseUpdateEdit = $this->put(route('portal.workspace.update', $document), [
            'title' => 'Collab Title Edited',
            'content' => 'New modified content',
        ]);
        $responseUpdateEdit->assertRedirect(route('portal.workspace.show', $document));
        
        $document->refresh();
        $this->assertEquals('Collab Title Edited', $document->title);
    }

    /** Submit review panel shows separate line-manager and coworker recipient dropdowns */
    public function test_review_panel_lists_line_manager_and_coworker_dropdowns(): void
    {
        $document = CollaborativeDocument::create([
            'title' => 'Routing Form Check',
            'doc_type' => 'document',
            'content' => 'Needs review.',
            'created_by' => $this->author->id,
            'current_holder_id' => $this->author->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->author)->get(route('portal.workspace.show', $document));

        $response->assertOk();
        $response->assertSeeText('Select Line Manager');
        $response->assertSeeText($this->lineManager->name);
        $response->assertSeeText('Select Recipient Coworker');
        $response->assertSeeText($this->collaboratorView->name);
        $response->assertSee('x-model="routeType"', false);
    }

    /** A staff member without a saved profile manager can still select a manager manually */
    public function test_document_can_be_routed_to_selected_line_manager_without_profile_manager(): void
    {
        $authorWithoutManager = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'brands_marketing',
            'line_manager_id' => null,
        ]);
        $selectedManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'department' => 'brands_marketing',
            'position_title' => 'Manager',
        ]);
        $document = CollaborativeDocument::create([
            'title' => 'Manual Manager Routing',
            'doc_type' => 'document',
            'content' => 'Needs selected manager review.',
            'created_by' => $authorWithoutManager->id,
            'current_holder_id' => $authorWithoutManager->id,
            'status' => 'draft',
        ]);

        $this->actingAs($authorWithoutManager)->post(route('portal.workspace.submit', $document), [
            'route_target' => 'manager',
            'recipient_id' => $selectedManager->id,
        ])->assertRedirect(route('portal.workspace.show', $document));

        $document->refresh();
        $this->assertEquals('under_review', $document->status);
        $this->assertEquals($selectedManager->id, $document->current_holder_id);
    }

    /** Test general document review routing pathway */
    public function test_document_review_routing_pathway(): void
    {
        $document = CollaborativeDocument::create([
            'title' => 'Client Brief',
            'doc_type' => 'document',
            'content' => 'Detailed campaign ideas',
            'created_by' => $this->author->id,
            'current_holder_id' => $this->author->id,
            'status' => 'draft',
        ]);

        // 1. Submit to Manager
        $this->actingAs($this->author);
        
        $responseSubmit = $this->post(route('portal.workspace.submit', $document), [
            'route_target' => 'manager',
        ]);
        $responseSubmit->assertRedirect();
        
        $document->refresh();
        $this->assertEquals('under_review', $document->status);
        $this->assertEquals($this->lineManager->id, $document->current_holder_id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->lineManager->id,
            'title' => 'Workspace Document Review Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Workspace Document Review Needed',
        ]);

        // 2. Manager signs off / approves
        $this->actingAs($this->lineManager);
        
        $responseAction = $this->post(route('portal.workspace.action', $document), [
            'action' => 'approve',
        ]);
        $responseAction->assertRedirect();

        $document->refresh();
        $this->assertEquals('finalized', $document->status);
        $this->assertEquals($this->author->id, $document->current_holder_id); // Returns back to author
    }
}
