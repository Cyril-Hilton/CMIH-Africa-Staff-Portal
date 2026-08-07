<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cvo;
    protected User $hrManager;
    protected User $lineManager;
    protected User $subordinate;
    protected User $otherStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
            'position_title' => 'Director',
            'department' => 'admin',
        ]);

        $this->cvo = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
            'position_title' => 'CVO',
            'department' => 'admin',
        ]);

        $this->hrManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'position_title' => 'Department Head',
            'department' => 'hr_admin',
        ]);

        $this->lineManager = User::factory()->create([
            'access_role' => 'manager',
            'status' => 'active',
            'position_title' => 'Manager',
            'department' => 'creatives',
        ]);

        $this->subordinate = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'position_title' => 'Executive',
            'department' => 'creatives',
            'line_manager_id' => $this->lineManager->id,
        ]);

        $this->otherStaff = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'position_title' => 'Executive',
            'department' => 'finance',
        ]);
    }

    /** Test User::canEditUser matrix rules */
    public function test_can_edit_user_matrix_rules(): void
    {
        // 1. Super Admin is untouchable
        $this->assertFalse(User::canEditUser($this->superAdmin, $this->cvo)); // cvo has super_admin role
        $this->assertFalse(User::canEditUser($this->cvo, $this->superAdmin));

        // 2. Super Admin can edit everyone else
        $this->assertTrue(User::canEditUser($this->superAdmin, $this->hrManager));
        $this->assertTrue(User::canEditUser($this->superAdmin, $this->lineManager));
        $this->assertTrue(User::canEditUser($this->superAdmin, $this->subordinate));

        // 3. CVO edits everyone except super admin
        $this->assertTrue(User::canEditUser($this->cvo, $this->hrManager));
        $this->assertTrue(User::canEditUser($this->cvo, $this->lineManager));
        $this->assertTrue(User::canEditUser($this->cvo, $this->subordinate));
        $this->assertFalse(User::canEditUser($this->cvo, $this->superAdmin));

        // 4. HR edits everyone except superadmin and CVO
        $this->assertTrue(User::canEditUser($this->hrManager, $this->lineManager));
        $this->assertTrue(User::canEditUser($this->hrManager, $this->subordinate));
        $this->assertFalse(User::canEditUser($this->hrManager, $this->cvo));
        $this->assertFalse(User::canEditUser($this->hrManager, $this->superAdmin));

        // 5. Manager only edits subordinates
        $this->assertTrue(User::canEditUser($this->lineManager, $this->subordinate));
        $this->assertFalse(User::canEditUser($this->lineManager, $this->otherStaff));
        $this->assertFalse(User::canEditUser($this->lineManager, $this->hrManager));

        // 6. Standard staff cannot edit anyone
        $this->assertFalse(User::canEditUser($this->subordinate, $this->otherStaff));
    }

    /** Authorized users can update privileges via endpoint */
    public function test_authorized_user_can_update_privileges(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('portal.directory.privileges', $this->subordinate), [
            'access_role' => 'manager',
            'position_title' => 'Manager',
            'department' => 'finance',
        ]);

        $response->assertRedirect();
        $this->subordinate->refresh();

        $this->assertEquals('manager', $this->subordinate->access_role);
        $this->assertEquals('Manager', $this->subordinate->position_title);
        $this->assertEquals('finance', $this->subordinate->department);
        $this->assertEquals('manager', $this->subordinate->job_level);
    }

    /** Non-superadmin cannot assign admin or superadmin privileges */
    public function test_non_superadmin_cannot_promote_to_admin_or_superadmin(): void
    {
        // Manager attempts to promote subordinate to admin
        $this->actingAs($this->lineManager);

        $response = $this->post(route('portal.directory.privileges', $this->subordinate), [
            'access_role' => 'admin',
            'position_title' => 'Manager',
            'department' => 'creatives',
        ]);

        $response->assertSessionHasErrors(['access_role']);
        $this->subordinate->refresh();
        $this->assertEquals('staff', $this->subordinate->access_role); // Unchanged
    }

    /** Unauthorized user cannot update privileges */
    public function test_unauthorized_user_cannot_update_privileges(): void
    {
        $this->actingAs($this->otherStaff);

        $response = $this->post(route('portal.directory.privileges', $this->subordinate), [
            'access_role' => 'manager',
            'position_title' => 'Manager',
            'department' => 'finance',
        ]);

        $response->assertStatus(403);
    }

    /** HR user can update salary and upload contract/job description files */
    public function test_hr_user_can_update_salary_and_upload_docs(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->hrManager);

        $contract = \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 100);
        $jobDescription = \Illuminate\Http\UploadedFile::fake()->create('jd.docx', 100);

        $response = $this->post(route('portal.directory.privileges', $this->subordinate), [
            'access_role' => 'staff',
            'position_title' => 'Executive',
            'department' => 'creatives',
            'salary' => 6500.50,
            'contract' => $contract,
            'job_description' => $jobDescription,
        ]);

        $response->assertRedirect();
        $this->subordinate->refresh();

        $this->assertEquals(6500.50, (float)$this->subordinate->salary);
        $this->assertNotNull($this->subordinate->contract_path);
        $this->assertNotNull($this->subordinate->job_description_path);

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($this->subordinate->contract_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($this->subordinate->job_description_path);
    }

    /** Non-HR Manager can update privileges but salary and docs remain unchanged/ignored */
    public function test_manager_cannot_update_salary_and_upload_docs(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->lineManager);

        $contract = \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 100);

        $response = $this->post(route('portal.directory.privileges', $this->subordinate), [
            'access_role' => 'staff',
            'position_title' => 'Executive',
            'department' => 'creatives',
            'salary' => 6500.50,
            'contract' => $contract,
        ]);

        $response->assertRedirect();
        $this->subordinate->refresh();

        // Salary and contract_path must remain null/default
        $this->assertEquals(0.00, (float)$this->subordinate->salary);
        $this->assertNull($this->subordinate->contract_path);
    }
}
