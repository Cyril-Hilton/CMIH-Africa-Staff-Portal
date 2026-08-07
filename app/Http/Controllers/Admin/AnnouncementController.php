<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::with('user')->latest()->paginate(8);

        return view('admin.announcements', compact('announcements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $announcement = Announcement::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'pinned' => (bool) ($validated['pinned'] ?? false),
            'audience_type' => 'all',
        ]);

        \App\Services\NotificationService::broadcast(
            'New Company Announcement: ' . $announcement->title,
            $announcement->plainBody(150),
            route('portal.announcements'),
            $request->user()->id
        );

        return back()->with('status', 'Announcement published.');
    }
}
