@php
$entryExtras=\App\Models\WeeklyConsolidatedColumn::where('user_id',$item->created_by)->where('department',\App\Models\User::normalizeDepartmentKey($item->department))->orderBy('order')->get();
@endphp
@foreach($entryExtras as $extra)
@if(trim((string)$item->customFieldValue($extra))!=='')<section class="mt-4 min-w-0 rounded-lg border border-brand-white/10 p-3"><h6 class="text-sm font-semibold">{{ $extra->label }}</h6><div class="weekly-rich-content mt-3 text-sm leading-7">{!! \App\Support\ReportRichText::html((string)$item->customFieldValue($extra)) !!}</div></section>@endif
@endforeach
