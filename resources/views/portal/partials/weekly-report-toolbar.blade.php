@php
$mode=request('report_period','week');
$anchor=\Carbon\CarbonImmutable::parse(request('report_date'));
$current=match($mode){'month'=>now()->startOfMonth(),'year'=>now()->startOfYear(),default=>now()->startOfWeek()};
$weekAnchor=\Carbon\CarbonImmutable::parse(request('weekly_from'))->startOfWeek();
$currentWeek=\Carbon\CarbonImmutable::now()->startOfWeek();
$weekOptions=collect([$currentWeek,$weekAnchor])->merge($savedWeeks)->map(fn($d)=>\Carbon\CarbonImmutable::parse($d)->startOfWeek())->unique(fn($d)=>$d->toDateString())->sortDesc();
if ($weeklyDepartmentFilter === 'operations_projects') {
    $operationWeeks = \App\Models\PerformanceRegister::query()->whereIn('department', \App\Models\User::departmentAliases('operations_projects'))
        ->whereNotNull('approved_at')->distinct()->orderByDesc('period_start')->limit(156)->pluck('period_start');
    $weekOptions = $weekOptions->merge($operationWeeks)->map(fn ($date) => \Carbon\CarbonImmutable::parse($date)->startOfWeek())->unique(fn ($date) => $date->toDateString())->sortDesc();
}
$weekState=$anchor->isSameDay($current)?'Current '.$mode:($anchor->lt($current)?'Historical '.$mode:'Upcoming '.$mode);
$periodParams=request()->except(['weekly_page','crm_page','brands_page','hr_page','report_date','report_period','weekly_from','weekly_to','brands_date','brands_period','hr_from','hr_to','hr_period','attendance_date','attendance_period','revenue_date','revenue_period','reporting_summary_period']);
$periodParams = array_filter($periodParams, fn ($key) => ! preg_match('/^ops_(?:.*_page|edit|add|review)$/', $key), ARRAY_FILTER_USE_KEY);
$periodUrl=fn($date,$period=null)=>route('dashboard',array_merge($periodParams,['weekly_department'=>$weeklyDepartmentFilter,'report_period'=>$period??$mode,'report_date'=>$date])).'#staff-weekly-performance';
@endphp
<div class="dashboard-week-toolbar mt-4 rounded-xl border border-brand-white/20 p-4" aria-label="Department reporting period">
<p class="text-sm font-semibold">{{ $weeklyConsolidatedDepartments[$weeklyDepartmentFilter]??'All Departments' }} · {{ $weekState }}</p>
<p class="mt-1">{{ \Carbon\Carbon::parse(request('weekly_from'))->format('d M Y') }}–{{ \Carbon\Carbon::parse(request('weekly_to'))->format('d M Y') }}</p>
<form method="GET" action="{{ route('dashboard') }}#staff-weekly-performance" data-no-ajax data-no-silent class="mt-3 flex flex-wrap items-end gap-3">
@foreach($periodParams as $name=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
<label>Reporting period<select name="report_period" class="mt-1 block rounded-lg bg-brand-black p-3">@foreach(['week'=>'Week','month'=>'Month','year'=>'Year'] as $value=>$label)<option value="{{ $value }}" @selected($mode===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Date in period<input type="date" name="report_date" value="{{ $anchor->toDateString() }}" required class="mt-1 block rounded-lg bg-brand-black p-3"></label>
<button class="rounded-lg border border-brand-white/20 px-4 py-3">Apply reporting period</button>
<label>Older weekly reports<select aria-label="Choose a saved reporting week" onchange="if(this.value)location.assign(this.value)" class="mt-1 block rounded-lg bg-brand-black p-3"><option value="">Choose a week…</option>@foreach($weekOptions as $week)<option value="{{ $periodUrl($week->toDateString(),'week') }}" @selected($mode==='week'&&$weekAnchor->eq($week))>{{ $week->format('d M') }}–{{ $week->endOfWeek()->format('d M Y') }}{{ $week->eq($currentWeek)?' · This week':'' }}</option>@endforeach</select></label>
</form>
<nav class="mt-3 flex flex-wrap gap-2" aria-label="Department reporting period navigation">
@foreach([-1=>'Previous',0=>'Current',1=>'Next'] as $offset=>$label)
@php($target=$offset===0?$current:match($mode){'month'=>$anchor->addMonths($offset),'year'=>$anchor->addYears($offset),default=>$anchor->addWeeks($offset)})
<a class="rounded-lg border border-brand-white/20 px-4 py-2 text-sm" href="{{ $periodUrl($target->toDateString()) }}">{{ $label }} {{ ucfirst($mode) }}</a>
@endforeach
</nav>
<p class="mt-3 text-xs text-brand-ash">All departmental reporting sections use this period. Weekly reports belong to the month and year in which their reporting week starts. Daily tasks have their own date filter.</p>
</div>
