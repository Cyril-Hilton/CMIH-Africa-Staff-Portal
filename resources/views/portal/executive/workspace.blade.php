@php
 $command=app(\App\Services\ExecutiveCommandCentre::class)->build(request())['command'];
 extract($command,EXTR_SKIP);
 $execUrl=fn(array $changes=[])=>route('dashboard',array_merge(request()->except(['page','exec_history_page','exec_page']),$changes)).'#executive-command';
 $projectLink=fn($row)=>$execUrl(array_merge(['exec_view'=>'projects','exec_project'=>null,'exec_activation'=>null,'exec_brief'=>null],match($row['source_kind']??'crm'){'activation'=>['exec_activation'=>$row['native_id']],'creative'=>['exec_brief'=>$row['native_id']],default=>['exec_project'=>$row['project']->id]}));
 $formatDate=fn($date)=>$date?\Illuminate\Support\Carbon::parse($date)->format('d M Y, H:i'):'Date setup needed';
@endphp
@include('portal.executive.styles')
@include('portal.executive.chart-styles')
<section id="executive-command" class="exec" data-no-silent aria-label="Executive command centre">
 <div class="top"><div><p class="eyebrow">CMIH Africa / Executive command centre</p><h2>Every project. Connected.</h2><p class="muted">Ownership, commitments and delivery across the organisation.</p></div><a class="button" href="{{ route('portal.project-journey') }}">Open relationship tracker ↗</a></div>
 <nav aria-label="Executive dashboard views">@foreach(config('executive_tracker.tabs') as $key=>$label)<a href="{{ $execUrl(['exec_view'=>$key]) }}" @if($view===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
 <form method="get" action="{{ route('dashboard') }}#executive-command" class="filters">
 @if(!empty($f['exec_activation']))<input type="hidden" name="exec_activation" value="{{ $f['exec_activation'] }}">@endif
@if(!empty($f['exec_brief']))<input type="hidden" name="exec_brief" value="{{ $f['exec_brief'] }}">@endif
<input type="hidden" name="exec_view" value="{{ $view }}"><input type="hidden" name="exec_calendar" value="{{ $f['exec_calendar']??'week' }}">
 <label>Period<select name="exec_period">@foreach(['day','week','month','year'] as $option)<option value="{{ $option }}" @selected($period===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
 <label>Reference date<input type="date" name="exec_date" value="{{ $anchor->toDateString() }}"></label>
 <label>Department<select name="exec_department"><option value="">All departments</option>@foreach($staff->pluck('department')->unique()->sort() as $dept)<option value="{{ \App\Models\User::normalizeDepartmentKey($dept) }}" @selected(($f['exec_department']??'')===\App\Models\User::normalizeDepartmentKey($dept))>{{ \App\Models\User::departmentLabel($dept) }}</option>@endforeach</select></label>
 <label>Team member<select name="exec_person"><option value="">Everyone</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int)($f['exec_person']??0)===$person->id)>{{ $person->name }}</option>@endforeach</select></label>
 <label>Project<select name="exec_project"><option value="">All projects</option>@foreach($projectRows->filter(fn($row)=>($row['source_kind']??'crm')==='crm') as $row)<option value="{{ $row['project']->id }}" @selected((int)($f['exec_project']??0)===$row['project']->id)>{{ $row['project']->client_name }} · {{ $row['project']->project_name }}</option>@endforeach</select></label>
 <label>Find<input name="exec_q" value="{{ $f['exec_q']??'' }}" placeholder="Project, client or owner"></label>
 <label>Source<select name="exec_scope">@foreach(['all'=>'All sources','work'=>'Work & deadlines','activity'=>'Reported activity','meetings'=>'Meetings & transport','availability'=>'Availability','attendance'=>'Recorded attendance'] as $key=>$label)<option value="{{ $key }}" @selected(($f['exec_scope']??'all')===$key)>{{ $label }}</option>@endforeach</select></label><button class="primary">Apply filters</button><a class="button" href="{{ $execUrl(['exec_activation'=>null,'exec_brief'=>null,'exec_project'=>null,'exec_person'=>null,'exec_department'=>null,'exec_q'=>null,'exec_scope'=>'all']) }}">Clear selection</a>
 </form>
 <p class="report-note">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · Live source records. Missing plans receive no readiness credit; reported progress and independently verified delivery remain distinct.</p>
 @if($view!=='reports')
 @include('portal.executive.tab-chart',['chartSpec'=>app(\App\Services\ExecutiveTabCharts::class)->forTab($command)])
 @endif
 @if($view==='reports')
 @include('portal.executive.reports')
 @else
 @include('portal.executive.'.$view)
 @endif
</section>
