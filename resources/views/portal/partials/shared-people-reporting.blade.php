<div class="dw-people">
@foreach(['hr'=>'Human Resources','administrator'=>'Administrator'] as $type=>$label)
<section>
    <div class="dw-heading"><h5>{{ $label }}</h5></div>
    <p class="dw-meta">Approved results with the preceding period alongside. {{ $department['people_reporting']['publication'][$type]['approved']??0 }} approved reports · {{ $department['people_reporting']['publication'][$type]['awaiting']??0 }} reports not yet approved. Unapproved content remains private.</p>
    @php($lastGroup=null)
    @forelse($department['people_reporting'][$type]??[] as $metric)
        @if(($metric['group']??'Key Metrics')!==$lastGroup)
            @php($lastGroup=$metric['group']??'Key Metrics')
            <h6 class="mt-5 border-b border-current/10 pb-2 text-sm font-semibold">{{ $lastGroup }}</h6>
        @endif
        <div class="dw-fact" style="flex-wrap:wrap;gap:12px">
            <div style="min-width:0;flex:1 1 180px">{{ $metric['metric'] }}<p class="dw-meta">{{ $metric['period'] }}</p></div>
            <div style="text-align:right;max-width:100%;overflow-wrap:anywhere">
                <strong>{{ $metric['actual']===null?'Not Recorded':$metric['actual'].' '.$metric['unit'] }}</strong>
                <p class="dw-meta">Previous: {{ ($metric['previous']??null)===null?'Not Recorded':$metric['previous'].' '.$metric['unit'] }}
                    @if(($metric['change']??null)!==null)<span> · {{ $metric['change']>0?'+':'' }}{{ $metric['change'] }}{{ $metric['unit']==='%'?' pp':'' }}</span>@endif
                </p>
            </div>
        </div>
    @empty
        <p class="dw-empty">No approved figures for this period.</p>
    @endforelse
</section>
@endforeach
</div>
