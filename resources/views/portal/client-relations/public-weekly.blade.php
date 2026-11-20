
<div x-data="{clientReportingView:@js(request('revenue_view')?'revenue':'business')}">
<nav class="mb-5 flex flex-wrap gap-3 border-b border-brand-white/15 pb-3" role="tablist" aria-label="Client Relations Reporting">
<button type="button" role="tab" id="cr-report-business-tab" aria-controls="cr-report-business-panel" :aria-selected="clientReportingView==='business'" @click="clientReportingView='business'" :class="{'border-amber-200/50 bg-amber-300/10':clientReportingView==='business'}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Client & Business Performance</button>
<button type="button" role="tab" id="cr-report-revenue-tab" aria-controls="cr-report-revenue-panel" :aria-selected="clientReportingView==='revenue'" @click="clientReportingView='revenue'" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm">Revenue</button>
</nav>
<section role="tabpanel" id="cr-report-revenue-panel" aria-labelledby="cr-report-revenue-tab" x-show="clientReportingView==='revenue'" style="display:none">@include('portal.client-relations.revenue-review')</section>
<section role="tabpanel" id="cr-report-business-panel" aria-labelledby="cr-report-business-tab" x-show="clientReportingView==='business'">
@include('portal.client-relations.centre')
@include('portal.client-relations.published-register')

</section></div>