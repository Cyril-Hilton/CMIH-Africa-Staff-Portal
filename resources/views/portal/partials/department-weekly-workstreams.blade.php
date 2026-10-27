@php
$canReadDepartmentHistory=$key==='hr_admin'?auth()->user()->hasFullHrAccess():($key!=='finance'||auth()->user()->isCvoOrSuperAdmin()||\App\Models\User::normalizeDepartmentKey(auth()->user()->department)==='finance');
$weeklyTitles=['operations_projects'=>'Project Updates','finance'=>'Weekly Finance Updates','hr_admin'=>'People & Administration Updates','client_relations'=>'Weekly Client Service Updates'];
@endphp
<div class="dw-heading"><div><h5>{{ $weeklyTitles[$key] }}</h5><p class="dw-meta">Saved reports with their original owners, date ranges, progress and formatted content. Reports spanning several weeks remain visible while their saved dates overlap this selection.</p></div><span class="dw-status">{{ $savedWeeklyCount }} Saved Entries</span></div>
@if(!request('weekly_from') && !request('weekly_to'))<p class="dw-meta mb-4">Showing saved weekly updates across all reporting periods. Select a week above to narrow this view.</p>@endif
@if($canReadDepartmentHistory)
@php
$departmentEntries=app(\App\Services\DepartmentWeeklyPresentation::class)->build($key,request('weekly_from'),request('weekly_to'),['q'=>request('department_entry_search'),'status'=>request('department_entry_status'),'sort'=>request('department_entry_sort')]);
@endphp
<form method="GET" action="{{ isset($moduleDepartment)?url()->current():route('dashboard').'#staff-weekly-performance' }}" data-no-ajax class="mb-5 grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-4">
@if(isset($moduleDepartment))<input type="hidden" name="module_view" value="weekly">@endif<input type="hidden" name="weekly_department" value="{{ $key }}">@if($key==='client_relations')<input type="hidden" name="client_reporting_view" value="updates">@endif
@foreach(['weekly_from','weekly_to'] as $filter)@if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif @endforeach
<label class="text-xs text-brand-ash">Search Weekly Updates<input type="search" name="department_entry_search" value="{{ request('department_entry_search') }}" maxlength="200" class="mt-2 w-full min-w-0 rounded-lg border border-brand-white/15 bg-brand-black/30 p-3 text-brand-white" placeholder="Project, activity, lead or report content"></label>
<label class="text-xs text-brand-ash">Work Status<select name="department_entry_status" class="mt-2 w-full min-w-0 rounded-lg border border-brand-white/15 bg-brand-black/30 p-3 text-brand-white"><option value="">All Statuses</option>@foreach(['Planned','In Progress','Done','Blocked','Deferred'] as $status)<option @selected(request('department_entry_status')===$status)>{{ $status }}</option>@endforeach</select></label>
<label class="text-xs text-brand-ash">Sort Updates<select name="department_entry_sort" class="mt-2 w-full min-w-0 rounded-lg border border-brand-white/15 bg-brand-black/30 p-3 text-brand-white">@foreach(['newest'=>'Newest Week','oldest'=>'Oldest Week','title'=>'Project / Activity A–Z'] as $value=>$label)<option value="{{ $value }}" @selected(request('department_entry_sort','newest')===$value)>{{ $label }}</option>@endforeach</select></label>
<div class="flex flex-wrap items-end gap-3"><button class="dw-link">Apply Filters</button><a class="py-3 text-xs underline" href="{{ isset($moduleDepartment)?url()->current().'?'.http_build_query(['module_view'=>'weekly','weekly_from'=>request('weekly_from'),'weekly_to'=>request('weekly_to')]):route('dashboard',array_filter(['weekly_department'=>$key,'client_reporting_view'=>$key==='client_relations'?'updates':null,'weekly_from'=>request('weekly_from'),'weekly_to'=>request('weekly_to')])).'#staff-weekly-performance' }}">Reset</a></div>
</form>
@forelse($departmentEntries as $saved)
<article class="mb-5 min-w-0 rounded-xl border border-brand-white/20 p-4 sm:p-5" data-saved-weekly="{{ $saved->id }}">
<header class="flex min-w-0 flex-wrap items-start justify-between gap-3"><div class="min-w-0 flex-1"><p class="dw-meta !mt-0">WCT-{{ $saved->id }} · {{ $saved->week_start->format('d M Y') }}–{{ $saved->week_end->format('d M Y') }}</p><h6 class="mt-2 break-words text-lg font-semibold">{{ $saved->campaign_name ?: ($saved->client_name ?: 'Weekly Department Update') }}</h6>@if($saved->client_name && $saved->campaign_name)<p class="dw-meta">{{ $saved->client_name }}</p>@endif</div><span class="dw-status">{{ $saved->status }}</span></header>
<div class="mt-4 grid min-w-0 gap-4 border-y border-brand-white/10 py-3 text-xs sm:grid-cols-2"><section class="min-w-0"><h6 class="font-semibold">Responsibilities</h6><p class="dw-meta">Lead: {{ $saved->leadStaff?->name ?? 'Not recorded' }}<br>Prepared by: {{ $saved->creator?->name ?? 'Retained staff history' }}</p>@foreach($saved->supporting_staff_with_roles as $person)<p class="dw-meta">{{ $person->name }}@if($person->weekly_role) · {{ $person->weekly_role }}@endif</p>@endforeach</section><section class="min-w-0"><h6 class="font-semibold">Report Accountability</h6><p class="dw-meta">{{ ucwords(str_replace('_',' ',$saved->report_status ?: 'legacy_unreviewed')) }}@if($saved->project_ends_on)<br>Project timeline: {{ $saved->project_starts_on?->format('d M Y') ?? 'Start not recorded' }}–{{ $saved->project_ends_on->format('d M Y') }}@endif</p><p class="dw-meta">Work status is team-reported. Independent verification is shown in KPI Results.</p></section></div>
<div class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
@foreach(['deliverables'=>($key==='operations_projects'?'Execution & Project Progress':($key==='hr_admin'?'People & Workplace Progress':($key==='client_relations'?'Client Service Progress':'Processing & Finance Progress'))),'target_breakdown'=>'Responsibilities & KPI Commitments','achieved_breakdown'=>'Reported Results','gap_breakdown'=>'Gaps & Follow-up','notes'=>'Notes & Next Actions'] as $field=>$label)
@if(trim((string)$saved->$field))<section class="min-w-0 rounded-lg border border-brand-white/10 p-3 {{ $field==='deliverables'?'lg:col-span-2':'' }}"><h6 class="text-sm font-semibold">{{ $label }}</h6><div class="weekly-rich-content mt-3 text-sm leading-7">{!! \App\Support\ReportRichText::html($saved->$field) !!}</div></section>@endif
@endforeach
</div>
@include('portal.partials.weekly-entry-extra-fields',['item'=>$saved])
@include('portal.partials.weekly-entry-actions',['item'=>$saved])
</article>
@empty<p class="dw-empty">No weekly updates match this selection. Choose another reporting week or clear the filters to see earlier entries.</p>@endforelse
<div class="mt-4">{{ $departmentEntries->links() }}</div>
@else
<p class="dw-empty">Detailed {{ $key==='finance'?'Finance':'HR' }} reports are available to authorised staff. Approved department results remain available in the other tabs.</p>
@endif
