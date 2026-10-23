<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\EventPublication;
use Illuminate\Http\Request;

class EventPublicationController extends Controller
{
    public function index(Request $r)
    {
        abort_unless(EventPublication::allowed($r->user()), 403);
        $events = Event::with('creator')->orderByDesc('starts_at')->paginate(12);

        return view('portal.events.index', compact('events'));
    }

    public function store(Request $r, EventPublication $publisher)
    {
        abort_unless(EventPublication::allowed($r->user()), 403);
        $data = $this->data($r);
        $event = $publisher->save($data, $r->user(), $r->file('image'));

        return back()->with('status', $event->status === 'published' ? 'Event published to the website feed.' : 'Event saved as a draft.');
    }

    public function update(Request $r, Event $event, EventPublication $publisher)
    {
        abort_unless(EventPublication::allowed($r->user()) && ((int) $event->created_by === $r->user()->id || $r->user()->isLineManager() || $r->user()->isCvoOrSuperAdmin()), 403);
        $data = $this->data($r, false);
        $r->validate(['revision' => 'required|integer']);
        $publisher->save($data, $r->user(), $r->file('image'), $event, (int) $r->revision);

        return back()->with('status', 'Event updated. The website feed will refresh automatically.');
    }

    public function flyer(Request $r, Event $event)
    {
        abort_unless(EventPublication::allowed($r->user()) || $r->user()->hasRole(['admin', 'super_admin']), 403);
        $disk = \Illuminate\Support\Facades\Storage::disk($event->image_disk ?: 'public');
        abort_unless($event->image_path && $disk->exists($event->image_path), 404);

        return response()->file($disk->path($event->image_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function data(Request $r, bool $new = true): array
    {
        return $r->validate(['title' => 'required|string|max:255', 'summary' => 'nullable|string|max:2000', 'location' => 'required|string|max:255', 'starts_at' => 'required|date|after_or_equal:today', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'registration_url' => 'nullable|url:http,https|max:255', 'status' => 'required|in:published,draft', 'image' => [$new ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:max_width=6000,max_height=6000']]);
    }
}
