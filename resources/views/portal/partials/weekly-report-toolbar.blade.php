@php
$weekAnchor=\Carbon\CarbonImmutable::parse(request('weekly_from'))->startOfWeek();
$currentWeek=\Carbon\CarbonImmutable::now()->startOfWeek();
$weekOptions=collect([$currentWeek,$weekAnchor])->merge($savedWeeks)->map(fn($d)=>\Carbon\CarbonImmutable::parse($d)->startOfWeek())->unique(fn($d)=>$d->toDateString())->sortDesc();
$weekState=$weekAnchor->eq($currentWeek)?'Current week':($weekAnchor->lt($currentWeek)?'Historical week':'Upcoming week');
@endphp
<div class="dashboard-week-toolbar mt-4 rounded-xl border border-brand-white/20 p-4" aria-label="Weekly report controls">
<div class="flex flex-wrap items-center justify-between gap-3">
<div><p class="text-sm font-semibold">{{ $weeklyConsolidatedDepartments[$weeklyDepartmentFilter]??'All Departments' }} · {{ $weekState }}</p><p class="mt-1 text-brand-white">{{ \Carbon\Carbon::parse(request('weekly_from'))->format('d M Y') }}–{{ \Carbon\Carbon::parse(request('weekly_to'))->format('d M Y') }}</p></div>
<label class="text-sm">Reporting Week<select data-week-picker class="mt-1 block w-full rounded-lg bg-brand-black p-3"><option value="">Choose a week…</option>@foreach($weekOptions as $week)<option value="{{ $week->toDateString() }}" data-end="{{ $week->endOfWeek()->toDateString() }}" @selected($weekAnchor->eq($week))>{{ $week->format('d M') }}–{{ $week->endOfWeek()->format('d M Y') }}{{ $week->eq($currentWeek)?' · This week':'' }}</option>@endforeach</select></label>
</div>
<nav class="mt-3 flex flex-wrap gap-2" aria-label="Department reporting week navigation">
@foreach([-1=>'Previous Week',0=>'This Week',1=>'Next Week'] as $offset=>$label)
@php($targetWeek=$offset===0?$currentWeek:$weekAnchor->addWeeks($offset))
<a class="rounded-lg border border-brand-white/20 px-4 py-2 text-sm text-brand-white" href="{{ route('dashboard',array_merge(request()->except(['weekly_auto','weekly_page','brands_page','crm_page','department_entry_search','brands_search','search_weekly']),['weekly_department'=>$weeklyDepartmentFilter,'weekly_from'=>$targetWeek->toDateString(),'weekly_to'=>$targetWeek->endOfWeek()->toDateString()])) }}#staff-weekly-performance">{{ $label }}</a>
@endforeach
</nav>
<p class="mt-3 text-xs text-brand-ash">{{ $weekAnchor->lt($currentWeek)?'Historical reporting period. Overdue figures use the selected period end; later corrections remain traceable.':'Figures below belong to this reporting period. Reported activities and independently verified deliveries are separate measures.' }}</p>
</div>
