@php
$fPoints=collect($f_months);$fKnown=$fPoints->flatMap(fn($p)=>[$p['revenue'],$p['receipts'],$p['expenses']])->filter(fn($v)=>$v!==null);
$fMax=max(1,ceil(($fKnown->max()??0)/4)*4);
$fX=fn($i)=>70+($fPoints->count()>1?$i/($fPoints->count()-1)*1050:525);$fY=fn($n)=>300-((float)$n/$fMax*245);
@endphp
<article class="fd-panel" aria-label="Revenue, receipts and expenses chart" x-data="{revenue:true,receipts:true,expenses:true}">
<div class="flex flex-wrap justify-between items-center gap-3"><div><p class="fd-kicker">The financial picture</p><h4 class="text-xl font-semibold mt-2">Revenue, income & expenses</h4></div><div class="flex flex-wrap gap-4 text-xs">@foreach(['revenue'=>['Revenue','var(--fd-revenue)'],'receipts'=>['Cash received','var(--fd-receipts)'],'expenses'=>['Expenses','var(--fd-expenses)']] as $series=>[$label,$color])<label class="flex items-center gap-2"><input type="checkbox" x-model="{{ $series }}" style="width:auto;margin:0;accent-color:{{ $color }}"><span style="color:{{ $color }}">{{ $label }}</span></label>@endforeach</div></div>
<svg viewBox="0 0 1180 350" role="img" aria-labelledby="finance-chart-title finance-chart-desc" preserveAspectRatio="none"><title id="finance-chart-title">Filtered financial activity in GHS</title><desc id="finance-chart-desc">Reviewed monthly reporting amounts. Revenue and cash received are distinct series. Missing amounts leave gaps; confirmed zeros remain zero. Pending entries and partially selected periods are excluded.</desc>
@foreach(range(0,4) as $i)@php
$y=300-$i*61.25;
@endphp
<line x1="70" y1="{{ $y }}" x2="1120" y2="{{ $y }}" stroke="#ffffff16"/><text x="58" y="{{ $y+4 }}" fill="#9aaec5" text-anchor="end" font-size="11">{{ number_format($fMax*$i/4,0) }}</text>@endforeach
@foreach(['revenue'=>'var(--fd-revenue)','receipts'=>'var(--fd-receipts)','expenses'=>'var(--fd-expenses)'] as $series=>$color)
<g x-show="{{ $series }}" data-finance-series="{{ $series }}">
@if($fPoints->count()===1)
@php
$barValue=$fPoints->first()[$series];
@endphp
@php
$barX=250+array_search($series,['revenue','receipts','expenses'])*300;
@endphp
@if($barValue!==null)<rect data-finance-value="{{ $barValue }}" x="{{ $barX-60 }}" y="{{ $fY($barValue) }}" width="120" height="{{ max(0,300-$fY($barValue)) }}" rx="8" fill="{{ $color }}" fill-opacity=".8"><title>{{ ucfirst($series) }}: {{ $fMoney($barValue) }}</title></rect><text x="{{ $barX }}" y="{{ max(30,$fY($barValue)-12) }}" fill="{{ $color }}" text-anchor="middle" font-size="13">{{ number_format($barValue,2) }}</text>
@else<text x="{{ $barX }}" y="280" fill="{{ $color }}" text-anchor="middle" font-size="13">Not recorded</text>@endif
<text x="{{ $barX }}" y="330" fill="{{ $color }}" text-anchor="middle" font-size="11">{{ $series==='receipts'?'Cash received':ucfirst($series) }}</text>
@else
@php
$segments=[];$segment=[];
foreach($fPoints as $i=>$point){if($point[$series]===null){if($segment)$segments[]=$segment;$segment=[];}else{$segment[]=['x'=>$fX($i),'y'=>$fY($point[$series]),'label'=>$point['label'],'value'=>$point[$series]];}}
if($segment)$segments[]=$segment;
@endphp
@foreach($segments as $segment)
@if(count($segment)>1)<polyline points="{{ collect($segment)->map(fn($p)=>$p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="{{ $color }}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" @if($series!=='revenue')stroke-dasharray="{{ $series==='receipts'?'8 5':'3 5' }}"@endif/>@endif
@foreach($segment as $point)<circle data-finance-value="{{ $point['value'] }}" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="{{ $color }}" stroke="#132031" stroke-width="2"><title>{{ $point['label'] }} · {{ $series }}: {{ $fMoney($point['value']) }}</title></circle>@endforeach
@endforeach
@endif
</g>@endforeach
@foreach($fPoints as $i=>$point)@if($fPoints->count()>1&&($fPoints->count()<=13||$i%max(1,(int)ceil($fPoints->count()/12))===0))<text x="{{ $fX($i) }}" y="330" fill="#bac7d8" text-anchor="middle" font-size="11">{{ $point['label'] }}</text>@endif @endforeach
</svg><p class="fd-muted">GHS · Known reviewed amounts only. Gaps mean no amount is recorded for that month; zero means a recorded zero. Revenue and expenses follow the source period, while cash follows the actual receipt date. Monthly totals are not spread into invented daily transactions.</p>
</article>
