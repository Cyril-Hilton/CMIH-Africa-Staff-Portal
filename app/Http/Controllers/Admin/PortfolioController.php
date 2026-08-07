<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioAlbum;
use App\Models\PortfolioImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(): View
    {
        $albums = PortfolioAlbum::withCount('images')->latest()->paginate(10);
        return view('admin.portfolio', compact('albums'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'cover_image' => 'required|image|mimes:jpg,jpeg,png,webp,avif,gif|max:2048',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('portfolio/covers', 'public');
            $validated['cover_image'] = $path;
        }

        PortfolioAlbum::create($validated);

        return back()->with('success', 'Album created successfully.');
    }

    public function edit(PortfolioAlbum $album): View
    {
        return view('admin.portfolio-edit', compact('album'));
    }

    public function update(Request $request, PortfolioAlbum $album): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif,gif|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            // Delete old cover
            if ($album->cover_image) {
                Storage::disk('public')->delete($album->cover_image);
            }
            $path = $request->file('cover_image')->store('portfolio/covers', 'public');
            $validated['cover_image'] = $path;
        }

        $album->update($validated);

        return back()->with('success', 'Album details updated.');
    }

    public function upload(Request $request, PortfolioAlbum $album): RedirectResponse
    {
        $request->validate([
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp,avif,gif|max:5120', // 5MB max
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('portfolio/gallery/' . $album->id, 'public');
                $album->images()->create(['image_path' => $path]);
            }
        }

        return back()->with('success', 'Images uploaded successfully.');
    }

    public function destroyImage(PortfolioImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function destroy(PortfolioAlbum $album): RedirectResponse
    {
        // Delete cover
        if ($album->cover_image) {
            Storage::disk('public')->delete($album->cover_image);
        }

        // Delete all gallery images
        foreach ($album->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $album->delete(); // Cascade deletes image records

        return redirect()->route('admin.portfolio')->with('success', 'Album deleted.');
    }
}
