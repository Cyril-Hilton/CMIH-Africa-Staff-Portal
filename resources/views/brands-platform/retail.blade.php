@extends('layouts.site')

@section('title', $brand->name.' Retail Partner Workspace')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Retail Partner Workspace</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">{{ $brand->name }}</h1>
                    <p class="mt-2 text-sm text-brand-white/60">{{ $activation?->name ?: $brand->activation_name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('brands-platform.show', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brand Page</a>
                    <a href="{{ route('brands-platform.agency', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Agency View</a>
                </div>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach([
                    'Successful Today' => number_format($metrics['conversions']),
                    'Scans / Updates' => number_format($metrics['field_updates']),
                    'Failed Rate' => '0%',
                    'Value Redeemed' => 'GHS '.number_format($redemptions->sum('transaction_value'), 2),
                    'Verified Consumers' => number_format($metrics['verified_entries']),
                ] as $label => $value)
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <form method="POST" action="{{ route('brands-platform.field-activity.store', $brand->slug ?: $brand->id) }}" enctype="multipart/form-data" class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    @csrf
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Scan / Validate</p>
                    <div class="mt-4 grid gap-3">
                        <input type="hidden" name="staff_role" value="retail_staff">
                        <select name="activity_type" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="reward_redeemed">Valid Reward Redeemed</option>
                            <option value="retail_scan">Retail Scan</option>
                            <option value="retail_update">Retail Update</option>
                        </select>
                        <input name="reference_code" required placeholder="Reward / coupon / receipt reference" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="location" placeholder="Retail branch / partner location" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input name="conversion_count" type="number" min="0" value="1" placeholder="Successful redemptions" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                            <input name="transaction_value" type="number" min="0" step="0.01" placeholder="Value redeemed" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <input type="hidden" name="metadata[latitude]" data-brand-geo-lat>
                        <input type="hidden" name="metadata[longitude]" data-brand-geo-lng>
                        <input type="hidden" name="metadata[accuracy]" data-brand-geo-accuracy>
                        <textarea name="notes" rows="4" placeholder="Validation notes or blocked reason" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                        <input name="evidence" type="file" accept="image/*" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                        <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">Confirm Redemption</button>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Assigned Retail Scope</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @forelse($assignedLocations as $location)
                                <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                                    <p class="text-sm font-semibold text-brand-white">{{ $location['name'] ?? 'Assigned Partner' }}</p>
                                    <p class="mt-2 text-xs text-brand-white/55">Target {{ number_format((int) ($location['target'] ?? 0)) }} - daily {{ number_format((int) ($location['daily_target'] ?? 0)) }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40 sm:col-span-2">No specific retail location has been assigned yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Recent Branch Activity</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                                    <tr><th class="px-3 py-2">Time</th><th class="px-3 py-2">Reference</th><th class="px-3 py-2">Location</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Value</th></tr>
                                </thead>
                                <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                                    @forelse($redemptions as $activity)
                                        <tr>
                                            <td class="px-3 py-3">{{ $activity->created_at?->format('M d, H:i') }}</td>
                                            <td class="px-3 py-3">{{ $activity->reference_code ?: 'N/A' }}</td>
                                            <td class="px-3 py-3">{{ $activity->location ?: 'N/A' }}</td>
                                            <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->status) }}</td>
                                            <td class="px-3 py-3">GHS {{ number_format((float) $activity->transaction_value, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-8 text-center text-brand-white/40">No retail activity has been captured yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $redemptions->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const fillGeoFields = (position) => {
                document.querySelectorAll('[data-brand-geo-lat]').forEach((field) => field.value = position.coords.latitude);
                document.querySelectorAll('[data-brand-geo-lng]').forEach((field) => field.value = position.coords.longitude);
                document.querySelectorAll('[data-brand-geo-accuracy]').forEach((field) => field.value = Math.round(position.coords.accuracy || 0));
            };

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(fillGeoFields, () => {}, {
                    enableHighAccuracy: true,
                    maximumAge: 60000,
                    timeout: 8000,
                });
            }
        })();
    </script>
@endpush
