<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::latest()->paginate(24);
        return view('admin.brands', compact('brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp,avif|max:2048', // 2MB Max
            'logo_dark' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:2048',
        ]);

        $path = $request->file('logo')->store('brands', 'public');
        $darkPath = null;
        if ($request->hasFile('logo_dark')) {
            $darkPath = $request->file('logo_dark')->store('brands', 'public');
        }

        Brand::create([
            'name' => $validated['name'],
            'logo_path' => $path,
            'logo_dark_path' => $darkPath,
        ]);

        return back()->with('status', 'Brand added successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Storage::disk('public')->delete($brand->logo_path);
        if ($brand->logo_dark_path) {
            Storage::disk('public')->delete($brand->logo_dark_path);
        }
        $brand->delete();

        return back()->with('status', 'Brand deleted.');
    }
}
