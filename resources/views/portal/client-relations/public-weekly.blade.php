@php
$savedWeeklyCount=\App\Models\WeeklyConsolidatedItem::where('workflow_state','legacy')->whereIn('department',\App\Models\User::departmentAliases('client_relations'))->when(request('weekly_from'),fn($q,$date)=>$q->whereDate('week_end','>=',$date))->when(request('weekly_to'),fn($q,$date)=>$q->whereDate('week_start','<=',$date))->count();
@endphp
<div x-data="{clientReportingView:@js(request('revenue_view')?'revenue':(request('client_reporting_view')==='updates'?'updates':'business'))}">
<nav class="mb-5 flex flex-wrap gap-3 border-b border-brand-white/15 pb-3" role="tablist" aria-label="Client Relations Reporting">
<button type="button" role="tab" id="cr-report-business-tab" aria-controls="cr-report-business-panel" :aria-selected="clientReportingView==='business'" @click="clientReportingView='business'" :class="{'border-amber-200/50 bg-amber-300/10':clientReportingView==='business'}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Client & Business Performance</button>
<button type="button" role="tab" id="cr-report-updates-tab" aria-controls="cr-report-updates-panel" :aria-selected="clientReportingView==='updates'" @click="clientReportingView='updates'" :class="{'border-amber-200/50 bg-amber-300/10':clientReportingView==='updates'}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Weekly Team Updates ({{ $savedWeeklyCount }})</button>
<button type="button" role="tab" id="cr-report-revenue-tab" aria-controls="cr-report-revenue-panel" :aria-selected="clientReportingView==='revenue'" @click="clientReportingView='revenue'" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Revenue</button>
</nav>
<section role="tabpanel" id="cr-report-revenue-panel" aria-labelledby="cr-report-revenue-tab" x-show="clientReportingView==='revenue'" style="display:none">@include('portal.client-relations.revenue-review')</section>
<section role="tabpanel" id="cr-report-updates-panel" aria-labelledby="cr-report-updates-tab" x-show="clientReportingView==='updates'" style="display:none">
@include('portal.partials.department-weekly-workstreams')
</section>
<section role="tabpanel" id="cr-report-business-panel" aria-labelledby="cr-report-business-tab" x-show="clientReportingView==='business'">
@include('portal.client-relations.centre')
@include('portal.client-relations.published-register')

</section></div>
