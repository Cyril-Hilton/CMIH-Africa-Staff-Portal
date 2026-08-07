<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DummyUsersClockInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Test that all dummy department users can successfully clock in.
     */
    public function test_all_dummy_staff_users_can_clock_in(): void
    {
        $depts = [
            'hr_admin',
            'finance',
            'client_relations',
            'operations_projects',
            'brands_marketing',
            'creatives',
        ];

        foreach ($depts as $dept) {
            $email = $dept . '@cmih.africa';
            
            // 1. Fetch user seeded by DatabaseSeeder
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "User for department {$dept} should exist.");
            $this->assertEquals('staff', $user->access_role);
            $this->assertEquals($dept, $user->department);

            // 2. Clear out any previous attendance logs for clean testing
            Attendance::where('user_id', $user->id)->delete();
            Task::where('assigned_to', $user->id)->orWhere('assigned_by', $user->id)->delete();

            // 3. Attempting to clock in without a task should fail
            $response = $this->actingAs($user)->post(route('portal.attendance.clock-in'), [
                'daily_objective' => 'Work on ' . $dept . ' tasks today.',
            ]);
            $response->assertSessionHasErrors(['attendance']);

            // 4. Create a task assigned to/by this user for today
            Task::create([
                'title' => 'Daily Setup for ' . $dept,
                'assigned_to' => $user->id,
                'assigned_by' => $user->id,
                'department' => $dept,
                'status' => 'Open',
                'priority' => 'Medium',
            ]);

            // 5. Attempt clock in again (should succeed)
            $response = $this->actingAs($user)->post(route('portal.attendance.clock-in'), [
                'daily_objective' => 'Execute daily tasks for ' . $dept,
                'latitude' => 5.6037,
                'longitude' => -0.1870,
                'remote_notes' => 'Headquarters',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('status', 'Successfully clocked in for today!');

            // 6. Verify database entry exists for attendance
            $this->assertDatabaseHas('attendance', [
                'user_id' => $user->id,
                'daily_objective' => 'Execute daily tasks for ' . $dept,
                'latitude' => '5.60370000',
                'longitude' => '-0.18700000',
                'remote_notes' => 'Headquarters',
            ]);
        }
    }
}
