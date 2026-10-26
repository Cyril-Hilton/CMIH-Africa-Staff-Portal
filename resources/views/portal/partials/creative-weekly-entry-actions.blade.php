<section class="cs-section" aria-label="Original weekly reporting update">
<h4>Weekly Update · {{ $item->week_start->format('d M Y') }}–{{ $item->week_end->format('d M Y') }}</h4>
<p class="cs-muted">WCT-{{ $item->id }} · Reported work: {{ $item->status }}. This saved weekly report is separate from live workflow approvals.</p>
<h5 class="mt-4 font-semibold">Responsibilities</h5><p class="cs-muted">Lead: {{ $item->leadStaff?->name ?? 'Not recorded' }}</p>@foreach($item->supporting_staff_with_roles as $person)<p class="cs-muted">{{ $person->name }} · {{ $person->weekly_role }}</p>@endforeach
@foreach(['deliverables'=>'Weekly Progress','target_breakdown'=>'KPI Commitments','achieved_breakdown'=>'Reported Results','gap_breakdown'=>'Gaps & Follow-up','notes'=>'Notes & Next Actions'] as $field=>$label)
@if(trim((string)$item->$field))<h5 class="mt-4 font-semibold">{{ $label }}</h5><div class="weekly-rich-content mt-3">{!! \App\Support\ReportRichText::html($item->$field) !!}</div>@endif
@endforeach
@include('portal.partials.weekly-entry-extra-fields')
@include('portal.partials.weekly-entry-actions')
</section>
