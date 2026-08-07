<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Task;
use App\Services\WorkdayReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificationSoundSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_mute_sounds_in_profile(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'mute_sounds' => false,
        ]);

        // Mute notification sounds
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'mute_sounds' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue($user->mute_sounds);

        // Unmute notification sounds
        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                // omit mute_sounds to simulate unchecked checkbox
            ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse($user->mute_sounds);
    }

    public function test_polling_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/portal/notifications/poll');
        $response->assertStatus(401);
    }

    public function test_polling_returns_new_events_since_timestamp(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $otherUser = User::factory()->create(['status' => 'active']);

        // Set baseline time
        $baseline = now()->subSeconds(5);

        $this->actingAs($user);

        // Polling with no "since" parameter returns current timestamp and empty array
        $response = $this->getJson('/portal/notifications/poll');
        $response->assertStatus(200)
            ->assertJsonStructure(['timestamp', 'notifications'])
            ->assertJsonCount(0, 'notifications');

        // Create a conversation and a new message sent by otherUser
        $conversation = Conversation::create(['name' => 'Test Conv']);
        $conversation->users()->attach([$user->id, $otherUser->id]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'body' => 'Hello user',
        ]);

        // Create an announcement by otherUser
        $announcement = Announcement::create([
            'user_id' => $otherUser->id,
            'title' => 'New Announcement',
            'body' => 'Announcement body',
        ]);

        // Create a task assigned to user
        $task = Task::create([
            'title' => 'New Task',
            'details' => 'Task details',
            'assigned_to' => $user->id,
            'assigned_by' => $otherUser->id,
            'status' => 'open',
            'priority' => 'medium',
            'department' => $user->department ?: 'creatives',
        ]);

        // Poll with baseline timestamp
        $response = $this->getJson('/portal/notifications/poll?since=' . urlencode($baseline->toIso8601String()));
        $response->assertStatus(200);

        $notifications = $response->json('notifications');
        $this->assertCount(3, $notifications);

        $types = collect($notifications)->pluck('type')->toArray();
        $this->assertContains('message', $types);
        $this->assertContains('announcement', $types);
        $this->assertContains('task', $types);
    }

    public function test_polling_returns_unread_badge_counts(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $otherUser = User::factory()->create(['status' => 'active']);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Approval needed',
            'message' => 'Please review this request.',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Already read',
            'message' => 'This should not count.',
            'read_at' => now(),
        ]);

        $conversation = Conversation::create(['name' => 'Badge Count']);
        $conversation->users()->attach([$user->id, $otherUser->id]);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'body' => 'Unread message',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/portal/notifications/poll');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('unread_message_count', 1);
    }

    public function test_initial_poll_returns_recent_unread_persistent_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Workday check-in reminder',
            'message' => 'Please clock in once work starts.',
            'url' => '/dashboard',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/portal/notifications/poll');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => 'notif_' . $notification->id,
                'title' => 'Workday check-in reminder',
                'message' => 'Please clock in once work starts.',
            ]);
    }

    public function test_workday_morning_reminder_is_sent_once_to_active_internal_staff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00', 'Africa/Accra'));
        Cache::forget('workday_reminders:morning:2026-07-15');

        $staff = User::factory()->create(['status' => 'active', 'access_role' => 'staff']);
        $inactive = User::factory()->create(['status' => 'inactive', 'access_role' => 'staff']);
        $merchandiser = User::factory()->create(['status' => 'active', 'access_role' => 'merchandiser']);

        $firstRun = WorkdayReminderService::sendMorningReminder();
        $secondRun = WorkdayReminderService::sendMorningReminder();

        $this->assertFalse($firstRun['skipped']);
        $this->assertTrue($secondRun['skipped']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'title' => 'Workday check-in reminder',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $inactive->id,
            'title' => 'Workday check-in reminder',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $merchandiser->id,
            'title' => 'Workday check-in reminder',
        ]);

        Carbon::setTestNow();
    }

    public function test_evening_reminder_notifies_managers_with_pending_task_approvals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 18:00:00', 'Africa/Accra'));
        Cache::forget('workday_reminders:evening:2026-07-15');

        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'line_manager_id' => $manager->id,
        ]);

        Task::create([
            'title' => 'Awaiting manager review',
            'details' => 'Completed work pending approval.',
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'department' => 'creatives',
            'status' => 'Awaiting Approval',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'completion_review_requested_at' => now(),
            'copied_manager_ids' => [$manager->id],
            'custom_fields' => ['completion_manager_id' => $manager->id],
        ]);

        $result = WorkdayReminderService::sendEveningReminder();

        $this->assertFalse($result['skipped']);
        $this->assertSame(1, $result['manager_sent']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'title' => 'Workday clock-out reminder',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'title' => 'Pending task approvals reminder',
        ]);

        Carbon::setTestNow();
    }
}
