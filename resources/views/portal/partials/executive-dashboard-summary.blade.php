@if(auth()->user()->isCvoOrSuperAdmin())
@php($executiveQueues=app(\App\Services\ExecutiveDashboardSummary::class)->build(auth()->user()))
@php($activeExecutiveQueues=collect($executiveQueues)->filter(fn($queue)=>$queue['count']>0))
<section class="glass-panel rounded-2xl p-5 sm:p-6 mb-6" aria-label="Executive Review Queues">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div><h2 class="text-xl font-semibold">Executive Reviews</h2><p class="text-sm text-brand-ash mt-1">Your approval queues, alongside every department’s weekly results below.</p></div>
        <a href="#staff-weekly-performance" class="text-sm underline underline-offset-4">View Department Performance</a>
    </div>
    @if($activeExecutiveQueues->isEmpty())<p class="text-sm text-brand-ash">Your review queues are clear. Department reports and management tools remain available below.</p>@endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($activeExecutiveQueues as $queue)
        <a href="{{ route($queue['route']) }}" class="block rounded-xl border border-brand-white/10 p-4 hover:bg-brand-white/5 transition">
            <span class="text-sm font-semibold">{{ $queue['label'] }}</span>
            <strong class="block text-3xl mt-2">{{ number_format($queue['count']) }}</strong>
            <span class="block text-xs text-brand-ash mt-2">{{ $queue['description'] }}</span>
        </a>
        @endforeach
    </div>
    <details class="mt-5"><summary class="cursor-pointer text-sm font-semibold">Management Tools</summary>
        <nav class="flex flex-wrap gap-3 mt-3 text-sm" aria-label="Executive Management Tools">
            @foreach($executiveQueues as $queue)<a class="rounded-lg border border-brand-white/10 px-3 py-2" href="{{ route($queue['route']) }}">{{ $queue['label'] }} · {{ $queue['count'] }}</a>@endforeach
            @foreach(['portal.people'=>'People Management','portal.hr'=>'HR & Admin','portal.finance'=>'Finance','portal.appraisals.index'=>'Appraisals','portal.leaves'=>'Leave Management'] as $route=>$label)
            <a class="rounded-lg border border-brand-white/10 px-3 py-2 hover:bg-brand-white/5" href="{{ route($route) }}">{{ $label }}</a>
            @endforeach
            @if(auth()->user()->hasRole(['super_admin','admin']))
            <a class="rounded-lg border border-brand-white/10 px-3 py-2 hover:bg-brand-white/5" href="{{ route('admin.settings') }}">System Settings</a>
            @endif
        </nav>
    </details>
    @php($executiveLocations=app(\App\Services\ExecutiveDashboardSummary::class)->locations(auth()->user()))
    <details class="mt-5">
        <summary class="cursor-pointer text-sm font-semibold">Recorded Check-In Locations <span class="text-brand-ash">({{ count($executiveLocations) }})</span></summary>
        <p class="text-xs text-brand-ash mt-3 mb-3">Latest 30 GPS check-ins. Locations reflect the recorded check-in, not a live staff position. Opening a map shares that location with the map provider.</p>
        <div class="overflow-x-auto rounded-xl border border-brand-white/10">
            <table class="w-full text-sm" style="min-width:620px">
                <thead><tr class="text-left"><th class="p-3">Staff</th><th class="p-3">Department</th><th class="p-3 whitespace-nowrap">Clock-In</th><th class="p-3">Status</th><th class="p-3 whitespace-nowrap">Recorded Location</th></tr></thead>
                <tbody>@forelse($executiveLocations as $location)
                    <tr class="border-t border-brand-white/10">
                        <td class="p-3">{{ $location['staff'] }}</td><td class="p-3">{{ $location['department'] }}</td><td class="p-3 whitespace-nowrap">{{ $location['at'] }}</td><td class="p-3">{{ $location['status'] }}</td>
                        <td class="p-3"><a class="underline underline-offset-4" data-confirm="Open this recorded location in OpenStreetMap? The coordinates will be shared with the map provider." href="https://www.openstreetmap.org/?mlat={{ $location['latitude'] }}&amp;mlon={{ $location['longitude'] }}#map=16/{{ $location['latitude'] }}/{{ $location['longitude'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Open Recorded Location</a></td>
                    </tr>
                @empty<tr><td colspan="5" class="p-4 text-brand-ash">No recorded GPS check-ins.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </details>
</section>
@endif
