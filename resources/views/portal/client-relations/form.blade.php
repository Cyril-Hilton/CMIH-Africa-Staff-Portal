@php
$record=$project??null;
$useOld=(string)old('editing_project_id','new')===(string)($record?->id??'new');
@endphp
<form method="POST" action="{{ $record?route('portal.crm.update',$record):route('portal.crm.store') }}" class="mt-4 space-y-4" data-crm-project-form>@csrf
<input type="hidden" name="editing_project_id" value="{{ $record?->id??'new' }}">@if($record)<input type="hidden" name="revision" value="{{ $record->revision }}">@endif
@include('portal.client-relations.project-fields', [
    'record' => $record,
    'useOld' => $useOld,
    'clientList' => 'crm-client-names',
    'ownerOptions' => auth()->user()->isLineManager() ? $staff->pluck('name', 'id')->all() : [auth()->id() => auth()->user()->name],
])
@include('portal.client-relations.supporting-staff', [
    'options' => $supportingStaff,
    'selected' => array_map('intval', $useOld ? old('supporting_staff_ids', old('supporting_staff_present') ? [] : ($record?->supporting_staff_ids ?? [])) : ($record?->supporting_staff_ids ?? [])),
    'ownerId' => $record?->owner_id ?? auth()->id(),
])
@if($record?->import_key&&!$record->source_period_confirmed)<div class="rounded-xl border border-amber-300/30 p-3"><p class="text-sm text-amber-200">Confirm the Reporting Date</p><p class="mt-2 text-xs text-brand-ash">This source date needs confirmation before it contributes to totals.</p>@if(auth()->user()->isLineManager())<label class="mt-3 block text-sm"><input type="checkbox" name="confirm_source_period" value="1"> I checked the source and confirm the reporting date entered above.</label>@endif</div>@endif
<button type="submit" class="min-h-[44px] rounded-xl bg-brand-red px-4 py-3 font-semibold text-white">{{ auth()->user()->isLineManager() ? 'Publish Weekly Entry' : 'Submit Weekly Entry for Manager Review' }}</button>
</form>
@if($record?->import_key)<details class="mt-3 rounded-xl border border-brand-white/15 p-3"><summary class="cursor-pointer text-xs">Original Workbook Reference</summary><p class="mt-2 text-xs">{{ $record->source_reference }} · {{ $record->source_period_label }}</p><p class="mt-2 text-xs">Workbook Forecast: {{ $record->source_forecast===null?'Not Supplied':'GH₵ '.number_format((float)$record->source_forecast,2) }}. Retained separately from stage-based calculations.</p></details>@endif
