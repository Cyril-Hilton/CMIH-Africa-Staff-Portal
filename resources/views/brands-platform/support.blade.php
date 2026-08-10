@extends('layouts.site')

@section('title', $brand->name.' Support Staff Workspace')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Support Staff Workspace</p>
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
                    'Verified Consumers' => number_format($metrics['verified_entries']),
                    'Samples / Sales' => number_format($metrics['units']),
                    'Conversions' => number_format($metrics['conversions']),
                    'Target Progress' => $metrics['reach_rate'].'%',
                    'My Team' => number_format($metrics['assigned_staff']),
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
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Record My Activity</p>
                    <div class="mt-4 grid gap-3">
                        <select name="staff_role" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="supporting_staff">Supporting Staff</option>
                            <option value="promoter">Promoter</option>
                            <option value="sales_personnel">Sales Personnel</option>
                        </select>
                        <select name="activity_type" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="consumer_registration">Consumer Registration</option>
                            <option value="sample_distributed">Sample Distributed</option>
                            <option value="bottle_sale">Bottle Sale / Conversion</option>
                            <option value="reward_issued">Reward Issued</option>
                            <option value="stock_issue">Stock / Availability Issue</option>
                        </select>
                        <input name="location" placeholder="Assigned location" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input name="units" type="number" min="0" value="0" placeholder="Units / actions" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                            <input name="conversion_count" type="number" min="0" value="0" placeholder="Conversions" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <textarea name="notes" rows="4" placeholder="Notes or exceptions" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                        <input type="hidden" name="metadata[latitude]" data-brand-geo-lat>
                        <input type="hidden" name="metadata[longitude]" data-brand-geo-lng>
                        <input type="hidden" name="metadata[accuracy]" data-brand-geo-accuracy>
                        <input name="evidence" type="file" accept="image/*" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                        <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">Save Activity</button>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activation Scope</p>
                                <h2 class="mt-1 text-lg font-semibold text-brand-white">My Assigned Locations</h2>
                            </div>
                            <form method="POST" action="{{ route('brands-platform.field-activity.store', $brand->slug ?: $brand->id) }}" data-brand-geo-form class="flex gap-2">
                                @csrf
                                <input type="hidden" name="staff_role" value="{{ in_array('supporting_staff', $allowedRoles ?? [], true) ? 'supporting_staff' : ($allowedRoles[0] ?? 'supporting_staff') }}">
                                <input type="hidden" name="activity_type" value="check_in">
                                <input type="hidden" name="status" value="checked_in">
                                <input type="hidden" name="units" value="0">
                                <input type="hidden" name="metadata[latitude]" data-brand-geo-lat>
                                <input type="hidden" name="metadata[longitude]" data-brand-geo-lng>
                                <input type="hidden" name="metadata[accuracy]" data-brand-geo-accuracy>
                                <button class="rounded-md bg-brand-white px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Check In</button>
                            </form>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @forelse($assignedLocations as $location)
                                <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                                    <p class="text-sm font-semibold text-brand-white">{{ $location['name'] ?? 'Assigned Location' }}</p>
                                    <p class="mt-2 text-xs text-brand-white/55">Target {{ number_format((int) ($location['target'] ?? 0)) }} - daily {{ number_format((int) ($location['daily_target'] ?? 0)) }}</p>
                                    <p class="mt-1 text-[10px] uppercase tracking-wider text-brand-white/35">{{ count($location['staff_ids'] ?? []) }} assigned staff</p>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40 sm:col-span-2">No specific location has been assigned to your account yet. You can still record approved activity for the brand.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Full Promoter Leaderboard</p>
                        <div class="mt-4 grid gap-2">
                            @forelse($leaderboard as $row)
                                <div class="grid grid-cols-[1fr_auto_auto] gap-3 rounded-md bg-brand-black/35 px-3 py-2 text-xs text-brand-white/70">
                                    <span>{{ $row->user?->name ?: 'Unassigned' }}</span>
                                    <span>{{ number_format($row->units) }} activity</span>
                                    <span>{{ number_format($row->conversions) }} conv.</span>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40">No team activity has been recorded yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">My Activity Log</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                                    <tr><th class="px-3 py-2">Time</th><th class="px-3 py-2">Activity</th><th class="px-3 py-2">Location</th><th class="px-3 py-2">Units</th><th class="px-3 py-2">Conversions</th></tr>
                                </thead>
                                <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                                    @forelse($myActivities as $activity)
                                        <tr>
                                            <td class="px-3 py-3">{{ $activity->created_at?->format('M d, H:i') }}</td>
                                            <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->activity_type) }}</td>
                                            <td class="px-3 py-3">{{ $activity->location ?: 'N/A' }}</td>
                                            <td class="px-3 py-3">{{ number_format($activity->units) }}</td>
                                            <td class="px-3 py-3">{{ number_format($activity->conversion_count) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-8 text-center text-brand-white/40">You have not recorded activity yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $myActivities->links() }}</div>
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
