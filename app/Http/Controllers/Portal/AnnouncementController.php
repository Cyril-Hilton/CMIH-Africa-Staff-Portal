<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::visibleTo($user)
            ->with('user')
            ->latest()
            ->paginate(6, ['*'], 'announcements_page');

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->paginate(15, ['*'], 'notifications_page');
        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return view('portal.notifications', compact('announcements', 'notifications', 'unreadCount'));
    }

    public function markAsRead(Notification $notification, Request $request): RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        if ($notification->url) {
            return redirect($notification->url);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
