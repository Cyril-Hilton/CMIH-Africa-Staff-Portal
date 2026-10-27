<div class="my-3 rounded-xl border border-sky-300/20 p-3 text-xs">
<p class="font-semibold">Report: {{ $item->reportStatusLabel() }}</p>
<p class="mt-1 text-brand-ash">Work: {{ $item->status }} · Reporting approval does not verify task completion.</p>
@if($item->carried_from_id)<p class="mt-2 text-brand-ash">Carried from report #{{ $item->carried_from_id }}. Previous progress: {{ isset($item->previous_week_reference['progress_percent'])?$item->previous_week_reference['progress_percent'].'%':'Not recorded' }} (reference only).</p>@endif
@if($item->week_start?->lt(now()->startOfWeek()))
<form data-no-ajax data-no-silent method="POST" action="{{ route('portal.dashboard.weekly-consolidated.carry',$item) }}" class="mt-3">@csrf<button class="weekly-consolidated-action-button border border-sky-300/30">Copy This Activity to This Week</button></form>
@endif
@if(!empty($item->previous_week_reference['content']))
<details class="mt-3"><summary class="cursor-pointer font-semibold">Previous Week Reference · Not New Results</summary><p class="mt-2 text-brand-ash">Copied context is reference only. Enter this week's actual progress before submitting.</p>
@foreach(['deliverables'=>'Previous Update','target_breakdown'=>'Previous Targets','achieved_breakdown'=>'Previous Results','gap_breakdown'=>'Previous Gaps'] as $field=>$label)
@if(!empty($item->previous_week_reference['content'][$field]))<h6 class="mt-3 font-semibold">{{ $label }}</h6><div class="weekly-rich-content">{!! \App\Support\ReportRichText::html($item->previous_week_reference['content'][$field]) !!}</div>@endif
@endforeach
@if(!empty($item->previous_week_reference['content']['custom_fields']))<h6 class="mt-3 font-semibold">Previous Additional Fields</h6>@foreach($item->previous_week_reference['content']['custom_fields'] as $field=>$value)<p class="mt-2 font-semibold">{{ ucwords(str_replace('_',' ',$field)) }}</p><div class="weekly-rich-content">{!! \App\Support\ReportRichText::html(is_array($value)?json_encode($value):(string)$value) !!}</div>@endforeach @endif
</details>
@endif
<details class="mt-3"><summary class="cursor-pointer">Submit / Review Weekly Report</summary>
<form data-no-ajax data-no-silent method="POST" action="{{ route('portal.dashboard.weekly-consolidated.report-state',$item) }}" class="mt-3 space-y-2">@csrf
<select name="report_action" class="w-full rounded-lg bg-brand-black p-2"><option value="submit">Submit weekly report</option>@if($item->report_status==='submitted' && (int)$item->updated_by!==auth()->id() && (int)$item->report_submitted_by!==auth()->id())<option value="review">Review weekly report</option>@endif</select>
<label class="block">Summary / unfinished work explanation<input name="reason" required minlength="5" maxlength="2000" class="mt-2 w-full rounded-lg border border-brand-white/15 bg-brand-black p-2"></label><button class="weekly-consolidated-action-button border border-brand-white/20">Save Report Status</button></form>
</details>
@php($versions=\Illuminate\Support\Facades\DB::table('weekly_report_versions')->where('weekly_item_id',$item->id)->orderByDesc('id')->limit(20)->get())
@if($versions->isNotEmpty())<details class="mt-3"><summary>Correction History · {{ $versions->count() }} Recent Changes</summary>@foreach($versions as $version)<details class="mt-2"><summary>{{ $version->created_at }} · {{ ucwords(str_replace('_',' ',$version->action)) }}</summary><p>{{ $version->reason }}</p>@php($old=json_decode($version->snapshot,true))@foreach(['deliverables'=>'Previous Update','target_breakdown'=>'Previous Targets','achieved_breakdown'=>'Previous Results','gap_breakdown'=>'Previous Gaps'] as $field=>$label)@if(!empty($old[$field]))<h6 class="mt-3 font-semibold">{{ $label }}</h6><div class="weekly-rich-content">{!! \App\Support\ReportRichText::html($old[$field]) !!}</div>@endif @endforeach</details>@endforeach<p class="mt-2 text-brand-ash">Previous versions are retained securely.</p></details>@endif
</div>
