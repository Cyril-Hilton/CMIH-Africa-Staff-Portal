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
                <p><span class="text-brand-white/40">Brand:</span> {{ $asset->brand ?: '-' }}</p>
                <p><span class="text-brand-white/40">Type:</span> {{ $asset->type }}</p>
                <p><span class="text-brand-white/40">Availability:</span> {{ $asset->status }}</p>
                <p><span class="text-brand-white/40">Condition:</span> {{ $asset->condition }}</p>
                <p><span class="text-brand-white/40">Assigned To:</span> {{ $asset->assignee?->name ?? 'Unassigned' }}</p>
                <p><span class="text-brand-white/40">Safe Keeping:</span> {{ $asset->warehouse_location ?: '-' }}</p>
                <p><span class="text-brand-white/40">Last Handled:</span> {{ $asset->lastHandler?->name ?? '-' }}</p>
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
</x-app-layout>
