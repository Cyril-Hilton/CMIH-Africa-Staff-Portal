@if($sourcePerformance && $sourcePerformance['department']==='brands_marketing')
@include('portal.partials.brands-source-performance')
@elseif($sourcePerformance)
<section class="my-4 rounded-2xl border border-amber-300/25 bg-brand-black/25 p-5" aria-label="Automatic department KPI reporting" data-source-department="{{ $sourcePerformance['department'] }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h4 class="text-lg font-semibold">KPI Achievement & Reporting Gaps</h4><p class="mt-1 text-xs text-brand-ash">{{ ($sourcePerformance['native_sources']??false)?'Live portal records & departmental results':'Published department results' }} · {{ $sourcePerformance['provisional'] ? 'Current period · provisional' : 'Selected historical period' }}</p></div>
        <div class="text-right"><strong class="text-2xl">{{ $sourcePerformance['achievement_credit'] }} / 100</strong><p class="text-xs text-brand-ash">Business KPI credit · equal area shares</p><p class="mt-2 text-xs text-brand-ash">{{ ($sourcePerformance['native_sources']??false)?'Areas with source evidence:':'Reports submitted:' }} {{ $sourcePerformance['reported'] }} / {{ $sourcePerformance['expected'] }} areas · {{ $sourcePerformance['coverage'] ?? 0 }}% coverage</p></div>
    </div>
    <p class="mt-3 text-xs text-brand-ash">{{ $sourcePerformance['scoring_note'] }} Reporting coverage is not a performance score. @if($sourcePerformance['native_sources']??false) HR attendance coverage uses company-wide clock-in records; the attendance breakdown below is for HR/Admin staff. @else Attendance and punctuality are measured separately below; @endif punctuality contributes 10% to the overall score.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($sourcePerformance['areas'] as $area)
            <div class="rounded-xl border {{ $area['achievement_credit']>=100 ? 'border-emerald-300/25 bg-emerald-300/5' : 'border-amber-300/30 bg-amber-300/5' }} p-3">
                <p class="text-sm font-semibold">{{ $area['name'] }}</p><p class="mt-2 text-lg font-semibold">{{ $area['achievement_credit'] }} / 100 <span class="text-xs font-normal text-brand-ash">achievement credit</span></p>
                <p class="mt-1 text-xs text-brand-ash">{{ $area['state'] }} · {{ $area['records'] }} source records</p>
                @if($area['result_gap'])<p class="mt-2 text-xs text-amber-200">{{ $area['result_gap'] }}</p>@endif
            </div>
        @endforeach
    </div>
    @php($attendance=$sourcePerformance['attendance'])
    <div class="mt-4 rounded-xl border border-brand-white/15 p-3 text-xs" aria-label="Recorded attendance results">
        <p class="font-semibold">Attendance · actual clock-in timestamps and eligible working days</p>
        <p class="mt-2">{{ $attendance['clocked'] }} / {{ $attendance['expected'] }} eligible staff-days clocked · {{ round($attendance['attendance_credit'],1) }}% attendance · {{ $attendance['punctual'] }} on time · {{ $attendance['late'] }} late · {{ $attendance['missing'] }} missing clock-ins</p>
        <p class="mt-1 text-brand-ash">Punctuality: {{ round($attendance['punctuality_credit'],1) }}%. Weekends, company holidays, future days and approved leave are excluded. No eligible days earns no attendance credit.</p>
    </div>
    @if(count($sourcePerformance['metrics']))
        <div class="mt-4 overflow-auto"><table class="w-full text-left text-xs"><thead><tr class="border-b border-brand-white/15"><th class="p-2">Source KPI</th><th class="p-2">Achievement credit</th><th class="p-2">Result coverage</th><th class="p-2">Calculation / follow-up</th></tr></thead><tbody>
        @foreach($sourcePerformance['metrics'] as $metric)<tr class="border-b border-brand-white/10"><th class="p-2 font-semibold">{{ $metric['name'] }}</th><td class="p-2">{{ $metric['credited'] }}% @if($metric['achievement']===null)<span class="block text-amber-200">Target / baseline needs setup</span>@endif</td><td class="p-2">{{ $metric['reported'] }} / {{ $metric['expected'] }} results reported</td><td class="p-2"><strong>{{ $metric['state'] }}</strong><p class="mt-1 text-brand-ash">{{ $metric['note'] }}</p></td></tr>@endforeach
        </tbody></table></div>
    @endif
</section>
@endif
