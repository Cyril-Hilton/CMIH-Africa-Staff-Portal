@php
 $eventCompact=$compact??false;
 $eventState=$event['dependency_blocked']&&!$event['done']?'Waiting on upstream delivery':($event['leave_overlap']&&(!$event['done']||$event['kind']==='resources')?'Approved leave · coverage review':($event['late']?($event['done']?'Recorded late outcome':'Past recorded deadline'):ucwords(str_replace('_',' ',$event['state']))));
 $eventDate=$event['start']->format($event['date_only']?'d M Y':'d M Y, H:i');
 $eventEnd=$event['end']->format($event['date_only']?'d M Y':'d M Y, H:i');
 $eventRange=$event['start']->toDateString()!==$event['end']->toDateString()||(!$event['date_only']&&!$event['start']->equalTo($event['end']));
@endphp
<article class="event {{ $event['late']?'late':'' }} {{ $eventCompact?'event-compact':'' }}">
 <div class="event-flags"><span class="badge">{{ ucwords(str_replace('_',' ',$event['kind'])) }}</span><span class="badge {{ $event['late']&&!$event['leave_overlap']?'danger':'' }}">{{ $eventState }}</span><span class="event-date">{{ $eventDate }}@if($eventRange) → {{ $eventEnd }}@endif</span></div>
 <div class="event-body"><div class="event-title"><h4>@if($event['url'])<a href="{{ $event['url'] }}">{{ $event['title'] }}</a>@else
{{ $event['title'] }}@endif</h4>@unless($eventCompact)<p class="muted">{{ $event['person']?->name??'Owner setup needed' }}</p>@endunless
 @if($event['project']&&$event['title']!==$event['project']->project_name)<p class="muted">{{ $event['project']->client_name }} · {{ $event['project']->project_name }}</p>@endif</div>
 @if(count($event['support']))<div class="event-support"><span class="muted">Collaborators</span><p>{{ collect($event['support'])->map(fn($id)=>$names->get($id)?->name)->filter()->implode(' · ') }}</p></div>@endif
 </div>
 @if($event['leave_overlap']&&(!$event['done']||$event['kind']==='resources'))<p class="muted">Approved absence overlaps this assignment. Reassignment / deadline review needed; the recorded deadline is retained.</p>@endif
 <details class="event-details"><summary>Details & accountability</summary><div class="event-detail-grid">@foreach($event['meta'] as $label=>$value)@if(!str_starts_with($label,'_'))<div><span class="muted">{{ $label }}</span><strong>{{ filled($value)?$value:'Setup needed' }}</strong></div>@endif @endforeach</div>
@if($event['url'])<a class="button" href="{{ $event['url'] }}">Open original record ↗</a>@endif</details>
</article>
