@php
 $url=fn(array $change=[])=>route('portal.hr-workspace',array_replace(['hr_tab'=>$tab,'hr_period'=>$period,'hr_from'=>$from,'hr_to'=>$to,'hr_owner'=>$owner?:null],$v,$change));
 $asOf=min($to,today()->toDateString());
@endphp
<section class="brands-reports hr-dashboard hr-live-workspace space-y-5" aria-label="HR and Admin live registers">
 <header class="br-heading"><div><p class="text-xs uppercase tracking-widest text-brand-ash">HR &amp; Admin · Live registers</p><h2>Records, people &amp; administration</h2><p>{{ $from }} – {{ $to }} · Activities and period results</p></div><nav class="br-actions">@if($full)<a class="hr-report-control" href="{{ route('portal.hr',['hr_existing'=>1]) }}">HR Tools</a>@endif<a class="hr-report-control" href="{{ route('portal.hr-workspace.export',request()->query()+['format'=>'xlsx']) }}">Export Excel</a><a class="hr-report-control" href="{{ route('portal.hr-workspace.export',request()->query()+['format'=>'pdf']) }}">Export PDF</a></nav></header>
 @if(session('status'))<p class="br-status" role="status">{{ session('status') }}</p>@endif
 @if($errors->any())<ul class="br-error" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
 @if(auth()->user()->canAuditHrAndFinance()&&!auth()->user()->hasFullHrAccess())<p class="br-notice">Read-only HR audit · You can inspect records and history; approval and editing remain with the responsible managers.</p>@endif
 <nav class="br-tabs" aria-label="HR and Admin function tabs">
  @if($full)<a class="hr-report-control" href="{{ $url(['hr_tab'=>'overview']) }}" aria-current="{{ $tab==='overview'?'page':'false' }}">Overview</a>@endif
  @foreach(['HR','Admin'] as $group)@foreach($tabs as $key=>$t)@if($t['group']===$group)<a class="hr-report-control" href="{{ $url(['hr_tab'=>$key,'hr_q'=>null,'hr_status'=>null,'hr_page'=>null]) }}" aria-current="{{ $tab===$key?'page':'false' }}"><small>{{ $group }}</small> {{ $t['label'] }}</a>@endif @endforeach @endforeach
 </nav>
 <form method="GET" action="{{ route('portal.hr-workspace') }}" class="br-table-tools" data-no-ajax><input type="hidden" name="hr_tab" value="{{ $tab }}"><label>Reporting period<select name="hr_period">@foreach(['week'=>'Weekly','month'=>'Monthly','year'=>'Yearly','fortnight'=>'Two weeks','range'=>'Custom range'] as $key=>$label)<option value="{{ $key }}" @selected($period===$key)>{{ $label }}</option>@endforeach</select></label><label>From / date in period<input type="date" name="hr_from" value="{{ $from }}" required></label><label>To (custom range)<input type="date" name="hr_to" value="{{ $to }}"></label><button class="br-filter-button">Apply period</button></form>
 <p class="br-notice">Registers show current records. Period filters apply to activities, attendance and dated results. Counts are calculated automatically; they are not typed summary totals.</p>
 <div class="br-summary">@foreach($tab==='overview'?$home:$stats as $label=>$count)<div><small>{{ $label }}</small><strong>{{ number_format((float)$count,str_contains($label,'GHS')?2:(str_contains($label,'(%)')?1:0)) }}{{ str_contains($label,'(%)')?'%':'' }}</strong></div>@endforeach</div>
 @if(in_array($tab,['overview','attendance']))<p class="br-help">Attendance uses {{ $full?'eligible internal staff across all departments':'staff in your department' }} and recorded clock-in times. Approved leave, public holidays, weekends and future days are excluded from expected attendance.</p>@endif
 @if($tab==='overview')
  <section><h3 class="text-lg font-semibold">Activity summary</h3><div class="br-summary">@foreach($summary as $label=>$n)<div><small>{{ $label }}</small><strong>{{ $n }}</strong></div>@endforeach</div><nav class="br-staff">@foreach($tabCounts as $key=>$n)<a class="hr-report-control" href="{{ $url(['hr_tab'=>$key]) }}">{{ $tabs[$key]['label'] }} <strong>{{ $n }}</strong> open</a>@endforeach</nav></section>
  <div class="grid gap-5 lg:grid-cols-2"><section class="rounded-2xl border border-brand-white/10 p-4"><h3 class="text-lg font-semibold">Automatic alerts</h3><p class="br-help">Due within 30 days, already overdue, or needing immediate follow-up. Birthdays use the current week.</p><div class="max-h-96 overflow-y-auto space-y-2 mt-3">@forelse($alerts as $alert)<a class="block rounded-xl border border-orange-400/20 p-3 text-sm" href="{{ $url(['hr_tab'=>$alert['tab']]) }}">{{ $alert['label'] }}<small class="block text-brand-ash">{{ $alert['date'] }}</small></a>@empty<p class="br-help">No alerts from the recorded information.</p>@endforelse</div></section>
  <section class="rounded-2xl border border-brand-white/10 p-4"><h3 class="text-lg font-semibold">Next 10 activity deadlines</h3>@forelse($upcoming as $a)<a class="block border-b border-brand-white/10 py-3" href="{{ route('portal.hr-dashboard.activities.show',$a) }}"><strong>{{ $a->activity }}</strong><small class="block text-brand-ash">{{ $a->deadline }} · {{ $a->priority }} · {{ $a->status }}</small></a>@empty<p class="br-help">No upcoming deadlines recorded.</p>@endforelse</section></div>
 @else
  <form method="GET" action="{{ route('portal.hr-workspace') }}#hr-registers" class="br-table-tools" data-no-ajax><input type="hidden" name="hr_tab" value="{{ $tab }}"><input type="hidden" name="hr_period" value="{{ $period }}"><input type="hidden" name="hr_from" value="{{ $from }}"><input type="hidden" name="hr_to" value="{{ $to }}"><label class="br-table-search">Search registers<input type="search" name="hr_record_q" maxlength="150" value="{{ $v['hr_record_q']??'' }}" placeholder="Name, reference or recorded detail"></label><button class="br-filter-button">Search records</button></form>
  <section id="hr-registers" class="space-y-5" aria-label="Records and registers"><h3 class="text-xl font-semibold">Records / Registers</h3>
   @if($write && in_array($tab,['people','fleet','assets']))<a class="hr-report-control" href="{{ route('portal.hr-registers.import',['kind'=>match($tab){'people'=>'staff','fleet'=>'vehicle','assets'=>'asset'}]) }}">Import register from Excel</a>@endif
   @if($tab==='assets')<p class="br-help">Existing office asset records represent individual items. Where procurement quantity was never entered, each existing item counts once. Quantities explicitly saved in this register take priority.</p>@endif
   @if($tab==='facilities')@include('portal.hr-reporting.facility-inventory')@endif
   @if($tab==='attendance')@include('portal.hr-reporting.native-attendance')@endif
   @if(in_array($tab,['fleet','welfare','supplies']))@include('portal.hr-reporting.native-sources')@endif
   @if($tab==='performance')@include('portal.hr-reporting.native-performance')@endif
   @if($tab==='welfare')
    <details class="br-table-section"><summary class="cursor-pointer font-semibold">Birthday &amp; work anniversary calendar</summary><div class="br-table-wrap"><table class="br-table"><thead><tr><th>Staff</th><th>Birthday</th><th>Work anniversary</th></tr></thead><tbody>@foreach($registers->staff(auth()->user())->where('status','active') as $person)<tr><td>{{ $person->name }}</td><td>{{ $person->date_of_birth?->format('d M') ?? ($person->birthday_month&&$person->birthday_day?\Illuminate\Support\Carbon::create(2000,$person->birthday_month,$person->birthday_day)->format('d M'):'Needs setup') }}</td><td>{{ $person->start_date?->format('d M')??'Needs setup' }}</td></tr>@endforeach</tbody></table></div></details>
   @endif
   @foreach($groups as $kind=>$group)
    @php
     $schema=$group['schema'];if(!$full&&$kind==='staff')$schema['fields']=array_diff_key($schema['fields'],array_flip(['phone','email','next_of_kin_name','next_of_kin_phone']));
     $recordRows=$group['rows'];$recordSearch=trim($v['hr_record_q']??'');if($recordSearch!=='')$recordRows=$recordRows->filter(fn($r)=>str_contains(mb_strtolower($r['label'].' '.json_encode($r['data'])),mb_strtolower($recordSearch)));
     $recordPage=new \Illuminate\Pagination\LengthAwarePaginator($recordRows->forPage(max(1,request()->integer('hr_'.$kind.'_page',1)),10)->values(),$recordRows->count(),10,max(1,request()->integer('hr_'.$kind.'_page',1)),['path'=>route('portal.hr-workspace'),'pageName'=>'hr_'.$kind.'_page']);$recordPage->appends(request()->query());
    @endphp
    <section class="br-table-section"><header class="br-table-heading"><h4>{{ $schema['label'] }} <small>{{ $group['rows']->count() }}</small></h4><span class="br-table-hint">Current register · expand a row for full details</span></header>
     @if($write)<details class="mb-4"><summary class="hr-report-control hr-entry-private">Add record</summary>@include('portal.hr-reporting.register-form',['record'=>null])</details>@endif
     <div class="br-table-wrap"><table class="br-table"><thead><tr><th>Record</th>@foreach(array_slice($schema['fields'],0,3,true) as $f)<th>{{ $f['label'] }}</th>@endforeach<th>Details / Edit</th></tr></thead><tbody>
     @forelse($recordPage as $record)<tr><td><strong>{{ $record['label'] }}</strong></td>@foreach(array_slice($schema['fields'],0,3,true) as $field=>$f)<td>{{ $registers->value($record['data'][$field]??null,$f,auth()->user()) }}</td>@endforeach<td><details><summary class="hr-report-control">Open details</summary><dl class="grid gap-3 mt-3 sm:grid-cols-2">@foreach($schema['fields'] as $field=>$f)<div><dt class="br-help">{{ $f['label'] }}</dt><dd class="whitespace-pre-line">{{ $registers->value($record['data'][$field]??null,$f,auth()->user()) }}</dd></div>@endforeach</dl>
      @if($record['attachment'])<a class="hr-report-control" href="{{ route('portal.hr-registers.attachment',$record['id']) }}">Download document</a>@endif
      @if($record['url'])<a class="hr-report-control" href="{{ $record['url'] }}">Open source record</a>@endif
      @if($kind==='exit')<div class="mt-3"><h5 class="font-semibold">Assets to recover at clearance</h5>@forelse(\App\Models\Asset::where('assigned_to',$record['data']['staff_id']??0)->get() as $asset)<p>{{ $asset->asset_tag_or_code }} · {{ $asset->name }} · {{ $asset->quantity_procured??1 }} unit(s) · {{ $asset->location }}</p>@empty<p>No assets currently assigned.</p>@endforelse</div>@endif
      @if($write)<details class="mt-3"><summary class="hr-report-control">Edit record</summary>@include('portal.hr-reporting.register-form')</details><form class="mt-3" method="POST" action="{{ route('portal.hr-registers.destroy',[$kind,$record['id']]) }}" data-confirm="Archive this record? Its change history will be retained.">@csrf @method('DELETE')<input name="revision" type="hidden" value="{{ $record['revision'] }}"><button class="hr-report-control">{{ $kind==='staff'?'Suspend staff account':'Archive record' }}</button></form>@endif
      @if($full)<a class="hr-report-control mt-3" href="{{ route('portal.hr-registers.history',[$kind,$record['id']]) }}">Change history</a>@endif
     </details></td></tr>@empty<tr><td colspan="5" class="br-empty-cell">No records match this register.</td></tr>@endforelse
     </tbody></table></div>{{ $recordPage->links() }}
    </section>
   @endforeach
   @if($tab==='people'&&$full)<details class="rounded-xl border border-brand-white/10 p-4"><summary class="cursor-pointer font-semibold">Staff document checklist</summary>@foreach($registers->documentGaps(auth()->user()) as $gap)<p class="mt-3"><strong>{{ $gap['name'] }}</strong> · Missing: {{ implode(', ',$gap['missing']) }}</p>@endforeach</details>@endif
  </section>
 @endif
 @include('portal.hr-reporting.live-activities')
 @if($full)@include('portal.hr-reporting.saved-weekly-reports')@endif
</section>
