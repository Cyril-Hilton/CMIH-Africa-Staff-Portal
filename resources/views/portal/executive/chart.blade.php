@php
 $chartItems=$chartData->sortDesc()->take(15);
 $chartMax=($chartSuffix??'')==='%'?100:max(1,(float)$chartItems->max());
@endphp
<div class="panel"><h3>{{ $chartTitle }}</h3>
@forelse($chartItems as $label=>$value)<div class="between"><span class="muted">{{ $label }}</span><strong>{{ number_format($value,is_float($value)?1:0) }}{{ $chartSuffix??'' }}</strong></div><div class="bar" role="img" aria-label="{{ $label }}: {{ $value }}{{ $chartSuffix??'' }}"><span style="width:{{ 100*$value/$chartMax }}%"></span></div>
@empty<p class="empty">0 qualifying records in this selection.</p>@endforelse
@if($chartData->count()>15)<p class="muted">Top 15 of {{ $chartData->count() }} groups. Refine the filters for detail.</p>@endif
</div>
