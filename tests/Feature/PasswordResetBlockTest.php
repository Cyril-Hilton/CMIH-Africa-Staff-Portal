<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetBlockTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Newpass12!';

    /**
     * Test flow: Log in, add task, clock in, change password, and verify we are not logged out and can still access everything.
     */
    public function test_user_can_add_task_clock_in_change_password_and_remain_active(): void
    {
        // 1. Create a user with must_reset_password = true
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'must_reset_password' => true,
            'password' => Hash::make('oldpassword'),
        ]);

        // 2. Add a task today
        $task = Task::create([
            'title' => 'Daily Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        // 3. Clock in
        $response = $this->actingAs($user)->post('/portal/attendance/clock-in', [
            'daily_objective' => 'Finish task and update password',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Successfully clocked in for today!');
        $this->assertTrue(Attendance::where('user_id', $user->id)->exists());

        // 4. Access portal messaging (should be allowed since clocked in)
        $this->get('/portal/messages')->assertStatus(200);

        // 5. Change password via Profile Edit Form
        $response = $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'oldpassword',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');

        // 6. Verify password was updated and must_reset_password is false
        $user = $user->fresh();
        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->password));
        $this->assertFalse($user->must_reset_password);

        // 7. Verify we are still logged in and can access portal messages (without being asked to clock in again)
        $response = $this->get('/portal/messages');
        $response->assertStatus(200);
    }

    /**
     * Test flow: Password reset via forgot password token resets must_reset_password to false.
     */
    public function test_forgot_password_reset_resets_must_reset_password_field(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'must_reset_password' => true,
            'password' => Hash::make('oldpassword'),
        ]);

        // Request reset password token
        $token = \Password::broker()->createToken($user);

        // Reset password
        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertRedirect('/login');

        // Verify must_reset_password is false
        $user = $user->fresh();
        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->password));
        $this->assertFalse($user->must_reset_password);
    }
}
