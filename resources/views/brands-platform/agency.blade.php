@extends('layouts.site')

@section('title', $brand->name.' Agency Dashboard')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Activation Dashboard</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">{{ $brand->name }}</h1>
                    <p class="mt-2 text-sm text-brand-white/60">{{ $activation?->name ?: $brand->activation_name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('brands-platform.show', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brand Page</a>
                    @if($activation?->client_share_token)
                        <a href="{{ route('brands-platform.client-report', $activation->client_share_token) }}" class="rounded-md bg-brand-white px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Client Link</a>
                    @endif
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach([
                    'Reach' => number_format($metrics['reached']),
                    'Target' => number_format($metrics['target']),
                    'Reach Rate' => $metrics['reach_rate'].'%',
                    'Field Updates' => number_format($metrics['field_updates']),
                    'Assigned Staff' => number_format($metrics['assigned_staff']),
                ] as $label => $value)
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[0.78fr_1.22fr]">
                <form method="POST" action="{{ route('brands-platform.field-activity.store', $brand->slug ?: $brand->id) }}" enctype="multipart/form-data" class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    @csrf
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Record Field Activity</p>
                    <div class="mt-4 grid gap-3">
                        <select name="staff_role" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="agency_staff">Agency Staff</option>
                            <option value="supporting_staff">Supporting Staff</option>
                            <option value="promoter">Promoter</option>
                            <option value="sales_personnel">Sales Personnel</option>
                        </select>
                        <select name="activity_type" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="consumer_registration">Consumer Registration</option>
                            <option value="sample_distributed">Sample Distributed</option>
                            <option value="bottle_sale">Bottle Sale / Conversion</option>
                            <option value="reward_issued">Reward Issued</option>
                            <option value="retail_update">Retail / Partner Update</option>
                            <option value="stock_issue">Stock / Availability Issue</option>
                        </select>
                        <input name="location" placeholder="Location / branch" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="units" type="number" min="0" value="0" placeholder="Units / count" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <textarea name="notes" rows="4" placeholder="Activity notes, insight, issue, or follow-up" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                        <input name="evidence" type="file" accept="image/*" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                        <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">Save Activity</button>
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Gender Distribution</p>
                            <div class="mt-4 space-y-3">
                                @forelse($entriesByGender as $row)
                                    @php $width = $metrics['consumer_entries'] > 0 ? min(100, round(($row->total / $metrics['consumer_entries']) * 100)) : 0; @endphp
                                    <div>
                                        <div class="flex justify-between text-xs text-brand-white/70"><span>{{ $row->label }}</span><span>{{ $row->total }}</span></div>
                                        <div class="mt-1 h-2 rounded-full bg-brand-white/10"><div class="h-2 rounded-full bg-brand-red" style="width: {{ $width }}%"></div></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-brand-white/40">No consumer demographics captured yet.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Age Distribution</p>
                            <div class="mt-4 space-y-3">
                                @forelse($entriesByAge as $row)
                                    @php $width = $metrics['consumer_entries'] > 0 ? min(100, round(($row->total / $metrics['consumer_entries']) * 100)) : 0; @endphp
                                    <div>
                                        <div class="flex justify-between text-xs text-brand-white/70"><span>{{ $row->label }}</span><span>{{ $row->total }}</span></div>
                                        <div class="mt-1 h-2 rounded-full bg-brand-white/10"><div class="h-2 rounded-full bg-cyan-400" style="width: {{ $width }}%"></div></div>
                                    </div>
                                @empty
                                    <p class="text-sm text-brand-white/40">No consumer age data captured yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Location Performance</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                                    <tr><th class="px-3 py-2">Location</th><th class="px-3 py-2">Units</th><th class="px-3 py-2">Updates</th></tr>
                                </thead>
                                <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                                    @forelse($locationPerformance as $row)
                                        <tr><td class="px-3 py-3">{{ $row->label }}</td><td class="px-3 py-3">{{ number_format($row->units) }}</td><td class="px-3 py-3">{{ number_format($row->updates) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="px-3 py-8 text-center text-brand-white/40">No location activity yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Recent Transactions</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                            <tr><th class="px-3 py-2">Time</th><th class="px-3 py-2">Staff</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Activity</th><th class="px-3 py-2">Location</th><th class="px-3 py-2">Units</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                            @forelse($recentActivities as $activity)
                                <tr>
                                    <td class="px-3 py-3">{{ $activity->created_at?->format('M d, H:i') }}</td>
                                    <td class="px-3 py-3">{{ $activity->user?->name ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->staff_role) }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->activity_type) }}</td>
                                    <td class="px-3 py-3">{{ $activity->location ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ number_format($activity->units) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-brand-white/40">No activity has been recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $recentActivities->links() }}</div>
            </div>
        </div>
    </section>
@endsection
