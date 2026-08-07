<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ShareController extends Controller
{
    /**
     * Share a link/resource with another staff member via Direct Message and/or Email
     */
    public function share(Request $request): RedirectResponse
    {
        $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'resource_link' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:chat,email'],
        ]);

        $user = auth()->user();
        $recipient = User::findOrFail($request->input('recipient_id'));
        $link = $request->input('resource_link');
        $note = $request->input('note', '');
        $channels = $request->input('channels');

        $shareText = "🔗 Shared Resource: " . $link;
        if (!empty($note)) {
            $shareText .= "\n\nNote: " . $note;
        }

        // 1. Share via Portal Direct Chat
        if (in_array('chat', $channels, true)) {
            // Find or create direct message conversation
            $conversation = Conversation::where('type', 'direct')
                ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
                ->whereHas('users', fn ($q) => $q->where('user_id', $recipient->id))
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'creator_id' => $user->id,
                ]);
                $conversation->users()->attach($user->id, ['is_admin' => false]);
                $conversation->users()->attach($recipient->id, ['is_admin' => false]);
            }

            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'body' => $shareText,
            ]);
        }

        // 2. Share via Email
        if (in_array('email', $channels, true)) {
            try {
                Mail::raw("Hello " . $recipient->name . ",\n\n" . $user->name . " has shared a resource link with you from the CMIH Africa Portal.\n\n" . $shareText . "\n\nBest regards,\nCMIH Africa Portal", function ($mail) use ($recipient, $user) {
                    $mail->to($recipient->email)
                         ->subject('CMIH Portal: Resource shared by ' . $user->name);
                });
            } catch (\Exception $e) {
                // Log warning and continue so API doesn't fail if SMTP is not configured
                logger()->warning('Email sharing failed: ' . $e->getMessage());
            }
        }

        return back()->with('status', 'Resource shared successfully via: ' . implode(' & ', array_map('ucfirst', $channels)));
    }
}
