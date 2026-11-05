@php($value=$row[$column['key']]??null)
@if($column['format']==='people')
    @if(count($value??[]))
        <ul class="br-person-list">@foreach($value as $person)<li><strong>{{ $person['name'] }}</strong>@if($person['responsibility']??null)<span class="br-person-duty">{{ $person['responsibility'] }}</span>@endif</li>@endforeach</ul>
    @else<span class="br-help">No collaborators</span>@endif
@elseif($column['format']==='rich')
    <div class="weekly-rich-content">{!! $value?:'Not recorded' !!}</div>
@elseif($column['format']==='date')
    <span class="br-date">{{ $value?\Carbon\Carbon::parse($value)->format('d M Y'):'Not recorded' }}</span>
@elseif(in_array($column['format'],['number','percent']))
    {{ $value===null?'Not recorded':number_format((float)$value, is_float($value)?1:0) }}{{ $value!==null&&$column['format']==='percent'?'%':'' }}
@elseif(in_array($column['key'],['status','brief_status','priority','source_label'])&&$value)
    @php($tone=preg_match('/blocked|urgent|lost/i',$value)?'danger':(preg_match('/done|complet|approved|reviewed|won|closed/i',$value)?'success':(preg_match('/progress|high|pending|draft|awaiting|deferred|returned/i',$value)?'attention':(preg_match('/planned|scheduled|received|medium/i',$value)?'info':'neutral'))))
    <span class="br-status-badge br-status-{{ $tone }}">{{ $value }}</span>
@elseif($column['key']==='owner')
    <strong class="br-owner-name">{{ $value??'Not recorded' }}</strong>
@else{{ $value??'Not recorded' }}@endif
