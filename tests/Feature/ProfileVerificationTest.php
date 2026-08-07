<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $staffUser;
    protected User $otherStaffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'access_role'    => 'super_admin',
            'status'         => 'active',
            'department'     => 'admin',
            'position_title' => 'CVO',
        ]);

        $this->staffUser = User::factory()->create([
            'access_role'    => 'staff',
            'status'         => 'active',
            'department'     => 'creatives',
            'position_title' => 'Executive',
        ]);

        $this->otherStaffUser = User::factory()->create([
            'access_role'    => 'staff',
            'status'         => 'active',
            'department'     => 'creatives',
            'position_title' => 'Executive',
        ]);
    }

    /** Super Admin profile changes are applied immediately */
    public function test_super_admin_profile_changes_are_immediate(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->patch(route('profile.update'), [
            'name'           => 'Super Admin Edited',
            'email'          => $this->superAdmin->email,
            'department'     => 'finance',
            'position_title' => 'CVO',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $this->superAdmin->refresh();

        $this->assertEquals('finance', $this->superAdmin->department);
        $this->assertNull($this->superAdmin->requested_department);
    }

    /** Standard user profile changes are stored as pending */
    public function test_standard_user_profile_changes_are_pending(): void
    {
        $this->actingAs($this->staffUser);

        $response = $this->patch(route('profile.update'), [
            'name'           => 'Staff Edited',
            'email'          => $this->staffUser->email,
            'department'     => 'finance', // requested change
            'position_title' => 'Manager', // requested change
        ]);

        $response->assertRedirect(route('profile.edit'));
        $this->staffUser->refresh();

        // Active fields should NOT change
        $this->assertEquals('creatives', $this->staffUser->department);
        $this->assertEquals('Executive', $this->staffUser->position_title);

        // Requested fields should hold the new values
        $this->assertEquals('finance', $this->staffUser->requested_department);
        $this->assertEquals('Manager', $this->staffUser->requested_position_title);
        $this->assertNotNull($this->staffUser->requested_change_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Profile Change Approval Needed',
        ]);
    }

    /** Super Admin receives notification of profile request on polling */
    public function test_super_admin_receives_profile_request_polling_notification(): void
    {
        $this->staffUser->update([
            'requested_department'     => 'finance',
            'requested_position_title' => 'Manager',
            'requested_change_at'      => now(),
        ]);

        $this->actingAs($this->superAdmin);
        $response = $this->getJson(route('portal.notifications.poll', ['since' => now()->subMinutes(5)->toIso8601String()]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Profile Change Request',
        ]);
    }

    /** Unauthorized users cannot access approval or rejection endpoints */
    public function test_unauthorized_users_cannot_approve_or_reject_profile(): void
    {
        $this->staffUser->update([
            'requested_department'     => 'finance',
            'requested_position_title' => 'Manager',
            'requested_change_at'      => now(),
        ]);

        // Attempt as another standard staff user
        $this->actingAs($this->otherStaffUser);

        $responseApprove = $this->post(route('admin.users.approve-profile', $this->staffUser));
        $responseApprove->assertStatus(403);

        $responseReject = $this->post(route('admin.users.reject-profile', $this->staffUser));
        $responseReject->assertStatus(403);
    }

    /** Super Admin approval applies changes and auto-promotes if manager title is requested */
    public function test_super_admin_can_approve_profile_change(): void
    {
        $this->staffUser->update([
            'requested_department'     => 'finance',
            'requested_position_title' => 'Manager',
            'requested_change_at'      => now(),
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->post(route('admin.users.approve-profile', $this->staffUser));
        $response->assertRedirect();

        $this->staffUser->refresh();

        // Active fields should now be updated
        $this->assertEquals('finance', $this->staffUser->department);
        $this->assertEquals('Manager', $this->staffUser->position_title);

        // Access role and job level should be upgraded to Manager
        $this->assertEquals('manager', $this->staffUser->access_role);
        $this->assertEquals('manager', $this->staffUser->job_level);

        // Requested fields should be cleared
        $this->assertNull($this->staffUser->requested_department);
        $this->assertNull($this->staffUser->requested_position_title);
        $this->assertNull($this->staffUser->requested_change_at);
    }

    /** Super Admin rejection clears requested changes and leaves active fields intact */
    public function test_super_admin_can_reject_profile_change(): void
    {
        $this->staffUser->update([
            'requested_department'     => 'finance',
            'requested_position_title' => 'Manager',
            'requested_change_at'      => now(),
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->post(route('admin.users.reject-profile', $this->staffUser));
        $response->assertRedirect();

        $this->staffUser->refresh();

        // Active fields should remain unchanged
        $this->assertEquals('creatives', $this->staffUser->department);
        $this->assertEquals('Executive', $this->staffUser->position_title);

        // Requested fields should be cleared
        $this->assertNull($this->staffUser->requested_department);
        $this->assertNull($this->staffUser->requested_position_title);
        $this->assertNull($this->staffUser->requested_change_at);
    }
}
