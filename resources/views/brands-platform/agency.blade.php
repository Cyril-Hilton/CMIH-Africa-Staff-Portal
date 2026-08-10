@extends('layouts.site')

@section('title', $brand->name.' Agency Dashboard')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Agency Command Centre</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">{{ $brand->name }}</h1>
                    <p class="mt-2 text-sm text-brand-white/60">{{ $activation?->name ?: $brand->activation_name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('brands-platform.show', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brand Page</a>
                    <a href="{{ route('brands-platform.support', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Support</a>
                    <a href="{{ route('brands-platform.retail', $brand->slug ?: $brand->id) }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Retail</a>
                    @if($activation?->client_share_token && $activation->clientShareIsActive())
                        <a href="{{ route('brands-platform.client-report', $activation->client_share_token) }}" class="rounded-md bg-brand-white px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Client Link</a>
                    @endif
                </div>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif
            @if(session('client_link'))
                <div class="mb-5 rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4 text-sm text-brand-white/70">
                    Temporary client link:
                    <a href="{{ session('client_link') }}" class="font-semibold text-brand-white underline">{{ session('client_link') }}</a>
                </div>
            @endif

            <form method="GET" class="mb-5 grid gap-3 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                <select name="status" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                    <option value="">All statuses</option>
                    @foreach(['recorded', 'done', 'pending', 'blocked', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-brand-white px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Filter</button>
            </form>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                @foreach([
                    'Reach' => number_format($metrics['reached']),
                    'Target' => number_format($metrics['target']),
                    'Verified' => $metrics['verification_rate'].'%',
                    'Conversions' => number_format($metrics['conversions']),
                    'High Intent' => $metrics['high_intent_rate'].'%',
                    'Assigned Staff' => number_format($metrics['assigned_staff']),
                ] as $label => $value)
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activation Trend</p>
                    <div class="mt-4 h-72">
                        <canvas id="brandActivationTrendChart"></canvas>
                    </div>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activation Funnel</p>
                    <div class="mt-4 h-72">
                        <canvas id="brandActivationFunnelChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Consumer Demographics</p>
                    <div class="mt-4 h-64">
                        <canvas id="brandDemographicChart"></canvas>
                    </div>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Location Output</p>
                    <div class="mt-4 h-64">
                        <canvas id="brandLocationChart"></canvas>
                    </div>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Support Staff Ranking</p>
                    <div class="mt-4 h-64">
                        <canvas id="brandLeaderboardChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[0.78fr_1.22fr]">
                <div class="space-y-6">
                    <form method="POST" action="{{ route('brands-platform.field-activity.store', $brand->slug ?: $brand->id) }}" enctype="multipart/form-data" class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        @csrf
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Record Field Activity</p>
                        <div class="mt-4 grid gap-3">
                            <select name="staff_role" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                @foreach([
                                    'agency_staff' => 'Agency Staff',
                                    'supporting_staff' => 'Supporting Staff',
                                    'promoter' => 'Promoter',
                                    'sales_personnel' => 'Sales Personnel',
                                    'retail_staff' => 'Retail Staff',
                                    'field_supervisor' => 'Field Supervisor',
                                    'merchandiser' => 'Merchandiser',
                                ] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="activity_type" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                <option value="consumer_registration">Consumer Registration</option>
                                <option value="sample_distributed">Sample Distributed</option>
                                <option value="bottle_sale">Bottle Sale / Conversion</option>
                                <option value="reward_issued">Reward Issued</option>
                                <option value="reward_redeemed">Reward Redeemed</option>
                                <option value="retail_scan">Retail Scan</option>
                                <option value="retail_update">Retail / Partner Update</option>
                                <option value="stock_issue">Stock / Availability Issue</option>
                            </select>
                            <input name="location" placeholder="Location / branch" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <input name="units" type="number" min="0" value="0" placeholder="Units" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                                <input name="conversion_count" type="number" min="0" value="0" placeholder="Conversions" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                                <input name="transaction_value" type="number" min="0" step="0.01" placeholder="Value" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                            </div>
                            <input name="reference_code" placeholder="Coupon / reward / transaction reference" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                            <textarea name="notes" rows="4" placeholder="Activity notes, insight, issue, or follow-up" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                            <input name="evidence" type="file" accept="image/*" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">Save Activity</button>
                        </div>
                    </form>

                    @if($activation && auth()->user()?->isCvoOrSuperAdmin())
                        <form method="POST" action="{{ route('brands-platform.admin.client-link.generate', $activation) }}" class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                            @csrf
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Temporary Client Access</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                                <select name="duration" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                    @foreach($clientDurations as $value => $option)
                                        <option value="{{ $value }}">Valid for {{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-brand-white px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Generate</button>
                            </div>
                            @if($activation->client_share_expires_at)
                                <p class="mt-3 text-xs text-brand-white/45">Current link expires {{ $activation->client_share_expires_at->format('M d, Y H:i') }}.</p>
                            @endif
                        </form>
                    @endif
                </div>

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
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Generated Outputs</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'retail' => 'Retail', 'promoter' => 'Promoter', 'consumer-insights' => 'Insights', 'closeout' => 'Closeout'] as $type => $label)
                                    <a href="{{ route('brands-platform.export', [$brand->slug ?: $brand->id, $type]) }}?{{ http_build_query(request()->only(['from', 'to', 'status'])) }}" class="rounded-md border border-brand-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/55 hover:text-brand-white">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                                    <tr><th class="px-3 py-2">Location</th><th class="px-3 py-2">Units</th><th class="px-3 py-2">Conversions</th><th class="px-3 py-2">Updates</th></tr>
                                </thead>
                                <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                                    @forelse($locationPerformance as $row)
                                        <tr><td class="px-3 py-3">{{ $row->label }}</td><td class="px-3 py-3">{{ number_format($row->units) }}</td><td class="px-3 py-3">{{ number_format($row->conversions) }}</td><td class="px-3 py-3">{{ number_format($row->updates) }}</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-8 text-center text-brand-white/40">No location activity yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Top Support Staff</p>
                        <div class="mt-4 grid gap-2">
                            @forelse($leaderboard as $row)
                                <div class="grid grid-cols-[1fr_auto_auto] gap-3 rounded-md bg-brand-black/35 px-3 py-2 text-xs text-brand-white/70">
                                    <span>{{ $row->user?->name ?: 'Unassigned' }}</span>
                                    <span>{{ number_format($row->units) }} units</span>
                                    <span>{{ number_format($row->conversions) }} conv.</span>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40">No staff activity has been recorded yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Report Evidence Images</p>
                            <a href="{{ route('brands-platform.brand-gallery', $brand->slug ?: $brand->id) }}" class="text-[10px] font-bold uppercase tracking-wider text-brand-white/50 hover:text-brand-white">Open Gallery</a>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @forelse($reportImages as $activity)
                                <article class="overflow-hidden rounded-md border border-brand-white/10 bg-brand-black/35">
                                    <img src="{{ \App\Http\Controllers\Brands\BrandsPlatformController::storageUrl($activity->evidence_path) }}" alt="{{ $activity->brand?->name }} evidence" class="aspect-[4/3] w-full object-cover" loading="lazy">
                                    <div class="p-3 text-xs text-brand-white/60">
                                        <p class="font-semibold text-brand-white">{{ $activity->location ?: 'No location' }}</p>
                                        <p>{{ \Illuminate\Support\Str::headline($activity->activity_type) }} - {{ $activity->created_at?->format('M d, H:i') }}</p>
                                    </div>
                                </article>
                            @empty
                                <p class="text-sm text-brand-white/40 sm:col-span-2">No presentation evidence images have been uploaded yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Recent Transactions</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                            <tr><th class="px-3 py-2">Time</th><th class="px-3 py-2">Staff</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Activity</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Location</th><th class="px-3 py-2">Units</th><th class="px-3 py-2">Conversions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                            @forelse($recentActivities as $activity)
                                <tr>
                                    <td class="px-3 py-3">{{ $activity->created_at?->format('M d, H:i') }}</td>
                                    <td class="px-3 py-3">{{ $activity->user?->name ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->staff_role) }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->activity_type) }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($activity->status) }}</td>
                                    <td class="px-3 py-3">{{ $activity->location ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ number_format($activity->units) }}</td>
                                    <td class="px-3 py-3">{{ number_format($activity->conversion_count) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-3 py-8 text-center text-brand-white/40">No activity has been recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $recentActivities->links() }}</div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const chartPayload = {
                trend: {
                    labels: @json($consumerTrend['labels']),
                    consumers: @json($consumerTrend['data']),
                    activities: @json($activityTrend['data']),
                },
                funnel: {
                    labels: ['Target', 'Reached', 'Verified', 'Conversions'],
                    data: [{{ (int) $metrics['target'] }}, {{ (int) $metrics['reached'] }}, {{ (int) $metrics['verified_entries'] }}, {{ (int) $metrics['conversions'] }}],
                },
                demographics: {
                    labels: @json($entriesByGender->pluck('label')->values()),
                    data: @json($entriesByGender->pluck('total')->map(fn ($value) => (int) $value)->values()),
                },
                locations: {
                    labels: @json($locationPerformance->pluck('label')->values()),
                    units: @json($locationPerformance->pluck('units')->map(fn ($value) => (int) $value)->values()),
                    conversions: @json($locationPerformance->pluck('conversions')->map(fn ($value) => (int) $value)->values()),
                },
                leaderboard: {
                    labels: @json($leaderboard->map(fn ($row) => $row->user?->name ?: 'Unassigned')->values()),
                    units: @json($leaderboard->pluck('units')->map(fn ($value) => (int) $value)->values()),
                    conversions: @json($leaderboard->pluck('conversions')->map(fn ($value) => (int) $value)->values()),
                },
            };

            const loadChart = () => new Promise((resolve) => {
                if (window.Chart) {
                    resolve();
                    return;
                }

                const existing = document.querySelector('script[data-chart-js]');
                if (existing) {
                    existing.addEventListener('load', resolve, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.defer = true;
                script.dataset.chartJs = 'true';
                script.onload = resolve;
                document.head.appendChild(script);
            });

            const chartData = (labels, data, fallbackLabel = 'No data') => ({
                labels: labels.length ? labels : [fallbackLabel],
                data: data.length ? data : [0],
            });

            const ctx = (id) => document.getElementById(id);
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: 'rgba(255,255,255,.72)' } },
                },
                scales: {
                    x: { ticks: { color: 'rgba(255,255,255,.55)' }, grid: { color: 'rgba(255,255,255,.08)' } },
                    y: { beginAtZero: true, ticks: { color: 'rgba(255,255,255,.55)' }, grid: { color: 'rgba(255,255,255,.08)' } },
                },
            };

            loadChart().then(() => {
                Chart.defaults.color = 'rgba(255,255,255,.72)';
                Chart.defaults.borderColor = 'rgba(255,255,255,.1)';

                const trendLabels = chartPayload.trend.labels.length ? chartPayload.trend.labels : ['No data'];
                const consumerTrend = chartPayload.trend.consumers.length ? chartPayload.trend.consumers : [0];
                const activityTrend = chartPayload.trend.activities.length ? chartPayload.trend.activities : [0];

                if (ctx('brandActivationTrendChart')) {
                    new Chart(ctx('brandActivationTrendChart'), {
                        type: 'line',
                        data: {
                            labels: trendLabels,
                            datasets: [
                                { label: 'Consumers', data: consumerTrend, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.18)', tension: .35, fill: true },
                                { label: 'Field Updates', data: activityTrend, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,.12)', tension: .35, fill: true },
                            ],
                        },
                        options: commonOptions,
                    });
                }

                if (ctx('brandActivationFunnelChart')) {
                    new Chart(ctx('brandActivationFunnelChart'), {
                        type: 'bar',
                        data: {
                            labels: chartPayload.funnel.labels,
                            datasets: [{ label: 'Activation Funnel', data: chartPayload.funnel.data, backgroundColor: ['#991b1b', '#ef4444', '#22c55e', '#a78bfa'] }],
                        },
                        options: commonOptions,
                    });
                }

                const demographics = chartData(chartPayload.demographics.labels, chartPayload.demographics.data);
                if (ctx('brandDemographicChart')) {
                    new Chart(ctx('brandDemographicChart'), {
                        type: 'doughnut',
                        data: { labels: demographics.labels, datasets: [{ data: demographics.data, backgroundColor: ['#ef4444', '#22d3ee', '#a78bfa', '#f59e0b', '#22c55e'] }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: 'rgba(255,255,255,.72)' } } } },
                    });
                }

                const locations = chartData(chartPayload.locations.labels, chartPayload.locations.units);
                if (ctx('brandLocationChart')) {
                    new Chart(ctx('brandLocationChart'), {
                        type: 'bar',
                        data: {
                            labels: locations.labels,
                            datasets: [
                                { label: 'Units', data: locations.data, backgroundColor: 'rgba(239,68,68,.75)' },
                                { label: 'Conversions', data: chartPayload.locations.conversions.length ? chartPayload.locations.conversions : [0], backgroundColor: 'rgba(34,197,94,.75)' },
                            ],
                        },
                        options: { ...commonOptions, indexAxis: 'y' },
                    });
                }

                const leaderboard = chartData(chartPayload.leaderboard.labels, chartPayload.leaderboard.units);
                if (ctx('brandLeaderboardChart')) {
                    new Chart(ctx('brandLeaderboardChart'), {
                        type: 'bar',
                        data: {
                            labels: leaderboard.labels,
                            datasets: [
                                { label: 'Units', data: leaderboard.data, backgroundColor: 'rgba(239,68,68,.75)' },
                                { label: 'Conversions', data: chartPayload.leaderboard.conversions.length ? chartPayload.leaderboard.conversions : [0], backgroundColor: 'rgba(167,139,250,.75)' },
                            ],
                        },
                        options: { ...commonOptions, indexAxis: 'y' },
                    });
                }
            });
        })();
    </script>
@endpush
