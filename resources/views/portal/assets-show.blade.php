@php
    use App\Models\AssetWarehouseRequest;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $asset->is_warehouse_tracked ? 'Warehouse Assets Manager' : 'Office Asset Manager' }}</p>
                <h2 class="text-3xl font-display text-brand-white">{{ $asset->name }}</h2>
            </div>
            <a href="{{ $asset->is_warehouse_tracked ? route('portal.assets.warehouse.index') : route('portal.assets') }}" class="text-sm text-brand-ash hover:text-white transition">Back to Assets</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            @if($asset->image_path)
                <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-72 w-full rounded-xl object-cover bg-brand-white/10" alt="">
            @else
                <div class="flex h-72 w-full items-center justify-center rounded-xl bg-brand-white/10 text-sm font-bold uppercase tracking-wider text-brand-white/35">No image</div>
            @endif

            <div class="mt-5 grid gap-3 text-sm text-brand-white/70">
                <p><span class="text-brand-white/40">Asset Tag:</span> <span class="font-mono font-bold text-amber-300">{{ $asset->asset_tag_or_code }}</span></p>
                <p><span class="text-brand-white/40">Category:</span> {{ $asset->category ?: ($asset->type ?: 'Office Asset') }}</p>
                <p><span class="text-brand-white/40">Brand:</span> {{ $asset->brand ?: '-' }}</p>
                <p><span class="text-brand-white/40">Type:</span> {{ $asset->type }}</p>
                <p><span class="text-brand-white/40">Status:</span> {{ $asset->status }}</p>
                <p><span class="text-brand-white/40">Condition:</span> {{ $asset->condition }}</p>
                <p><span class="text-brand-white/40">Assigned To:</span> {{ $asset->assignee?->name ?? 'Unassigned' }} @if($asset->assignee) <span class="text-xs text-brand-white/50">({{ $asset->department_name }})</span> @endif</p>
                <p><span class="text-brand-white/40">Location:</span> {{ $asset->location ?: ($asset->warehouse_location ?: '-') }}</p>
                <p><span class="text-brand-white/40">Owner:</span> {{ $asset->owner ?: 'CMIH Africa' }}</p>
                @if($asset->serial_number)
                    <p><span class="text-brand-white/40">Serial Number:</span> <span class="font-mono">{{ $asset->serial_number }}</span></p>
                @endif
            </div>

            {{-- Official QR Code & Sticker Panel --}}
            <div class="mt-6 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-white p-1.5 shadow-md shrink-0">
                        {!! \App\Services\QrCodeService::svg($asset->scan_url, 56, 4) !!}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-[9px] font-bold uppercase tracking-widest text-brand-red">QR Sticker & Tracking</span>
                        <div class="font-mono text-xs font-bold text-amber-300">{{ $asset->asset_tag_or_code }}</div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <a href="{{ route('portal.assets.sticker', $asset) }}" target="_blank"
                               class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300 hover:bg-amber-500/20 transition">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6zM18 12h.01"/></svg> Print Sticker
                            </a>
                            <a download data-no-loader data-asset-download href="{{ route('portal.assets.qr', [$asset, 'download' => 1]) }}"
                               class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-400 hover:bg-white/15 transition">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/></svg> QR SVG
                            </a>
                            <a href="{{ $asset->scan_url }}" target="_blank"
                               class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white/70 hover:bg-white/15 hover:text-white transition">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h2v2h-2zM19 15h2M15 19v2M19 19h2v2h-2z"/></svg> Test Scan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass-panel rounded-2xl p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-brand-white">Asset Details</h3>
            <div class="prose prose-invert mt-3 max-w-none text-brand-white/70">
                {!! $asset->description ?: '<p>No description has been added.</p>' !!}
            </div>

            @if($asset->warehouse_notes)
                <div class="mt-5 rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/40">Warehouse Notes</p>
                    <p class="mt-2 text-sm text-brand-white/70">{{ $asset->warehouse_notes }}</p>
                </div>
            @endif

            <div class="mt-6">
                <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-white/70">Warehouse Request History</h4>
                <div class="mt-3 space-y-3">
                    @forelse($asset->warehouseRequests as $requestItem)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-brand-white">{{ $requestItem->request_code }}</p>
                                <span class="rounded-full border border-brand-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white/60">
                                    {{ $warehouseStatusLabels[$requestItem->status] ?? AssetWarehouseRequest::statusLabel($requestItem->status) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-brand-white/60">{{ $requestItem->requester?->name }} requested {{ $requestItem->requested_quantity }} for {{ $requestItem->destination_location }}.</p>
                        </div>
                    @empty
                        <p class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4 text-sm text-brand-white/45">No warehouse request history yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
    @include('portal.partials.asset-download-confirm')
</x-app-layout>
