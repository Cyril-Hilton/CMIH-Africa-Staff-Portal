@php
$savedQuery=\App\Models\WeeklyConsolidatedItem::query()->with(['leadStaff','creator'])->where('workflow_state','legacy')->whereIn('department',\App\Models\User::departmentAliases($key));
$savedQuery->when(request('weekly_from'),fn($q,$date)=>$q->whereDate('week_end','>=',$date))->when(request('weekly_to'),fn($q,$date)=>$q->whereDate('week_start','<=',$date));
$savedCount=(clone $savedQuery)->count();
$canShowSaved=!in_array($key,['finance','hr_admin'],true)||auth()->user()->isCvoOrSuperAdmin()||\App\Models\User::normalizeDepartmentKey(auth()->user()->department)===$key;
@endphp
@if($savedCount && $canShowSaved)
<section class="mt-6 border-t border-brand-white/15 pt-4" aria-label="Existing weekly reporting">
<h5 class="text-lg font-semibold">Existing Weekly Reporting · {{ $savedCount }} Entries</h5>
<p class="dw-meta">Your original weekly submissions, shown with their saved dates and reported status. These entries are not automatically marked as independently verified.</p>
@if($key==='brands_marketing')
@include('portal.partials.brands-workstreams')
@else
@foreach($savedQuery->orderByDesc('week_start')->limit(100)->get() as $saved)
<details class="mt-3 rounded-xl border border-brand-white/15 p-4">
<summary>{{ $saved->campaign_name }} · {{ $saved->client_name }} · {{ $saved->week_start->format('d M Y') }}–{{ $saved->week_end->format('d M Y') }}</summary>
<p class="dw-meta">{{ $saved->leadStaff?->name ?? $saved->creator?->name ?? 'Unassigned' }} · {{ $saved->status }}</p>
@php($savedContent=app(\App\Services\BrandsWeeklyPresentation::class)->content($saved->deliverables))
<p class="mt-3 whitespace-pre-line text-sm leading-7">{{ $savedContent['text'] }}</p>
@foreach($savedContent['tables'] as $table)<div class="mt-3 overflow-x-auto" tabindex="0"><table class="text-xs"><tbody>@foreach($table as $cells)<tr>@foreach($cells as $cell)<td class="min-w-[7rem]">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endforeach
</details>
@endforeach
@endif
</section>
@endif
