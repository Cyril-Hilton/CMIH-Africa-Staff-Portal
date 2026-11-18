@if($sourcePerformance)
<section class="my-4 rounded-2xl border border-amber-300/25 bg-brand-black/25 p-5" aria-label="Automatic department KPI reporting">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h4 class="text-lg font-semibold">KPI Results & Reporting Gaps</h4><p class="mt-1 text-xs text-brand-ash">Calculated from published departmental records. {{ $sourcePerformance['provisional'] ? 'Current-period results are provisional.' : 'Historical reporting period.' }}</p></div><div class="text-right"><strong class="text-2xl">{{ $sourcePerformance['coverage']===null?'No reporting areas configured':$sourcePerformance['coverage'].'%' }}</strong><p class="text-xs text-brand-ash">Reporting coverage · {{ $sourcePerformance['reported'] }} / {{ $sourcePerformance['expected'] }} areas</p></div></div>
    <p class="mt-3 text-xs text-brand-ash">Reporting coverage measures whether each required area has evidence. Business KPI achievement below compares actual results with recorded targets. Missing reports receive no reporting credit; missing results receive no target-achievement credit.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($sourcePerformance['areas'] as $area)
            <div class="rounded-xl border {{ $area['reported'] ? 'border-emerald-300/25 bg-emerald-300/5' : 'border-amber-300/30 bg-amber-300/5' }} p-3"><p class="text-sm font-semibold">{{ $area['name'] }}</p><p class="mt-1 text-xs {{ $area['reported'] ? 'text-emerald-200' : 'text-amber-200' }}">{{ $area['state'] }} · {{ $area['records'] }} source records · {{ $area['score'] }}% reporting credit</p>@if($area['gap'])<p class="mt-2 text-xs text-brand-ash">{{ $area['gap'] }}</p>@endif</div>
        @endforeach
    </div>
    @if(count($sourcePerformance['metrics']))
        <div class="mt-4 overflow-auto"><table class="w-full text-left text-xs"><thead><tr class="border-b border-brand-white/15"><th class="p-2">Automatic source KPI</th><th class="p-2">Achievement</th><th class="p-2">Result coverage</th><th class="p-2">Status / calculation</th></tr></thead><tbody>
        @foreach($sourcePerformance['metrics'] as $metric)<tr class="border-b border-brand-white/10"><th class="p-2 font-semibold">{{ $metric['name'] }}</th><td class="p-2">{{ $metric['achievement']===null?'Target / baseline needed':$metric['achievement'].'%' }}</td><td class="p-2">{{ $metric['reported'] }} / {{ $metric['expected'] }} results reported</td><td class="p-2"><strong>{{ $metric['state'] }}</strong><p class="mt-1 text-brand-ash">{{ $metric['note'] }}</p></td></tr>@endforeach
        </tbody></table></div>
    @endif
</section>
@endif
