<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
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
        $brand = $request->string('brand')->toString();
        $staffFilter = $request->string('staff')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $assetsQuery = Asset::query()
            ->where(function ($query) {
                $query->where('is_warehouse_tracked', false)
                    ->orWhereNull('is_warehouse_tracked');
            });

        if ($search !== '') {
            $assetsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('assigned_to', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('warehouse_location', 'like', "%{$search}%");
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

        if ($brand !== '') {
            $assetsQuery->where('brand', $brand);
        }

        if ($staffFilter !== '') {
            $assetsQuery->where(function ($query) use ($staffFilter) {
                $query->where('assigned_to', $staffFilter)
                    ->orWhere('added_by', $staffFilter)
                    ->orWhere('last_handled_by', $staffFilter);
            });
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

        $assets = $assetsQuery->paginate(10, ['*'], 'asset_page')->withQueryString();
        $staff = \App\Models\User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $canCreateAssets = $request->user()?->isActive() ?? false;
        $brands = Asset::where(function ($query) {
                $query->where('is_warehouse_tracked', false)
                    ->orWhereNull('is_warehouse_tracked');
            })
            ->whereNotNull('brand')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return view('portal.assets', compact('assets', 'search', 'type', 'status', 'condition', 'brand', 'staffFilter', 'sort', 'direction', 'staff', 'canCreateAssets', 'brands'));
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
            'is_warehouse_tracked' => false,
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

    public function update(Request $request, Asset $asset): RedirectResponse|JsonResponse
    {
        $this->authorizeAssetManagement($request, $asset);

        if (! $asset->is_warehouse_tracked && $request->boolean('is_warehouse_tracked') && ! $request->user()->canOwnWarehouseAssets()) {
            abort(403, 'Only Super Admin, the Operations HOD, an active acting Operations HOD, or an appointed Warehouse Assets collaborator can move assets into the warehouse manager.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'condition' => ['required', 'string', 'in:New,Excellent,Good,Fair,Poor'],
            'type' => ['required', 'string', 'max:80'],
            'status' => ['required', 'string', 'max:80'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:120'],
            'asset_tag' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'asset_value' => ['nullable', 'numeric', 'min:0'],
            'po_quantity' => ['nullable', 'integer', 'min:0'],
            'quantity_procured' => ['nullable', 'integer', 'min:0'],
            'owner' => ['nullable', 'string', 'max:120'],
            'asset_use_type' => ['nullable', 'string', 'max:80'],
            'warehouse_location' => ['nullable', 'string', 'max:255'],
            'warehouse_quantity' => ['nullable', 'integer', 'min:0'],
            'warehouse_notes' => ['nullable', 'string', 'max:3000'],
            'is_warehouse_tracked' => ['nullable', 'boolean'],
            'remodel_status' => ['nullable', 'string', 'max:120'],
            'remodel_notes' => ['nullable', 'string', 'max:3000'],
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
            'brand' => $validated['brand'] ?? null,
            'asset_tag' => $validated['asset_tag'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'category' => $validated['category'] ?? null,
            'asset_value' => $validated['asset_value'] ?? null,
            'po_quantity' => $validated['po_quantity'] ?? 0,
            'quantity_procured' => $validated['quantity_procured'] ?? 0,
            'owner' => $validated['owner'] ?? null,
            'asset_use_type' => $validated['asset_use_type'] ?? null,
            'warehouse_location' => $validated['warehouse_location'] ?? null,
            'warehouse_quantity' => $validated['warehouse_quantity'] ?? $asset->warehouse_quantity,
            'warehouse_notes' => $validated['warehouse_notes'] ?? null,
            'is_warehouse_tracked' => $request->user()->canOwnWarehouseAssets()
                ? $request->boolean('is_warehouse_tracked')
                : (bool) $asset->is_warehouse_tracked,
            'remodel_status' => $validated['remodel_status'] ?? null,
            'remodel_notes' => $validated['remodel_notes'] ?? null,
        ])->save();

        $route = $asset->is_warehouse_tracked ? 'portal.assets.warehouse.index' : 'portal.assets';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Asset updated.',
                'redirect_url' => route($route),
            ]);
        }

        return redirect()->route($route)->with('status', 'Asset updated.');
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

        if ($asset->is_warehouse_tracked && ! $user->canOwnWarehouseAssets()) {
            abort(403, 'Only Super Admin, the Operations HOD, an active acting Operations HOD, or an appointed Warehouse Assets collaborator can edit warehouse assets.');
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
