<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Warehouse Asset Intake</h1>
            <a class="underline" href="{{ route('portal.assets.warehouse.index') }}#warehouse-inventory">Warehouse inventory</a>
        </div>
    </x-slot>
    <div class="mx-auto max-w-6xl space-y-5">
        <section class="glass-panel rounded-2xl border border-brand-white/10 p-5">
            <h2 class="font-semibold">{{ $pending }} {{ $canReview ? 'awaiting review' : 'of your suggestions awaiting review' }}</h2>
            <p class="mt-1 text-sm text-brand-ash">Reported quantities describe the activity in the source. Use a confirmed count for units currently in the warehouse.</p>
            @if(session('status'))<p role="status" class="mt-3 text-sm text-emerald-300">{{ session('status') }}</p>@endif
            @if($errors->any())<p role="alert" class="mt-3 text-sm text-brand-red">{{ $errors->first() }}</p>@endif
            <form method="GET" action="{{ route('portal.assets.warehouse.intake') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <label class="text-sm">Search item or source reference
                    <input type="search" name="q" value="{{ request('q') }}" class="mt-1 block rounded-lg bg-brand-black p-2.5">
                </label>
                <label class="text-sm">Status
                    <select name="status" class="mt-1 block rounded-lg bg-brand-black p-2.5">
                        @foreach(['pending'=>'Awaiting review','approved'=>'Approved','dismissed'=>'Dismissed','superseded'=>'Replaced by updated report','all'=>'All'] as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-lg border border-brand-white/20 px-4 py-2.5 text-sm">Filter suggestions</button>
            </form>
        </section>

        @forelse($suggestions as $suggestion)
            @php($source = $suggestion->reported)
            @php($review = $suggestion->review ?? [])
            <details class="glass-panel rounded-xl border border-brand-white/10 p-4" @if($errors->any() && (int) old('suggestion_id') === $suggestion->id) open @endif>
                <summary class="cursor-pointer">
                    <strong>{{ filled($source['name'] ?? null) ? $source['name'] : 'Item name needed' }}</strong>
                    <span class="ml-2 text-sm text-brand-ash">{{ $suggestion->source_reference }} · {{ $suggestion->status === 'pending' ? 'Awaiting review' : \Illuminate\Support\Str::headline($suggestion->status) }}</span>
                </summary>
                @include('portal.partials.executive-record-fields', ['fields' => [
                    'Source' => $source['basis'] ?? null, 'Reported by' => $suggestion->submitter?->name,
                    'Activity' => $source['activity'] ?? null, 'Project' => $source['project'] ?? null,
                    'Linked activation' => $suggestion->activation?->title, 'Brand' => $source['brand'] ?? null,
                    'Reported date' => $source['reported_date'] ?? null, 'Reported location' => $source['reported_location'] ?? null,
                    'Reported quantity' => $source['quantity'] ?? null, 'Reported returns' => $source['returned_quantity'] ?? null,
                    'Recorded asset code' => $source['asset_code'] ?? null, 'Vendor' => $source['vendor'] ?? null,
                    'Details' => $source['details'] ?? null, 'Review notes' => $review['notes'] ?? null,
                ]])
                @if($suggestion->asset)
                    <a class="mt-3 inline-block text-sm underline" href="{{ route('portal.assets.show', $suggestion->asset) }}">Open {{ $suggestion->asset->name }}</a>
                @endif
                @if($suggestion->reviewer && $suggestion->status !== 'pending')
                    <p class="mt-3 text-sm text-brand-ash">Reviewed by {{ $suggestion->reviewer->name }} on {{ $suggestion->reviewed_at?->format('d M Y') }}</p>
                @endif
                @if($canReview && $suggestion->status === 'pending')
                    @php($value = fn ($field, $fallback = '') => (int) old('suggestion_id') === $suggestion->id ? old($field, $review[$field] ?? $fallback) : ($review[$field] ?? $fallback))
                    <form method="POST" action="{{ route('portal.assets.warehouse.intake.review', $suggestion) }}" class="mt-4 space-y-4 border-t border-brand-white/10 pt-4">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $suggestion->revision }}">
                        <input type="hidden" name="suggestion_id" value="{{ $suggestion->id }}">
                        <fieldset class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <legend class="mb-3 font-semibold">Inventory details</legend>
                            @foreach(['name'=>['Item name', $source['name'] ?? '', 255], 'brand'=>['Brand', $source['brand'] ?? '', 120],
                                'type'=>['Asset type', $source['type'] ?? '', 80], 'category'=>['Category', '', 100],
                                'asset_tag'=>['Asset tag', $source['asset_code'] ?? '', 100], 'condition'=>['Condition', '', 80],
                                'warehouse_location'=>['Warehouse location', '', 255]] as $field => [$label, $initial, $limit])
                                <label class="text-sm">{{ $label }}
                                    <input name="{{ $field }}" maxlength="{{ $limit }}" value="{{ $value($field, $initial) }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5">
                                </label>
                            @endforeach
                            <label class="text-sm">Confirmed units on hand
                                <input type="number" name="warehouse_quantity" min="0" max="9999999" step="1" value="{{ $value('warehouse_quantity') }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5">
                            </label>
                        </fieldset>
                        @if($assets->isNotEmpty())
                            <label class="block text-sm">Or link to existing inventory
                                <select name="asset_id" class="mt-1 block w-full max-w-md rounded-lg bg-brand-black p-2.5">
                                    <option value="">Choose an asset</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected((int) $value('asset_id') === $asset->id)>{{ $asset->name }}{{ $asset->asset_tag ? ' · '.$asset->asset_tag : '' }}{{ $asset->brand ? ' · '.$asset->brand : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="block text-sm">Review notes
                            <textarea name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5">{{ $value('notes') }}</textarea>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button name="decision" value="save" class="rounded-lg border border-brand-white/20 px-4 py-2 text-sm">Save corrections</button>
                            <button name="decision" value="approve" class="rounded-lg border border-emerald-400/50 bg-emerald-400/10 px-4 py-2 text-sm font-semibold">Approve new asset</button>
                            @if($assets->isNotEmpty())<button name="decision" value="link" class="rounded-lg border border-sky-400/40 px-4 py-2 text-sm">Approve and link existing</button>@endif
                            <button name="decision" value="dismiss" class="rounded-lg border border-brand-white/20 px-4 py-2 text-sm">Dismiss suggestion</button>
                        </div>
                    </form>
                @endif
            </details>
        @empty
            <p class="glass-panel rounded-xl p-5 text-brand-ash">No asset suggestions match this filter.</p>
        @endforelse
        {{ $suggestions->links() }}
    </div>
</x-app-layout>
