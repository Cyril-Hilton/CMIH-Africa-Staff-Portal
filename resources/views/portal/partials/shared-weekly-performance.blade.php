@php($initialPerformanceDepartment = array_key_exists($weeklyDepartmentFilter, $sharedWeeklyPerformance) ? $weeklyDepartmentFilter : array_key_first($sharedWeeklyPerformance))
<section x-data="{ department: @js($initialPerformanceDepartment), init() {
const storageKey = 'cmih.sharedWeeklyTab.{{ auth()->id() }}';
if (@js($weeklyDepartmentFilter) === 'all') {
try { const saved = sessionStorage.getItem(storageKey); if (@js(array_keys($sharedWeeklyPerformance)).includes(saved)) this.department = saved; } catch (_) {}
}
this.$watch('department', value => { try { sessionStorage.setItem(storageKey, value); } catch (_) {} });
} }" id="staff-weekly-performance" class="mt-6 space-y-4" aria-label="Staff-wide departmental performance">
<div class="rounded-2xl border border-sky-400/20 bg-sky-400/5 p-5"><h3 class="text-xl font-semibold text-brand-white">Weekly Departmental Performance</h3><p class="mt-2 text-sm text-brand-ash">View each department’s targets, progress and results.</p></div>
<div role="tablist" aria-label="Weekly performance departments" class="flex flex-wrap gap-2 border-b border-brand-white/10 pb-3">
@foreach($sharedWeeklyPerformance as $key=>$department)
<button type="button" role="tab" id="performance-tab-{{ $key }}" aria-controls="performance-panel-{{ $key }}" :aria-selected="department === @js($key)" @click="department = @js($key)" @keydown.right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()" @keydown.left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()" class="shrink-0 whitespace-nowrap rounded-xl border px-4 py-3 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-300" :class="department === @js($key) ? 'border-sky-300/60 bg-sky-400/15 text-sky-100' : 'border-brand-white/15 text-brand-ash hover:text-brand-white'">{{ $department['label'] }}</button>
@endforeach
</div>
@foreach($sharedWeeklyPerformance as $key=>$department)
<div role="tabpanel" id="performance-panel-{{ $key }}" aria-labelledby="performance-tab-{{ $key }}" x-show="department === @js($key)" @if($key !== $initialPerformanceDepartment) style="display: none" @endif class="rounded-2xl border border-brand-white/15 bg-brand-white/5 p-4"><div class="flex cursor-pointer flex-wrap items-center justify-between gap-3 text-brand-white"><strong>{{ $department['label'] }}</strong><span class="text-xs text-brand-ash">
@if($key === 'creatives')
Live Creative Workflow & Evidence
@else
{{ $department['summary']['verified'] }} / {{ $department['summary']['total'] }} deliverables verified · {{ $department['summary']['overdue'] }} overdue
@endif
</span></div>
@if($key === 'creatives')
@include('portal.creative.workspace',['embedded'=>true])
@else
@if($key === 'client_relations')
@include('portal.client-relations.public-weekly')
@endif
@if(isset($weeklyDepartmentTables[$key]))
@include('portal.partials.weekly-department-tables', ['weeklyDepartmentTables'=>[$key=>$weeklyDepartmentTables[$key]]])
@endif
@if(isset($department['people_reporting']))
<div class="mt-4 grid min-w-0 gap-4">
@foreach(['hr'=>'HR Reporting','administrator'=>'Administrator Reporting'] as $reportKey=>$reportTitle)
<details class="min-w-0 rounded-xl border border-brand-white/15 p-4"><summary class="cursor-pointer font-semibold text-brand-white">{{ $reportTitle }}</summary><p class="mt-2 text-xs text-brand-ash">Approved weekly totals for the selected period.</p>
<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-xs text-brand-white"><thead><tr>@foreach(['Period','Metric','Result'] as $heading)<th class="whitespace-nowrap border-b border-brand-white/15 p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($department['people_reporting'][$reportKey] as $metric)<tr><td class="whitespace-nowrap p-3">{{ $metric['period'] }}</td><td class="p-3">{{ $metric['metric'] }}</td><td class="whitespace-nowrap p-3">{{ $metric['actual']===null?'Not Recorded':$metric['actual'].' '.$metric['unit'] }}</td></tr>@empty<tr><td colspan="3" class="p-3 text-brand-ash">No approved weekly figures for this period.</td></tr>@endforelse</tbody></table></div></details>
@endforeach
</div>
@endif
<div class="mt-4 overflow-x-auto"><table class="w-full text-left text-xs text-brand-white"><thead><tr>@foreach(['KPI / Scope','Client / Project','Owner','Reporting Period','Agreed Target','Approved Result','Achievement','Gap vs Target','Performance'] as $heading)<th class="min-w-[8rem] border-b border-brand-white/20 p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($department['kpis'] as $row)<tr><td class="border-b border-brand-white/10 p-3">{{ $row['name'] }}<span class="mt-1 block text-brand-ash">{{ ucfirst($row['scope']) }}</span></td><td class="p-3">{{ $row['client']??'Department-wide' }}<span class="block">{{ $row['project'] }}</span></td><td class="p-3">{{ $row['owner'] }}</td><td class="p-3">{{ $row['period'] }}</td><td class="p-3">{{ $row['target'] }}</td><td class="p-3">{{ $row['actual'] }}</td><td class="p-3">{{ $row['achievement']===null?'Not measured':$row['achievement'].'%' }}</td><td class="p-3">{{ $row['gap']===null?'Not measured':sprintf('%+.1f pp',$row['gap']) }}</td><td class="p-3"><span class="rounded-lg border px-2 py-1 {{ $row['rag']==='red'?'border-red-400/40 text-red-300':($row['rag']==='amber'?'border-amber-400/40 text-amber-200':($row['rag']==='green'?'border-emerald-400/40 text-emerald-200':'border-brand-white/20 text-brand-ash')) }}">{{ strtoupper($row['rag']) }}</span></td></tr>@empty<tr><td colspan="9" class="p-4 text-brand-ash">No approved period targets recorded. Missing reporting is visible, not assumed successful.</td></tr>@endforelse</tbody></table></div>
<details class="mt-4"><summary class="cursor-pointer text-sm font-semibold text-brand-white">Clients, Projects & Deliverable Performance</summary><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-xs text-brand-white"><thead><tr>@foreach(['Reference / Project','Client','Owner','Deadline','Workflow','Progress','Verified Quality','Cause / Delay'] as $heading)<th class="min-w-[8rem] border-b border-brand-white/20 p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($department['deliverables'] as $row)<tr><td class="p-3">WCT-{{ $row['id'] }} · {{ $row['project'] }}</td><td class="p-3">{{ $row['client']??'Internal' }}</td><td class="p-3">{{ $row['owner'] }}</td><td class="p-3">{{ $row['due']??'Not recorded' }}</td><td class="p-3">{{ ucfirst($row['state']) }}{{ $row['overdue']?' · OVERDUE':'' }}</td><td class="p-3">{{ $row['progress']??0 }}%</td><td class="p-3">{{ $row['quality']===null?'Not verified':$row['quality'].'%' }}</td><td class="p-3">{{ $row['root_cause']?ucwords(str_replace('_',' ',$row['root_cause'])):'No cause recorded' }}</td></tr>@empty<tr><td colspan="8" class="p-4 text-brand-ash">No governed deliverables recorded for this selection.</td></tr>@endforelse</tbody></table></div></details>
@endif
</div>@endforeach
</section>
