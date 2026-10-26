@php
$savedWeeklyCount=\App\Models\WeeklyConsolidatedItem::where('workflow_state','legacy')->whereIn('department',\App\Models\User::departmentAliases('client_relations'))->when(request('weekly_from'),fn($q,$date)=>$q->whereDate('week_end','>=',$date))->when(request('weekly_to'),fn($q,$date)=>$q->whereDate('week_start','<=',$date))->count();
@endphp
<div x-data="{clientReportingView:@js(request('revenue_view')?'revenue':(request('client_reporting_view')==='updates'?'updates':'business'))}">
<nav class="mb-5 flex flex-wrap gap-3 border-b border-brand-white/15 pb-3" role="tablist" aria-label="Client Relations Reporting">
<button type="button" role="tab" id="cr-report-business-tab" aria-controls="cr-report-business-panel" :aria-selected="clientReportingView==='business'" @click="clientReportingView='business'" :class="{'border-amber-200/50 bg-amber-300/10':clientReportingView==='business'}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Client & Business Performance</button>
<button type="button" role="tab" id="cr-report-updates-tab" aria-controls="cr-report-updates-panel" :aria-selected="clientReportingView==='updates'" @click="clientReportingView='updates'" :class="{'border-amber-200/50 bg-amber-300/10':clientReportingView==='updates'}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Weekly Team Updates ({{ $savedWeeklyCount }})</button>
<button type="button" role="tab" id="cr-report-revenue-tab" aria-controls="cr-report-revenue-panel" :aria-selected="clientReportingView==='revenue'" @click="clientReportingView='revenue'" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Revenue & Finance Confirmation</button>
</nav>
<section role="tabpanel" id="cr-report-revenue-panel" aria-labelledby="cr-report-revenue-tab" x-show="clientReportingView==='revenue'" style="display:none">@include('portal.client-relations.revenue-review')</section>
<section role="tabpanel" id="cr-report-updates-panel" aria-labelledby="cr-report-updates-tab" x-show="clientReportingView==='updates'" style="display:none">
@include('portal.partials.department-weekly-workstreams')
</section>
<section role="tabpanel" id="cr-report-business-panel" aria-labelledby="cr-report-business-tab" x-show="clientReportingView==='business'">
@include('portal.client-relations.centre')
<details class="mt-5 rounded-2xl border border-amber-300/25 bg-amber-300/[.03] p-5" aria-label="Client Relations Weekly Source Figures"><summary class="cursor-pointer font-semibold text-amber-100">Published Financial Register</summary><p class="mt-2 text-xs text-brand-ash">Published reporting snapshots, grouped by project. Amounts are visible to all staff.</p>
<div class="mt-4 grid gap-3 xl:grid-cols-2">
@forelse($publicCrmProgress as $row)
<article class="rounded-xl border border-amber-300/20 bg-brand-black/25 p-4"><div class="mb-3 flex flex-wrap justify-between gap-3"><div><p class="text-xs uppercase tracking-wider text-amber-200">{{ $row->client_name }}</p><h4 class="mt-1 font-semibold text-brand-white">{{ $row->project_name }}</h4></div><span class="text-xs text-brand-ash">{{ $row->week_start->format('d M Y') }}</span></div><p class="text-xs text-brand-ash">{{ $row->owner?->name??'Unassigned' }} · {{ config('crm_pipeline.stages.'.$row->stage.'.label') }} · {{ ($row->actual_amount===null||$row->achievement_percent===null)?'Not Measured':number_format((float)$row->achievement_percent,1).'%' }}</p>
<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach(['proposed_amount'=>'Proposed','po_amount'=>'PO','target_amount'=>'Target','actual_amount'=>'Revenue','invoiced_amount'=>'Invoiced','collected_amount'=>'Collected'] as $f=>$label)<div class="min-w-0 border-l border-amber-300/30 pl-3"><p class="text-xs text-brand-ash">{{ $label }}@if($f==='actual_amount'&&$row->reported_actual_amount!==null&&$row->actual_amount===null) · {{ $row->revenue_state==='awaiting_cr'?'Awaiting Client Relations Confirmation':'Awaiting Finance Confirmation' }}@endif</p><strong class="mt-1 block break-words text-sm text-brand-white">{{ $row->$f===null?'Not Recorded':'GH₵ '.number_format((float)$row->$f,2) }}</strong></div>@endforeach</div>
<details class="mt-4"><summary class="cursor-pointer text-xs text-amber-100">Notes & Next Action</summary><div class="mt-2 space-y-2 text-xs text-brand-ash"><p>{{ $row->description?:'Description not recorded.' }}</p><p>Bottleneck: {{ $row->shared_bottlenecks?:'Not Recorded' }}</p><p>Next Action: {{ $row->shared_next_action?:'Not Recorded' }}</p><p>{{ $row->comments }}</p><p>Weighted Forecast: {{ $row->proposed_amount===null||$row->stage_percent===null?'Not Recorded':'GH₵ '.number_format((float)$row->proposed_amount*(float)$row->stage_percent/100,2) }}</p></div></details>
</article>
@empty<p class="text-sm text-brand-ash">No published weekly snapshots for this selection. Explore the account portfolio above.</p>@endforelse
</div><div class="mt-4">{{ $publicCrmProgress->links() }}</div></details>

</section></div>
