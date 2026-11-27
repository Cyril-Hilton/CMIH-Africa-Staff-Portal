@php
 $bkAttendance=$sourcePerformance['attendance'];
 $bkGaps=collect($sourcePerformance['areas'])->filter(fn($area)=>!empty($area['result_gap']))->count();
@endphp
<section class="brands-kpi-workspace" aria-label="Automatic department KPI reporting" data-source-department="{{ $sourcePerformance['department'] }}">
 <header class="bk-heading">
  <div><span class="bk-eyebrow">{{ \App\Models\User::departmentLabel($sourcePerformance['department']) }} · Performance</span><h4>KPI Achievement & Reporting Gaps</h4><p class="bk-muted">{{ ($sourcePerformance['native_sources']??false)?'Live portal records & departmental results':'Published department results' }} · {{ $sourcePerformance['provisional']?'Current period · provisional':'Selected historical period' }}</p></div>
  <div class="bk-overall"><span>Business KPI credit</span><strong>{{ $sourcePerformance['has_measured_result']?$sourcePerformance['achievement_credit']:'—' }}<small>{{ $sourcePerformance['has_measured_result']?' / 100':' Not measured' }}</small></strong><span>Equal area shares</span></div>
 </header>
 <div class="bk-summary"><span><i aria-hidden="true"></i><strong>{{ $sourcePerformance['reported'] }} / {{ $sourcePerformance['expected'] }}</strong> {{ ($sourcePerformance['native_sources']??false)?'areas with source evidence':'areas reported' }}</span><span><strong>{{ $sourcePerformance['coverage']??0 }}%</strong> coverage</span><span><strong>{{ $bkGaps }}</strong> {{ $bkGaps===1?'area needs':'areas need' }} an update</span></div>
 <div class="bk-grid">
 @foreach($sourcePerformance['areas'] as $area)
  <article class="bk-card" data-kpi-area="{{ $area['name'] }}" data-measurement-state="{{ $area['measurement_state'] }}">
   <div class="bk-card-top"><span class="bk-index">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span class="bk-state" @if($area['achievement_credit']>=100)data-complete="true"@endif>{{ $area['state'] }}</span></div>
   <h5>{{ $area['name'] }}</h5>
   @php
    $bkMeasured=$area['measurement_state']==='measured';
    $bkLabel=match($area['measurement_state']){'pending_review'=>'Review pending','not_applicable'=>'Not assigned','no_records'=>'No source records',default=>'Not measured'};
   @endphp
   <p class="bk-value">{{ $bkMeasured?$area['achievement_credit']:'—' }}<span>{{ $bkMeasured?' / 100':' '.$bkLabel }}</span></p><p class="bk-muted">{{ $bkMeasured?'Achievement credit':'Source records are not achievement credit' }}</p>
   <div class="bk-progress" @if($bkMeasured)role="meter" aria-label="{{ $area['name'] }} achievement credit" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $area['achievement_credit'] }}"@else aria-hidden="true"@endif><span style="width:{{ $bkMeasured?max(0,min(100,$area['achievement_credit'])):0 }}%"></span></div>
   <p class="bk-records">{{ $area['records'] }} source records</p>
   @if($area['result_gap'])<details class="bk-update"><summary>Update & gap details <span aria-hidden="true">⌄</span></summary><p>{{ $area['result_gap'] }}</p></details>@endif
  </article>
 @endforeach
 </div>
 <details class="bk-disclosure"><summary><span>How this score works</span><span aria-hidden="true">⌄</span></summary><p>{{ $sourcePerformance['scoring_note'] }} Recorded entries establish reporting coverage; only measured outcomes earn achievement credit. A dash means no measured result yet, while 0 / 100 means a measured zero. Missing measures retain zero credited weight until resolved. Attendance and punctuality are measured separately below; punctuality contributes 10% to the overall score.</p></details>
 <div class="bk-attendance" aria-label="Recorded attendance results">
  <div><h5>Attendance</h5><p class="bk-muted">Actual clock-in timestamps and eligible working days</p></div>
  <div class="bk-attendance-stats"><span><strong>{{ $bkAttendance['clocked'] }} / {{ $bkAttendance['expected'] }}</strong>staff-days clocked</span><span><strong>{{ round($bkAttendance['attendance_credit'],1) }}%</strong>attendance</span><span><strong>{{ $bkAttendance['punctual'] }}</strong>on time</span><span><strong>{{ $bkAttendance['late'] }}</strong>late</span><span><strong>{{ $bkAttendance['missing'] }}</strong>missing clock-ins</span></div>
  <details class="bk-disclosure"><summary>Punctuality · {{ round($bkAttendance['punctuality_credit'],1) }}% <span aria-hidden="true">⌄</span></summary><p>Weekends, company holidays, future days and approved leave are excluded. No eligible days earns no attendance credit.</p></details>
 </div>
 @if(count($sourcePerformance['metrics']))<details class="bk-disclosure bk-calculations"><summary><span>Source KPI calculations <small>{{ count($sourcePerformance['metrics']) }} measures</small></span><span aria-hidden="true">⌄</span></summary><div class="bk-table-wrap"><table><thead><tr><th>Source KPI</th><th>Achievement credit</th><th>Result coverage</th><th>Calculation / follow-up</th></tr></thead><tbody>
 @foreach($sourcePerformance['metrics'] as $metric)<tr><th>{{ $metric['name'] }}</th><td>@if($metric['has_measured_result']){{ $metric['credited'] }}%@else — <span class="bk-target-gap">Not measured · {{ $metric['credited'] }}% credited</span>@endif</td><td>{{ $metric['expected']?($metric['reported'].' / '.$metric['expected'].' results reported'):'No measured obligations in scope' }}</td><td><strong>{{ $metric['state'] }}</strong><p>{{ $metric['note'] }}</p></td></tr>@endforeach
 </tbody></table></div></details>@endif
</section>
