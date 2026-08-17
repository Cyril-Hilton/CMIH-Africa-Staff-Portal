<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'condition' => $request->string('condition')->toString(),
            'brand' => $request->string('brand')->toString(),
            'staff' => $request->string('staff')->toString(),
        ];

        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $assetsQuery = Asset::query()
            ->with(['assignee', 'creator', 'lastHandler'])
            ->withCount([
                'warehouseRequests as open_warehouse_requests_count' => fn (Builder $query) => $query
                    ->whereNotIn('status', [AssetWarehouseRequest::STATUS_CLOSED, AssetWarehouseRequest::STATUS_REJECTED]),
            ]);

        $this->applyAssetFilters($assetsQuery, $filters);
        $this->applyAssetSorting($assetsQuery, $sort, $direction);

        $assets = $assetsQuery->paginate(10)->withQueryString();

        $warehouseQuery = Asset::query()
            ->with(['assignee', 'lastHandler', 'warehouseRequests.requester'])
            ->where('is_warehouse_tracked', true);
        $this->applyAssetFilters($warehouseQuery, $filters);

        $warehouseAssets = $warehouseQuery
            ->orderByRaw("CASE WHEN status = 'Available' THEN 0 ELSE 1 END")
            ->orderBy('brand')
            ->orderBy('name')
            ->paginate(8, ['*'], 'warehouse_page')
            ->withQueryString();

        $requestQuery = AssetWarehouseRequest::with(['asset', 'requester', 'reviewer', 'issuer', 'closer'])
            ->latest();

        $myRequestQuery = AssetWarehouseRequest::with(['asset', 'reviewer', 'issuer', 'closer'])
            ->where('requested_by', $request->user()->id)
            ->latest();

        $this->applyWarehouseRequestFilters($requestQuery, $filters);
        $this->applyWarehouseRequestFilters($myRequestQuery, $filters);

        if (! $this->canManageWarehouse($request->user())) {
            $requestQuery->where('requested_by', $request->user()->id);
        }

        $warehouseRequests = $requestQuery->paginate(8, ['*'], 'warehouse_request_page')->withQueryString();
        $myWarehouseRequests = $myRequestQuery->paginate(5, ['*'], 'my_warehouse_request_page')->withQueryString();

        $warehouseStats = [
            'tracked' => (clone $warehouseQuery)->count(),
            'quantity' => (clone $warehouseQuery)->sum('warehouse_quantity'),
            'pending' => AssetWarehouseRequest::whereIn('status', [
                AssetWarehouseRequest::STATUS_PENDING_CHECK,
                AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
                AssetWarehouseRequest::STATUS_APPROVED_FOR_USE,
                AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE,
            ])->count(),
            'issued' => AssetWarehouseRequest::where('status', AssetWarehouseRequest::STATUS_ISSUED)->count(),
        ];

        $staff = User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $brands = Asset::query()
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
        $canCreateAssets = $request->user()?->isActive() ?? false;
        $canManageWarehouse = $this->canManageWarehouse($request->user());

        return view('portal.assets', [
            'assets' => $assets,
            'warehouseAssets' => $warehouseAssets,
            'warehouseRequests' => $warehouseRequests,
            'myWarehouseRequests' => $myWarehouseRequests,
            'warehouseStats' => $warehouseStats,
            'filters' => $filters,
            'search' => $filters['search'],
            'type' => $filters['type'],
            'status' => $filters['status'],
            'condition' => $filters['condition'],
            'brand' => $filters['brand'],
            'staffFilter' => $filters['staff'],
            'sort' => $sort,
            'direction' => $direction,
            'staff' => $staff,
            'brands' => $brands,
            'canCreateAssets' => $canCreateAssets,
            'canManageWarehouse' => $canManageWarehouse,
            'warehouseStatusLabels' => $this->warehouseStatusLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->assetValidationRules());

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assets', 'public');
        }

        Asset::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'condition' => $validated['condition'],
            'type' => $validated['type'],
            'status' => 'Available',
            'assigned_to' => $validated['assigned_to'] ?? null,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'image_path' => $imagePath,
            'added_by' => $request->user()->id,
            'brand' => $validated['brand'] ?? null,
            'warehouse_location' => $validated['warehouse_location'] ?? null,
            'warehouse_quantity' => $validated['warehouse_quantity'] ?? 1,
            'is_warehouse_tracked' => $request->boolean('is_warehouse_tracked'),
            'warehouse_notes' => $validated['warehouse_notes'] ?? null,
            'last_handled_by' => $validated['assigned_to'] ?? null,
            'last_handled_at' => filled($validated['assigned_to'] ?? null) ? now() : null,
        ]);

        return back()->with('status', 'Asset added successfully.');
    }

    public function show(Asset $asset): View
    {
        $asset->load(['assignee', 'creator', 'lastHandler', 'warehouseRequests.requester']);

        return view('portal.assets-show', [
            'asset' => $asset,
            'warehouseStatusLabels' => $this->warehouseStatusLabels(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->authorizeAssetManagement(request(), $asset);

        $staff = User::internalStaff()->where('status', 'active')->orderBy('name')->get();

        return view('portal.assets-edit', compact('asset', 'staff'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorizeAssetManagement($request, $asset);

        $validated = $request->validate($this->assetValidationRules([
            'status' => ['required', 'string', Rule::in(['Available', 'In Use', 'Maintenance', 'Retired'])],
        ]));

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
            'warehouse_location' => $validated['warehouse_location'] ?? null,
            'warehouse_quantity' => $validated['warehouse_quantity'] ?? 1,
            'is_warehouse_tracked' => $request->boolean('is_warehouse_tracked'),
            'warehouse_notes' => $validated['warehouse_notes'] ?? null,
        ]);

        if ($asset->isDirty('assigned_to')) {
            $asset->last_handled_by = $validated['assigned_to'] ?? null;
            $asset->last_handled_at = filled($validated['assigned_to'] ?? null) ? now() : null;
        }

        $asset->save();

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

    public function requestWarehouseAsset(Request $request, Asset $asset): RedirectResponse
    {
        if (! $request->user()?->isActive()) {
            abort(403, 'Only active staff can request warehouse assets.');
        }

        if (! $asset->is_warehouse_tracked) {
            return back()->withErrors(['asset' => 'This asset is not currently tracked in the warehouse.']);
        }

        $validated = $request->validate([
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'requested_for' => ['nullable', 'date'],
            'destination_location' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:2000'],
            'requester_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ((int) $validated['requested_quantity'] > (int) $asset->warehouse_quantity) {
            return back()->withErrors(['requested_quantity' => 'Requested quantity is higher than the quantity currently available in the warehouse.']);
        }

        AssetWarehouseRequest::create([
            'request_code' => $this->nextWarehouseRequestCode(),
            'asset_id' => $asset->id,
            'requested_by' => $request->user()->id,
            'requested_quantity' => $validated['requested_quantity'],
            'requested_for' => $validated['requested_for'] ?? null,
            'destination_location' => $validated['destination_location'],
            'purpose' => $validated['purpose'],
            'requester_notes' => $validated['requester_notes'] ?? null,
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ]);

        return back()->with('status', 'Warehouse asset request submitted for approval.');
    }

    public function updateWarehouseEvidence(Request $request, AssetWarehouseRequest $assetWarehouseRequest): RedirectResponse
    {
        $this->authorizeWarehouseEvidence($request, $assetWarehouseRequest);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(['pre_use', 'return'])],
            'evidence_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $path = $request->file('evidence_image')->store('assets/warehouse', 'public');

        if ($validated['stage'] === 'pre_use') {
            $assetWarehouseRequest->fill([
                'pre_use_image_path' => $path,
                'requester_notes' => $validated['note'] ?? $assetWarehouseRequest->requester_notes,
                'status' => AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
            ])->save();

            return back()->with('status', 'Inspection image submitted. The approver can now approve use of the asset.');
        }

        $assetWarehouseRequest->fill([
            'return_image_path' => $path,
            'return_note' => $validated['note'] ?? null,
            'status' => AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE,
            'returned_at' => now(),
        ])->save();

        return back()->with('status', 'Return image submitted. The asset is now pending closure.');
    }

    public function correctWarehouseRequest(Request $request, AssetWarehouseRequest $assetWarehouseRequest): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ((int) $assetWarehouseRequest->requested_by !== (int) $user->id && ! $this->canManageWarehouse($user))) {
            abort(403, 'Only the requester or warehouse approvers can correct this request.');
        }

        if (! in_array($assetWarehouseRequest->status, [
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
            AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ], true)) {
            return back()->withErrors(['request' => 'Only pending or returned requests can be corrected.']);
        }

        $validated = $request->validate([
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'requested_for' => ['nullable', 'date'],
            'destination_location' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:2000'],
            'requester_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = $assetWarehouseRequest->asset;
        if ($asset && (int) $validated['requested_quantity'] > (int) $asset->warehouse_quantity) {
            return back()->withErrors(['requested_quantity' => 'Requested quantity is higher than the quantity currently available in the warehouse.']);
        }

        $assetWarehouseRequest->fill([
            'requested_quantity' => $validated['requested_quantity'],
            'requested_for' => $validated['requested_for'] ?? null,
            'destination_location' => $validated['destination_location'],
            'purpose' => $validated['purpose'],
            'requester_notes' => $validated['requester_notes'] ?? null,
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ])->save();

        return back()->with('status', 'Warehouse asset request corrected and resubmitted.');
    }

    public function warehouseAction(Request $request, AssetWarehouseRequest $assetWarehouseRequest): RedirectResponse
    {
        if (! $this->canManageWarehouse($request->user())) {
            abort(403, 'Only HR, Operations HOD, CVO, or Super Admin can approve warehouse asset requests.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve_check', 'send_back', 'reject', 'approve_use', 'issue', 'close'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ]);

        DB::transaction(function () use ($request, $assetWarehouseRequest, $validated): void {
            $lockedRequest = AssetWarehouseRequest::whereKey($assetWarehouseRequest->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::whereKey($lockedRequest->asset_id)->lockForUpdate()->firstOrFail();

            match ($validated['action']) {
                'approve_check' => $this->approveCheck($lockedRequest, $request->user(), $validated['note'] ?? null),
                'send_back' => $this->sendBack($lockedRequest, $request->user(), $validated['note'] ?? null),
                'reject' => $this->rejectRequest($lockedRequest, $request->user(), $validated['note'] ?? null),
                'approve_use' => $this->approveUse($lockedRequest, $request->user(), $validated['note'] ?? null),
                'issue' => $this->issueAsset($request, $lockedRequest, $asset, $validated['note'] ?? null),
                'close' => $this->closeRequest($lockedRequest, $asset, $request->user(), $validated['note'] ?? null),
            };
        });

        return back()->with('status', 'Warehouse request updated.');
    }

    public function exportWarehouse(Request $request, string $format = 'csv'): Response|StreamedResponse
    {
        if (! $this->canManageWarehouse($request->user())) {
            abort(403, 'Only HR, Operations HOD, CVO, or Super Admin can export warehouse asset data.');
        }

        $format = strtolower($format);
        $filters = [
            'search' => $request->string('search')->toString(),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'condition' => $request->string('condition')->toString(),
            'brand' => $request->string('brand')->toString(),
            'staff' => $request->string('staff')->toString(),
        ];
        $rows = $this->warehouseExportRows($filters);
        $fileBase = 'asset_warehouse_tracker_' . now()->format('Y-m-d_H-i-s');

        if ($format === 'xls' || $format === 'excel') {
            $html = view('portal.assets-warehouse-export', ['rows' => $rows, 'printedAt' => now()])->render();

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$fileBase.'.xls"',
            ]);
        }

        if ($format === 'pdf' || $format === 'print') {
            $html = view('portal.assets-warehouse-export', ['rows' => $rows, 'printedAt' => now()])->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Disposition' => 'inline; filename="'.$fileBase.'.html"',
            ]);
        }

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileBase.'.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    private function assetValidationRules(array $overrides = []): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'condition' => ['required', 'string', Rule::in(['New', 'Good', 'Fair', 'Poor'])],
            'type' => ['required', 'string', Rule::in(['Hardware', 'Software', 'Vehicle', 'Other'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:255'],
            'warehouse_location' => ['nullable', 'string', 'max:255'],
            'warehouse_quantity' => ['nullable', 'integer', 'min:0'],
            'warehouse_notes' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ], $overrides);
    }

    private function applyAssetFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $search = $filters['search'];
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('warehouse_location', 'like', "%{$search}%")
                    ->orWhereHas('assignee', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['type', 'status', 'condition', 'brand'] as $column) {
            if ($filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }

        if ($filters['staff'] !== '') {
            $staffId = (int) $filters['staff'];
            $query->where(function (Builder $staffQuery) use ($staffId): void {
                $staffQuery->where('assigned_to', $staffId)
                    ->orWhere('last_handled_by', $staffId)
                    ->orWhere('added_by', $staffId);
            });
        }
    }

    private function applyWarehouseRequestFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $search = $filters['search'];
                $searchQuery->where('request_code', 'like', "%{$search}%")
                    ->orWhere('destination_location', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('requester_notes', 'like', "%{$search}%")
                    ->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                        $assetQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('warehouse_location', 'like', "%{$search}%");
                    })
                    ->orWhereHas('requester', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['type', 'status', 'condition', 'brand'] as $column) {
            if ($filters[$column] !== '') {
                $query->whereHas('asset', fn (Builder $assetQuery) => $assetQuery->where($column, $filters[$column]));
            }
        }

        if ($filters['staff'] !== '') {
            $staffId = (int) $filters['staff'];
            $query->where(function (Builder $staffQuery) use ($staffId): void {
                $staffQuery->where('requested_by', $staffId)
                    ->orWhere('reviewed_by', $staffId)
                    ->orWhere('issued_by', $staffId)
                    ->orWhere('closed_by', $staffId)
                    ->orWhereHas('asset', function (Builder $assetQuery) use ($staffId): void {
                        $assetQuery->where('assigned_to', $staffId)
                            ->orWhere('last_handled_by', $staffId)
                            ->orWhere('added_by', $staffId);
                    });
            });
        }
    }

    private function applyAssetSorting(Builder $query, string $sort, string $direction): void
    {
        $sortable = ['name', 'type', 'status', 'condition', 'brand', 'warehouse_quantity', 'created_at', 'assigned_to'];

        if (in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);

            return;
        }

        $query->latest();
    }

    private function authorizeAssetManagement(Request $request, Asset $asset): void
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'You must be an active staff member to manage assets.');
        }

        $department = User::normalizeDepartmentKey($user->department);
        $isAssetTeam = in_array($department, ['operations_projects', 'hr_admin'], true);

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

    private function canManageWarehouse(?User $user): bool
    {
        if (! $user || ! $user->isActive()) {
            return false;
        }

        if ($user->isCvoOrSuperAdmin() || $user->hasRole('admin') || $user->hasFullHrAccess()) {
            return true;
        }

        $department = User::normalizeDepartmentKey($user->department);
        $position = strtolower(trim((string) $user->position_title));
        $level = strtolower(trim((string) $user->job_level));

        return $department === 'operations_projects'
            && ($user->isLineManager()
                || $user->access_role === 'manager'
                || $level === 'manager'
                || str_contains($position, 'manager')
                || str_contains($position, 'head'));
    }

    private function authorizeWarehouseEvidence(Request $request, AssetWarehouseRequest $assetWarehouseRequest): void
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'Only active staff can upload warehouse evidence.');
        }

        if ((int) $assetWarehouseRequest->requested_by === (int) $user->id || $this->canManageWarehouse($user)) {
            return;
        }

        abort(403, 'Only the requester or warehouse approvers can upload evidence for this request.');
    }

    private function nextWarehouseRequestCode(): string
    {
        $prefix = 'AWR-' . now()->format('Ym') . '-';
        $latest = AssetWarehouseRequest::where('request_code', 'like', $prefix.'%')
            ->orderByDesc('request_code')
            ->value('request_code');

        $next = 1;
        if ($latest) {
            $next = ((int) Str::afterLast($latest, '-')) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (AssetWarehouseRequest::where('request_code', $candidate)->exists());

        return $candidate;
    }

    private function approveCheck(AssetWarehouseRequest $request, User $user, ?string $note): void
    {
        if (! in_array($request->status, [
            AssetWarehouseRequest::STATUS_PENDING_CHECK,
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
        ], true)) {
            abort(422, 'This request cannot be approved for inspection from its current status.');
        }

        $request->fill([
            'status' => AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK,
            'reviewed_by' => $user->id,
            'review_note' => $note,
            'approved_to_check_at' => now(),
        ])->save();
    }

    private function sendBack(AssetWarehouseRequest $request, User $user, ?string $note): void
    {
        $request->fill([
            'status' => AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
            'reviewed_by' => $user->id,
            'review_note' => $note ?: 'Returned for correction.',
        ])->save();
    }

    private function rejectRequest(AssetWarehouseRequest $request, User $user, ?string $note): void
    {
        $request->fill([
            'status' => AssetWarehouseRequest::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'review_note' => $note ?: 'Rejected.',
        ])->save();
    }

    private function approveUse(AssetWarehouseRequest $request, User $user, ?string $note): void
    {
        if (! in_array($request->status, [
            AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK,
            AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
        ], true)) {
            abort(422, 'This request cannot be approved for use from its current status.');
        }

        if (! $request->pre_use_image_path) {
            abort(422, 'The requester must upload the inspection image before the asset is approved for use.');
        }

        $request->fill([
            'status' => AssetWarehouseRequest::STATUS_APPROVED_FOR_USE,
            'reviewed_by' => $user->id,
            'review_note' => $note,
            'approved_for_use_at' => now(),
        ])->save();
    }

    private function issueAsset(Request $request, AssetWarehouseRequest $warehouseRequest, Asset $asset, ?string $note): void
    {
        if ($warehouseRequest->status !== AssetWarehouseRequest::STATUS_APPROVED_FOR_USE) {
            abort(422, 'Only requests approved for use can be issued.');
        }

        if ($warehouseRequest->requested_quantity > $asset->warehouse_quantity) {
            abort(422, 'The warehouse no longer has enough quantity for this request.');
        }

        $issueImage = null;
        if ($request->hasFile('evidence_image')) {
            $issueImage = $request->file('evidence_image')->store('assets/warehouse', 'public');
        }

        $asset->fill([
            'warehouse_quantity' => max(0, (int) $asset->warehouse_quantity - (int) $warehouseRequest->requested_quantity),
            'assigned_to' => $warehouseRequest->requested_by,
            'status' => 'In Use',
            'last_handled_by' => $warehouseRequest->requested_by,
            'last_handled_at' => now(),
        ])->save();

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_ISSUED,
            'issued_by' => $request->user()->id,
            'issue_note' => $note,
            'issue_image_path' => $issueImage,
            'issued_at' => now(),
        ])->save();
    }

    private function closeRequest(AssetWarehouseRequest $request, Asset $asset, User $user, ?string $note): void
    {
        if ($request->status !== AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE) {
            abort(422, 'Only returned requests can be closed.');
        }

        if (! $request->return_image_path) {
            abort(422, 'A return image is required before closure.');
        }

        $asset->fill([
            'warehouse_quantity' => (int) $asset->warehouse_quantity + (int) $request->requested_quantity,
            'assigned_to' => null,
            'status' => 'Available',
            'last_handled_by' => $request->requested_by,
            'last_handled_at' => now(),
        ])->save();

        $request->fill([
            'status' => AssetWarehouseRequest::STATUS_CLOSED,
            'closed_by' => $user->id,
            'return_note' => $note ?: $request->return_note,
            'closed_at' => now(),
        ])->save();
    }

    private function warehouseExportRows(array $filters = []): array
    {
        $query = AssetWarehouseRequest::with(['asset', 'requester', 'reviewer', 'issuer', 'closer'])
            ->latest();

        if ($filters !== []) {
            $this->applyWarehouseRequestFilters($query, $filters);
        }

        return $query->get()
            ->map(fn (AssetWarehouseRequest $request) => [
                'Request Code' => $request->request_code,
                'Asset' => $request->asset?->name,
                'Brand' => $request->asset?->brand,
                'Condition' => $request->asset?->condition,
                'Warehouse Location' => $request->asset?->warehouse_location,
                'Requested By' => $request->requester?->name,
                'Requested Quantity' => $request->requested_quantity,
                'Destination' => $request->destination_location,
                'Purpose' => strip_tags((string) $request->purpose),
                'Status' => AssetWarehouseRequest::statusLabel($request->status),
                'Reviewer' => $request->reviewer?->name,
                'Issuer' => $request->issuer?->name,
                'Closer' => $request->closer?->name,
                'Requested For' => optional($request->requested_for)->format('Y-m-d'),
                'Issued At' => optional($request->issued_at)->format('Y-m-d H:i'),
                'Returned At' => optional($request->returned_at)->format('Y-m-d H:i'),
                'Closed At' => optional($request->closed_at)->format('Y-m-d H:i'),
            ])
            ->all();
    }

    private function warehouseStatusLabels(): array
    {
        return [
            AssetWarehouseRequest::STATUS_PENDING_CHECK => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_PENDING_CHECK),
            AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK),
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION),
            AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED),
            AssetWarehouseRequest::STATUS_APPROVED_FOR_USE => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_APPROVED_FOR_USE),
            AssetWarehouseRequest::STATUS_ISSUED => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_ISSUED),
            AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE),
            AssetWarehouseRequest::STATUS_CLOSED => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_CLOSED),
            AssetWarehouseRequest::STATUS_REJECTED => AssetWarehouseRequest::statusLabel(AssetWarehouseRequest::STATUS_REJECTED),
        ];
    }
}
