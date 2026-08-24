<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Office Asset Manager</p>
                <h2 class="text-3xl font-display text-brand-white">Office Assets & Inventory</h2>
                <p class="mt-1 text-sm text-brand-white/60">Internal IT, office, software, vehicle, and company asset register.</p>
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
         x-data="{ showModal: false }"
         class="space-y-6 transition-opacity duration-150">
        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <form method="GET" action="{{ route('portal.assets') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search assets, location, brand..."
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

                <select name="staff" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All staff</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) $staffFilter === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>

                <div class="flex flex-wrap gap-2 xl:col-span-6">
                    <button type="submit" class="rounded-full bg-brand-red px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">
                        Filter
                    </button>
                    <a href="{{ route('portal.assets') }}" class="rounded-full border border-brand-white/10 px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-white/55 transition hover:text-brand-white">
                        Clear
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'assets']) }}" data-no-asset-async class="rounded-full border border-emerald-500/30 px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                        Export CSV
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
                    <p class="text-sm text-brand-white/60">{{ $assets->total() }} asset records shown with live assignment and condition tracking.</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-brand-white/10" data-preserve-scroll data-scroll-key="office-assets-table">
                <table class="w-full min-w-[900px] text-left text-sm text-brand-white/70">
                    <thead class="bg-brand-white/[0.03] text-xs uppercase tracking-[0.2em] text-brand-ash">
                        <tr>
                            @php
                                $sortUrl = fn ($field) => route('portal.assets', array_merge(request()->query(), [
                                    'sort' => $field,
                                    'direction' => request('sort') === $field && request('direction') === 'asc' ? 'desc' : 'asc',
                                ]));
                            @endphp
                            <th class="px-4 py-3"><a href="{{ $sortUrl('name') }}">Asset Name</a></th>
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
                                            @if($asset->description)
                                                <div class="mt-1 line-clamp-2 text-xs text-brand-white/45">{{ strip_tags($asset->description) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">{{ $asset->type }}</td>
                                <td class="px-4 py-4">{{ $asset->brand ?: '-' }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ strtolower((string) $asset->status) === 'available' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-brand-white/5 text-brand-white/70' }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">{{ $asset->condition }}</td>
                                <td class="px-4 py-4">{{ $asset->assignee?->name ?? '-' }}</td>
                                <td class="px-4 py-4">{{ $asset->location ?: ($asset->warehouse_location ?: '-') }}</td>
                                <td class="px-4 py-4 text-right">
                                    @if($canManageAsset)
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('portal.assets.edit', $asset) }}" class="rounded-full border border-brand-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-white/65 transition hover:text-brand-white">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('portal.assets.destroy', $asset) }}" data-confirm="Delete this office asset?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-brand-red/30 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red transition hover:bg-brand-red/10">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-white/30">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-brand-white/40">No office assets match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $assets->links() }}
            </div>
        </section>

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
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Asset Name</label>
                            <input type="text" name="name" required class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="e.g. MacBook Pro">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Description</label>
                            <textarea name="description" rows="3" class="wysiwyg-editor w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Serial number, specs, notes..."></textarea>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Condition</label>
                                <select name="condition" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    @foreach(['New', 'Good', 'Fair', 'Poor'] as $option)
                                        <option value="{{ $option }}" class="bg-[#0a0a0a] text-white">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Type</label>
                                <select name="type" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    @foreach(['Hardware', 'Software', 'Vehicle', 'Other'] as $option)
                                        <option value="{{ $option }}" class="bg-[#0a0a0a] text-white">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Assigned To</label>
                                <select name="assigned_to" class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                                    <option value="" class="bg-[#0a0a0a] text-white/50">Unassigned</option>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" class="bg-[#0a0a0a] text-white">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
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
    </div>
    @include('portal.partials.asset-async')
</x-app-layout>
