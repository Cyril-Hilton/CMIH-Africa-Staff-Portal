<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_messenger_index_shows_empty_chat_welcome_page()
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('portal.messages'));

        $response->assertStatus(200);
        $response->assertViewHas('conversation', null);
    }

    public function test_portal_layout_has_global_sidebar_hide_show_toggle()
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('portal.messages'));

        $response->assertOk();
        $response->assertSee('portalSidebarCollapsed', false);
        $response->assertSee('toggleSidebar()', false);
        $response->assertSee('hideSidebar()', false);
        $response->assertSee('aria-label="Toggle navigation menu"', false);
        $response->assertSee('aria-label="Hide navigation"', false);
        $response->assertSee('x-show="! sidebarCollapsed || sidebarOpen"', false);
    }

    public function test_user_can_view_conversation_they_have_access_to()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        $response = $this->actingAs($user)->get(route('portal.messages.show', $conversation));

        $response->assertStatus(200);
        $response->assertViewHas('conversation');
    }

    public function test_user_cannot_view_group_they_are_not_part_of()
    {
        $user = User::factory()->create(['status' => 'active']);
        $otherUser = User::factory()->create(['status' => 'active']);

        $group = Conversation::create([
            'name' => 'Secret Group',
            'type' => 'group',
            'creator_id' => $otherUser->id,
        ]);
        $group->users()->attach($otherUser->id, ['is_admin' => true]);

        $response = $this->actingAs($user)->get(route('portal.messages.show', $group));

        $response->assertStatus(403);
    }

    public function test_private_message_attachment_requires_conversation_access()
    {
        Storage::fake('local');

        $sender = User::factory()->create(['status' => 'active']);
        $member = User::factory()->create(['status' => 'active']);
        $outsider = User::factory()->create(['status' => 'active']);

        $group = Conversation::create([
            'name' => 'Private Group',
            'type' => 'group',
            'creator_id' => $sender->id,
        ]);
        $group->users()->attach($sender->id, ['is_admin' => true]);
        $group->users()->attach($member->id, ['is_admin' => false]);

        Storage::disk('local')->put('messenger/private-note.txt', 'private chat file');

        $message = Message::create([
            'conversation_id' => $group->id,
            'user_id' => $sender->id,
            'attachment_path' => 'messenger/private-note.txt',
            'attachment_type' => 'file',
        ]);

        $this->assertSame(route('portal.messages.attachment', $message), $message->attachmentUrl());

        $this->actingAs($member)
            ->get(route('portal.messages.attachment', $message))
            ->assertOk();

        $this->actingAs($outsider)
            ->get(route('portal.messages.attachment', $message))
            ->assertForbidden();
    }

    public function test_user_can_send_message_and_marks_other_as_read_when_shown()
    {
        $user1 = User::factory()->create(['status' => 'active']);
        $user2 = User::factory()->create(['status' => 'active']);

        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        // Send a message as user1
        $this->actingAs($user1)->post(route('portal.messages.send', $conversation), [
            'body' => 'Hello World',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'body' => 'Hello World',
        ]);

        $message = Message::first();
        $this->assertEquals(0, $message->readCount());

        // Now user2 views the conversation, marking it as read
        $this->actingAs($user2)->get(route('portal.messages.show', $conversation));

        $this->assertEquals(1, $message->fresh()->readCount());
    }

    public function test_user_can_send_emoji_and_multiline_message()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);
        $emoji = html_entity_decode('&#128522;', ENT_QUOTES, 'UTF-8');
        $body = "Hello team {$emoji}\nSecond line";

        $response = $this->actingAs($user)->post(route('portal.messages.send', $conversation), [
            'body' => $body,
        ]);

        $response->assertRedirect(route('portal.messages.show', $conversation));
        $message = Message::first();

        $this->assertNotNull($message);
        $this->assertSame($body, $message->body);
    }

    public function test_message_composer_supports_emoji_picker_and_shift_enter_newlines()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        $response = $this->actingAs($user)->get(route('portal.messages.show', $conversation));

        $response->assertOk();
        $response->assertSee('aria-label="Insert emoji"', false);
        $response->assertSee('insertEmoji(code)', false);
        $response->assertSee('@keydown.enter="if (! $event.shiftKey)', false);
        $response->assertDontSee('@keydown.enter.prevent', false);
    }

    public function test_message_counts_include_unread_broadcast_messages()
    {
        $sender = User::factory()->create(['status' => 'active']);
        $receiver = User::factory()->create(['status' => 'active']);
        $broadcast = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        Message::create([
            'conversation_id' => $broadcast->id,
            'user_id' => $sender->id,
            'body' => 'Unread broadcast',
        ]);

        $response = $this->actingAs($receiver)->get(route('portal.messages'));

        $response->assertOk();
        $response->assertSee('data-sidebar-message-count="1"', false);
        $response->assertSee('data-conversation-unread-count="1"', false);
        $this->assertSame(1, Message::unreadFor($receiver)->count());
    }

    public function test_opening_conversation_clears_unread_message_count()
    {
        $sender = User::factory()->create(['status' => 'active']);
        $receiver = User::factory()->create(['status' => 'active']);
        $broadcast = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        Message::create([
            'conversation_id' => $broadcast->id,
            'user_id' => $sender->id,
            'body' => 'Unread broadcast',
        ]);

        $this->assertSame(1, Message::unreadFor($receiver)->count());

        $response = $this->actingAs($receiver)->get(route('portal.messages.show', $broadcast));

        $response->assertOk();
        $this->assertSame(0, Message::unreadFor($receiver)->count());
    }

    public function test_user_can_reply_to_a_message()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        $msg1 = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => 'First message',
        ]);

        $this->actingAs($user)->post(route('portal.messages.send', $conversation), [
            'body' => 'Reply message',
            'reply_to_id' => $msg1->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => 'Reply message',
            'reply_to_id' => $msg1->id,
        ]);
    }

    public function test_user_can_edit_own_message()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => 'Original text',
        ]);

        $response = $this->actingAs($user)->post(route('portal.messages.edit', $msg), [
            'body' => 'Updated text',
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $msg->id,
            'body' => 'Updated text',
            'is_edited' => true,
        ]);
    }

    public function test_user_can_delete_own_message_soft()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'name' => 'All Staff',
            'type' => 'broadcast',
        ]);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => 'Original text',
        ]);

        $this->actingAs($user)->post(route('portal.messages.delete', $msg));

        $this->assertDatabaseHas('messages', [
            'id' => $msg->id,
            'body' => null,
            'is_deleted' => true,
        ]);
    }

    public function test_user_can_forward_message()
    {
        $user = User::factory()->create(['status' => 'active']);
        
        $conv1 = Conversation::create(['name' => 'All Staff', 'type' => 'broadcast']);
        $conv2 = Conversation::create(['name' => 'Secondary', 'type' => 'broadcast']);

        $msg = Message::create([
            'conversation_id' => $conv1->id,
            'user_id' => $user->id,
            'body' => 'Text to forward',
        ]);

        $response = $this->actingAs($user)->post(route('portal.messages.forward', $msg), [
            'conversation_id' => $conv2->id,
        ]);

        $response->assertRedirect(route('portal.messages.show', $conv2));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv2->id,
            'user_id' => $user->id,
            'body' => 'Text to forward (Forwarded)',
        ]);
    }

    public function test_user_can_create_group()
    {
        $user = User::factory()->create(['status' => 'active']);
        $member1 = User::factory()->create(['status' => 'active']);
        $member2 = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post(route('portal.messages.groups.create'), [
            'name' => 'New Group',
            'description' => 'Test Group Description',
            'members' => [$member1->id, $member1->id, $member2->id, $user->id],
        ]);

        $response->assertSessionHasNoErrors();
        $group = Conversation::where('name', 'New Group')->first();
        $this->assertNotNull($group);
        $this->assertEquals('group', $group->type);
        $this->assertTrue($group->isAdmin($user));
        $this->assertTrue($group->users->contains($member1->id));
        $this->assertTrue($group->users->contains($member2->id));
        $this->assertSame(3, $group->users()->count());
    }

    public function test_group_creation_rejects_merchandiser_members()
    {
        $user = User::factory()->create(['status' => 'active']);
        $merchandiser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'merchandiser',
        ]);

        $response = $this->actingAs($user)->post(route('portal.messages.groups.create'), [
            'name' => 'Invalid Group',
            'members' => [$merchandiser->id],
        ]);

        $response->assertSessionHasErrors('members');
        $this->assertDatabaseMissing('conversations', [
            'name' => 'Invalid Group',
        ]);
    }

    public function test_group_chat_modal_has_fallback_click_targets()
    {
        $user = User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('portal.messages'));

        $response->assertOk();
        $response->assertSee('id="new-group-chat-modal"', false);
        $response->assertSee('data-open-group-chat', false);
        $response->assertSee('data-close-group-chat', false);
    }

    public function test_group_members_are_visible_to_non_admin_group_members()
    {
        $admin = User::factory()->create(['status' => 'active', 'name' => 'Group Admin']);
        $member = User::factory()->create(['status' => 'active', 'name' => 'Visible Member']);
        $otherMember = User::factory()->create(['status' => 'active', 'name' => 'Second Member']);
        $conversation = Conversation::create([
            'name' => 'Design Group',
            'type' => 'group',
            'creator_id' => $admin->id,
        ]);
        $conversation->users()->attach($admin->id, ['is_admin' => true]);
        $conversation->users()->attach($member->id, ['is_admin' => false]);
        $conversation->users()->attach($otherMember->id, ['is_admin' => false]);

        $response = $this->actingAs($member)->get(route('portal.messages.show', $conversation));

        $response->assertOk();
        $response->assertSee('data-group-member-names', false);
        $response->assertSee('Group Members');
        $response->assertSee('Group Admin');
        $response->assertSee('Visible Member');
        $response->assertSee('Second Member');
        $response->assertDontSee('Remove');
    }

    public function test_archived_message_attachment_uses_dropbox_shared_url()
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create(['name' => 'Archive Test', 'type' => 'broadcast']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => 'Archived image',
            'attachment_path' => 'messenger/local-image.jpg',
            'attachment_type' => 'image',
            'dropbox_shared_url' => 'https://dropbox.example.com/shared-image?raw=1',
            'dropbox_archived_at' => now(),
        ]);

        $this->assertSame('https://dropbox.example.com/shared-image?raw=1', $message->attachmentUrl());
    }
}
