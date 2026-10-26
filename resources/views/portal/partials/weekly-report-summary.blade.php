@php
$summaryPeriod=request('reporting_summary_period','weekly');if(!in_array($summaryPeriod,['weekly','monthly','quarterly','yearly']))$summaryPeriod='weekly';
$anchor=\Carbon\CarbonImmutable::parse(request('weekly_from')?:now()->toDateString());
$summaryFrom=match($summaryPeriod){'monthly'=>$anchor->startOfMonth(),'quarterly'=>$anchor->startOfQuarter(),'yearly'=>$anchor->startOfYear(),default=>$anchor->startOfWeek()};
$summaryTo=match($summaryPeriod){'monthly'=>$anchor->endOfMonth(),'quarterly'=>$anchor->endOfQuarter(),'yearly'=>$anchor->endOfYear(),default=>$anchor->endOfWeek()};
$reportSummary=app(\App\Services\WeeklyReportingHistory::class)->summary($key,$summaryFrom->toDateString(),$summaryTo->toDateString());
@endphp
<section class="my-4 rounded-xl border border-brand-white/15 p-4" aria-label="Weekly reporting accountability">
<h5 class="font-semibold">Work & Reporting Accountability</h5>
<p class="dw-meta">{{ $summaryFrom->format('d M Y') }}–{{ $summaryTo->format('d M Y') }} · {{ $reportSummary['entries'] }} weekly entries · {{ $reportSummary['projects'] }} linked project series · {{ $reportSummary['unlinked_history'] }} unlinked historical entries. Historical project links are not guessed.</p>
<div class="mt-3 flex flex-wrap gap-2">@foreach(['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $value=>$label)<a class="dw-link" href="{{ route('dashboard',array_merge(request()->query(),['weekly_department'=>$key,'reporting_summary_period'=>$value])) }}#staff-weekly-performance">{{ $label }}</a>@endforeach</div>
<div class="mt-4 grid gap-3 sm:grid-cols-2"><div><h6 class="font-semibold">Work Status · Latest Report per Workstream</h6>@foreach(['Done'=>'Reported Completed','In Progress'=>'In Progress / Pending','Planned'=>'Planned','Blocked'=>'Blocked','Deferred'=>'Deferred'] as $status=>$label)<p class="dw-meta">{{ $label }}: {{ $reportSummary['work'][$status]??0 }}</p>@endforeach</div><div><h6 class="font-semibold">Report Status</h6>@foreach(['draft'=>'Draft','submitted'=>'Submitted','reviewed'=>'Reviewed','legacy_unreviewed'=>'Historical · Not Independently Reviewed'] as $status=>$label)<p class="dw-meta">{{ $label }}: {{ $reportSummary['reports'][$status]??0 }}</p>@endforeach<p class="dw-meta">Missing Weeks (tracked projects): {{ $reportSummary['missing'] }}</p></div></div>
<p class="dw-meta">Weekly entries belong to the month/year in which their reporting week starts. Yearly totals read the underlying weekly records directly, so boundary weeks are not counted twice. Reported completion is separate from verified performance. Missing measures are never treated as zero, and percentages are not added together.</p>
</section>
