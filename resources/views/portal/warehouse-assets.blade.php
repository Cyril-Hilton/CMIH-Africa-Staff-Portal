<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">CAMS</p>
                <h2 class="text-3xl font-display text-brand-white">Warehouse Assets Manager</h2>
                <p class="mt-1 max-w-3xl text-sm text-brand-white/60">Master Asset Inventory, POSM stock movements, requisitions, custody sign-off, photo evidence, and return audits.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.assets') }}" class="rounded-full border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/65 transition hover:text-brand-white">
                    Office Assets
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'inventory', 'format' => 'csv'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-emerald-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                    Inventory CSV
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'inventory', 'format' => 'excel'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-sky-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-sky-300 transition hover:bg-sky-500/10">
                    Inventory Excel
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'inventory', 'format' => 'pdf'] + request()->query()) }}" data-no-asset-async target="_blank" class="rounded-full border border-brand-red/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-red transition hover:bg-brand-red/10">
                    Inventory PDF
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'requests', 'format' => 'csv'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-emerald-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                    Requests CSV
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'requests', 'format' => 'excel'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-emerald-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                    Requests Excel
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'requests', 'format' => 'pdf'] + request()->query()) }}" data-no-asset-async target="_blank" class="rounded-full border border-emerald-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300 transition hover:bg-emerald-500/10">
                    Requests PDF
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'posm', 'format' => 'csv'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-amber-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-amber-300 transition hover:bg-amber-500/10">
                    POSM CSV
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'posm', 'format' => 'excel'] + request()->query()) }}" data-no-asset-async class="rounded-full border border-amber-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-amber-300 transition hover:bg-amber-500/10">
                    POSM Excel
                </a>
                <a href="{{ route('portal.assets.warehouse.export', ['scope' => 'posm', 'format' => 'pdf'] + request()->query()) }}" data-no-asset-async target="_blank" class="rounded-full border border-amber-500/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-amber-300 transition hover:bg-amber-500/10">
                    POSM PDF
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 p-4 text-sm text-brand-red">
            <p class="font-semibold">Please check the highlighted form entries.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="warehouse-asset-manager-region"
         data-asset-async-region
         data-asset-list-path="{{ parse_url(route('portal.assets.warehouse.index'), PHP_URL_PATH) }}"
         data-asset-action-prefix="{{ parse_url(route('portal.assets.warehouse.index'), PHP_URL_PATH) }}"
         data-refresh-url="{{ route('portal.assets.warehouse.index', request()->query()) }}"
         class="space-y-6 transition-opacity duration-150">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @foreach([
                ['label' => 'Master Records', 'value' => $metrics['master_records'], 'tone' => 'text-brand-white'],
                ['label' => 'Total Qty', 'value' => $metrics['total_quantity'], 'tone' => 'text-sky-300'],
                ['label' => 'Available', 'value' => $metrics['available'], 'tone' => 'text-emerald-300'],
                ['label' => 'Deployed', 'value' => $metrics['deployed'], 'tone' => 'text-amber-300'],
                ['label' => 'Under Remodel', 'value' => $metrics['under_remodel'], 'tone' => 'text-purple-300'],
                ['label' => 'Pending Approvals', 'value' => $metrics['pending_approvals'], 'tone' => 'text-brand-red'],
            ] as $metric)
                <div class="rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/45">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-4xl font-display {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <form method="GET" action="{{ route('portal.assets.warehouse.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-8">
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search name, tag, serial..."
                       class="xl:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">

                <select name="brand" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All brands</option>
                    @foreach($brands as $option)
                        <option value="{{ $option }}" @selected($filters['brand'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="category" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All categories</option>
                    @foreach($categories as $option)
                        <option value="{{ $option }}" @selected($filters['category'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="condition" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All condition</option>
                    @foreach(['New', 'Excellent', 'Good', 'Fair', 'Poor'] as $option)
                        <option value="{{ $option }}" @selected($filters['condition'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All status</option>
                    @foreach(['Available', 'In Use', 'Deployed', 'Pending Approval', 'In Repair', 'Under Remodel', 'Retired'] as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="location" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All locations</option>
                    @foreach($locations as $option)
                        <option value="{{ $option }}" @selected($filters['location'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <select name="request_status" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="">All request status</option>
                    @foreach($statusLabels as $statusKey => $label)
                        <option value="{{ $statusKey }}" @selected($filters['request_status'] === $statusKey)>{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex flex-wrap gap-2 xl:col-span-8">
                    <button type="submit" class="rounded-full bg-brand-red px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">
                        Filter Warehouse
                    </button>
                    <a href="{{ route('portal.assets.warehouse.index') }}" class="rounded-full border border-brand-white/10 px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-white/55 transition hover:text-brand-white">
                        Clear
                    </a>
                </div>
            </form>
        </section>

        @if($canImportWarehouse || $canGrantWarehouseCollaborators)
            <section class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                @if($canImportWarehouse)
                    <div class="glass-panel rounded-2xl p-5 sm:p-6">
                        <h3 class="text-lg font-semibold text-brand-white">Bulk Import Assets</h3>
                        <p class="mt-1 text-sm text-brand-white/55">Upload a CSV, XLSX, or DOCX table with headers such as Asset Name, Asset Value, Category, Brand, PO Quantity, Quantity Procured, Owner, Status, Asset Type, Condition, Asset Tag, Serial Number, Custodian, and Location.</p>
                        <form method="POST" action="{{ route('portal.assets.warehouse.import') }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                            @csrf
                            <input type="file" name="asset_file" required accept=".csv,.txt,.xlsx,.docx"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-red-dark">
                            <p class="text-xs text-brand-white/40">Structured CSV, XLSX and DOCX tables are parsed directly. Convert scanned PDFs, legacy DOC files, or old XLS files to CSV/XLSX first for accurate row mapping.</p>
                            <button class="rounded-full bg-brand-red px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">
                                Import Master Inventory
                            </button>
                        </form>
                    </div>
                @endif

                @if($canGrantWarehouseCollaborators)
                    <div class="glass-panel rounded-2xl p-5 sm:p-6">
                        <h3 class="text-lg font-semibold text-brand-white">Warehouse Collaborators</h3>
                        <p class="mt-1 text-sm text-brand-white/55">Operations HOD and Super Admin own this space. Appointed collaborators can help edit, import, and process warehouse requests until rights are revoked.</p>

                        <form method="POST" action="{{ route('portal.assets.warehouse.collaborators.store') }}" class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                            @csrf
                            <select name="user_id" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                <option value="">Select staff collaborator</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }} - {{ \App\Models\User::departmentLabel($member->department) }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-full bg-brand-white px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-black transition hover:bg-brand-white/85">
                                Grant Rights
                            </button>
                            <div class="flex flex-wrap gap-4 text-xs text-brand-white/60 md:col-span-2">
                                <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_edit" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red"> Edit</label>
                                <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_import" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red"> Import</label>
                                <label class="inline-flex items-center gap-2"><input type="checkbox" name="can_approve" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red"> Approve</label>
                            </div>
                            <textarea name="notes" rows="2" placeholder="Reason / handover note" class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                        </form>

                        <div class="mt-5 divide-y divide-brand-white/10 rounded-xl border border-brand-white/10">
                            @forelse($collaborators as $collaborator)
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-semibold text-brand-white">{{ $collaborator->user?->name }}</p>
                                        <p class="text-xs text-brand-white/45">Granted by {{ $collaborator->grantor?->name ?? 'System' }} - {{ $collaborator->created_at?->format('d M Y') }}</p>
                                        @if($collaborator->notes)
                                            <p class="mt-1 text-xs text-brand-white/50">{{ $collaborator->notes }}</p>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('portal.assets.warehouse.collaborators.destroy', $collaborator) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-full border border-brand-red/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-red transition hover:bg-brand-red/10">
                                            Revoke
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="p-4 text-sm text-brand-white/40">No active collaborators appointed yet.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)]">
            @if($canEditWarehouse)
                <div class="glass-panel rounded-2xl p-5 sm:p-6">
                    <h3 class="text-lg font-semibold text-brand-white">Master Asset Database</h3>
                    <p class="mt-1 text-sm text-brand-white/55">Add the CAMS source-of-truth record for warehouse assets, POSM, fixtures, staging, visibility, and equipment.</p>

                    <form action="{{ route('portal.assets.warehouse.store') }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        <input name="name" required placeholder="Asset name" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="asset_tag" placeholder="Asset tag" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="serial_number" placeholder="Serial number" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="category" placeholder="Category" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="brand" placeholder="Brand" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="asset_value" type="number" step="0.01" min="0" placeholder="Asset value" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="po_quantity" type="number" min="0" placeholder="PO quantity" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="quantity_procured" type="number" min="0" placeholder="Qty procured" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="warehouse_quantity" type="number" min="0" value="1" required placeholder="Current qty" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="owner" placeholder="Owner" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="type" required placeholder="Asset type" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <select name="asset_use_type" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            <option value="">Use type</option>
                            <option value="Fixed">Fixed</option>
                            <option value="Current">Current</option>
                        </select>
                        <select name="status" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            @foreach(['Available', 'Deployed', 'Pending Approval', 'In Repair', 'Under Remodel', 'Retired'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        <select name="condition" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            @foreach(['New', 'Excellent', 'Good', 'Fair', 'Poor'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        <input name="warehouse_location" placeholder="Safe keeping location" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <select name="assigned_to" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            <option value="">Current custodian</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        <textarea name="description" rows="2" placeholder="Description, tags, procurement notes..." class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                        <textarea name="warehouse_notes" rows="2" placeholder="Warehouse notes" class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                        <input type="file" name="image" accept="image/*" class="md:col-span-2 text-xs text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-red-dark">
                        <button class="md:col-span-2 rounded-full bg-brand-red px-5 py-2.5 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">
                            Add To Master Inventory
                        </button>
                    </form>
                </div>
            @endif

            <div class="glass-panel rounded-2xl p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-white">User Requisition Form</h3>
                <p class="mt-1 text-sm text-brand-white/55">Submit asset requests for gatekeeper approval and custody audit.</p>

                <form method="POST" action="{{ $requestableAssets->first() ? route('portal.assets.warehouse.request', $requestableAssets->first()) : '#' }}" class="mt-5 space-y-3" x-data="{ assetUrl: '' }" x-init="assetUrl = $refs.assetSelect.selectedOptions[0]?.dataset.url || ''" @submit="if (assetUrl) $el.action = assetUrl">
                    @csrf
                    <select x-ref="assetSelect" @change="assetUrl = $event.target.selectedOptions[0]?.dataset.url || ''" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0" required>
                        <option value="">Select warehouse asset</option>
                        @foreach($requestableAssets as $asset)
                            <option value="{{ $asset->id }}" data-url="{{ route('portal.assets.warehouse.request', $asset) }}">
                                {{ $asset->name }} - {{ $asset->brand ?: 'No brand' }} - Qty {{ $asset->warehouse_quantity }}
                            </option>
                        @endforeach
                    </select>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input name="requested_quantity" type="number" min="1" value="1" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                        <input name="requested_for" type="date" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    </div>
                    <input name="destination_location" required placeholder="Destination / activation location" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                    <textarea name="purpose" rows="3" required placeholder="Purpose of request" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                    <textarea name="requester_notes" rows="2" placeholder="Extra notes" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                    <button type="submit" class="w-full rounded-full bg-brand-white px-5 py-2.5 text-xs font-bold uppercase tracking-[0.2em] text-brand-black transition hover:bg-brand-white/85" @disabled($requestableAssets->isEmpty())>
                        Submit Request
                    </button>
                </form>
            </div>
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <div class="mb-5 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-brand-white">Master Asset Inventory Grid</h3>
                    <p class="text-sm text-brand-white/55">Asset values, procurement quantities, condition, location, status, custody, and lifecycle state.</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-brand-white/10" data-preserve-scroll data-scroll-key="warehouse-master-grid">
                <table class="w-full min-w-[1250px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-white/[0.03] uppercase tracking-[0.16em] text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Asset Name</th>
                            <th class="px-4 py-3">Tag / Serial</th>
                            <th class="px-4 py-3">Value</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Brand</th>
                            <th class="px-4 py-3">PO Qty</th>
                            <th class="px-4 py-3">Procured</th>
                            <th class="px-4 py-3">Current Qty</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Condition</th>
                            <th class="px-4 py-3">Custodian / Location</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/10">
                        @forelse($warehouseAssets as $asset)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($asset->image_path)
                                            <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-10 w-10 rounded-lg bg-brand-white/10 object-cover" alt="">
                                        @endif
                                        <div>
                                            <a href="{{ route('portal.assets.show', $asset) }}" class="font-semibold text-brand-white transition hover:text-brand-red">{{ $asset->name }}</a>
                                            @if($asset->description)
                                                <p class="mt-1 line-clamp-2 max-w-xs text-brand-white/45">{{ strip_tags($asset->description) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p>{{ $asset->asset_tag ?: '-' }}</p>
                                    <p class="text-brand-white/40">{{ $asset->serial_number ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-4">{{ $asset->asset_value !== null ? 'GHS ' . number_format((float) $asset->asset_value, 2) : '-' }}</td>
                                <td class="px-4 py-4">{{ $asset->category ?: '-' }}</td>
                                <td class="px-4 py-4">{{ $asset->brand ?: '-' }}</td>
                                <td class="px-4 py-4">{{ $asset->po_quantity ?? 0 }}</td>
                                <td class="px-4 py-4">{{ $asset->quantity_procured ?? 0 }}</td>
                                <td class="px-4 py-4 font-bold text-brand-white">{{ $asset->warehouse_quantity ?? 0 }}</td>
                                <td class="px-4 py-4">{{ $asset->owner ?: 'CMIH' }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white/70">{{ $asset->status }}</span>
                                </td>
                                <td class="px-4 py-4">{{ $asset->asset_use_type ?: $asset->type }}</td>
                                <td class="px-4 py-4">{{ $asset->condition }}</td>
                                <td class="px-4 py-4">
                                    <p>{{ $asset->assignee?->name ?? $asset->lastHandler?->name ?? '-' }}</p>
                                    <p class="text-brand-white/40">{{ $asset->warehouse_location ?: $asset->location ?: '-' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-10 text-center text-sm text-brand-white/40">No warehouse master inventory records match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $warehouseAssets->links() }}
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="glass-panel rounded-2xl p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-white">Warehouse & POSM Inventory Ledger</h3>
                <p class="mt-1 text-sm text-brand-white/55">Stock-in, stock-out, net balance, warehouse location, and deployment notes.</p>

                @if($canEditWarehouse)
                    <form method="POST" action="{{ route('portal.assets.warehouse.posm.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input name="item_name" required placeholder="Item name" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <select name="item_type" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            @foreach(['POSM', 'Uniform', 'Banner', 'Tablet', 'AV', 'Other'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        <input name="client_brand" placeholder="Brand" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="location" placeholder="Location" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                        <input name="quantity_in" type="number" min="0" value="0" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                        <input name="quantity_out" type="number" min="0" value="0" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                        <textarea name="notes" rows="2" placeholder="Movement notes" class="sm:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0"></textarea>
                        <button class="sm:col-span-2 rounded-full bg-brand-red px-5 py-2.5 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">Save Movement</button>
                    </form>
                @endif

                <div class="mt-5 overflow-x-auto rounded-xl border border-brand-white/10" data-preserve-scroll data-scroll-key="warehouse-posm-ledger">
                    <table class="w-full min-w-[720px] text-left text-xs text-brand-white/70">
                        <thead class="bg-brand-white/[0.03] uppercase tracking-[0.16em] text-brand-ash">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Brand</th>
                                <th class="px-4 py-3">In</th>
                                <th class="px-4 py-3">Out</th>
                                <th class="px-4 py-3">Balance</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3">Logged By</th>
                                @if($canEditWarehouse)
                                    <th class="px-4 py-3 text-right">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/10">
                            @forelse($posmEntries as $entry)
                                @php($balance = (int) $entry->quantity_in - (int) $entry->quantity_out)
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-brand-white">{{ $entry->item_name }}</p>
                                        <p class="text-brand-white/45">{{ $entry->item_type }}</p>
                                    </td>
                                    <td class="px-4 py-4">{{ $entry->client_brand ?: '-' }}</td>
                                    <td class="px-4 py-4 text-emerald-300">+{{ $entry->quantity_in }}</td>
                                    <td class="px-4 py-4 text-brand-red">-{{ $entry->quantity_out }}</td>
                                    <td class="px-4 py-4 font-bold {{ $balance >= 0 ? 'text-emerald-300' : 'text-brand-red' }}">{{ $balance }}</td>
                                    <td class="px-4 py-4">{{ $entry->location ?: 'Warehouse' }}</td>
                                    <td class="px-4 py-4">{{ $entry->creator?->name ?? 'System' }}</td>
                                    @if($canEditWarehouse)
                                        <td class="px-4 py-4 text-right">
                                            <form method="POST" action="{{ route('portal.assets.warehouse.posm.destroy', $entry) }}" onsubmit="return confirm('Remove this warehouse movement?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-full border border-brand-red/30 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red transition hover:bg-brand-red/10">Delete</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEditWarehouse ? 8 : 7 }}" class="px-4 py-8 text-center text-sm text-brand-white/40">No warehouse/POSM stock movements logged yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $posmEntries->links() }}</div>
            </div>

            <div class="glass-panel rounded-2xl p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-brand-white">Requisition & Sign-Off Hub</h3>
                <p class="mt-1 text-sm text-brand-white/55">Approval phases: request, check approval, inspection image, use approval, issue, return image, closure.</p>

                <div class="mt-5 space-y-4">
                    @forelse($warehouseRequests as $requestItem)
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-brand-white">{{ $requestItem->request_code }}</p>
                                        <span class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white/65">
                                            {{ $statusLabels[$requestItem->status] ?? \App\Models\AssetWarehouseRequest::statusLabel($requestItem->status) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-brand-white/70">{{ $requestItem->asset?->name }} requested by {{ $requestItem->requester?->name }}.</p>
                                    <p class="mt-1 text-xs text-brand-white/45">Qty {{ $requestItem->requested_quantity }} for {{ $requestItem->destination_location }} - {{ $requestItem->created_at?->format('d M Y H:i') }}</p>
                                    @if($requestItem->review_note)
                                        <p class="mt-2 rounded-lg border border-amber-500/20 bg-amber-500/10 p-2 text-xs text-amber-200">{{ $requestItem->review_note }}</p>
                                    @endif
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-[10px] text-brand-white/45">
                                    <span>{{ $requestItem->pre_use_image_path ? 'Pre-use photo: yes' : 'Pre-use photo: no' }}</span>
                                    <span>{{ $requestItem->issue_image_path ? 'Issue photo: yes' : 'Issue photo: no' }}</span>
                                    <span>{{ $requestItem->return_image_path ? 'Return photo: yes' : 'Return photo: no' }}</span>
                                </div>
                            </div>

                            @if((int) $requestItem->requested_by === (int) auth()->id() && $requestItem->status === \App\Models\AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION)
                                <form method="POST" action="{{ route('portal.assets.warehouse.correct', $requestItem) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="requested_quantity" type="number" min="1" value="{{ $requestItem->requested_quantity }}" required class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    <input name="requested_for" type="date" value="{{ $requestItem->requested_for?->format('Y-m-d') }}" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    <input name="destination_location" value="{{ $requestItem->destination_location }}" required class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    <textarea name="purpose" rows="2" required class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">{{ $requestItem->purpose }}</textarea>
                                    <textarea name="requester_notes" rows="2" class="md:col-span-2 rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">{{ $requestItem->requester_notes }}</textarea>
                                    <button class="md:col-span-2 rounded-full bg-brand-red px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white">Resubmit Correction</button>
                                </form>
                            @endif

                            @if((int) $requestItem->requested_by === (int) auth()->id() && in_array($requestItem->status, [\App\Models\AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK, \App\Models\AssetWarehouseRequest::STATUS_ISSUED], true))
                                <form method="POST" action="{{ route('portal.assets.warehouse.evidence', $requestItem) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-[140px_minmax(0,1fr)]">
                                    @csrf
                                    <input type="hidden" name="stage" value="{{ $requestItem->status === \App\Models\AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK ? 'pre_use' : 'return' }}">
                                    <input type="file" name="evidence_image" accept="image/*" required class="text-xs text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white">
                                    <input name="note" placeholder="Evidence note" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                                    <button class="md:col-span-2 rounded-full bg-brand-white px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-black">
                                        Upload {{ $requestItem->status === \App\Models\AssetWarehouseRequest::STATUS_APPROVED_TO_CHECK ? 'Inspection' : 'Return' }} Photo
                                    </button>
                                </form>
                            @endif

                            @if($canApproveWarehouse && ! in_array($requestItem->status, [\App\Models\AssetWarehouseRequest::STATUS_CLOSED, \App\Models\AssetWarehouseRequest::STATUS_REJECTED], true))
                                <form method="POST" action="{{ route('portal.assets.warehouse.action', $requestItem) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-[220px_minmax(0,1fr)]" x-data="{ action: 'approve_check' }">
                                    @csrf
                                    <select name="action" x-model="action" class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                        <option value="approve_check">Approve to Check</option>
                                        <option value="return_correction">Send Back for Correction</option>
                                        <option value="reject">Reject</option>
                                        <option value="approve_use">Approve for Use</option>
                                        <option value="issue">Issue Asset</option>
                                        <option value="close">Close Return</option>
                                        <option value="send_remodel">Close and Send to Remodel</option>
                                    </select>
                                    <div>
                                        <input name="note" :required="['return_correction', 'reject'].includes(action)" placeholder="Approval, decline, issue, closure or remodel note" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0">
                                        <p x-show="['return_correction', 'reject'].includes(action)" class="mt-1 text-[11px] font-semibold text-amber-300">A blocking note is required when sending back or rejecting a requisition.</p>
                                    </div>
                                    <input type="file" name="evidence_image" accept="image/*" class="md:col-span-2 text-xs text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white">
                                    <button class="md:col-span-2 rounded-full bg-brand-red px-5 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white">Submit Gatekeeper Action</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/[0.03] p-8 text-center text-sm text-brand-white/45">
                            No warehouse requests match the selected filters.
                        </div>
                    @endforelse
                </div>

                <div class="mt-5">{{ $warehouseRequests->links() }}</div>
            </div>
        </section>
    </div>
    @include('portal.partials.asset-async')
</x-app-layout>
