<div class="event {{ $event['late']?'late':'' }}">
 <span class="badge">{{ ucwords(str_replace('_',' ',$event['kind'])) }}</span><span class="badge {{ $event['late']?'danger':'' }}">{{ $event['late']?($event['done']?'Recorded late outcome':'Past recorded deadline'):ucwords(str_replace('_',' ',$event['state'])) }}</span>
 <h4>@if($event['url'])<a href="{{ $event['url'] }}">{{ $event['title'] }}</a>@else
{{ $event['title'] }}@endif</h4><p class="muted">{{ $event['person']?->name??'Owner setup needed' }}</p>
 @if($event['project'])<p class="muted">{{ $event['project']->client_name }} · {{ $event['project']->project_name }}</p>@endif
 <p class="muted">{{ $event['start']->format($event['date_only']?'d M Y':'d M Y, H:i') }}@if(!$event['end']->equalTo($event['start'])) → {{ $event['end']->format('d M Y, H:i') }}@endif</p>
 @if(count($event['support']))<p class="muted">Collaborators: {{ collect($event['support'])->map(fn($id)=>$names->get($id)?->name)->filter()->implode(' · ') }}</p>@endif
 <details><summary>Dates, commitments & source</summary>@foreach($event['meta'] as $label=>$value)@if(!str_starts_with($label,'_'))<p class="muted">{{ $label }}: <strong>{{ filled($value)?$value:'Setup needed' }}</strong></p>@endif @endforeach
@if($event['url'])<a class="button" href="{{ $event['url'] }}">Open original record ↗</a>@endif</details>
</div>
