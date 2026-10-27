@php
$sourceSections=app(\App\Services\SharedModuleActivity::class)->build($sourceDepartment,$sourceFrom,$sourceTo);
@endphp
<section id="module-source-activity-{{ $sourceModuleView??(isset($moduleDepartment)?'weekly':'shared') }}" class="min-w-0 my-5" aria-label="Shared module activity">
<h5 class="text-lg font-semibold">Module Activity</h5><p class="module-muted text-sm text-brand-ash mt-2">{{ $sourceFrom }}–{{ $sourceTo }} · Movements and processing records created in this period; projects overlap the selected dates. Operational records are reported activity, not verified KPI results.</p>
<form method="GET" action="{{ url()->current() }}" class="my-4 flex flex-wrap items-end gap-3" data-no-ajax>
<input type="hidden" name="weekly_from" value="{{ $sourceFrom }}"><input type="hidden" name="weekly_to" value="{{ $sourceTo }}">
@if(isset($moduleDepartment))<input type="hidden" name="module_view" value="{{ $sourceModuleView??'weekly' }}">@else<input type="hidden" name="weekly_department" value="{{ $sourceDepartment }}">@endif
<label class="text-sm">Search Module Activity<input class="mt-2 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3" type="search" name="activity_q_{{ $sourceDepartment }}" maxlength="150" value="{{ request('activity_q_'.$sourceDepartment) }}" placeholder="{{ $sourceDepartment==='finance'?'Status or record number':'Activity or brand' }}"></label><button class="min-h-[44px] rounded-lg border border-brand-white/20 px-4 py-3">Search All Matching Records</button></form>
@foreach($sourceSections as $sourceSection)
@php
$sourceAnchor='module-source-activity-'.($sourceModuleView??(isset($moduleDepartment)?'weekly':'shared'));
$sourceSection['rows']->fragment($sourceAnchor);
if(isset($moduleDepartment))$sourceSection['rows']->appends(['module_view'=>$sourceModuleView??'weekly']);
@endphp
<details class="my-4 rounded-xl border border-brand-white/15 p-4" open><summary class="cursor-pointer font-semibold">{{ $sourceSection['label'] }} · {{ $sourceSection['rows']->total() }} Records</summary><div class="module-scroll overflow-x-auto"><table class="w-full text-left text-xs" data-table-enhancer="off"><thead><tr>@foreach(['Reference','Activity','Context','Status','Recorded','Details'] as $label)<th class="p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($sourceSection['rows'] as $sourceRow)<tr>@foreach(['reference','title','context','status','date','detail'] as $field)<td class="p-3 border-t border-brand-white/10">{{ $sourceRow[$field]??'—' }}</td>@endforeach</tr>@empty<tr><td colspan="6" class="p-4 text-brand-ash">No recorded activity for this selection.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $sourceSection['rows']->links() }}</div></details>@endforeach
</section>
