<section class="cr-centre" data-crm-centre data-endpoint="{{ route('portal.crm.centre.data') }}" data-export="{{ url('/portal/client-relations/centre/export') }}" data-relationships="{{ route('portal.crm.centre.relationship') }}" data-financials="{{ url('/portal/client-relations/financial-register') }}" data-allocations="{{ url('/portal/client-relations/allocations') }}" data-targets="{{ url('/portal/client-relations/account-targets') }}" data-module="{{ route('portal.crm.index') }}" data-from="{{ request('weekly_from') }}" data-to="{{ request('weekly_to') }}" aria-label="Client & Business Performance Centre">
<header class="cr-header"><div><p class="cr-eyebrow">CLIENT RELATIONS / CONNECTIONS INTO GROWTH</p><h3>Client & Business Performance Centre</h3><p>See the relationships. Follow the business. Know the next move.</p></div><div class="cr-actions"><button type="button" data-cr-refresh>Refresh</button><button type="button" data-cr-export="xlsx">Excel</button><button type="button" data-cr-export="pdf">Business Review PDF</button>@if(\App\Services\ClientRelationsPipeline::member(auth()->user()))<a href="{{ route('portal.crm.index') }}#crm-entry">Manage Pipeline</a>@endif</div></header>
<p role="status" class="cr-status" data-cr-status>Connecting client relationships and business results…</p>
<div data-cr-pulse class="cr-pulse"></div>
<nav class="cr-tabs" aria-label="Client Relations Views">
@foreach(['web'=>'Relationship Web','radar'=>'Client Health Radar','growth'=>'Growth & Revenue','funnel'=>'Opportunity Funnel','actions'=>'Action Queue','review'=>'Weekly Business Review'] as $key=>$name)<button type="button" data-cr-view="{{ $key }}" aria-pressed="{{ $key==='web'?'true':'false' }}">{{ $name }}</button>@endforeach
</nav>
<form data-cr-filters data-no-silent data-no-ajax class="cr-filters" aria-label="Filter Client Relations Business">
<label>Data Source<select name="source"><option value="workbook" @selected(!app()->environment('production'))>Source Workbook</option><option value="all" @selected(app()->environment('production'))>All Portal Records</option></select></label>
<label>Search<input name="q" placeholder="Client or project" maxlength="150"></label>
<label>Client<select name="client"><option value="">All Clients</option></select></label>
<label>Account Owner<select name="owner"><option value="">All Owners</option></select></label>
<label>Sales Stage<select name="stage"><option value="">All Sales Stages</option>@foreach(config('crm_pipeline.stages') as $key=>$stage)<option value="{{ $key }}">{{ ucwords($stage['label']) }}</option>@endforeach</select></label>
<label>From<input type="date" name="from" value="{{ request('weekly_from',now()->startOfYear()->toDateString()) }}"></label><label>Through<input type="date" name="to" value="{{ request('weekly_to',now()->endOfYear()->toDateString()) }}"></label>
<label>Sort Clients<select name="sort"><option value="client">Client Name</option><option value="revenue">Revenue: Highest First</option></select></label><label>Clients Per Page<select name="per_page"><option>6</option><option selected>12</option><option>24</option><option>48</option></select></label>
<button type="submit">Apply Filters</button><button type="button" data-cr-reset>Clear Filters</button>
</form>
<div data-cr-content aria-busy="true"></div><nav class="cr-pagination" data-cr-pagination aria-label="Client Relationship Pages"></nav>
<dialog class="cr-dialog" data-cr-dialog aria-label="Client Relations Details"><header><strong data-cr-dialog-title>Client Details</strong><button type="button" data-cr-close aria-label="Close Client Relations Details">Close</button></header><div data-cr-dialog-body></div></dialog>
</section>
