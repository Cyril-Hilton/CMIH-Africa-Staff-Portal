@php
 $command=app(\App\Services\ExecutiveCommandCentre::class)->build(request())['command'];
 extract($command,EXTR_SKIP);
 $execUrl=fn(array $changes=[])=>route('dashboard',array_merge(request()->except(['page','exec_history_page']),$changes)).'#executive-command';
 $formatDate=fn($date)=>$date?\Illuminate\Support\Carbon::parse($date)->format('d M Y, H:i'):'Date setup needed';
@endphp
@include('portal.executive.styles')
<section id="executive-command" class="exec" aria-label="Executive command centre">
 <div class="top"><div><p class="eyebrow">CMIH Africa / Executive command centre</p><h2>Every project. Connected.</h2><p class="muted">Ownership, commitments and delivery across the organisation.</p></div><a class="button" href="{{ route('portal.project-journey') }}">Open relationship tracker ↗</a></div>
 <nav aria-label="Executive dashboard views">@foreach(config('executive_tracker.tabs') as $key=>$label)<a href="{{ $execUrl(['exec_view'=>$key]) }}" @if($view===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
 <form method="get" action="{{ route('dashboard') }}#executive-command" class="filters">
 <input type="hidden" name="exec_view" value="{{ $view }}"><input type="hidden" name="exec_calendar" value="{{ $f['exec_calendar']??'week' }}">
 <label>Period<select name="exec_period">@foreach(['day','week','month','year'] as $option)<option value="{{ $option }}" @selected($period===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
 <label>Reference date<input type="date" name="exec_date" value="{{ $anchor->toDateString() }}"></label>
 <label>Department<select name="exec_department"><option value="">All departments</option>@foreach($staff->pluck('department')->unique()->sort() as $dept)<option value="{{ \App\Models\User::normalizeDepartmentKey($dept) }}" @selected(($f['exec_department']??'')===\App\Models\User::normalizeDepartmentKey($dept))>{{ \App\Models\User::departmentLabel($dept) }}</option>@endforeach</select></label>
 <label>Team member<select name="exec_person"><option value="">Everyone</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int)($f['exec_person']??0)===$person->id)>{{ $person->name }}</option>@endforeach</select></label>
 <label>Project<select name="exec_project"><option value="">All projects</option>@foreach($projectRows as $row)<option value="{{ $row['project']->id }}" @selected((int)($f['exec_project']??0)===$row['project']->id)>{{ $row['project']->client_name }} · {{ $row['project']->project_name }}</option>@endforeach</select></label>
 <label>Find<input name="exec_q" value="{{ $f['exec_q']??'' }}" placeholder="Project, client or owner"></label>
 <label>Source<select name="exec_scope">@foreach(['all'=>'All sources','work'=>'Work & deadlines','activity'=>'Reported activity','meetings'=>'Meetings & transport','availability'=>'Availability','attendance'=>'Recorded attendance'] as $key=>$label)<option value="{{ $key }}" @selected(($f['exec_scope']??'all')===$key)>{{ $label }}</option>@endforeach</select></label><button class="primary">Apply filters</button>
 </form>
 <p class="report-note">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · Live source records. Missing plans receive no readiness credit; reported progress and independently verified delivery remain distinct.</p>
 @if($view==='reports')
 @include('portal.executive.reports')
 @else
 @include('portal.executive.'.$view)
 @endif
</section>
