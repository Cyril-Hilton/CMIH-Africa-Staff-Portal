<div data-executive-tracker-results>
    @php($trackerParams = request()->except(['tracker_page','fragment']))
    <nav class="ept-tabs" aria-label="Tracker work types">
        @foreach(['projects'=>'Project journeys','weekly'=>'Department weekly work','tasks'=>'Daily tasks','all'=>'All tracked work'] as $key=>$label)
            <a class="ept-tab" @if($tracker['type']===$key) aria-current="page" @endif href="{{ route('dashboard', array_replace($trackerParams,['tracker_type'=>$key])) }}#executive-project-tracker">{{ $label }} <span>{{ $tracker['counts'][$key] }}</span></a>
        @endforeach
    </nav>

    <div class="ept-stats" aria-label="Tracker summary">
        @foreach(['total'=>'Tracked records','active'=>'In motion','attention'=>'Needs attention','complete'=>'Completion recorded'] as $key=>$label)
            <div class="ept-stat"><span>{{ $label }}</span><strong>{{ $tracker['summary'][$key] }}</strong></div>
        @endforeach
    </div>
    <div class="ept-sub">{{ $tracker['summary']['unlinked'] }} records have missing project links / handover evidence. These stay visible for follow-up.</div>
    @if($tracker['distribution']->isNotEmpty())
        <div class="ept-chart" role="img" aria-label="Work distribution by current stage">
            @foreach($tracker['distribution'] as $label=>$count)
                <div class="ept-chart-row"><div><span>{{ $label }}</span><b>{{ $count }}</b></div><div class="ept-chart-track"><i style="width:{{ round(100*$count/max(1,$tracker['distribution']->max())) }}%"></i></div></div>
            @endforeach
        </div>
    @endif
    <p class="ept-sub" style="margin-top:14px">✓ Recorded &nbsp; ● Current stage &nbsp; ○ Awaiting evidence · Stages reflect saved records, rather than elapsed time.</p>
    @forelse($tracker['records'] as $row)
        <article class="ept-card" data-attention="{{ $row['attention'] ? 'true' : 'false' }}" aria-label="{{ $row['title'] }} progress">
            <div class="ept-card-head">
                <div>
                    <span class="ept-kicker">{{ $row['origin'] }}</span><h4>{{ $row['title'] }}</h4>
                    <p class="ept-sub">{{ $row['client'] ?: 'Internal work' }} · {{ collect($row['departments'])->map(fn($dept)=>\App\Models\User::departmentLabel($dept))->implode(' ↔ ') }}</p>
                    <div style="margin-top:10px"><span class="ept-badge">{{ $row['cancelled'] ? 'Closed / cancelled' : $row['current'] }}</span>@if($row['overdue'])<span class="ept-badge ept-warning">Past recorded deadline</span>@elseif($row['attention'])<span class="ept-badge ept-warning">Needs attention</span>@endif</div>
                </div>
                <div class="ept-owner"><span class="ept-sub">{{ $row['complete'] ? 'Responsible owner' : 'Current responsible person' }}</span><strong>{{ $tracker['names']->get($row['owner_id']) ?: 'Not assigned / not recorded' }}</strong><p class="ept-sub" style="margin-top:8px">Due {{ $row['due']?->format('d M Y') ?? 'not recorded' }}</p>@if($row['progress']!==null)<p class="ept-sub">Reported progress {{ $row['progress'] }}%</p>@endif</div>
            </div>
            <ol class="ept-steps" aria-label="{{ $row['title'] }} milestones">
                @foreach($row['steps'] as $step)
                    <li class="ept-step" data-state="{{ $row['cancelled'] && $step['state']==='current' ? 'waiting' : $step['state'] }}" @if(!$row['cancelled'] && $step['state']==='current') aria-current="step" @endif>
                        <span class="ept-stop" aria-hidden="true">{{ $step['state']==='done' ? '✓' : $loop->iteration }}</span><b>{{ $step['label'] }}</b><small>{{ $step['team'] }}</small>
                        @if(!empty($step['owner_id']))<small>{{ $tracker['names']->get($step['owner_id']) ?: 'Owner not recorded' }}</small>@endif
                        <em>{{ $step['state']==='done' ? 'Recorded' : (!$row['cancelled'] && $step['state']==='current' ? 'Work is here' : 'Awaiting evidence') }}</em><small>{{ $step['detail'] }}</small>@if(!empty($step['date']))<small>{{ $step['date']->format($step['label']==='Client project' ? 'd M Y' : 'd M Y · H:i') }}</small>@endif
                    </li>
                @endforeach
            </ol>
            <div class="ept-team"><span class="ept-sub" style="margin-right:6px">Connected team</span>
                @forelse($row['people_ids'] as $id)
                    <span class="ept-person" data-owner="{{ $id===$row['owner_id'] ? 'true' : 'false' }}">{{ $tracker['names']->get($id) ?: 'Former / unknown staff' }}@if($id===$row['owner_id']) · Owner @endif</span>
                @empty<span class="ept-sub">Team assignment not recorded.</span>@endforelse
            </div>
            <details data-tracker-detail="{{ $row['key'] }}"><summary>Sources, handovers & follow-up · {{ count($row['sources']) }} linked records @if(count($row['gaps'])) · {{ count($row['gaps']) }} evidence gaps @endif</summary>
                <div class="ept-detail-grid">
                    <div><h5>Open the underlying work</h5>@foreach($row['sources'] as $source)<a class="ept-source underline" href="{{ $source['url'] }}">{{ $source['title'] }} ↗<span>{{ $source['label'] }}</span></a>@endforeach</div>
                    <div><h5>Next handover / follow-up</h5>
                        @if($row['cancelled'])<p class="ept-sub">This record is closed or cancelled.</p>
                        @elseif($row['complete'])<p class="ept-sub">Completion is recorded for this workflow. See the source record for verification details.</p>
                        @else
                            @php($next=collect($row['steps'])->first(fn($step)=>$step['state']!=='done'))
                            <p class="ept-sub">{{ $next['label'] ?? 'Review linked work' }} · {{ $next['team'] ?? 'Responsible team' }}</p>
                        @endif
                        @if($row['blocker'])<p class="ept-issue">{{ strip_tags($row['blocker']) }}</p>@endif
                        @foreach($row['gaps'] as $gap)<p class="ept-issue">{{ $gap }}</p>@endforeach
                    </div>
                </div>
            </details>
        </article>
    @empty
        <div class="ept-empty" style="margin-top:16px">No matching work in this period. Try Department weekly work, Daily tasks, another department or a different reporting period. No sample progress is displayed.</div>
    @endforelse
    @if($tracker['records']->hasPages())<div style="margin-top:18px">{{ $tracker['records']->links() }}</div>@endif
    <div class="ept-footer"><span>Updated {{ $tracker['updatedAt']->timezone('Africa/Accra')->format('d M Y · H:i:s') }} GMT</span><span>{{ $tracker['records']->total() }} matching records · {{ $tracker['records']->perPage() }} per page · Same department, staff and period filters as Executive Activity</span></div>
</div>
