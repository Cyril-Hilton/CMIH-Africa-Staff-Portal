<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicEventFeedController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:24']);
        $events = Event::where('status', 'published')->where(fn ($q) => $q->whereDate('ends_at', '>=', today())->orWhere(fn ($q) => $q->whereNull('ends_at')->whereDate('starts_at', '>=', today())))->orderBy('starts_at')->orderBy('id')->paginate($v['per_page'] ?? 12);
        $data = $events->getCollection()->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'summary' => strip_tags($e->summary ?? ''), 'location' => $e->location, 'starts_at' => $e->starts_at->toDateString(), 'ends_at' => $e->ends_at?->toDateString(), 'registration_url' => $e->registration_url, 'image_url' => $e->image_path ? route('public.events.flyer', ['event' => $e->id, 'v' => substr(hash('sha256', $e->image_path), 0, 16)]) : null, 'updated_at' => $e->updated_at->toIso8601String()]);
        $response = response()->json(['data' => $data, 'meta' => ['current_page' => $events->currentPage(), 'last_page' => $events->lastPage(), 'total' => $events->total(), 'per_page' => $events->perPage()]]);
        $response->headers->set('Cache-Control', 'public, max-age=30, s-maxage=30');
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->isNotModified($r);

        return $response;
    }

    public function flyer(Request $r, Event $event)
    {
        abort_unless($event->status === 'published' && $event->image_path, 404);
        $disk = Storage::disk($event->image_disk ?: 'public');
        abort_unless($disk->exists($event->image_path), 404);
        $response = response()->file($disk->path($event->image_path), ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
        $response->setEtag(hash_file('sha256', $disk->path($event->image_path)));
        $response->isNotModified($r);

        return $response;
    }
}
