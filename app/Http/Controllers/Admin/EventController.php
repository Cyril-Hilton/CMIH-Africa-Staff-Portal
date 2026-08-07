<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::orderByDesc('starts_at')->paginate(8);

        return view('admin.events', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'string', Rule::in(['published', 'draft'])],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,bmp,gif,tif,tiff', 'max:6144'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('events', 'public');
        }

        Event::create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'location' => $validated['location'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'registration_url' => $validated['registration_url'] ?? null,
            'status' => $validated['status'],
            'image_path' => $imagePath,
        ]);

        return back()->with('status', 'Event published.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        if ($event->image_path) {
            Storage::disk('public')->delete($event->image_path);
        }

        $event->delete();

        return back()->with('status', 'Event deleted.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'string', Rule::in(['published', 'draft'])],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,bmp,gif,tif,tiff', 'max:6144'],
        ]);

        $imagePath = $event->image_path;

        if ($request->hasFile('image')) {
            if ($event->image_path) {
                Storage::disk('public')->delete($event->image_path);
            }

            $imagePath = $request->file('image')->store('events', 'public');
        }

        $event->update([
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'location' => $validated['location'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'registration_url' => $validated['registration_url'] ?? null,
            'status' => $validated['status'],
            'image_path' => $imagePath,
        ]);

        return back()->with('status', 'Event updated.');
    }
}
