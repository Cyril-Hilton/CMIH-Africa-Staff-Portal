<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\PosmLedger;
use App\Models\User;
use App\Models\WarehouseAssetCollaborator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseAssetController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'brand' => $request->string('brand')->toString(),
            'category' => $request->string('category')->toString(),
            'condition' => $request->string('condition')->toString(),
            'status' => $request->string('status')->toString(),
            'location' => $request->string('location')->toString(),
            'staff' => $request->string('staff')->toString(),
            'request_status' => $request->string('request_status')->toString(),
        ];

        $assetsQuery = Asset::with(['assignee', 'creator', 'lastHandler'])
            ->where('is_warehouse_tracked', true);

        $this->applyAssetFilters($assetsQuery, $filters);

        $warehouseAssets = $assetsQuery
            ->latest()
            ->paginate(12, ['*'], 'asset_page')
            ->withQueryString();

        $allWarehouseAssets = Asset::query()
            ->where('is_warehouse_tracked', true)
            ->get();

        $requestableAssets = Asset::query()
            ->where('is_warehouse_tracked', true)
            ->orderBy('name')
            ->get();

        $requestsQuery = AssetWarehouseRequest::with(['asset', 'requester', 'reviewer', 'issuer', 'closer'])
            ->latest();

        if ($filters['request_status'] !== '') {
            $requestsQuery->where('status', $filters['request_status']);
        }

        if (! $this->canExportWarehouse($request->user())) {
            $requestsQuery->where('requested_by', $request->user()->id);
        }

        $warehouseRequests = $requestsQuery
            ->paginate(10, ['*'], 'request_page')
            ->withQueryString();

        $posmEntries = PosmLedger::with('creator')
            ->latest()
            ->paginate(10, ['*'], 'posm_page')
            ->withQueryString();

        $staff = User::internalStaff()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $collaborators = WarehouseAssetCollaborator::with(['user', 'grantor'])
            ->where('is_active', true)
            ->latest()
            ->get();

        $metrics = [
            'master_records' => $allWarehouseAssets->count(),
            'total_quantity' => (int) $allWarehouseAssets->sum('warehouse_quantity'),
            'available' => $allWarehouseAssets
                ->filter(fn (Asset $asset) => strtolower((string) $asset->status) === 'available')
                ->count(),
            'deployed' => $allWarehouseAssets
                ->filter(fn (Asset $asset) => in_array(strtolower((string) $asset->status), ['in use', 'deployed', 'checked_out'], true))
                ->count(),
            'under_remodel' => $allWarehouseAssets
                ->filter(fn (Asset $asset) => str_contains(strtolower((string) $asset->status), 'remodel')
                    || str_contains(strtolower((string) $asset->remodel_status), 'remodel'))
                ->count(),
            'pending_approvals' => AssetWarehouseRequest::whereIn('status', [
                AssetWarehouseRequest::STATUS_PENDING_CHECK,
                AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
                AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE,
            ])->count(),
        ];

        $brands = Asset::where('is_warehouse_tracked', true)
            ->whereNotNull('brand')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $categories = Asset::where('is_warehouse_tracked', true)
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $locations = Asset::where('is_warehouse_tracked', true)
            ->whereNotNull('warehouse_location')
            ->select('warehouse_location')
            ->distinct()
            ->orderBy('warehouse_location')
            ->pluck('warehouse_location');

        $canEditWarehouse = $this->canManageWarehouse($request->user(), 'edit');
        $canImportWarehouse = $this->canManageWarehouse($request->user(), 'import');
        $canApproveWarehouse = $this->canManageWarehouse($request->user(), 'approve');
        $canManageWarehouse = $canEditWarehouse || $canImportWarehouse || $canApproveWarehouse;
        $canGrantWarehouseCollaborators = $this->canGrantWarehouseCollaborators($request->user());
        $statusLabels = $this->warehouseStatusLabels();

        return view('portal.warehouse-assets', compact(
            'filters',
            'warehouseAssets',
            'warehouseRequests',
            'posmEntries',
            'staff',
            'metrics',
            'brands',
            'categories',
            'locations',
            'canManageWarehouse',
            'canEditWarehouse',
            'canImportWarehouse',
            'canApproveWarehouse',
            'statusLabels',
            'requestableAssets',
            'collaborators',
            'canGrantWarehouseCollaborators',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWarehouseManager($request, 'edit');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:120'],
            'asset_value' => ['nullable', 'numeric', 'min:0'],
            'po_quantity' => ['nullable', 'integer', 'min:0'],
            'quantity_procured' => ['nullable', 'integer', 'min:0'],
            'warehouse_quantity' => ['required', 'integer', 'min:0'],
            'owner' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:80'],
            'asset_use_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'string', 'max:80'],
            'condition' => ['required', 'string', 'max:80'],
            'warehouse_location' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'warehouse_notes' => ['nullable', 'string', 'max:3000'],
            'remodel_status' => ['nullable', 'string', 'max:120'],
            'remodel_notes' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('assets/warehouse', 'public')
            : null;

        Asset::create([
            'name' => $validated['name'],
            'asset_tag' => $validated['asset_tag'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'asset_value' => $validated['asset_value'] ?? null,
            'po_quantity' => $validated['po_quantity'] ?? 0,
            'quantity_procured' => $validated['quantity_procured'] ?? 0,
            'warehouse_quantity' => $validated['warehouse_quantity'],
            'owner' => $validated['owner'] ?? null,
            'type' => $validated['type'],
            'asset_use_type' => $validated['asset_use_type'] ?? null,
            'status' => $validated['status'],
            'condition' => $validated['condition'],
            'warehouse_location' => $validated['warehouse_location'] ?? null,
            'location' => $validated['location'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'warehouse_notes' => $validated['warehouse_notes'] ?? null,
            'remodel_status' => $validated['remodel_status'] ?? null,
            'remodel_notes' => $validated['remodel_notes'] ?? null,
            'image_path' => $imagePath,
            'is_warehouse_tracked' => true,
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Warehouse asset added to the CAMS master inventory.');
    }

    public function requestAsset(Request $request, Asset $asset): RedirectResponse|JsonResponse
    {
        $this->authorizeWarehouseAccess($request);

        abort_unless($asset->is_warehouse_tracked, 404);

        $validated = $request->validate([
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'requested_for' => ['nullable', 'date'],
            'destination_location' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:3000'],
            'requester_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if ((int) $validated['requested_quantity'] > max(1, (int) $asset->warehouse_quantity)) {
            return $this->validationFailure($request, [
                'requested_quantity' => 'Requested quantity is higher than the current warehouse quantity.',
            ]);
        }

        AssetWarehouseRequest::create([
            'request_code' => $this->nextRequestCode(),
            'asset_id' => $asset->id,
            'requested_by' => $request->user()->id,
            'requested_quantity' => $validated['requested_quantity'],
            'requested_for' => $validated['requested_for'] ?? null,
            'destination_location' => $validated['destination_location'],
            'purpose' => $validated['purpose'],
            'requester_notes' => $validated['requester_notes'] ?? null,
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ]);

        return back()->with('status', 'Warehouse asset request sent for approval.');
    }

    public function correct(Request $request, AssetWarehouseRequest $warehouseRequest): RedirectResponse|JsonResponse
    {
        $this->authorizeRequestOwner($request, $warehouseRequest);

        abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION, 403);

        $validated = $request->validate([
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'requested_for' => ['nullable', 'date'],
            'destination_location' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:3000'],
            'requester_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $asset = $warehouseRequest->asset()->firstOrFail();
        if ((int) $validated['requested_quantity'] > max(1, (int) $asset->warehouse_quantity)) {
            return $this->validationFailure($request, [
                'requested_quantity' => 'Requested quantity is higher than the current warehouse quantity.',
            ]);
        }

        $warehouseRequest->fill([
            'requested_quantity' => $validated['requested_quantity'],
            'requested_for' => $validated['requested_for'] ?? null,
            'destination_location' => $validated['destination_location'],
            'purpose' => $validated['purpose'],
            'requester_notes' => $validated['requester_notes'] ?? null,
            'status' => AssetWarehouseRequest::STATUS_PENDING_CHECK,
        ])->save();

        return back()->with('status', 'Warehouse asset request corrected and returned for review.');
    }

    public function evidence(Request $request, AssetWarehouseRequest $warehouseRequest): RedirectResponse
    {
        $this->authorizeRequestOwner($request, $warehouseRequest);

        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:pre_use,return'],
            'evidence_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $path = $request->file('evidence_image')->store('assets/warehouse/evidence', 'public');

        if ($validated['stage'] === 'pre_use') {
            abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK, 403);

            $warehouseRequest->fill([
                'pre_use_image_path' => $path,
                'requester_notes' => $validated['note'] ?? $warehouseRequest->requester_notes,
                'status' => AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
            ])->save();
        }

        if ($validated['stage'] === 'return') {
            abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_ISSUED, 403);

            $warehouseRequest->fill([
                'return_image_path' => $path,
                'return_note' => $validated['note'] ?? $warehouseRequest->return_note,
                'returned_at' => now(),
                'status' => AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE,
            ])->save();
        }

        return back()->with('status', 'Warehouse evidence uploaded.');
    }

    public function action(Request $request, AssetWarehouseRequest $warehouseRequest): RedirectResponse
    {
        $this->authorizeWarehouseManager($request, 'approve');

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve_check,return_correction,reject,approve_use,issue,close,send_remodel'],
            'note' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), ['return_correction', 'reject'], true)),
                'string',
                'max:3000',
            ],
            'evidence_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ]);

        $asset = $warehouseRequest->asset()->lockForUpdate()->firstOrFail();
        $note = $validated['note'] ?? null;

        return match ($validated['action']) {
            'approve_check' => $this->approveCheck($warehouseRequest, $request->user(), $note),
            'return_correction' => $this->returnCorrection($warehouseRequest, $request->user(), $note),
            'reject' => $this->reject($warehouseRequest, $request->user(), $note),
            'approve_use' => $this->approveUse($warehouseRequest, $request->user(), $note),
            'issue' => $this->issue($request, $warehouseRequest, $asset, $note),
            'close' => $this->closeRequest($warehouseRequest, $asset, $request->user(), $note),
            'send_remodel' => $this->sendToRemodel($warehouseRequest, $asset, $request->user(), $note),
        };
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $user = $request->user();
        abort_unless($this->canExportWarehouse($user), 403);

        $format = strtolower($request->string('format')->toString() ?: 'csv');
        $scope = strtolower($request->string('scope')->toString() ?: 'requests');
        $filters = [
            'search' => $request->string('search')->toString(),
            'brand' => $request->string('brand')->toString(),
            'category' => $request->string('category')->toString(),
            'condition' => $request->string('condition')->toString(),
            'status' => $request->string('status')->toString(),
            'location' => $request->string('location')->toString(),
            'staff' => $request->string('staff')->toString(),
            'request_status' => $request->string('request_status')->toString(),
        ];

        [$rows, $label, $filenamePrefix] = match ($scope) {
            'inventory' => $this->warehouseInventoryExport($filters),
            'posm' => $this->warehousePosmExport($filters),
            default => $this->warehouseRequestExport($filters),
        };

        if ($format === 'pdf') {
            return response(view('portal.assets-warehouse-export', [
                'rows' => $rows,
                'title' => $label,
                'printedAt' => now(),
            ]));
        }

        $extension = $format === 'excel' ? 'xls' : 'csv';
        $mime = $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv; charset=UTF-8';
        $filename = $filenamePrefix . '_' . now()->format('Y-m-d_H-i-s') . '.' . $extension;

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $headings = array_keys($rows[0] ?? ['Notice' => 'No warehouse records found']);
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeWarehouseManager($request, 'import');

        $validated = $request->validate([
            'asset_file' => ['required', 'file', 'mimes:csv,txt,xlsx,docx', 'max:51200'],
        ]);

        $file = $validated['asset_file'];
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'csv', 'txt' => $this->rowsFromCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->rowsFromXlsx($file->getRealPath()),
            'docx' => $this->rowsFromDocx($file->getRealPath()),
            default => [],
        };

        if ($rows === []) {
            return $this->validationFailure($request, [
                'asset_file' => 'No structured asset rows were found. Please upload a CSV, XLSX, or DOCX table with headers such as Asset Name, Brand, Quantity Procured, Status, Condition, and Location.',
            ]);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $payload = $this->warehouseImportPayload($row, $request->user());

            if (($payload['name'] ?? '') === '') {
                $skipped++;
                continue;
            }

            $lookup = filled($payload['asset_tag'] ?? null)
                ? ['asset_tag' => $payload['asset_tag']]
                : null;

            if ($lookup) {
                Asset::updateOrCreate($lookup, $payload);
            } else {
                Asset::create($payload);
            }

            $imported++;
        }

        return back()->with('status', "Warehouse import complete: {$imported} asset row(s) saved, {$skipped} skipped.");
    }

    public function grantCollaborator(Request $request): RedirectResponse
    {
        $this->authorizeWarehouseGrantor($request);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'can_edit' => ['nullable', 'boolean'],
            'can_import' => ['nullable', 'boolean'],
            'can_approve' => ['nullable', 'boolean'],
        ]);

        $target = User::findOrFail($validated['user_id']);

        abort_if($target->isMerchandiserAccount(), 422, 'Only internal CMIH staff can be warehouse collaborators.');

        WarehouseAssetCollaborator::where('user_id', $target->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ]);

        WarehouseAssetCollaborator::create([
            'user_id' => $target->id,
            'granted_by' => $request->user()->id,
            'can_edit' => $request->boolean('can_edit', true),
            'can_import' => $request->boolean('can_import', true),
            'can_approve' => $request->boolean('can_approve', true),
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', "{$target->name} can now help manage Warehouse Assets.");
    }

    public function revokeCollaborator(Request $request, WarehouseAssetCollaborator $collaborator): RedirectResponse
    {
        $this->authorizeWarehouseGrantor($request);

        $collaborator->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Warehouse collaborator rights revoked.');
    }

    public function storePosm(Request $request): RedirectResponse
    {
        $this->authorizeWarehouseManager($request, 'edit');

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'string', 'in:POSM,Uniform,Banner,Tablet,AV,Other'],
            'client_brand' => ['nullable', 'string', 'max:255'],
            'quantity_in' => ['required', 'integer', 'min:0'],
            'quantity_out' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        PosmLedger::create($validated + [
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Warehouse POSM inventory movement saved.');
    }

    public function destroyPosm(Request $request, PosmLedger $entry): RedirectResponse
    {
        $this->authorizeWarehouseManager($request, 'edit');

        $entry->delete();

        return back()->with('status', 'Warehouse POSM inventory movement removed.');
    }

    private function applyAssetFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        foreach (['brand', 'category', 'condition', 'status'] as $field) {
            if ($filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if ($filters['location'] !== '') {
            $query->where('warehouse_location', $filters['location']);
        }

        if ($filters['staff'] !== '') {
            $query->where(function (Builder $staffQuery) use ($filters) {
                $staffQuery->where('assigned_to', $filters['staff'])
                    ->orWhere('last_handled_by', $filters['staff']);
            });
        }
    }

    private function approveCheck(AssetWarehouseRequest $warehouseRequest, User $manager, ?string $note): RedirectResponse
    {
        abort_unless(in_array($warehouseRequest->status, [
            AssetWarehouseRequest::STATUS_PENDING_CHECK,
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
        ], true), 403);

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK,
            'reviewed_by' => $manager->id,
            'review_note' => $note,
            'approved_to_check_at' => now(),
        ])->save();

        return back()->with('status', 'Request approved for physical inspection.');
    }

    private function returnCorrection(AssetWarehouseRequest $warehouseRequest, User $manager, ?string $note): RedirectResponse
    {
        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,
            'reviewed_by' => $manager->id,
            'review_note' => $note,
        ])->save();

        return back()->with('status', 'Request sent back for correction.');
    }

    private function reject(AssetWarehouseRequest $warehouseRequest, User $manager, ?string $note): RedirectResponse
    {
        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_REJECTED,
            'reviewed_by' => $manager->id,
            'review_note' => $note,
        ])->save();

        return back()->with('status', 'Warehouse request rejected.');
    }

    private function approveUse(AssetWarehouseRequest $warehouseRequest, User $manager, ?string $note): RedirectResponse
    {
        abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED, 403);

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_APPROVED_FOR_USE,
            'reviewed_by' => $manager->id,
            'review_note' => $note,
            'approved_for_use_at' => now(),
        ])->save();

        return back()->with('status', 'Asset approved for use after inspection.');
    }

    private function issue(Request $request, AssetWarehouseRequest $warehouseRequest, Asset $asset, ?string $note): RedirectResponse
    {
        abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_APPROVED_FOR_USE, 403);

        $issueImage = $request->hasFile('evidence_image')
            ? $request->file('evidence_image')->store('assets/warehouse/evidence', 'public')
            : null;

        $asset->fill([
            'warehouse_quantity' => max(0, (int) $asset->warehouse_quantity - (int) $warehouseRequest->requested_quantity),
            'status' => 'In Use',
            'assigned_to' => $warehouseRequest->requested_by,
            'last_handled_by' => $request->user()->id,
            'last_handled_at' => now(),
        ])->save();

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_ISSUED,
            'issued_by' => $request->user()->id,
            'issue_note' => $note,
            'issue_image_path' => $issueImage,
            'issued_at' => now(),
        ])->save();

        return back()->with('status', 'Asset issued and custody updated.');
    }

    private function closeRequest(AssetWarehouseRequest $warehouseRequest, Asset $asset, User $manager, ?string $note): RedirectResponse
    {
        abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE, 403);

        $asset->fill([
            'warehouse_quantity' => (int) $asset->warehouse_quantity + (int) $warehouseRequest->requested_quantity,
            'status' => 'Available',
            'assigned_to' => null,
            'last_handled_by' => $manager->id,
            'last_handled_at' => now(),
        ])->save();

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_CLOSED,
            'closed_by' => $manager->id,
            'return_note' => $note ?? $warehouseRequest->return_note,
            'closed_at' => now(),
        ])->save();

        return back()->with('status', 'Asset return audited and closed.');
    }

    private function sendToRemodel(AssetWarehouseRequest $warehouseRequest, Asset $asset, User $manager, ?string $note): RedirectResponse
    {
        abort_unless($warehouseRequest->status === AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE, 403);

        $asset->fill([
            'status' => 'Under Remodel',
            'remodel_status' => 'Awaiting Remodel',
            'remodel_notes' => $note,
            'assigned_to' => null,
            'last_handled_by' => $manager->id,
            'last_handled_at' => now(),
        ])->save();

        $warehouseRequest->fill([
            'status' => AssetWarehouseRequest::STATUS_CLOSED,
            'closed_by' => $manager->id,
            'return_note' => $note ?? $warehouseRequest->return_note,
            'closed_at' => now(),
        ])->save();

        return back()->with('status', 'Asset closed and moved into remodel tracking.');
    }

    private function warehouseInventoryExport(array $filters): array
    {
        $query = Asset::with(['assignee', 'creator', 'lastHandler'])
            ->where('is_warehouse_tracked', true)
            ->latest();

        $this->applyAssetFilters($query, $filters);

        return [
            $query->get()->map(fn (Asset $asset) => $this->exportInventoryRow($asset))->all(),
            'Warehouse Master Asset Inventory',
            'warehouse_master_inventory',
        ];
    }

    private function warehouseRequestExport(array $filters): array
    {
        $query = AssetWarehouseRequest::with(['asset', 'requester', 'reviewer', 'issuer', 'closer'])
            ->latest();

        if ($filters['request_status'] !== '') {
            $query->where('status', $filters['request_status']);
        }

        return [
            $query->get()->map(fn (AssetWarehouseRequest $requestItem) => $this->exportRow($requestItem))->all(),
            'Warehouse Requisition & Sign-Off Log',
            'warehouse_asset_requests',
        ];
    }

    private function warehousePosmExport(array $filters): array
    {
        $query = PosmLedger::with('creator')->latest();

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_type', 'like', "%{$search}%")
                    ->orWhere('client_brand', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($filters['brand'] !== '') {
            $query->where('client_brand', $filters['brand']);
        }

        if ($filters['location'] !== '') {
            $query->where('location', $filters['location']);
        }

        return [
            $query->get()->map(fn (PosmLedger $entry) => $this->exportPosmRow($entry))->all(),
            'Warehouse POSM Inventory Ledger',
            'warehouse_posm_ledger',
        ];
    }

    private function exportInventoryRow(Asset $asset): array
    {
        return [
            'Asset Name' => $asset->name,
            'Asset Tag' => $asset->asset_tag,
            'Serial Number' => $asset->serial_number,
            'Category' => $asset->category,
            'Brand' => $asset->brand,
            'Asset Value' => $asset->asset_value,
            'PO Quantity' => $asset->po_quantity,
            'Quantity Procured' => $asset->quantity_procured,
            'Current Quantity' => $asset->warehouse_quantity,
            'Owner' => $asset->owner,
            'Status' => $asset->status,
            'Asset Type' => $asset->asset_use_type ?: $asset->type,
            'Condition' => $asset->condition,
            'Warehouse Location' => $asset->warehouse_location ?: $asset->location,
            'Current Custodian' => $asset->assignee?->name,
            'Last Handled By' => $asset->lastHandler?->name,
            'Last Handled At' => $asset->last_handled_at?->format('Y-m-d H:i:s'),
            'Remodel Status' => $asset->remodel_status,
            'Warehouse Notes' => Str::limit(strip_tags((string) $asset->warehouse_notes), 180, ''),
            'Created At' => $asset->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function exportRow(AssetWarehouseRequest $requestItem): array
    {
        return [
            'Request Code' => $requestItem->request_code,
            'Asset' => $requestItem->asset?->name,
            'Asset Tag' => $requestItem->asset?->asset_tag,
            'Brand' => $requestItem->asset?->brand,
            'Requester' => $requestItem->requester?->name,
            'Quantity' => $requestItem->requested_quantity,
            'Destination' => $requestItem->destination_location,
            'Purpose' => Str::limit(strip_tags((string) $requestItem->purpose), 120, ''),
            'Status' => AssetWarehouseRequest::statusLabel($requestItem->status),
            'Reviewed By' => $requestItem->reviewer?->name,
            'Review Note' => Str::limit(strip_tags((string) $requestItem->review_note), 180, ''),
            'Issued By' => $requestItem->issuer?->name,
            'Issue Note' => Str::limit(strip_tags((string) $requestItem->issue_note), 180, ''),
            'Closed By' => $requestItem->closer?->name,
            'Return Note' => Str::limit(strip_tags((string) $requestItem->return_note), 180, ''),
            'Requested For' => $requestItem->requested_for?->format('Y-m-d'),
            'Approved To Check At' => $requestItem->approved_to_check_at?->format('Y-m-d H:i:s'),
            'Approved For Use At' => $requestItem->approved_for_use_at?->format('Y-m-d H:i:s'),
            'Issued At' => $requestItem->issued_at?->format('Y-m-d H:i:s'),
            'Returned At' => $requestItem->returned_at?->format('Y-m-d H:i:s'),
            'Closed At' => $requestItem->closed_at?->format('Y-m-d H:i:s'),
            'Created At' => $requestItem->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function exportPosmRow(PosmLedger $entry): array
    {
        return [
            'Item Name' => $entry->item_name,
            'Item Type' => $entry->item_type,
            'Brand' => $entry->client_brand,
            'Quantity In' => $entry->quantity_in,
            'Quantity Out' => $entry->quantity_out,
            'Balance' => (int) $entry->quantity_in - (int) $entry->quantity_out,
            'Location' => $entry->location,
            'Notes' => Str::limit(strip_tags((string) $entry->notes), 180, ''),
            'Logged By' => $entry->creator?->name,
            'Created At' => $entry->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function rowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            fclose($handle);
            return [];
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = $this->combineImportRow($headers, $line);
        }

        fclose($handle);

        return $rows;
    }

    private function rowsFromXlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            foreach ($shared?->si ?? [] as $si) {
                $parts = [];
                foreach ($si->xpath('.//t') ?: [] as $textNode) {
                    $parts[] = (string) $textNode;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $tableRows = [];

        foreach ($sheet?->sheetData?->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c ?? [] as $cell) {
                $ref = (string) $cell['r'];
                $columnIndex = $this->excelColumnIndex(preg_replace('/\d+/', '', $ref));
                $value = (string) ($cell->v ?? '');

                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? $value;
                }

                $cells[$columnIndex] = trim($value);
            }

            if ($cells !== []) {
                ksort($cells);
                $tableRows[] = array_values($cells);
            }
        }

        if (count($tableRows) < 2) {
            return [];
        }

        $headers = array_shift($tableRows);

        return array_map(fn (array $line) => $this->combineImportRow($headers, $line), $tableRows);
    }

    private function rowsFromDocx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $document?->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tableRows = [];
        foreach ($document?->xpath('//w:tr') ?: [] as $tr) {
            $cells = [];
            foreach ($tr->xpath('.//w:tc') ?: [] as $tc) {
                $texts = [];
                foreach ($tc->xpath('.//w:t') ?: [] as $textNode) {
                    $texts[] = (string) $textNode;
                }
                $cells[] = trim(implode(' ', $texts));
            }
            if ($cells !== []) {
                $tableRows[] = $cells;
            }
        }

        if (count($tableRows) < 2) {
            return [];
        }

        $headers = array_shift($tableRows);

        return array_map(fn (array $line) => $this->combineImportRow($headers, $line), $tableRows);
    }

    private function combineImportRow(array $headers, array $line): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$this->normaliseImportHeader((string) $header)] = trim((string) ($line[$index] ?? ''));
        }

        return $row;
    }

    private function warehouseImportPayload(array $row, User $actor): array
    {
        $staffLookup = User::internalStaff()->where('status', 'active')->get()
            ->keyBy(fn (User $user) => strtolower(trim($user->email)))
            ->merge(User::internalStaff()->where('status', 'active')->get()->keyBy(fn (User $user) => strtolower(trim($user->name))));

        $custodian = $this->rowValue($row, ['custodian', 'assignedto', 'assigned', 'currentcustodian']);
        $assignedUser = $custodian !== '' ? $staffLookup->get(strtolower(trim($custodian))) : null;

        return [
            'name' => $this->rowValue($row, ['assetname', 'name', 'item', 'itemname']),
            'asset_tag' => $this->rowValue($row, ['assettag', 'tag', 'assetid', 'assetcode']),
            'serial_number' => $this->rowValue($row, ['serialnumber', 'serial', 'serialno']),
            'description' => $this->rowValue($row, ['description', 'notes', 'details']),
            'category' => $this->rowValue($row, ['category']),
            'brand' => $this->rowValue($row, ['brand', 'clientbrand', 'client']),
            'asset_value' => $this->decimalFromValue($this->rowValue($row, ['assetvalue', 'value', 'purchasevalue', 'cost'])),
            'po_quantity' => $this->integerFromValue($this->rowValue($row, ['poquantity', 'poqty'])),
            'quantity_procured' => $this->integerFromValue($this->rowValue($row, ['quantityprocured', 'qtyprocured', 'procured'])),
            'warehouse_quantity' => $this->integerFromValue($this->rowValue($row, ['warehousequantity', 'currentquantity', 'currentqty', 'quantity', 'qty'])) ?: 1,
            'owner' => $this->rowValue($row, ['owner']) ?: 'CMIH',
            'type' => $this->rowValue($row, ['assettype', 'type']) ?: 'Other',
            'asset_use_type' => $this->rowValue($row, ['assetusetype', 'usetype']),
            'status' => $this->rowValue($row, ['status']) ?: 'Available',
            'condition' => $this->rowValue($row, ['condition']) ?: 'Good',
            'warehouse_location' => $this->rowValue($row, ['warehouselocation', 'location', 'safekeepinglocation']),
            'location' => $this->rowValue($row, ['generallocation', 'office']),
            'assigned_to' => $assignedUser?->id,
            'warehouse_notes' => $this->rowValue($row, ['warehousenotes']),
            'remodel_status' => $this->rowValue($row, ['remodelstatus', 'maintenance']),
            'remodel_notes' => $this->rowValue($row, ['remodelnotes', 'maintenancenotes']),
            'is_warehouse_tracked' => true,
            'added_by' => $actor->id,
        ];
    }

    private function rowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function normaliseImportHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($header))) ?: '';
    }

    private function decimalFromValue(string $value): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value);

        return $clean === '' ? null : (float) $clean;
    }

    private function integerFromValue(string $value): int
    {
        $clean = preg_replace('/[^0-9\-]/', '', $value);

        return $clean === '' ? 0 : max(0, (int) $clean);
    }

    private function excelColumnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function nextRequestCode(): string
    {
        $prefix = 'AWR-' . now()->format('Ym') . '-';
        $lastCode = AssetWarehouseRequest::where('request_code', 'like', $prefix . '%')
            ->latest('id')
            ->value('request_code');

        $next = $lastCode ? ((int) Str::afterLast($lastCode, '-')) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function warehouseStatusLabels(): array
    {
        return [
            AssetWarehouseRequest::STATUS_PENDING_CHECK => 'Awaiting Check Approval',
            AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK => 'Approved To Check',
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION => 'Returned For Correction',
            AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED => 'Inspection Submitted',
            AssetWarehouseRequest::STATUS_APPROVED_FOR_USE => 'Approved For Use',
            AssetWarehouseRequest::STATUS_ISSUED => 'Issued / In Use',
            AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE => 'Returned, Pending Closure',
            AssetWarehouseRequest::STATUS_CLOSED => 'Closed',
            AssetWarehouseRequest::STATUS_REJECTED => 'Rejected',
        ];
    }

    private function authorizeWarehouseAccess(Request $request): void
    {
        abort_unless($request->user()?->isActive(), 403);
    }

    private function authorizeRequestOwner(Request $request, AssetWarehouseRequest $warehouseRequest): void
    {
        $user = $request->user();

        abort_unless($user?->isActive(), 403);
        abort_unless((int) $warehouseRequest->requested_by === (int) $user->id || $this->canManageWarehouse($user, 'approve'), 403);
    }

    private function authorizeWarehouseManager(Request $request, string $capability = 'edit'): void
    {
        abort_unless($this->canManageWarehouse($request->user(), $capability), 403);
    }

    private function authorizeWarehouseGrantor(Request $request): void
    {
        abort_unless($this->canGrantWarehouseCollaborators($request->user()), 403);
    }

    private function canManageWarehouse(?User $user, string $capability = 'edit'): bool
    {
        if (! $user || ! $user->isActive()) {
            return false;
        }

        if ($user->isOperationsDepartmentLead() || $user->isActingForOperationsDepartmentLead()) {
            return true;
        }

        $collaborator = WarehouseAssetCollaborator::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $collaborator) {
            return false;
        }

        return match ($capability) {
            'import' => (bool) $collaborator->can_import,
            'approve' => (bool) $collaborator->can_approve,
            default => (bool) $collaborator->can_edit,
        };
    }

    private function canExportWarehouse(?User $user): bool
    {
        return $user?->isActive() && $user->canExportWarehouseAssets();
    }

    private function canGrantWarehouseCollaborators(?User $user): bool
    {
        return $user?->isActive()
            && ($user->isOperationsDepartmentLead()
                || $user->isActingForOperationsDepartmentLead());
    }

    private function validationFailure(Request $request, array $errors): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => collect($errors)->flatten()->first() ?: 'Please check the form entries and try again.',
                'errors' => collect($errors)->map(fn ($message) => (array) $message)->all(),
            ], 422);
        }

        return back()->withErrors($errors)->withInput();
    }
}
