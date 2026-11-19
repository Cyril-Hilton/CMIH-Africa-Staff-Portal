@php
$fPoints=collect($f_months);$fMax=max(1,ceil($fPoints->flatMap(fn($p)=>[$p['revenue'],$p['receipts'],$p['expenses']])->max()/4)*4);
$fX=fn($i)=>70+($fPoints->count()>1?$i/($fPoints->count()-1)*1050:525);$fY=fn($n)=>300-((float)$n/$fMax*245);
@endphp
<article class="fd-panel" aria-label="Revenue, receipts and expenses chart" x-data="{revenue:true,receipts:true,expenses:true}">
<div class="flex flex-wrap justify-between items-center gap-3"><div><p class="fd-kicker">The financial picture</p><h4 class="text-xl font-semibold mt-2">Revenue, income & expenses</h4></div><div class="flex flex-wrap gap-4 text-xs">@foreach(['revenue'=>['Revenue','#e6c78d'],'receipts'=>['Cash received','#67d8bb'],'expenses'=>['Expenses','#85b5f1']] as $series=>[$label,$color])<label class="flex items-center gap-2"><input type="checkbox" x-model="{{ $series }}" style="width:auto;margin:0;accent-color:{{ $color }}"><span style="color:{{ $color }}">{{ $label }}</span></label>@endforeach</div></div>
<svg viewBox="0 0 1180 350" role="img" aria-labelledby="finance-chart-title finance-chart-desc" preserveAspectRatio="none"><title id="finance-chart-title">Filtered financial activity in GHS</title><desc id="finance-chart-desc">Monthly reporting amounts. Revenue and cash received are distinct series; pending entries and partially selected periods are excluded.</desc><defs><linearGradient id="finance-gold-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#e6c78d" stop-opacity=".2"/><stop offset="100%" stop-color="#e6c78d" stop-opacity="0"/></linearGradient></defs>
@foreach(range(0,4) as $i)@php
$y=300-$i*61.25;
@endphp<line x1="70" y1="{{ $y }}" x2="1120" y2="{{ $y }}" stroke="#ffffff16"/><text x="58" y="{{ $y+4 }}" fill="#9aaec5" text-anchor="end" font-size="11">{{ number_format($fMax*$i/4,0) }}</text>@endforeach
@foreach(['revenue'=>'#e6c78d','receipts'=>'#67d8bb','expenses'=>'#85b5f1'] as $series=>$color)
<g x-show="{{ $series }}">@if($fPoints->count()===1)
@php
$barIndex=array_search($series,['revenue','receipts','expenses']);$barValue=$fPoints->first()[$series];$barX=250+$barIndex*300;
@endphp
<rect x="{{ $barX-60 }}" y="{{ $fY($barValue) }}" width="120" height="{{ max(0,300-$fY($barValue)) }}" rx="8" fill="{{ $color }}" fill-opacity=".8"><title>{{ ucfirst($series) }}: {{ $fMoney($barValue) }}</title></rect><text x="{{ $barX }}" y="{{ max(30,$fY($barValue)-12) }}" fill="{{ $color }}" text-anchor="middle" font-size="13">{{ number_format($barValue,2) }}</text><text x="{{ $barX }}" y="330" fill="{{ $color }}" text-anchor="middle" font-size="11">{{ $series==='receipts'?'Cash received':ucfirst($series) }}</text>
@else
@if($series==='revenue'&&$fPoints->count()>1)<polygon points="70,300 {{ $fPoints->map(fn($p,$i)=>$fX($i).','.$fY($p[$series]))->implode(' ') }} 1120,300" fill="url(#finance-gold-area)"/>@endif<polyline points="{{ $fPoints->map(fn($p,$i)=>$fX($i).','.$fY($p[$series]))->implode(' ') }}" fill="none" stroke="{{ $color }}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" @if($series!=='revenue')stroke-dasharray="{{ $series==='receipts'?'8 5':'3 5' }}"@endif/>
@foreach($fPoints as $i=>$point)<circle cx="{{ $fX($i) }}" cy="{{ $fY($point[$series]) }}" r="4" fill="{{ $color }}" stroke="#132031" stroke-width="2"><title>{{ $point['label'] }} · {{ $series }}: {{ $fMoney($point[$series]) }}</title></circle>@endforeach @endif</g>@endforeach
@foreach($fPoints as $i=>$point)@if($fPoints->count()>1&&($fPoints->count()<=13||$i%max(1,(int)ceil($fPoints->count()/12))===0))<text x="{{ $fX($i) }}" y="330" fill="#bac7d8" text-anchor="middle" font-size="11">{{ $point['label'] }}</text>@endif @endforeach
</svg><p class="fd-muted">GHS · Reported by month of the source period. A zero series means no reviewed amount matches these filters. Monthly totals are not spread over invented daily transactions.</p>
</article>
