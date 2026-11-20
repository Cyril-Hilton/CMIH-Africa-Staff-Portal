@php
 $spec=$chartSpec;
 $palette=['#93e5da','#86baff','#f1c782','#c4a4f5','#faab9b','#83ccd8','#d2d996','#a8c2d8','#d9a8ce','#b7d5bd'];
 $allRows=collect($spec['rows']);
 $plotRows=$spec['type']==='columns'?$allRows:$allRows->take(10);
 $maximum=$spec['unit']==='%'?100:max(1,$plotRows->flatMap(fn($row)=>$row['values'])->max()??0);
 $power=10**floor(log10($maximum));
 $ceiling=$spec['unit']==='%'?100:collect([1,2,5,10])->map(fn($n)=>$n*$power)->first(fn($n)=>$n>=$maximum);
 $ticks=$spec['unit']==='%'?[0,25,50,75,100]:collect(range(0,4))->map(fn($i)=>ceil($ceiling*$i/4))->unique()->values()->all();
 $format=fn($n)=>number_format($n,($spec['unit']==='%'&&floor($n)!=$n)?1:0).($spec['unit']==='%'?'%':'');
@endphp
<section class="exec-chart-panel panel" data-executive-top-chart="{{ $view }}" aria-labelledby="{{ $spec['id'] }}-heading">
 <div class="exec-chart-heading">
  <div><p class="eyebrow">{{ config('executive_tracker.tabs.'.$view) }} / Live perspective</p><h3 id="{{ $spec['id'] }}-heading">{{ $spec['title'] }}</h3><p class="muted">{{ $spec['subtitle'] }}</p></div>
  <div class="exec-chart-total"><strong>{{ number_format($spec['total']) }}</strong><span>{{ $spec['headline'] }}</span><small>{{ $from->format('d M') }} — {{ $to->format('d M Y') }}</small></div>
 </div>
 @if(count($spec['series'])>1)<div class="exec-chart-legend">@foreach($spec['series'] as $index=>$label)<span><i style="--series-color:{{ $palette[$index] }}"></i>{{ $label }}</span>@endforeach</div>@endif
 @if($spec['type']==='donut')
 <div class="exec-chart-orbit-layout">
  <svg class="exec-chart-orbit" viewBox="0 0 300 300" role="img" aria-labelledby="{{ $spec['id'] }}-title {{ $spec['id'] }}-description">
   <title id="{{ $spec['id'] }}-title">{{ $spec['subtitle'] }}</title><desc id="{{ $spec['id'] }}-description">{{ $spec['total'] }} {{ $spec['headline'] }}. Exact category values follow.</desc>
   <defs><radialGradient id="{{ $spec['id'] }}-glow"><stop stop-color="#8fe0db" stop-opacity=".12"/><stop offset="1" stop-color="#8fe0db" stop-opacity="0"/></radialGradient></defs>
   <circle cx="150" cy="150" r="148" fill="url(#{{ $spec['id'] }}-glow)"/><circle cx="150" cy="150" r="126" fill="none" stroke="#ffffff" stroke-opacity=".05"/>
   <circle cx="150" cy="150" r="104" fill="none" stroke="#344b5a" stroke-width="23"/>
   @php $offset=0; @endphp
   @foreach($allRows as $index=>$row)
    @php $arc=$spec['total']>0?653.45127*$row['values'][0]/$spec['total']:0; @endphp
    @if($arc>0)<circle class="exec-chart-segment" cx="150" cy="150" r="104" fill="none" stroke="{{ $palette[$index%count($palette)] }}" stroke-width="23" stroke-dasharray="{{ max(0,$arc-min(3,$arc*.08)) }} 653.45127" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 150 150)"><title>{{ $row['label'] }}: {{ $format($row['values'][0]) }}</title></circle>@endif
    @php $offset+=$arc; @endphp
   @endforeach
   <text x="150" y="147" text-anchor="middle" class="exec-chart-ring-value">{{ number_format($spec['total']) }}</text><text x="150" y="172" text-anchor="middle" class="exec-chart-ring-label">MATCHING RECORDS</text>
  </svg>
  <div class="exec-chart-distribution">@forelse($allRows as $index=>$row)<div><div class="exec-chart-category"><span><i style="--series-color:{{ $palette[$index%count($palette)] }}"></i>{{ $row['label'] }}</span><strong>{{ $format($row['values'][0]) }}</strong></div><div class="exec-chart-track"><span style="width:{{ $spec['total']>0?100*$row['values'][0]/$spec['total']:0 }}%;--series-color:{{ $palette[$index%count($palette)] }}"></span></div></div>@empty<p class="empty">0 qualifying records in this selection.</p>@endforelse</div>
 </div>
 @else
 @php
  $seriesCount=count($spec['series']);$rowHeight=36+14*$seriesCount;
  $height=$spec['type']==='columns'?350:70+max(1,$plotRows->count())*$rowHeight;
 @endphp
 <div class="exec-chart-canvas scroll">
  <svg viewBox="0 0 1100 {{ $height }}" class="exec-chart-svg" role="img" aria-labelledby="{{ $spec['id'] }}-title {{ $spec['id'] }}-description">
   <title id="{{ $spec['id'] }}-title">{{ $spec['subtitle'] }}</title><desc id="{{ $spec['id'] }}-description">{{ $spec['note'] }} Exact values are available in the chart data table.</desc>
   <defs>@foreach($palette as $index=>$color)<linearGradient id="{{ $spec['id'] }}-fill-{{ $index }}" x1="0" y1="0" x2="1" y2="1"><stop stop-color="{{ $color }}" stop-opacity=".55"/><stop offset="1" stop-color="{{ $color }}"/></linearGradient>@endforeach</defs>
   @if($spec['type']==='columns')
    @foreach($ticks as $tick)@php $y=280-240*$tick/$ceiling; @endphp<line x1="55" x2="1080" y1="{{ $y }}" y2="{{ $y }}" class="exec-chart-grid"/><text x="39" y="{{ $y+4 }}" text-anchor="end" class="exec-chart-axis">{{ $format($tick) }}</text>@endforeach
    @php $slot=1025/max(1,$plotRows->count()); @endphp
    @foreach($plotRows as $index=>$row)
     @foreach($row['values'] as $series=>$value)@php $barWidth=min(40,$slot*.7/$seriesCount); @endphp<rect class="exec-chart-mark" x="{{ 55+$index*$slot+$slot*.15+$series*$barWidth }}" y="{{ 280-240*$value/$ceiling }}" width="{{ max(1,$barWidth-2) }}" height="{{ 240*$value/$ceiling }}" rx="3" fill="url(#{{ $spec['id'] }}-fill-{{ $series }})"><title>{{ $row['label'] }} · {{ $spec['series'][$series] }}: {{ $format($value) }}</title></rect>@endforeach
     @if($index%max(1,(int)ceil($plotRows->count()/12))===0||$loop->last)<text x="{{ 55+($index+.5)*$slot }}" y="307" text-anchor="middle" class="exec-chart-axis">{{ $row['label'] }}</text>@endif
    @endforeach
   @else
    @foreach($ticks as $tick)@php $x=350+670*$tick/$ceiling; @endphp<line x1="{{ $x }}" x2="{{ $x }}" y1="30" y2="{{ $height-25 }}" class="exec-chart-grid"/><text x="{{ $x }}" y="18" text-anchor="middle" class="exec-chart-axis">{{ $format($tick) }}</text>@endforeach
    @foreach($plotRows as $index=>$row)
     @php $y=55+$index*$rowHeight; @endphp
     <text x="4" y="{{ $y+10 }}" class="exec-chart-label"><title>{{ $row['label'] }}</title>{{ \Illuminate\Support\Str::limit($row['label'],40) }}</text>
     @foreach($row['values'] as $series=>$value)
      @php $colorIndex=$seriesCount===1?$index%count($palette):$series; @endphp
      <rect x="350" y="{{ $y+$series*14 }}" width="670" height="10" rx="5" fill="#ffffff" fill-opacity=".035"/>
      <rect class="exec-chart-mark" x="350" y="{{ $y+$series*14 }}" width="{{ 670*$value/$ceiling }}" height="10" rx="5" fill="url(#{{ $spec['id'] }}-fill-{{ $colorIndex }})"><title>{{ $row['label'] }} · {{ $spec['series'][$series] }}: {{ $format($value) }}</title></rect>
      <text x="1038" y="{{ $y+9+$series*14 }}" class="exec-chart-axis">{{ $format($value) }}</text>
     @endforeach
    @endforeach
    @if($plotRows->isEmpty())<text x="550" y="65" text-anchor="middle" class="exec-chart-axis">0 qualifying records in this selection</text>@endif
   @endif
  </svg>
 </div>
 @endif
 <p class="exec-chart-note">{{ $spec['note'] }}@if($plotRows->count()<$allRows->count()&&$spec['type']!=='donut') Showing {{ $plotRows->count() }} of {{ $allRows->count() }} groups; all groups are listed below.@endif</p>
 <details class="exec-chart-data"><summary>View chart data · {{ $allRows->count() }} {{ $spec['type']==='columns'?'dates':'groups' }}</summary><div class="scroll"><table><caption class="sr-only">{{ $spec['subtitle'] }} · exact values</caption><thead><tr><th>{{ $spec['type']==='columns'?'Date':'Group' }}</th>@foreach($spec['series'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($allRows as $row)<tr><td>{{ $row['label'] }}</td>@foreach($row['values'] as $value)<td>{{ $format($value) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($spec['series'])+1 }}">0 qualifying records</td></tr>@endforelse</tbody></table></div></details>
</section>
