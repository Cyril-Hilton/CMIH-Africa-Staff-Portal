<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Office Asset Manager</p>
                <h2 class="text-3xl font-display text-brand-white">Office Assets & Inventory</h2>
                <p class="mt-1 text-sm text-brand-white/60">Internal IT, office, software, vehicle, and company asset register with QR sticker tagging.</p>
            </div>
            <a href="{{ route('portal.assets.warehouse.index') }}" class="rounded-full border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/70 transition hover:border-brand-red/40 hover:text-brand-white">
                Open Warehouse Assets
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div id="office-asset-manager-region"
         data-asset-async-region
         data-asset-list-path="{{ parse_url(route('portal.assets'), PHP_URL_PATH) }}"
         data-asset-action-prefix="{{ parse_url(route('portal.assets'), PHP_URL_PATH) }}"
         data-refresh-url="{{ route('portal.assets', request()->query()) }}"
         x-data="{
             showModal: false,
             showStickerModal: false,
             activeSticker: null,
             openStickerModal(data) {
                 this.activeSticker = data;
                 this.showStickerModal = true;
             }
         }"
         class="space-y-6 transition-opacity duration-150">
        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <form method="GET" action="{{ route('portal.assets') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search assets, tag, location, brand..."
                       class="xl:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">

                <select name="brand" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All brands</option>
                    @foreach($brands as $brandOption)
                        <option value="{{ $brandOption }}" @selected($brand === $brandOption)>{{ $brandOption }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All status</option>
                    @foreach(['Available', 'In Use', 'Maintenance', 'Retired'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="condition" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All condition</option>
                    @foreach(['New', 'Good', 'Fair', 'Poor'] as $option)
                        <option value="{{ $option }}" @selected($condition === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="type" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All types</option>
                    @foreach(['Hardware', 'Software', 'Vehicle', 'Furniture', 'Office Equipment', 'Other'] as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="staff" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All staff</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) $staffFilter === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>

                <div class="flex flex-wrap items-center gap-3 md:col-span-2 xl:col-span-6">
                    <button type="submit" class="rounded-full border border-brand-white/20 bg-brand-white/10 px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-white transition hover:bg-brand-white/20">
                        Filter
                    </button>
                    <a href="{{ route('portal.assets') }}" class="text-xs uppercase tracking-[0.2em] text-brand-white/50 transition hover:text-brand-white">
                        Reset
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'assets']) }}" data-no-asset-async class="rounded-full border border-emerald-500/30 px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                        Export Excel
                    </a>
                    @if($canCreateAssets)
                        <button type="button" @click.prevent="showModal = true" class="ml-auto rounded-full bg-brand-white px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-black transition hover:bg-brand-white/85">
                            Add Asset
                        </button>
                    @endif
                </div>
            </form>
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-brand-white">Office Asset Register</h3>
                    <p class="text-sm text-brand-white/60">{{ $assets->total() }} asset records with live assignment, condition, and QR sticker tracking.</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-brand-white/10" data-preserve-scroll data-scroll-key="office-assets-table">
                <table class="w-full min-w-[980px] text-left text-sm text-brand-white/70">
                    <thead class="bg-brand-white/[0.03] text-xs uppercase tracking-[0.2em] text-brand-ash">
                        <tr>
                            @php
                                $sortUrl = fn ($field) => route('portal.assets', array_merge(request()->query(), [
                                    'sort' => $field,
                                    'direction' => request('sort') === $field && request('direction') === 'asc' ? 'desc' : 'asc',
                                    'staff' => $staffFilter,
                                ]));
                            @endphp
                            <th class="px-4 py-3"><a href="{{ $sortUrl('name') }}">Asset Name</a></th>
                            <th class="px-4 py-3 text-center">QR & Sticker</th>
                            <th class="px-4 py-3"><a href="{{ $sortUrl('type') }}">Type</a></th>
                            <th class="px-4 py-3">Brand</th>
                            <th class="px-4 py-3"><a href="{{ $sortUrl('status') }}">Status</a></th>
                            <th class="px-4 py-3"><a href="{{ $sortUrl('condition') }}">Condition</a></th>
                            <th class="px-4 py-3"><a href="{{ $sortUrl('assigned_to') }}">Assigned To</a></th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/10">
                        @forelse ($assets as $asset)
                            @php
                                $viewer = auth()->user();
                                $viewerDept = strtolower(trim((string) ($viewer->department ?? '')));
                                $canManageAsset = $viewer && (
                                    $viewer->isCvoOrSuperAdmin()
                                    || $viewer->hasRole('admin')
                                    || $viewer->hasFullHrAccess()
                                    || in_array($viewerDept, ['operations_projects', 'operations', 'hr_admin', 'admin'], true)
                                    || (int) $asset->added_by === (int) $viewer->id
                                    || (int) $asset->assigned_to === (int) $viewer->id
                                );

                                $stickerData = [
                                    'id' => $asset->id,
                                    'name' => $asset->name,
                                    'asset_tag' => $asset->asset_tag_or_code,
                                    'category' => $asset->category ?: ($asset->type ?: 'Office Asset'),
                                    'type' => $asset->type,
                                    'status' => $asset->status,
                                    'condition' => $asset->condition,
                                    'assigned_to' => $asset->assignee?->name ?? 'Company Pool',
                                    'department' => $asset->department_name,
                                    'location' => $asset->location ?: ($asset->warehouse_location ?: 'CMIH Office'),
                                    'owner' => $asset->owner ?: 'CMIH Africa',
                                    'scan_url' => $asset->scan_url,
                                    'qr_url' => route('portal.assets.qr', $asset),
                                    'sticker_url' => route('portal.assets.sticker', $asset),
                                ];
                            @endphp
                            <tr>
                                <td class="px-4 py-4 text-brand-white">
                                    <div class="flex items-center gap-3">
                                        @if($asset->image_path)
                                            <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-11 w-11 rounded-lg bg-brand-white/10 object-cover" alt="">
                                        @else
                                            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-white/10 text-[10px] font-bold uppercase tracking-wider text-brand-white/35">Asset</div>
                                        @endif
                                        <div>
                                            <a href="{{ route('portal.assets.show', $asset) }}" class="font-semibold transition hover:text-brand-red">{{ $asset->name }}</a>
                                            @if($asset->category)
                                                <div class="text-[11px] font-medium text-brand-white/60">{{ $asset->category }}</div>
                                            @endif
                                            @if($asset->description)
                                                <div class="mt-0.5 line-clamp-1 text-xs text-brand-white/45">{{ strip_tags($asset->description) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Unique QR Code & Sticker Cell --}}
                                <td class="px-4 py-4 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <button type="button"
                                                @click="openStickerModal({{ json_encode($stickerData) }})"
                                                class="group relative flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white p-1 shadow transition hover:scale-105 hover:border-brand-red hover:shadow-brand-red/30 cursor-pointer"
                                                title="Click to view, print or download sticker">
                                            {!! \App\Services\QrCodeService::svg($asset->scan_url, 32, 4) !!}
                                            <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-brand-red text-[7px] font-bold text-white shadow">
                                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M9 3H4a1 1 0 0 0-1 1v5a2 2 0 0 0 .586 1.414l9 9a2 2 0 0 0 2.828 0l5-5a2 2 0 0 0 0-2.828l-9-9A2 2 0 0 0 9 3Z"/><circle cx="7" cy="7" r="1"/></svg>
                                            </span>
                                        </button>
                                        <span class="font-mono text-[10px] font-bold text-amber-300 tracking-wider">
                                            {{ $asset->asset_tag_or_code }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button type="button"
                                                    @click="openStickerModal({{ json_encode($stickerData) }})"
                                                    class="rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white/70 transition hover:bg-white/15 hover:text-white"
                                                    title="Open Sticker dialog">
                                                Sticker
                                            </button>
                                            <a download data-no-loader data-asset-download href="{{ route('portal.assets.qr', [$asset, 'download' => 1]) }}"
                                               class="rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-400 transition hover:bg-white/15 hover:text-emerald-300"
                                               title="Download QR SVG">
                                                QR
                                            </a>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4">{{ $asset->type }}</td>
                                <td class="px-4 py-4">{{ $asset->brand ?: '-' }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ strtolower((string) $asset->status) === 'available' ? 'bg-emerald-500/10 text-emerald-300' : (strtolower((string) $asset->status) === 'in use' ? 'bg-blue-500/10 text-blue-300' : 'bg-brand-white/5 text-brand-white/70') }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-medium {{ in_array(strtolower($asset->condition), ['new', 'good', 'excellent']) ? 'text-emerald-300' : 'text-amber-300' }}">
                                        {{ $asset->condition }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-white">{{ $asset->assignee?->name ?? 'Company Pool' }}</div>
                                    @if($asset->assignee)
                                        <div class="text-[11px] text-white/50">{{ $asset->department_name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">{{ $asset->location ?: ($asset->warehouse_location ?: '-') }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex justify-end items-center gap-1.5">
                                        <a href="{{ route('portal.assets.sticker', $asset) }}" target="_blank"
                                           class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300 transition hover:bg-amber-500/20"
                                           title="Print Sticker Page">
                                            Print
                                        </a>

                                        @if($canManageAsset)
                                            <a href="{{ route('portal.assets.edit', $asset) }}" class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white/65 transition hover:text-brand-white">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('portal.assets.destroy', $asset) }}" data-confirm="Delete this office asset?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-brand-red/30 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-red transition hover:bg-brand-red/10">
                                                    Delete
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('portal.assets.show', $asset) }}" class="text-xs text-brand-white/40 hover:text-white">View</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-brand-white/40">No office assets match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $assets->links() }}
            </div>
        </section>

        {{-- Add Asset Modal --}}
        <div x-show="showModal" x-cloak data-no-asset-region-replace class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-3 py-4 sm:px-6 sm:py-8">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>

            <div class="relative my-auto flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0a0a0a] shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                <div class="flex items-start justify-between gap-4 border-b border-white/10 p-4 sm:p-5">
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-red">Office Asset Manager</p>
                        <h3 class="text-lg font-display text-white">Add Office / Company Asset</h3>
                    </div>
                    <button type="button" @click="showModal = false" class="text-white/40 transition hover:text-white">Close</button>
                </div>

                <form action="{{ route('portal.assets.store') }}" method="POST" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Name *</label>
                                <input type="text" name="name" required class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Ergonomic Office Chair">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Brand / Maker</label>
                                <input type="text" name="brand" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Herman Miller, Apple">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Category</label>
                                <input type="text" name="category" list="category-suggestions" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Furniture">
                                <datalist id="category-suggestions">
                                    <option value="Furniture">
                                    <option value="IT Equipment">
                                    <option value="Office Appliances">
                                    <option value="Vehicles">
                                    <option value="Media & Production">
                                    <option value="Fixtures & Decor">
                                </datalist>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Type *</label>
                                <input type="text" name="type" required list="type-suggestions" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Office Chair">
                                <datalist id="type-suggestions">
                                    <option value="Office Chair">
                                    <option value="Executive Desk">
                                    <option value="Laptop">
                                    <option value="Desktop Computer">
                                    <option value="Monitor">
                                    <option value="Printer / Scanner">
                                    <option value="Conference Table">
                                    <option value="Air Conditioner">
                                    <option value="Vehicle">
                                </datalist>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Tag (Optional)</label>
                                <input type="text" name="asset_tag" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Auto-generated if empty">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Condition *</label>
                                <select name="condition" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    @foreach(['Good', 'New', 'Fair', 'Poor'] as $option)
                                        <option value="{{ $option }}" class="bg-[#0a0a0a] text-white">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Assigned To</label>
                                <select name="assigned_to" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    <option value="" class="bg-[#0a0a0a] text-white/50">Company Pool / Unassigned</option>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" class="bg-[#0a0a0a] text-white">{{ $member->name }} ({{ \App\Models\User::departmentLabel($member->department) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Location</label>
                                <input type="text" name="location" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. Client Relations Office">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Owner</label>
                                <input type="text" name="owner" value="CMIH" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="CMIH">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Serial Number (Optional)</label>
                                <input type="text" name="serial_number" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. SN-98234-X">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Description & Notes</label>
                            <textarea name="description" rows="2" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Specs, notes, or distinguishing marks..."></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Image</label>
                            <input type="file" name="image" accept="image/*" class="w-full text-xs text-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-red-dark">
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-white/10 p-4 sm:flex-row sm:justify-end sm:p-5">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60 transition hover:text-white">Cancel</button>
                        <button type="submit" class="rounded-full bg-brand-red px-6 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Physical Asset Sticker & QR Code Modal --}}
        <div x-show="showStickerModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6">
            <div class="fixed inset-0 bg-black/85 backdrop-blur-md" @click="showStickerModal = false"></div>

            <div class="relative my-auto w-full max-w-xl overflow-hidden rounded-3xl border border-white/15 bg-[#0f0f0f] shadow-2xl">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-red/20 text-brand-red">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M9 3H4a1 1 0 0 0-1 1v5a2 2 0 0 0 .586 1.414l9 9a2 2 0 0 0 2.828 0l5-5a2 2 0 0 0 0-2.828l-9-9A2 2 0 0 0 9 3Z"/><circle cx="7" cy="7" r="1"/></svg>
                        </span>
                        <div>
                            <h3 class="text-base font-display font-bold text-white">Office Asset QR Sticker</h3>
                            <p class="text-xs text-white/50">Printable physical tag for furniture & company assets</p>
                        </div>
                    </div>
                    <button type="button" @click="showStickerModal = false" class="rounded-lg p-1.5 text-white/40 hover:bg-white/10 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Modal Content --}}
                <div class="p-6 space-y-6">
                    {{-- Sticker Preview Card (Exact printable design) --}}
                    <div class="flex justify-center">
                        <div id="modalStickerCard" class="w-full max-w-md rounded-xl border-2 border-black bg-white p-4 text-black shadow-2xl select-none">
                            {{-- Header --}}
                            <div class="flex items-center justify-between border-b-2 border-black pb-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-[#E50914] px-1.5 py-0.5 font-display text-xs font-bold text-white tracking-wider">CMIH</span>
                                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-black">CMIH Africa Asset Tag</span>
                                </div>
                                <span class="rounded bg-black px-2 py-0.5 font-mono text-[11px] font-bold text-white" x-text="activeSticker?.asset_tag"></span>
                            </div>

                            {{-- Body --}}
                            <div class="flex items-center gap-3.5">
                                {{-- QR Box --}}
                                <div class="flex flex-col items-center justify-center rounded-lg border border-black bg-white p-1.5 shrink-0">
                                    <div id="modalQrContainer" class="flex h-28 w-28 items-center justify-center">
                                        <img :src="activeSticker?.qr_url" alt="Asset QR code" class="h-28 w-28">
                                    </div>
                                    <span class="mt-1 text-[7.5px] font-extrabold uppercase tracking-widest text-neutral-600">Scan to verify</span>
                                </div>

                                {{-- Details --}}
                                <div class="flex-1 min-w-0 space-y-1 text-[10px]">
                                    <div class="font-bold text-black text-xs leading-snug line-clamp-2" x-text="activeSticker?.name"></div>

                                    <div class="flex pt-0.5">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Category:</span>
                                        <span class="font-semibold text-black truncate" x-text="activeSticker?.category"></span>
                                    </div>

                                    <div class="flex">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Type:</span>
                                        <span class="font-semibold text-black truncate" x-text="activeSticker?.type"></span>
                                    </div>

                                    <div class="flex">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Assigned:</span>
                                        <span class="font-bold text-[#E50914] truncate">
                                            <span x-text="activeSticker?.assigned_to"></span>
                                            <template x-if="activeSticker?.department && activeSticker?.department !== 'Unassigned'">
                                                <span class="text-black font-semibold" x-text="' (' + activeSticker.department + ')'"></span>
                                            </template>
                                        </span>
                                    </div>

                                    <div class="flex">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Location:</span>
                                        <span class="font-semibold text-black truncate" x-text="activeSticker?.location"></span>
                                    </div>

                                    <div class="flex">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Status:</span>
                                        <span class="font-semibold text-black" x-text="activeSticker?.status + ' • ' + activeSticker?.condition"></span>
                                    </div>

                                    <div class="flex">
                                        <span class="w-16 font-bold uppercase text-[8px] text-neutral-500">Owner:</span>
                                        <span class="font-semibold text-black" x-text="activeSticker?.owner"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="mt-3 flex items-center justify-between border-t border-dashed border-neutral-400 pt-1.5 text-[7px] font-bold uppercase text-neutral-600 tracking-wider">
                                <span>Property of CMIH Africa • Do Not Remove</span>
                                <span>portal.cmih.africa</span>
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {{-- 1. Print Sticker --}}
                        <a :href="activeSticker?.sticker_url + '?print=1'" target="_blank"
                           class="flex flex-col items-center justify-center gap-1 rounded-2xl border border-brand-red/40 bg-brand-red/10 p-3 text-center text-xs font-bold text-white transition hover:bg-brand-red hover:shadow-lg hover:shadow-brand-red/25">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6zM18 12h.01"/></svg>
                            <span>Print Sticker</span>
                        </a>

                        {{-- 2. Download Sticker PNG --}}
                        <button type="button" data-asset-download @click="downloadModalStickerPng()"
                                class="flex flex-col items-center justify-center gap-1 rounded-2xl border border-white/10 bg-white/5 p-3 text-center text-xs font-bold text-white transition hover:bg-white/15">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <span>Save Sticker (PNG)</span>
                        </button>

                        {{-- 3. Download QR SVG --}}
                        <a download data-no-loader data-asset-download :href="activeSticker?.qr_url + '?download=1'"
                           class="flex flex-col items-center justify-center gap-1 rounded-2xl border border-white/10 bg-white/5 p-3 text-center text-xs font-bold text-emerald-400 transition hover:bg-white/15">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h2v2h-2zM19 15h2M15 19v2M19 19h2v2h-2z"/></svg>
                            <span>Download QR</span>
                        </a>

                        {{-- 4. Test Mobile Scan --}}
                        <a :href="activeSticker?.scan_url" target="_blank"
                           class="flex flex-col items-center justify-center gap-1 rounded-2xl border border-white/10 bg-white/5 p-3 text-center text-xs font-bold text-white/70 transition hover:bg-white/15 hover:text-white">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                            <span>Test Scan Page</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('portal.partials.asset-async')

    <script>
        // Window helper for high-res PNG sticker download
        window.downloadModalStickerPng = function() {
            const alpineEl = document.getElementById('office-asset-manager-region');
            if (!alpineEl || !alpineEl._x_dataStack) return;
            const data = alpineEl._x_dataStack[0].activeSticker;
            if (!data) return;

            const canvas = document.createElement('canvas');
            const scale = 3;
            const width = 380;
            const height = 240;
            canvas.width = width * scale;
            canvas.height = height * scale;
            const ctx = canvas.getContext('2d');
            ctx.scale(scale, scale);

            // Card background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);

            // Border
            ctx.strokeStyle = '#111111';
            ctx.lineWidth = 2.5;
            ctx.strokeRect(0, 0, width, height);

            function fitText(value, maxWidth) {
                let text = String(value || '-');
                if (ctx.measureText(text).width <= maxWidth) return text;
                while (text.length && ctx.measureText(text + '…').width > maxWidth) text = text.slice(0, -1);
                return text + '…';
            }

            // Header bar
            ctx.fillStyle = '#E50914';
            ctx.fillRect(12, 12, 38, 18);
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px sans-serif';
            ctx.fillText('CMIH', 17, 26);

            ctx.fillStyle = '#111111';
            ctx.font = 'bold 11px sans-serif';
            ctx.fillText('CMIH AFRICA ASSET TAG', 56, 26);

            // Asset Tag Badge
            ctx.fillStyle = '#111111';
            ctx.fillRect(width - 120, 12, 108, 20);
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 10px monospace';
            ctx.fillText(fitText(data.asset_tag, 96), width - 114, 26);

            // Divider
            ctx.strokeStyle = '#111111';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(12, 38);
            ctx.lineTo(width - 12, 38);
            ctx.stroke();

            // Load QR Code SVG as Image
            const qrImg = new Image();
            qrImg.crossOrigin = 'anonymous';
            qrImg.onload = function() {
                ctx.strokeStyle = '#111111';
                ctx.lineWidth = 1;
                ctx.strokeRect(12, 46, 124, 124);
                ctx.drawImage(qrImg, 14, 48, 120, 120);

                ctx.fillStyle = '#555555';
                ctx.font = 'bold 8px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('SCAN TO VERIFY', 74, 182);
                ctx.textAlign = 'left';

                // Asset Details
                ctx.fillStyle = '#111111';
                ctx.font = 'bold 13px sans-serif';
                ctx.fillText(fitText(data.name, width - 160), 148, 62);

                let y = 82;
                function drawField(label, val, isHighlight) {
                    ctx.fillStyle = '#666666';
                    ctx.font = 'bold 8px sans-serif';
                    ctx.fillText(label.toUpperCase(), 148, y);

                    ctx.fillStyle = isHighlight ? '#E50914' : '#111111';
                    ctx.font = isHighlight ? 'bold 10px sans-serif' : '600 9px sans-serif';
                    ctx.fillText(fitText(val, width - 227), 215, y);
                    y += 18;
                }

                drawField('Category:', data.category);
                drawField('Type:', data.type);
                const assignedText = data.assigned_to + (data.department && data.department !== 'Unassigned' ? ' (' + data.department + ')' : '');
                drawField('Assigned:', assignedText, true);
                drawField('Location:', data.location);
                drawField('Condition:', data.status + ' • ' + data.condition);
                drawField('Owner:', data.owner);

                // Footer
                ctx.strokeStyle = '#888888';
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(12, 214);
                ctx.lineTo(width - 12, 214);
                ctx.stroke();
                ctx.setLineDash([]);

                ctx.fillStyle = '#666666';
                ctx.font = '7px sans-serif';
                ctx.fillText('PROPERTY OF CMIH AFRICA • DO NOT REMOVE', 12, 228);
                ctx.textAlign = 'right';
                ctx.fillText('portal.cmih.africa', width - 12, 228);

                // Trigger file download
                const link = document.createElement('a');
                link.download = 'cmih-sticker-' + data.id + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            };

            // Fetch the SVG QR from the server route
            qrImg.onerror = () => window.alert('Unable to load the QR code. Please refresh and try again.');
            qrImg.src = data.qr_url;
        };

    </script>
    @include('portal.partials.asset-download-confirm')
</x-app-layout>
