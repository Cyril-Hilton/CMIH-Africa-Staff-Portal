@include('portal.partials.department-workspace-styles')
@php($initialPerformanceDepartment = $weeklyDepartmentFilter === 'all' ? 'all' : (array_key_exists($weeklyDepartmentFilter, $sharedWeeklyPerformance) ? $weeklyDepartmentFilter : array_key_first($sharedWeeklyPerformance)))
<section x-data="{ department: @js($initialPerformanceDepartment) }" id="staff-weekly-performance" class="mt-6 space-y-4" aria-label="Staff-wide departmental performance">

@if($initialPerformanceDepartment==='all')
<div role="tabpanel" id="performance-panel-all" aria-labelledby="performance-tab-all" class="grid gap-3 sm:grid-cols-2">
@foreach($sharedWeeklyPerformance as $overviewKey=>$overview)
<a class="rounded-xl border border-brand-white/20 p-4 text-brand-white" href="{{ route('dashboard',array_merge(request()->only(['weekly_from','weekly_to']),['weekly_department'=>$overviewKey])) }}#staff-weekly-performance"><strong>{{ $overview['label'] }}</strong><p class="mt-2 text-sm text-brand-ash">{{ $overview['summary']['verified'] }} independently verified deliveries · {{ $overview['summary']['overdue'] }} overdue verified-workflow commitments</p><p class="mt-3 text-sm underline">Open weekly reports & activities</p></a>
@endforeach
</div>
@endif
@foreach($sharedWeeklyPerformance as $key=>$department)
@continue($key !== $initialPerformanceDepartment)
<div role="tabpanel" data-department="{{ $key }}" id="performance-panel-{{ $key }}" aria-labelledby="performance-tab-{{ $key }}" x-show="department === @js($key)" @if($key !== $initialPerformanceDepartment) style="display: none" @endif class="rounded-2xl border border-brand-white/15 bg-brand-white/5 p-4"><div class="flex cursor-pointer flex-wrap items-center justify-between gap-3 text-brand-white"><strong>{{ $department['label'] }}</strong><span class="text-xs text-brand-ash">
@if($key === 'creatives')
Live Creative Workflow & Evidence
@else
{{ \Carbon\Carbon::parse(request('weekly_from'))->format('d M') }}–{{ \Carbon\Carbon::parse(request('weekly_to'))->format('d M Y') }}
@endif
</span></div>
@if($key === 'creatives')
@include('portal.partials.weekly-report-summary')
@include('portal.creative.workspace',['embedded'=>true])
@elseif($key === 'client_relations')
@include('portal.partials.weekly-report-summary')
@include('portal.client-relations.public-weekly')
@include('portal.client-relations.kpi-scorecards')
@else
@include('portal.partials.department-workspace')
@endif
</div>@endforeach
</section>
