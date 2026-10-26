@include('portal.partials.department-workspace-styles')
@php($initialPerformanceDepartment = array_key_exists($weeklyDepartmentFilter, $sharedWeeklyPerformance) ? $weeklyDepartmentFilter : array_key_first($sharedWeeklyPerformance))
<section x-data="{ department: @js($initialPerformanceDepartment) }" id="staff-weekly-performance" class="mt-6 space-y-4" aria-label="Staff-wide departmental performance">
<div class="rounded-2xl border border-sky-400/20 bg-sky-400/5 p-5"><h3 class="text-xl font-semibold text-brand-white">Weekly Departmental Performance</h3><p class="mt-2 text-sm text-brand-ash">View each department’s targets, progress and results.</p></div>
<div role="tablist" aria-label="Weekly performance departments" class="flex flex-wrap gap-2 border-b border-brand-white/10 pb-3">
@foreach($sharedWeeklyPerformance as $key=>$department)
<button type="button" role="tab" id="performance-tab-{{ $key }}" aria-controls="performance-panel-{{ $key }}" :aria-selected="department === @js($key)" @click="window.location.href = @js(route('dashboard', array_merge(['weekly_department'=>$key], request('weekly_auto')==='1'?[]:request()->only(['weekly_from','weekly_to'])), false).'#staff-weekly-performance')" @keydown.right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()" @keydown.left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()" class="shrink-0 whitespace-nowrap rounded-xl border px-4 py-3 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-300" :class="department === @js($key) ? 'border-sky-300/60 bg-sky-400/15 text-sky-100' : 'border-brand-white/15 text-brand-ash hover:text-brand-white'">{{ $department['label'] }}</button>
@endforeach
</div>
@foreach($sharedWeeklyPerformance as $key=>$department)
@continue($key !== $initialPerformanceDepartment)
<div role="tabpanel" data-department="{{ $key }}" id="performance-panel-{{ $key }}" aria-labelledby="performance-tab-{{ $key }}" x-show="department === @js($key)" @if($key !== $initialPerformanceDepartment) style="display: none" @endif class="rounded-2xl border border-brand-white/15 bg-brand-white/5 p-4"><div class="flex cursor-pointer flex-wrap items-center justify-between gap-3 text-brand-white"><strong>{{ $department['label'] }}</strong><span class="text-xs text-brand-ash">
@if($key === 'creatives')
Live Creative Workflow & Evidence
@else
{{ $department['summary']['verified'] }} / {{ $department['summary']['total'] }} deliverables verified · {{ $department['summary']['overdue'] }} overdue
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
