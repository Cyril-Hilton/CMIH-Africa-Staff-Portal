<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $condition = $request->string('condition')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $assetsQuery = Asset::query();

        if ($search !== '') {
            $assetsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('assigned_to', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($type !== '') {
            $assetsQuery->where(function ($q) use ($type) {
                $q->where('type', $type);
                if (\Illuminate\Support\Facades\Schema::hasColumn('assets', 'asset_type')) {
                    $q->orWhere('asset_type', $type);
                }
            });
        }

        if ($status !== '') {
            $assetsQuery->where('status', $status);
        }

        if ($condition !== '') {
            $assetsQuery->where('condition', $condition);
        }

        switch ($sort) {
            case 'name':
            case 'status':
            case 'condition':
                $assetsQuery->orderBy($sort, $direction);
                break;
            case 'asset_type':
            case 'type':
                $assetsQuery->orderBy('type', $direction);
                break;
            case 'assigned_to':
                $assetsQuery->orderBy('assigned_to', $direction);
                break;
            default:
                $assetsQuery->latest();
        }

        $assets = $assetsQuery->paginate(10)->withQueryString();
        $staff = \App\Models\User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $canCreateAssets = $request->user()?->isActive() ?? false;

        return view('portal.assets', compact('assets', 'search', 'type', 'status', 'condition', 'sort', 'direction', 'staff', 'canCreateAssets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'condition' => ['required', 'string', 'in:New,Good,Fair,Poor'],
            'type' => ['required', 'string', 'in:Hardware,Software,Vehicle,Other'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'], // 10MB max
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        Asset::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'condition' => $validated['condition'],
            'type' => $validated['type'],
            'status' => 'Available', // Default status
            'assigned_to' => $validated['assigned_to'] ?? null,
            'image_path' => $imagePath,
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Asset added successfully.');
    }

    public function show(Asset $asset): View
    {
        return view('portal.assets-show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        $this->authorizeAssetManagement(request(), $asset);

        $staff = \App\Models\User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        return view('portal.assets-edit', compact('asset', 'staff'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorizeAssetManagement($request, $asset);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'condition' => ['required', 'string', 'in:New,Good,Fair,Poor'],
            'type' => ['required', 'string', 'in:Hardware,Software,Vehicle,Other'],
            'status' => ['required', 'string', 'in:Available,In Use,Maintenance,Retired'], // Expanded status options
            'assigned_to' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');

            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }

            $asset->image_path = $imagePath;
        }

        $asset->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'condition' => $validated['condition'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return redirect()->route('portal.assets')->with('status', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorizeAssetManagement(request(), $asset);

        if ($asset->image_path) {
            Storage::disk('public')->delete($asset->image_path);
        }

        $asset->delete();

        return back()->with('status', 'Asset deleted.');
    }

    private function authorizeAssetManagement(Request $request, Asset $asset): void
    {
        $user = $request->user();

        if (!$user || !$user->isActive()) {
            abort(403, 'You must be an active staff member to manage assets.');
        }

        $department = strtolower(trim((string) $user->department));
        $isAssetTeam = in_array($department, ['operations_projects', 'operations', 'hr_admin', 'admin'], true);

        if (
            $user->isCvoOrSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasFullHrAccess()
            || $isAssetTeam
            || (int) $asset->added_by === (int) $user->id
            || (int) $asset->assigned_to === (int) $user->id
        ) {
            return;
        }

        abort(403, 'Only asset collaborators, operations, HR/Admin, CVO, or Super Admin can manage this asset.');
    }
}
