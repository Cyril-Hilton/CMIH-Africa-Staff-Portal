<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MessageController extends Controller
{
    // ─── Index: show messenger with no active conversation selected ──────────

    public function index(): View
    {
        $user = auth()->user();

        // Ensure broadcast channel exists
        $broadcast = Conversation::where('type', 'broadcast')->first();
        if (! $broadcast) {
            $broadcast = Conversation::create([
                'name'        => 'All Staff',
                'description' => 'Company-wide broadcast channel for all CMIH staff.',
                'type'        => 'broadcast',
                'creator_id'  => null,
            ]);
        }

        // Sidebar data
        $groups       = $user->conversations()->where('type', 'group')->with('users')->latest()->get();
        $directChats  = $user->conversations()->where('type', 'direct')->with('users')->latest()->get();

        // Members list/modals
        $allStaff     = User::where('id', '!=', $user->id)
                            ->internalStaff()
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->get();

        $conversation = null;
        $messages     = collect();
        $members      = collect();
        $isGroupAdmin = false;

        $forwardConversations = collect([$broadcast])
            ->concat($groups)
            ->concat($directChats)
            ->filter()
            ->unique('id');

        return view('portal.messages', compact(
            'conversation',
            'messages',
            'broadcast',
            'groups',
            'directChats',
            'members',
            'allStaff',
            'isGroupAdmin',
            'forwardConversations'
        ));
    }

    // ─── Create Broadcast ────────────────────────────────────────────────────

    public function createBroadcast(): RedirectResponse
    {
        $broadcast = Conversation::where('type', 'broadcast')->first();

        if (! $broadcast) {
            $broadcast = Conversation::create([
                'name'        => 'All Staff',
                'description' => 'Company-wide broadcast channel for all CMIH staff.',
                'type'        => 'broadcast',
                'creator_id'  => null,
            ]);
        }

        return redirect()->route('portal.messages.show', $broadcast)
                         ->with('status', 'Broadcast channel opened.');
    }

    // ─── Show: load a single conversation ────────────────────────────────────

    public function show(Conversation $conversation): View|RedirectResponse
    {
        $user = auth()->user();

        // Access control: non-broadcast conversations are members-only
        if (! $conversation->hasAccess($user)) {
            abort(403, 'You are not a member of this conversation.');
        }

        // Mark all messages from other users in this conversation as read
        $unreadMessages = $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->get();

        foreach ($unreadMessages as $msg) {
            if (!$msg->readers->contains($user->id)) {
                $msg->readers()->syncWithoutDetaching([$user->id]);
            }
        }

        // Load messages eagerly with sender info, replyTo, and readers
        $messages = $conversation->messages()
            ->with(['user', 'readers', 'replyTo.user'])
            ->oldest()
            ->get();

        // Sidebar data
        $broadcast    = Conversation::where('type', 'broadcast')->first();
        $groups       = $user->conversations()->where('type', 'group')->with('users')->latest()->get();
        $directChats  = $user->conversations()->where('type', 'direct')->with('users')->latest()->get();

        // Members list for the group info panel and modals
        $members      = $conversation->users()->get();
        $allStaff     = User::where('id', '!=', $user->id)
                            ->internalStaff()
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->get();

        $isGroupAdmin = $conversation->isAdmin($user);

        // List of all conversations current user can forward to
        $forwardConversations = collect([$broadcast])
            ->concat($groups)
            ->concat($directChats)
            ->filter()
            ->unique('id');

        return view('portal.messages', compact(
            'conversation',
            'messages',
            'broadcast',
            'groups',
            'directChats',
            'members',
            'allStaff',
            'isGroupAdmin',
            'forwardConversations'
        ));
    }

    // ─── Send: post a message (text + optional media) ────────────────────────

    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();

        if (! $conversation->hasAccess($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'body'        => ['nullable', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'exists:messages,id'],
            'attachment'  => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,pdf,doc,docx,xls,xlsx'],
        ]);

        if (empty($validated['body']) && ! $request->hasFile('attachment')) {
            return back()->withErrors(['body' => 'Please enter a message or attach a file.']);
        }

        $replyToId = null;
        if (!empty($validated['reply_to_id'])) {
            $replyMsg = Message::find($validated['reply_to_id']);
            if ($replyMsg && $replyMsg->conversation_id === $conversation->id) {
                $replyToId = $replyMsg->id;
            }
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file    = $request->file('attachment');
            $mime    = $file->getMimeType();
            $storedPath = $file->store('messenger', 'local');

            $attachmentPath = $storedPath;

            if (str_starts_with($mime, 'image/')) {
                $attachmentType = 'image';
            } elseif (str_starts_with($mime, 'video/')) {
                $attachmentType = 'video';
            } else {
                $attachmentType = 'file';
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'reply_to_id'     => $replyToId,
            'body'            => $validated['body'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
        ]);

        return redirect()->route('portal.messages.show', $conversation)
                         ->with('scrollToBottom', true);
    }

    public function downloadAttachment(Request $request, Message $message)
    {
        if (! $message->hasAttachment()) {
            abort(404);
        }

        if (! $message->conversation->hasAccess($request->user())) {
            abort(403);
        }

        $path = ltrim(str_replace('\\', '/', (string) $message->attachment_path), '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $name = basename($path);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, $name);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, $name);
        }

        abort(404);
    }

    // ─── Edit Message ────────────────────────────────────────────────────────

    public function editMessage(Request $request, Message $message): RedirectResponse
    {
        $user = auth()->user();

        if ($message->user_id !== $user->id) {
            abort(403, 'You can only edit your own messages.');
        }

        if ($message->is_deleted) {
            return back()->withErrors(['body' => 'Cannot edit a deleted message.']);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message->update([
            'body' => $validated['body'],
            'is_edited' => true,
        ]);

        return back()->with('status', 'Message edited.');
    }

    // ─── Delete Message ──────────────────────────────────────────────────────

    public function deleteMessage(Request $request, Message $message): RedirectResponse
    {
        $user = auth()->user();

        $isAdmin = false;
        if ($message->conversation->type === 'group') {
            $isAdmin = $message->conversation->isAdmin($user);
        }

        if ($message->user_id !== $user->id && !$isAdmin) {
            abort(403, 'You are not authorized to delete this message.');
        }

        $message->update([
            'body' => null,
            'attachment_path' => null,
            'attachment_type' => null,
            'is_deleted' => true,
        ]);

        return back()->with('status', 'Message deleted.');
    }

    // ─── Forward Message ─────────────────────────────────────────────────────

    public function forwardMessage(Request $request, Message $message): RedirectResponse
    {
        $user = auth()->user();

        if (!$message->conversation->hasAccess($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
        ]);

        $targetConversation = Conversation::findOrFail($validated['conversation_id']);

        if (!$targetConversation->hasAccess($user)) {
            abort(403);
        }

        // Copy body or attachment to the target conversation
        Message::create([
            'conversation_id' => $targetConversation->id,
            'user_id'         => $user->id,
            'body'            => $message->body ? $message->body . ' (Forwarded)' : null,
            'attachment_path' => $message->attachment_path,
            'attachment_type' => $message->attachment_type,
        ]);

        return redirect()->route('portal.messages.show', $targetConversation)
                         ->with('status', 'Message forwarded.');
    }

    // ─── Create Group ─────────────────────────────────────────────────────────

    public function createGroup(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'members'     => ['required', 'array', 'min:1'],
            'members.*'   => ['integer', 'exists:users,id'],
        ]);

        $requestedMemberIds = collect($validated['members'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->unique()
            ->values();
        $memberIds = User::whereIn('id', $requestedMemberIds)
            ->internalStaff()
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($memberIds->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['members' => 'Please select at least one active internal staff member for the group.']);
        }

        $conversation = Conversation::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type'        => 'group',
            'creator_id'  => $user->id,
        ]);

        // Attach creator as admin
        $conversation->users()->attach($user->id, ['is_admin' => true]);

        // Attach selected members (not admin)
        foreach ($memberIds as $memberId) {
            $conversation->users()->syncWithoutDetaching([
                $memberId => ['is_admin' => false],
            ]);
        }

        return redirect()->route('portal.messages.show', $conversation)
                         ->with('status', 'Group created successfully.');
    }

    // ─── Start DM ─────────────────────────────────────────────────────────────

    public function startDm(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id', 'different:' . $user->id],
        ]);

        $recipientId = (int) $validated['recipient_id'];

        // Check if DM already exists between these two users
        $existing = Conversation::where('type', 'direct')
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('user_id', $recipientId))
            ->first();

        if ($existing) {
            return redirect()->route('portal.messages.show', $existing);
        }

        // Create new DM conversation
        $conversation = Conversation::create([
            'type'       => 'direct',
            'creator_id' => $user->id,
        ]);

        $conversation->users()->attach($user->id, ['is_admin' => false]);
        $conversation->users()->attach($recipientId, ['is_admin' => false]);

        return redirect()->route('portal.messages.show', $conversation);
    }

    // ─── Add Group Member ─────────────────────────────────────────────────────

    public function addMember(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = auth()->user();

        if ($conversation->type !== 'group' || ! $conversation->isAdmin($user)) {
            abort(403, 'Only group admins can add members.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $newUserId = (int) $validated['user_id'];
        $newUser = User::internalStaff()
            ->where('status', 'active')
            ->find($newUserId);

        if (! $newUser) {
            return back()->withErrors(['user_id' => 'Only active CMIH staff can be added to staff group chats.']);
        }

        if (! $conversation->users->contains('id', $newUserId)) {
            $conversation->users()->attach($newUserId, ['is_admin' => false]);
        }

        return back()->with('status', 'Member added.');
    }

    // ─── Remove Group Member ──────────────────────────────────────────────────

    public function removeMember(Request $request, Conversation $conversation, User $target): RedirectResponse
    {
        $user = auth()->user();

        if ($conversation->type !== 'group' || ! $conversation->isAdmin($user)) {
            abort(403, 'Only group admins can remove members.');
        }

        // Prevent removing yourself (admin) through this route
        if ($target->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself.']);
        }

        $conversation->users()->detach($target->id);

        return back()->with('status', 'Member removed.');
    }
}
