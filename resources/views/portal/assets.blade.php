<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">DAM & Inventory Hub</p>
                <h2 class="text-3xl font-display text-brand-white">Assets & Inventory Management</h2>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-400">
            ✅ {{ session('status') }}
        </div>
    @endif

    <div x-data="{ activeTab: @js($activeTab ?? 'it-assets'), showModal: false }" class="space-y-6">
        <!-- Navigation Tab Switcher -->
        <div class="flex items-center gap-2 border-b border-brand-white/10 pb-3 overflow-x-auto">
            <button @click="activeTab = 'it-assets'"
                    :class="activeTab === 'it-assets' ? 'bg-brand-red text-white font-bold shadow-lg shadow-brand-red/20' : 'text-brand-white/60 hover:text-white hover:bg-brand-white/5'"
                    class="rounded-xl px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] transition-all flex items-center gap-2 shrink-0 cursor-pointer">
                <span>💻 IT & Company Assets</span>
                <span class="rounded-full bg-brand-white/20 px-2 py-0.5 text-[10px]">{{ $assets->total() ?? count($assets) }}</span>
            </button>
            <button @click="activeTab = 'warehouse-posm'"
                    :class="activeTab === 'warehouse-posm' ? 'bg-brand-red text-white font-bold shadow-lg shadow-brand-red/20' : 'text-brand-white/60 hover:text-white hover:bg-brand-white/5'"
                    class="rounded-xl px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] transition-all flex items-center gap-2 shrink-0 cursor-pointer">
                <span>📦 Warehouse & POSM Inventory Ledger</span>
                <span class="rounded-full bg-brand-white/20 px-2 py-0.5 text-[10px]">{{ $posmEntries->total() ?? count($posmEntries) }}</span>
            </button>
        </div>

        <!-- ── TAB 1: IT & COMPANY ASSETS ────────────────────────────────── -->
        <div x-show="activeTab === 'it-assets'" x-transition class="glass-panel rounded-2xl p-6 relative space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-brand-white">IT Equipment & Company Assets</h3>
                    <p class="text-sm text-brand-white/70">Hardware, software, vehicles, office gear availability & assignment history.</p>
                </div>
                @if($canCreateAssets)
                    <button type="button" @click.prevent="showModal = true" class="inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white hover:opacity-90 transition cursor-pointer">
                        + Add Asset
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.3em] text-brand-ash">
                        <tr>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['tab' => 'it-assets', 'sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                    Asset Name
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ request('sort') === 'name' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                        <svg class="w-2 h-2 {{ request('sort') === 'name' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['tab' => 'it-assets', 'sort' => 'type', 'direction' => (request('sort') === 'type' || request('sort') === 'asset_type') && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                    Type
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ (request('sort') === 'type' || request('sort') === 'asset_type') && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                        <svg class="w-2 h-2 {{ (request('sort') === 'type' || request('sort') === 'asset_type') && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['tab' => 'it-assets', 'sort' => 'status', 'direction' => request('sort') === 'status' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                    Status
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ request('sort') === 'status' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                        <svg class="w-2 h-2 {{ request('sort') === 'status' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['tab' => 'it-assets', 'sort' => 'condition', 'direction' => request('sort') === 'condition' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                    Condition
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ request('sort') === 'condition' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                        <svg class="w-2 h-2 {{ request('sort') === 'condition' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </a>
                            </th>
                            <th class="py-3">
                                <a href="{{ route('portal.assets', array_merge(request()->query(), ['tab' => 'it-assets', 'sort' => 'assigned_to', 'direction' => request('sort') === 'assigned_to' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                    Assigned To
                                    <div class="flex flex-col">
                                        <svg class="w-2 h-2 {{ request('sort') === 'assigned_to' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                        <svg class="w-2 h-2 {{ request('sort') === 'assigned_to' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </a>
                            </th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            @endphp
                            <tr class="border-t border-brand-white/10">
                                <td class="py-4 text-brand-white">
                                    <div class="flex items-center gap-3">
                                        @if($asset->image_path)
                                            <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="w-10 h-10 rounded-lg object-cover bg-brand-white/10" alt="">
                                        @endif
                                        <div>
                                            <p class="font-semibold text-brand-white">{{ $asset->name }}</p>
                                            @if($asset->description)
                                                <div class="text-xs text-brand-white/50 mt-1">{!! $asset->description !!}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4">{{ $asset->type }}</td>
                                <td class="py-4">
                                    <span class="px-2 py-1 rounded text-xs uppercase tracking-wider {{ in_array(strtolower($asset->status), ['available', 'in stock', 'new'], true) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-brand-white/10' }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td class="py-4">{{ $asset->condition }}</td>
                                <td class="py-4">{{ $asset->assignee?->name ?? '-' }}</td>
                                <td class="py-4 text-right">
                                    @if($canManageAsset)
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('portal.assets.edit', $asset) }}" class="text-brand-white/40 hover:text-brand-white transition" title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </a>
                                            <button
                                                type="button"
                                                @click="$dispatch('open-confirm-modal', { url: '{{ route('portal.assets.destroy', $asset) }}' })"
                                                class="text-brand-white/40 hover:text-brand-red transition" title="Delete"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-white/30">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-brand-white/40 italic">No IT or company equipment recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($assets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 pt-4 border-t border-brand-white/10">
                    {{ $assets->links() }}
                </div>
            @endif
        </div>

        <!-- ── TAB 2: WAREHOUSE & POSM INVENTORY LEDGER ────────────────── -->
        <div x-show="activeTab === 'warehouse-posm'" x-transition class="space-y-6">
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Warehouse Tracking</p>
                        <h3 class="text-xl font-display text-brand-white uppercase">📋 POSM & Materials Deployment Ledger</h3>
                        <p class="text-xs text-brand-white/60 mt-1">Real-time stock movements across Warehouse A, Warehouse B, and field deployments.</p>
                    </div>
                </div>

                {{-- Form + Ledger Grid --}}
                <div class="grid grid-cols-1 lg:grid-cols-[340px_minmax(0,1fr)] gap-6">
                    {{-- Form --}}
                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/40 p-5 h-fit">
                        <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-3">Log Deployment / Stock Movement</h4>
                        <form method="POST" action="{{ route('portal.assets.posm.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Item Name *</label>
                                <input type="text" name="item_name" required
                                       class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"
                                       placeholder="e.g. Pull-up Banner A">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Item Type *</label>
                                <select name="item_type" required
                                        class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white">
                                    <option value="POSM">POSM</option>
                                    <option value="Uniform">Uniform / T-shirts</option>
                                    <option value="Banner">Banner / Rollup</option>
                                    <option value="Tablet">Tablet / Device</option>
                                    <option value="AV">AV / Sound Equipment</option>
                                    <option value="Other">Other Material</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Client / Brand</label>
                                <input type="text" name="client_brand"
                                       class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"
                                       placeholder="e.g. Unilever / Nestle">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Qty In *</label>
                                    <input type="number" name="quantity_in" required min="0" value="0"
                                           class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Qty Out *</label>
                                    <input type="number" name="quantity_out" required min="0" value="0"
                                           class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Location</label>
                                <input type="text" name="location"
                                       class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"
                                       placeholder="e.g. Warehouse A / Accra Office">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Notes / Deployment Details</label>
                                <textarea name="notes" rows="2"
                                          class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"
                                          placeholder="Purpose of movement..."></textarea>
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-bold text-white transition-all shadow-md cursor-pointer">
                                Submit Log Entry
                            </button>
                        </form>
                    </div>

                    {{-- Ledger Table --}}
                    <div class="overflow-x-auto rounded-xl border border-brand-white/10 bg-brand-black/30 p-4 max-h-[600px] overflow-y-auto min-w-0">
                        <table class="w-full text-left text-xs text-brand-white/70 min-w-[650px]">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-2.5">Item Name</th>
                                    <th class="py-2.5">Type</th>
                                    <th class="py-2.5">Client / Brand</th>
                                    <th class="py-2.5">In</th>
                                    <th class="py-2.5">Out</th>
                                    <th class="py-2.5">Net Bal</th>
                                    <th class="py-2.5">Location</th>
                                    <th class="py-2.5">Logged By</th>
                                    <th class="py-2.5">Date</th>
                                    <th class="py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse($posmEntries ?? [] as $entry)
                                    <tr>
                                        <td class="py-3 font-semibold text-brand-white">
                                            {{ $entry->item_name }}
                                            @if($entry->notes)
                                                <div class="text-[10px] text-brand-white/40 font-normal mt-0.5">{!! $entry->notes !!}</div>
                                            @endif
                                        </td>
                                        <td class="py-3">{{ $entry->item_type }}</td>
                                        <td class="py-3">{{ $entry->client_brand ?? '—' }}</td>
                                        <td class="py-3 text-emerald-400 font-semibold">+{{ $entry->quantity_in }}</td>
                                        <td class="py-3 text-brand-red font-semibold">-{{ $entry->quantity_out }}</td>
                                        @php $net = $entry->quantity_in - $entry->quantity_out; @endphp
                                        <td class="py-3 font-bold {{ $net >= 0 ? 'text-emerald-400' : 'text-brand-red' }}">
                                            {{ $net }}
                                        </td>
                                        <td class="py-3 text-brand-white/60">{{ $entry->location ?? 'Warehouse' }}</td>
                                        <td class="py-3 text-brand-white/60">{{ $entry->creator?->name ?? 'System' }}</td>
                                        <td class="py-3 text-brand-ash">{{ $entry->created_at?->format('d M H:i') }}</td>
                                        <td class="py-3 text-right">
                                            <form method="POST" action="{{ route('portal.assets.posm.destroy', $entry) }}" onsubmit="return confirm('Remove log entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-brand-red/60 hover:text-brand-red transition-colors cursor-pointer" title="Delete log">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="py-8 text-center text-xs text-brand-white/40 italic">No warehouse POSM or material stock ledger entries logged yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($posmEntries instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $posmEntries->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Add IT Asset Modal -->
        <div x-show="showModal"
             style="display: none;"
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-3 py-4 sm:px-6 sm:py-8"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>

            <!-- Modal Content -->
            <div class="relative my-auto flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-[#0a0a0a] border border-white/10 shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-4rem)]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95">

                <div class="relative z-10 flex min-h-0 flex-1 flex-col">
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-white/10 p-4 sm:p-5">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-red mb-1">DAM & Inventory</p>
                            <h3 class="text-lg font-display text-white">Add New IT / Company Asset</h3>
                        </div>
                        <button @click="showModal = false" class="text-white/40 hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form action="{{ route('portal.assets.store') }}" method="POST" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Asset Name</label>
                                <input type="text" name="name" required class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors placeholder-white/20" placeholder="e.g. MacBook Pro M3">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Description</label>
                                <textarea name="description" rows="2" class="wysiwyg-editor w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors placeholder-white/20" placeholder="Serial number, specs, etc."></textarea>
                            </div>

                            <div x-data class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Condition</label>
                                    <div class="relative">
                                        <select name="condition" class="w-full appearance-none bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                                            <option value="New" class="bg-[#0a0a0a] text-white">New</option>
                                            <option value="Good" class="bg-[#0a0a0a] text-white">Good</option>
                                            <option value="Fair" class="bg-[#0a0a0a] text-white">Fair</option>
                                            <option value="Poor" class="bg-[#0a0a0a] text-white">Poor</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white/50">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Type</label>
                                    <div class="relative">
                                        <select name="type" class="w-full appearance-none bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                                            <option value="Hardware" class="bg-[#0a0a0a] text-white">Hardware</option>
                                            <option value="Software" class="bg-[#0a0a0a] text-white">Software</option>
                                            <option value="Vehicle" class="bg-[#0a0a0a] text-white">Vehicle</option>
                                            <option value="Other" class="bg-[#0a0a0a] text-white">Other</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white/50">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Assigned To</label>
                                    <div class="relative">
                                        <select name="assigned_to" class="w-full appearance-none bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                                            <option value="" class="bg-[#0a0a0a] text-white/50">Unassigned</option>
                                            @foreach($staff as $user)
                                                <option value="{{ $user->id }}" class="bg-[#0a0a0a] text-white">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white/50">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 mb-1.5">Asset Image</label>
                                <label class="flex min-h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-white/10 px-3 py-4 hover:border-brand-red/50 hover:bg-white/5 transition-all group">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-5 h-5 mb-1.5 text-white/30 group-hover:text-brand-red transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                        <p class="mb-0.5 text-[10px] text-white/50"><span class="font-semibold text-white group-hover:text-brand-red">Click to upload</span></p>
                                    </div>
                                    <input type="file" name="image" accept="image/*" class="hidden" />
                                </label>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-white/10 bg-[#0a0a0a] p-4 sm:flex-row sm:justify-end sm:p-5">
                            <button type="button" @click="showModal = false" class="px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60 hover:text-white transition">Cancel</button>
                            <button type="submit" class="rounded-full bg-brand-red px-6 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark hover:shadow-lg hover:shadow-brand-red/25 transition-all cursor-pointer">Save Asset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
