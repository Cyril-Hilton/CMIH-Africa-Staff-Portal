@php
    use App\Models\AssetWarehouseRequest;

    $statusTone = function (?string $status): string {
        return match (strtolower((string) $status)) {
            'available' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
            'in use' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
            'maintenance' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
            'retired' => 'bg-brand-white/10 text-brand-white/50 border-brand-white/10',
            default => 'bg-brand-white/10 text-brand-white/70 border-brand-white/10',
        };
    };

    $requestTone = function (?string $status): string {
        return match ($status) {
            AssetWarehouseRequest::STATUS_PENDING_CHECK => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
            AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK,
            AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED,
            AssetWarehouseRequest::STATUS_APPROVED_FOR_USE => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
            AssetWarehouseRequest::STATUS_ISSUED => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
            AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
            AssetWarehouseRequest::STATUS_CLOSED => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
            AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION => 'bg-orange-500/15 text-orange-300 border-orange-500/30',
            AssetWarehouseRequest::STATUS_REJECTED => 'bg-brand-red/15 text-brand-red border-brand-red/30',
            default => 'bg-brand-white/10 text-brand-white/70 border-brand-white/10',
        };
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">DAM & Inventory</p>
            <h2 class="text-3xl font-display text-brand-white">Assets & Warehouse Tracking</h2>
        </div>
    </x-slot>

    <div x-data="{ showModal: false }" class="space-y-6">
        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-brand-white">Asset Filters</h3>
                    <p class="text-sm text-brand-white/60">Filter by brand, condition, availability, staff member, type, or keyword.</p>
                </div>
                @if($canCreateAssets)
                    <button type="button" @click.prevent="showModal = true" class="inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white hover:opacity-90 transition">
                        Add Asset
                    </button>
                @endif
            </div>

            <form method="GET" action="{{ route('portal.assets') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search assets..." class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder:text-brand-white/30 focus:border-brand-red focus:ring-brand-red">

                <select name="brand" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                    <option value="">All brands</option>
                    @foreach($brands as $brandOption)
                        <option value="{{ $brandOption }}" @selected($brand === $brandOption)>{{ $brandOption }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                    <option value="">All availability</option>
                    @foreach(['Available', 'In Use', 'Maintenance', 'Retired'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="condition" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                    <option value="">All conditions</option>
                    @foreach(['New', 'Good', 'Fair', 'Poor'] as $option)
                        <option value="{{ $option }}" @selected($condition === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="type" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                    <option value="">All types</option>
                    @foreach(['Hardware', 'Software', 'Vehicle', 'Other'] as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="staff" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                    <option value="">All staff</option>
                    @foreach($staff as $user)
                        <option value="{{ $user->id }}" @selected((string) $staffFilter === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>

                <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-6">
                    <button type="submit" class="rounded-full bg-brand-red px-5 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-white hover:bg-brand-red-dark transition">Apply Filters</button>
                    <a href="{{ route('portal.assets') }}" class="rounded-full border border-brand-white/10 px-5 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-brand-white/70 hover:text-brand-white hover:bg-brand-white/10 transition">Clear</a>
                </div>
            </form>
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-brand-white">Asset Overview</h3>
                    <p class="text-sm text-brand-white/70">Track availability, condition, allocation, and ownership history.</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.25em] text-brand-ash">
                        <tr>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Asset</a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'brand', 'direction' => request('sort') === 'brand' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Brand</a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'type', 'direction' => request('sort') === 'type' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Type</a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'status', 'direction' => request('sort') === 'status' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Availability</a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'condition', 'direction' => request('sort') === 'condition' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Condition</a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['sort' => 'assigned_to', 'direction' => request('sort') === 'assigned_to' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1">Staff</a>
                            </th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            @php
                                $viewer = auth()->user();
                                $viewerDept = \App\Models\User::normalizeDepartmentKey($viewer->department ?? '');
                                $canManageAsset = $viewer && (
                                    $viewer->isCvoOrSuperAdmin()
                                    || $viewer->hasRole('admin')
                                    || $viewer->hasFullHrAccess()
                                    || in_array($viewerDept, ['operations_projects', 'hr_admin'], true)
                                    || (int) $asset->added_by === (int) $viewer->id
                                    || (int) $asset->assigned_to === (int) $viewer->id
                                );
                            @endphp
                            <tr class="border-t border-brand-white/10 align-top">
                                <td class="py-4 text-brand-white">
                                    <div class="flex items-start gap-3">
                                        @if($asset->image_path)
                                            <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-12 w-12 rounded-lg object-cover bg-brand-white/10" alt="">
                                        @else
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-white/10 text-[10px] font-bold uppercase tracking-wider text-brand-white/50">Asset</div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-brand-white">{{ $asset->name }}</p>
                                            @if($asset->description)
                                                <div class="mt-1 max-w-md text-xs text-brand-white/50">{!! \Illuminate\Support\Str::limit(strip_tags($asset->description), 140) !!}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4">{{ $asset->brand ?: '-' }}</td>
                                <td class="py-4">{{ $asset->type }}</td>
                                <td class="py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $statusTone($asset->status) }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td class="py-4">{{ $asset->condition }}</td>
                                <td class="py-4">
                                    <p>{{ $asset->assignee?->name ?? 'Unassigned' }}</p>
                                    @if($asset->is_warehouse_tracked)
                                        <p class="mt-1 text-[11px] text-brand-white/40">Warehouse qty: {{ $asset->warehouse_quantity }}</p>
                                    @endif
                                </td>
                                <td class="py-4 text-right">
                                    @if($canManageAsset)
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('portal.assets.edit', $asset) }}" class="rounded-full border border-brand-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-white/70 hover:bg-brand-white/10 hover:text-brand-white transition">Edit</a>
                                            <button type="button" @click="$dispatch('open-confirm-modal', { url: '{{ route('portal.assets.destroy', $asset) }}' })" class="rounded-full border border-brand-red/30 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red hover:bg-brand-red/10 transition">Delete</button>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-white/30">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-brand-white/50">No assets match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 border-t border-brand-white/10 pt-4">
                {{ $assets->links() }}
            </div>
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-red">Asset Warehouse Tracker</p>
                    <h3 class="mt-1 text-2xl font-display text-brand-white">Warehouse Items, Requests & Approvals</h3>
                    <p class="mt-2 max-w-3xl text-sm text-brand-white/65">Track assets kept in safe storage, who handled them last, request approvals, before-use images, return images, and closure status.</p>
                </div>
                @if($canManageWarehouse)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('portal.assets.warehouse.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rounded-full border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/70 hover:bg-brand-white/10 hover:text-brand-white">CSV</a>
                        <a href="{{ route('portal.assets.warehouse.export', array_merge(request()->query(), ['format' => 'xls'])) }}" class="rounded-full border border-emerald-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-emerald-300 hover:bg-emerald-500/10">Excel</a>
                        <a href="{{ route('portal.assets.warehouse.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-full border border-amber-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-300 hover:bg-amber-500/10">PDF / Print</a>
                    </div>
                @endif
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/45">Tracked Items</p>
                    <p class="mt-2 text-3xl font-display text-brand-white">{{ number_format($warehouseStats['tracked']) }}</p>
                </div>
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/45">Available Quantity</p>
                    <p class="mt-2 text-3xl font-display text-emerald-300">{{ number_format($warehouseStats['quantity']) }}</p>
                </div>
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/45">Pending Approval</p>
                    <p class="mt-2 text-3xl font-display text-amber-300">{{ number_format($warehouseStats['pending']) }}</p>
                </div>
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/45">Issued / In Use</p>
                    <p class="mt-2 text-3xl font-display text-sky-300">{{ number_format($warehouseStats['issued']) }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.22em] text-brand-ash">
                        <tr>
                            <th class="py-3">Item</th>
                            <th class="py-3">Brand</th>
                            <th class="py-3">Condition</th>
                            <th class="py-3">Safe Keeping</th>
                            <th class="py-3">Last Handled</th>
                            <th class="py-3">Qty</th>
                            <th class="py-3">Request Asset</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouseAssets as $asset)
                            <tr class="border-t border-brand-white/10 align-top">
                                <td class="py-4">
                                    <div class="flex items-start gap-3">
                                        @if($asset->image_path)
                                            <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-14 w-14 rounded-lg object-cover bg-brand-white/10" alt="">
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-lg bg-brand-white/10 text-[10px] font-bold uppercase tracking-wider text-brand-white/45">Asset</div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-brand-white">{{ $asset->name }}</p>
                                            <p class="mt-1 max-w-sm text-xs text-brand-white/45">{{ \Illuminate\Support\Str::limit(strip_tags((string) $asset->description), 120) ?: 'No description yet.' }}</p>
                                            @if($asset->warehouse_notes)
                                                <p class="mt-1 max-w-sm text-[11px] text-brand-white/35">{{ \Illuminate\Support\Str::limit($asset->warehouse_notes, 120) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4">{{ $asset->brand ?: '-' }}</td>
                                <td class="py-4">{{ $asset->condition }}</td>
                                <td class="py-4">{{ $asset->warehouse_location ?: $asset->location ?: '-' }}</td>
                                <td class="py-4">
                                    <p>{{ $asset->lastHandler?->name ?? $asset->assignee?->name ?? '-' }}</p>
                                    @if($asset->last_handled_at)
                                        <p class="mt-1 text-[11px] text-brand-white/40">{{ $asset->last_handled_at->format('M d, Y H:i') }}</p>
                                    @endif
                                </td>
                                <td class="py-4">
                                    <span class="font-semibold text-brand-white">{{ $asset->warehouse_quantity }}</span>
                                </td>
                                <td class="py-4">
                                    @if($asset->warehouse_quantity > 0)
                                        <details class="group">
                                            <summary class="cursor-pointer rounded-full border border-brand-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-white/70 hover:bg-brand-white/10 hover:text-brand-white">Request</summary>
                                            <form method="POST" action="{{ route('portal.assets.warehouse.request', $asset) }}" class="mt-3 grid gap-2 rounded-xl border border-brand-white/10 bg-brand-black/50 p-3">
                                                @csrf
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input type="number" name="requested_quantity" min="1" max="{{ $asset->warehouse_quantity }}" value="1" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">
                                                    <input type="date" name="requested_for" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">
                                                </div>
                                                <input type="text" name="destination_location" required placeholder="Destination / where it will be used" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30">
                                                <textarea name="purpose" rows="2" required placeholder="Purpose of request" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30"></textarea>
                                                <textarea name="requester_notes" rows="2" placeholder="Extra note, if any" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30"></textarea>
                                                <button class="rounded-full bg-brand-red px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark">Submit Request</button>
                                            </form>
                                        </details>
                                    @else
                                        <span class="text-xs text-brand-white/35">Out of stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-brand-white/50">No warehouse-tracked assets match the filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 border-t border-brand-white/10 pt-4">
                {{ $warehouseAssets->links() }}
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="glass-panel rounded-2xl p-5 sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-brand-white">{{ $canManageWarehouse ? 'Warehouse Approval Queue' : 'Warehouse Requests' }}</h3>
                        <p class="text-sm text-brand-white/60">{{ $canManageWarehouse ? 'Approve checks, issue assets, return requests for corrections, and close returned items.' : 'Track your asset requests and approver notes.' }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse($warehouseRequests as $requestItem)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-brand-white/40">{{ $requestItem->request_code }}</p>
                                    <h4 class="mt-1 text-base font-semibold text-brand-white">{{ $requestItem->asset?->name }}</h4>
                                    <p class="text-sm text-brand-white/55">Requested by {{ $requestItem->requester?->name ?? 'Unknown staff' }} for {{ $requestItem->destination_location }}</p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $requestTone($requestItem->status) }}">
                                    {{ $warehouseStatusLabels[$requestItem->status] ?? $requestItem->status }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-3 text-xs text-brand-white/60 sm:grid-cols-3">
                                <p><span class="text-brand-white/35">Qty:</span> {{ $requestItem->requested_quantity }}</p>
                                <p><span class="text-brand-white/35">Needed:</span> {{ $requestItem->requested_for?->format('M d, Y') ?? '-' }}</p>
                                <p><span class="text-brand-white/35">Asset Brand:</span> {{ $requestItem->asset?->brand ?: '-' }}</p>
                            </div>
                            <p class="mt-3 text-sm text-brand-white/70">{{ $requestItem->purpose }}</p>
                            @if($requestItem->review_note)
                                <p class="mt-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">Approver note: {{ $requestItem->review_note }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach(['pre_use_image_path' => 'Before Use', 'issue_image_path' => 'Issued', 'return_image_path' => 'Returned'] as $pathKey => $label)
                                    @if($requestItem->{$pathKey})
                                        <a href="{{ Storage::disk('public')->url($requestItem->{$pathKey}) }}" target="_blank" class="rounded-full border border-brand-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white hover:bg-brand-white/10">{{ $label }} Image</a>
                                    @endif
                                @endforeach
                            </div>

                            @if($canManageWarehouse && $requestItem->isOpen())
                                <form method="POST" action="{{ route('portal.assets.warehouse.action', $requestItem) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-brand-white/10 pt-4 md:grid-cols-[1fr_auto]">
                                    @csrf
                                    <div class="grid gap-2">
                                        <textarea name="note" rows="2" placeholder="Approval note, correction request, issue note, or closure comment" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30"></textarea>
                                        @if($requestItem->status === AssetWarehouseRequest::STATUS_APPROVED_FOR_USE)
                                            <input type="file" name="evidence_image" accept="image/*" class="text-xs text-brand-white file:mr-3 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-white">
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-start gap-2 md:max-w-xs">
                                        @if(in_array($requestItem->status, [AssetWarehouseRequest::STATUS_PENDING_CHECK, AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION], true))
                                            <button name="action" value="approve_check" class="rounded-full bg-sky-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white">Approve Check</button>
                                        @endif
                                        @if($requestItem->pre_use_image_path && in_array($requestItem->status, [AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK, AssetWarehouseRequest::STATUS_INSPECTION_SUBMITTED], true))
                                            <button name="action" value="approve_use" class="rounded-full bg-emerald-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black">Approve Use</button>
                                        @endif
                                        @if($requestItem->status === AssetWarehouseRequest::STATUS_APPROVED_FOR_USE)
                                            <button name="action" value="issue" class="rounded-full bg-violet-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white">Issue</button>
                                        @endif
                                        @if($requestItem->status === AssetWarehouseRequest::STATUS_RETURNED_PENDING_CLOSURE)
                                            <button name="action" value="close" class="rounded-full bg-emerald-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black">Close</button>
                                        @endif
                                        <button name="action" value="send_back" class="rounded-full border border-amber-500/40 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-200">Send Back</button>
                                        <button name="action" value="reject" class="rounded-full border border-brand-red/40 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-red">Reject</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-5 text-sm text-brand-white/50">No warehouse requests yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 border-t border-brand-white/10 pt-4">
                    {{ $warehouseRequests->links() }}
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-white">My Warehouse Requests</h3>
                <p class="text-sm text-brand-white/60">Upload before-use and return images here. A request remains open until final return images are verified and closed.</p>

                <div class="mt-5 space-y-4">
                    @forelse($myWarehouseRequests as $requestItem)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/35">{{ $requestItem->request_code }}</p>
                                    <p class="mt-1 font-semibold text-brand-white">{{ $requestItem->asset?->name }}</p>
                                </div>
                                <span class="rounded-full border px-2 py-1 text-[9px] font-bold uppercase tracking-wider {{ $requestTone($requestItem->status) }}">{{ $warehouseStatusLabels[$requestItem->status] ?? $requestItem->status }}</span>
                            </div>

                            @if($requestItem->review_note)
                                <p class="mt-3 rounded-lg bg-brand-white/5 px-3 py-2 text-xs text-brand-white/60">{{ $requestItem->review_note }}</p>
                            @endif

                            @if(in_array($requestItem->status, [AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION, AssetWarehouseRequest::STATUS_PENDING_CHECK], true))
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wider text-amber-200">Correct Request</summary>
                                    <form method="POST" action="{{ route('portal.assets.warehouse.correct', $requestItem) }}" class="mt-3 grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="number" name="requested_quantity" min="1" value="{{ $requestItem->requested_quantity }}" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">
                                            <input type="date" name="requested_for" value="{{ $requestItem->requested_for?->format('Y-m-d') }}" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">
                                        </div>
                                        <input type="text" name="destination_location" value="{{ $requestItem->destination_location }}" required class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">
                                        <textarea name="purpose" rows="2" required class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">{{ $requestItem->purpose }}</textarea>
                                        <textarea name="requester_notes" rows="2" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white">{{ $requestItem->requester_notes }}</textarea>
                                        <button class="rounded-full bg-amber-500 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black">Resubmit</button>
                                    </form>
                                </details>
                            @endif

                            @if(in_array($requestItem->status, [AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK], true))
                                <form method="POST" action="{{ route('portal.assets.warehouse.evidence', $requestItem) }}" enctype="multipart/form-data" class="mt-3 grid gap-2">
                                    @csrf
                                    <input type="hidden" name="stage" value="pre_use">
                                    <input type="file" name="evidence_image" accept="image/*" required class="text-xs text-brand-white file:mr-3 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-white">
                                    <textarea name="note" rows="2" placeholder="Inspection note" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30"></textarea>
                                    <button class="rounded-full bg-sky-500 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-white">Upload Before-Use Image</button>
                                </form>
                            @endif

                            @if($requestItem->status === AssetWarehouseRequest::STATUS_ISSUED)
                                <form method="POST" action="{{ route('portal.assets.warehouse.evidence', $requestItem) }}" enctype="multipart/form-data" class="mt-3 grid gap-2">
                                    @csrf
                                    <input type="hidden" name="stage" value="return">
                                    <input type="file" name="evidence_image" accept="image/*" required class="text-xs text-brand-white file:mr-3 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-white">
                                    <textarea name="note" rows="2" placeholder="Return condition note" class="rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder:text-brand-white/30"></textarea>
                                    <button class="rounded-full bg-emerald-500 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black">Upload Return Image</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-5 text-sm text-brand-white/50">You have not requested any warehouse asset yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 border-t border-brand-white/10 pt-4">
                    {{ $myWarehouseRequests->links() }}
                </div>
            </div>
        </section>

        <div x-show="showModal"
             style="display: none;"
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-3 py-4 sm:px-6 sm:py-8"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative my-auto flex max-h-[calc(100vh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0a0a0a] shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-4rem)]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-white/10 p-4 sm:p-5">
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-red">DAM & Inventory</p>
                        <h3 class="text-lg font-display text-white">Add New Asset</h3>
                    </div>
                    <button @click="showModal = false" class="text-white/40 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form action="{{ route('portal.assets.store') }}" method="POST" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Name</label>
                                <input type="text" name="name" required class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Activation backdrop frame">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Brand</label>
                                <input type="text" name="brand" list="asset-brands" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Rexona, Guinness">
                                <datalist id="asset-brands">
                                    @foreach($brands as $brandOption)
                                        <option value="{{ $brandOption }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Description</label>
                            <textarea name="description" rows="2" class="wysiwyg-editor w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Asset details, serial number, quantity context, or physical notes."></textarea>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Condition</label>
                                <select name="condition" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    @foreach(['New', 'Good', 'Fair', 'Poor'] as $option)
                                        <option value="{{ $option }}" class="bg-[#0a0a0a] text-white">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Type</label>
                                <select name="type" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    @foreach(['Hardware', 'Software', 'Vehicle', 'Other'] as $option)
                                        <option value="{{ $option }}" class="bg-[#0a0a0a] text-white">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Warehouse Qty</label>
                                <input type="number" name="warehouse_quantity" value="1" min="0" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                            </div>
                            <div class="flex items-end">
                                <label class="flex w-full cursor-pointer items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-white/70">
                                    <input type="checkbox" name="is_warehouse_tracked" value="1" checked class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                    Track in warehouse
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Assigned To</label>
                                <select name="assigned_to" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    <option value="" class="bg-[#0a0a0a] text-white/50">Unassigned</option>
                                    @foreach($staff as $user)
                                        <option value="{{ $user->id }}" class="bg-[#0a0a0a] text-white">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Safe Keeping Location</label>
                                <input type="text" name="warehouse_location" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Warehouse shelf, room, rack, or custody point">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">General Location</label>
                                <input type="text" name="location" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Optional">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Image</label>
                                <input type="file" name="image" accept="image/*" class="w-full text-xs text-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-red-dark">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Warehouse Notes</label>
                            <textarea name="warehouse_notes" rows="2" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Storage instructions, handling warnings, missing parts, or checkout rules."></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Internal Notes</label>
                            <textarea name="notes" rows="2" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Optional internal note."></textarea>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-white/10 bg-[#0a0a0a] p-4 sm:flex-row sm:justify-end sm:p-5">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60 hover:text-white transition">Cancel</button>
                        <button type="submit" class="rounded-full bg-brand-red px-6 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark hover:shadow-lg hover:shadow-brand-red/25 transition-all">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
