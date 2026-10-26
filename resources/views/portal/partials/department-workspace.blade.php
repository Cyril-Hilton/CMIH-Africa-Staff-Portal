@php
$design = [
'operations_projects'=>['Operations & Projects','From Plan to Execution','Project Readiness & Delivery','#fbbf24','Execution Register'],
'finance'=>['Finance','Clarity in Every Commitment','Processing, Controls & Performance','#67e8f9','Processing Register'],
'brands_marketing'=>['Brands & Marketing','Brand Insight. Retail Impact.','Brand Analytics, Shopper Marketing & Retail Coordination','#f9a8d4','Brand & Retail Reporting'],
'hr_admin'=>['HR & Admin','People Make It Happen','People, Workplace & Service Delivery','#c4b5fd','Department Register'],
][$key];
$savedWeeklyCount=\App\Models\WeeklyConsolidatedItem::where('workflow_state','legacy')->whereIn('department',\App\Models\User::departmentAliases($key))->when(request('weekly_from'),fn($q,$date)=>$q->whereDate('week_end','>=',$date))->when(request('weekly_to'),fn($q,$date)=>$q->whereDate('week_start','<=',$date))->count();
$total=$department['summary']['total']; $verified=$department['summary']['verified'];
$rate=$total?round(100*$verified/$total):null;
$needsAttention=collect($department['deliverables'])->where('overdue',true);
@endphp

<div class="dw" data-kind="{{ $key }}" style="--dw:{{ $design[3] }}" x-data="{brandSearch:'',brandStatus:'',section:'{{ $key==='hr_admin'?(auth()->user()->hasFullHrAccess()?(request()->has('attendance_period')?'attendance':'weekly'):'people'):($key==='finance'&&request('revenue_view')?'revenue':'weekly') }}'}">
<div class="dw-hero"><div><p class="dw-kicker">{{ $design[1] }}</p><h4>{{ $design[0] }}</h4><p class="dw-sub">{{ $design[2] }}</p></div><div class="dw-ring" style="--rate:{{ $rate??0 }}"><div><strong>{{ $rate===null?'—':$rate.'%' }}</strong><small>Verified Delivery</small></div></div></div>
@include('portal.partials.weekly-report-summary')
<div class="dw-strip"><div><strong>{{ $savedWeeklyCount }}</strong><span>Saved Weekly Entries</span></div><div><strong>{{ $total }}</strong><span>Commitments</span></div><div><strong>{{ $verified }}</strong><span>Verified</span></div><div><strong class="{{ $needsAttention->count()?'text-rose-300':'' }}">{{ $needsAttention->count() }}</strong><span>Overdue</span></div><div><strong>{{ count($department['kpis']) }}</strong><span>KPI Targets</span></div></div>
<div class="dw-tabs" role="tablist" aria-label="{{ $design[0] }} Reporting Views">
@foreach(($key==='hr_admin'?['people'=>'People & Workplace']+(auth()->user()->hasFullHrAccess()?['attendance'=>'Attendance']:[]):[])+['weekly'=>($key==='brands_marketing'?'Weekly Workstreams':'Weekly Updates')]+($key==='operations_projects'?['handover'=>'Plans & Handovers']:[])+($key==='finance'?['revenue'=>'Revenue & CR Confirmation']:[])+['results'=>'KPI Results','delivery'=>($key==='brands_marketing'?'Team Activity & Delivery':'Delivery & Deadlines'),'register'=>$design[4]] as $view=>$label)<button type="button" role="tab" id="dw-tab-{{ $key }}-{{ $view }}" aria-controls="dw-panel-{{ $key }}-{{ $view }}" :aria-selected="section===@js($view)" @click="section=@js($view)" @keydown.right.prevent="$el.nextElementSibling?.click(); $el.nextElementSibling?.focus()" @keydown.left.prevent="$el.previousElementSibling?.click(); $el.previousElementSibling?.focus()">{{ $label }}</button>@endforeach
</div>
@if($key==='finance')<section role="tabpanel" id="dw-panel-finance-revenue" aria-labelledby="dw-tab-finance-revenue" x-show="section==='revenue'" style="display:none">@include('portal.client-relations.revenue-review')</section>@endif
@if($key==='operations_projects')<section role="tabpanel" id="dw-panel-operations_projects-handover" aria-labelledby="dw-tab-operations_projects-handover" x-show="section==='handover'" style="display:none">@include('portal.operations.handover-workspace',['handoverEmbedded'=>true])</section>@endif
@if(in_array($key,['operations_projects','finance','hr_admin']))
<section role="tabpanel" id="dw-panel-{{ $key }}-weekly" aria-labelledby="dw-tab-{{ $key }}-weekly" x-show="section==='weekly'" @if($key==='hr_admin' && (!auth()->user()->hasFullHrAccess() || request()->has('attendance_period')))style="display:none"@endif>
@if($key==='operations_projects'&&auth()->user()->canManageOperationsPrintingRequests())<div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-brand-white/20 p-4"><span class="text-sm">Vendor Printing Requests</span><a class="dw-link" href="{{ route('portal.printing-requests.create') }}">Create request</a><a class="dw-link" href="{{ route('portal.printing-requests.index') }}">Saved requests & PDFs</a></div>@endif
@include('portal.partials.department-weekly-workstreams')
</section>
@endif
@if($key==='brands_marketing')<section role="tabpanel" id="dw-panel-brands_marketing-weekly" aria-labelledby="dw-tab-brands_marketing-weekly" x-show="section==='weekly'">@include('portal.partials.brands-workstreams')</section>@endif
@if($key==='hr_admin' && auth()->user()->hasFullHrAccess())
@php
$attendanceFilters=[];
foreach(['period','date','q','department','punctuality','sort','direction'] as $filter) if(request()->filled('attendance_'.$filter)) $attendanceFilters[$filter]=request('attendance_'.$filter);
$attendanceData=app(\App\Services\HrAttendanceReport::class)->build($attendanceFilters,'attendance_page');
$attendanceData['logs']->appends(['attendance_period'=>$attendanceData['period'],'weekly_department'=>'hr_admin'])->fragment('staff-weekly-performance');
@endphp
<section role="tabpanel" id="dw-panel-hr_admin-attendance" aria-labelledby="dw-tab-hr_admin-attendance" x-show="section==='attendance'" @if(!request()->has('attendance_period'))style="display:none"@endif>
<div class="dw-heading"><h5>Attendance & Punctuality</h5></div>
@include('portal.hr-reporting.attendance-panel',array_merge($attendanceData,['attendancePrefix'=>'attendance_','attendanceEmbedded'=>true]))
</section>
@endif
@if($key==='hr_admin')<section role="tabpanel" id="dw-panel-{{ $key }}-people" aria-labelledby="dw-tab-{{ $key }}-people" x-show="section==='people'" @if(auth()->user()->hasFullHrAccess())style="display:none"@endif>
@include('portal.partials.shared-people-reporting')
</section>@endif
<section role="tabpanel" id="dw-panel-{{ $key }}-results" aria-labelledby="dw-tab-{{ $key }}-results" x-show="section==='results'" style="display:none">
<div class="dw-heading"><h5>Targets & Results</h5></div>
@forelse($department['kpis'] as $row)<article class="dw-kpi"><div><h6>{{ $row['name'] }}</h6><p class="dw-meta">{{ $row['owner'] }} · {{ ucfirst($row['scope']) }}<br>Client: {{ $row['client']??'Department-wide' }}<br>Project: {{ $row['project']??'Not Applicable' }}<br>{{ $row['period'] }}</p></div><div><div class="dw-targets"><div><span>Agreed Target</span><strong>{{ $row['target'] }}</strong></div><div><span>Approved Result</span><strong>{{ $row['actual'] }}</strong></div></div><div class="dw-track"><i style="width:{{ min(100,max(0,$row['achievement']??0)) }}%"></i></div><p class="dw-meta">Gap: {{ $row['gap']===null?'Not Measured':sprintf('%+.1f pp',$row['gap']) }}</p></div><div class="dw-kpi-score"><strong class="{{ $row['rag']==='red'?'text-rose-300':($row['rag']==='amber'?'text-amber-200':($row['rag']==='green'?'text-emerald-200':'text-brand-ash')) }}">{{ $row['achievement']===null?'—':$row['achievement'].'%' }}</strong><p class="dw-meta">{{ $row['achievement']===null?'Awaiting Result':strtoupper($row['rag']) }}</p></div></article>@empty<p class="dw-empty">No approved KPI targets for this period.</p>@endforelse
</section>
<section role="tabpanel" id="dw-panel-{{ $key }}-delivery" aria-labelledby="dw-tab-{{ $key }}-delivery" x-show="section==='delivery'" style="display:none"><div class="dw-heading"><h5>{{ $key==='brands_marketing'?'Team Activity & Delivery':'Delivery & Deadlines' }}</h5></div>@if($needsAttention->count())<p class="dw-alert">{{ $needsAttention->count() }} overdue {{ $needsAttention->count()===1?'commitment needs':'commitments need' }} attention.</p>@endif
@forelse($department['deliverables'] as $row)<article class="dw-work"><span class="dw-index">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><h6>{{ $row['project'] }}</h6><p class="dw-meta">Client: {{ $row['client']??'Internal' }}<br>{{ $row['owner'] }} · WCT-{{ $row['id'] }}</p><div class="dw-track"><i style="width:{{ min(100,max(0,$row['progress']??0)) }}%"></i></div><p class="dw-meta">{{ $row['progress']??0 }}% Progress · Quality: {{ $row['quality']===null?'Not Verified':$row['quality'].'%' }}</p></div><div><span class="dw-status {{ $row['overdue']?'text-rose-300':'' }}">{{ ucwords(str_replace('_',' ',$row['state'])) }}</span><p class="dw-meta">Due {{ $row['due']??'Not Recorded' }}{{ $row['overdue']?' · OVERDUE':'' }}</p></div><div class="dw-meta">{{ $row['root_cause']?ucwords(str_replace('_',' ',$row['root_cause'])):'No Delay Cause Recorded' }}</div></article>@empty<p class="dw-empty">No delivery commitments recorded for this period.</p>@endforelse</section>
<section role="tabpanel" id="dw-panel-{{ $key }}-register" aria-labelledby="dw-tab-{{ $key }}-register" x-show="section==='register'" style="display:none">@if(!empty($weeklyDepartmentTables[$key]['sections']))@include('portal.partials.department-register-tabs')@elseif(isset($weeklyDepartmentTables[$key]))@include('portal.partials.weekly-department-tables',['weeklyDepartmentTables'=>[$key=>$weeklyDepartmentTables[$key]],'presentationOnly'=>true])@else<div class="dw-heading"><h5>{{ $design[4] }}</h5></div><p class="dw-empty">No additional approved register entries for this reporting period.</p>@endif</section>
</div>
