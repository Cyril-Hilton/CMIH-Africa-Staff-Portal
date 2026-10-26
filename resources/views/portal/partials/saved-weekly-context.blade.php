@php
$savedQuery=\App\Models\WeeklyConsolidatedItem::query()->with(['leadStaff','creator'])->where('workflow_state','legacy')->whereIn('department',\App\Models\User::departmentAliases($key));
$savedQuery->when(request('weekly_from'),fn($q,$date)=>$q->whereDate('week_end','>=',$date))->when(request('weekly_to'),fn($q,$date)=>$q->whereDate('week_start','<=',$date));
$savedCount=(clone $savedQuery)->count();
$canShowSaved=($key==='hr_admin'?auth()->user()->hasFullHrAccess():($key!=='finance'||auth()->user()->isCvoOrSuperAdmin()||\App\Models\User::normalizeDepartmentKey(auth()->user()->department)==='finance'));
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
@foreach(['deliverables'=>'Deliverables & Progress','target_breakdown'=>'Reported Targets','achieved_breakdown'=>'Reported Results','gap_breakdown'=>'Reported Gaps','notes'=>'Notes'] as $field=>$label)
@if(trim((string)$saved->$field))
@php($savedContent=app(\App\Services\BrandsWeeklyPresentation::class)->content($saved->$field))
<h6 class="mt-4 text-sm font-semibold">{{ $label }}</h6>
<div class="weekly-rich-content mt-2 text-sm leading-7">{!! $savedContent['html'] !!}</div>
@endif
@endforeach
</details>
@endforeach
@endif
</section>
@endif
