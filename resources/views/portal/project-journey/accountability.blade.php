@if(!empty($accountability))
<section class="mt-4 space-y-4 rounded-2xl border border-teal-300/25 bg-brand-black/20 p-4" aria-label="Connected project accountability">
    <header><p class="text-xs uppercase tracking-widest text-teal-200">One project · connected responsibilities</p><h4 class="mt-1 text-lg font-semibold">{{ $accountability['title'] }}</h4><p class="mt-1 text-xs text-brand-ash">{{ $accountability['client'] }} · Client Relations owner: {{ $accountability['account_owner'] }}</p></header>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach($accountability['lanes'] as $team=>$lane)
            <div class="rounded-xl border {{ $team==='creative'?'border-violet-300/35 bg-violet-300/5':'border-orange-300/35 bg-orange-300/5' }} p-3">
                <strong>{{ ucfirst($team) }}</strong><p class="mt-1 text-xs">{{ $lane['required']===false?'Not required · stage skipped':($lane['required']===null?'Setup needed · decide whether this team is required':'Required · linked workflow') }}</p>
                @if($lane['manager'])<p class="mt-1 text-xs text-brand-ash">Tagged HOD: {{ $lane['manager'] }}</p>@endif
            </div>
        @endforeach
    </div>
    @foreach($accountability['work'] as $work)
        <article class="rounded-xl border {{ $work['late']?'border-amber-300/50':'border-brand-white/15' }} p-3">
            <div class="flex flex-wrap items-center justify-between gap-2"><h5 class="font-semibold">{{ $work['team'] }} · {{ $work['title'] }}</h5><span class="text-xs text-teal-100">{{ $work['state'] }}</span></div>
            <p class="mt-2 text-sm"><strong>{{ $work['owner'] }}</strong> · requested / handed over by {{ $work['from'] }}</p>
            @if(count($work['support']))<div class="mt-3 flex flex-wrap items-center gap-2 text-xs"><span class="text-brand-ash">Supporting team</span>@foreach($work['support'] as $support)<span class="rounded-lg border border-violet-300/40 bg-violet-300/10 px-2 py-1">{{ $support }}</span>@endforeach</div>@endif
            <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-2 xl:grid-cols-4">
                @foreach(['requested'=>$work['team']==='Creative'?'Requester deadline':'Requested setup deadline','agreed'=>$work['team']==='Creative'?'Creative HOD delivery commitment':'Operations HOD setup commitment','start'=>$work['team']==='Creative'?'Planned work start':'Handover meeting held','delivered'=>'First delivery submitted'] as $key=>$label)
                    <div><dt class="text-brand-ash">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $work[$key]?->timezone('Africa/Accra')->format('d M Y · H:i') ?? ($key==='delivered'?'Delivery pending':'Setup needed') }}</dd></div>
                @endforeach
            </dl>
            @if($work['team']==='Operations')
                <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-3">
                    @foreach(['activation'=>'Activation starts','requested_execution'=>'Client Relations execution completion deadline','agreed_execution'=>'Operations HOD execution commitment'] as $key=>$label)
                        <div><dt class="text-brand-ash">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $work[$key]?->timezone('Africa/Accra')->format('d M Y · H:i') ?? 'Setup needed' }}</dd></div>
                    @endforeach
                </dl>
            @endif
            @if($work['waiting'])<p class="mt-3 text-xs text-amber-200">{{ $work['waiting'] }}</p>@endif
            @if($work['conflict'])<p class="mt-2 text-xs text-amber-200">Timeline conflict: the requested deadline is earlier than the department HOD’s commitment. Reconcile the dates; this alone does not establish who caused a delay.</p>@endif
            @if($work['late'])<p class="mt-2 text-xs text-amber-200">Past a recorded deadline · review the handover, dependency and deadline history before attributing responsibility.</p>@endif
            <a href="{{ $work['url'] }}" class="mt-3 inline-block text-xs font-semibold text-teal-200 underline">Open {{ strtolower($work['team']) }} work and assignment ↗</a>
        </article>
    @endforeach
    @if($accountability['asset_custody']->isNotEmpty())
        <section aria-label="Project warehouse assets and custody" class="space-y-2"><h5 class="font-semibold">Project assets & custody</h5>
        <p class="text-xs text-brand-ash">Requests remain planned until warehouse issue. Current and previous custody are retained per movement.</p>
        @foreach($accountability['asset_custody'] as $movement)<article class="rounded-lg border border-brand-white/15 p-3 text-xs"><strong>{{ $movement->asset?->name }} · {{ $movement->requested_quantity }} units</strong><p>Custodian: {{ $movement->custodian?->name??$movement->requester?->name }} · {{ $movement::statusLabel($movement->status) }}</p><p>Requested: {{ $movement->created_at->format('d M Y H:i') }} · Issued: {{ $movement->issued_at?->format('d M Y H:i')??'Not issued' }} · Returned: {{ $movement->returned_at?->format('d M Y H:i')??'Return pending' }}</p><a class="underline text-teal-200" href="{{ route('portal.assets.warehouse.index',['request_status'=>$movement->status]) }}">Warehouse movement {{ $movement->request_code }}</a></article>@endforeach
        </section>
    @endif
    @if($accountability['finance_work']->isNotEmpty())<section aria-label="Connected Finance processing" class="space-y-2"><h5 class="font-semibold">Finance processing & commitments</h5><p class="text-xs text-brand-ash">Shared processing status from the same Finance records. Private amounts and documents remain restricted.</p>@foreach($accountability['finance_work'] as $item)<article class="rounded-xl border border-brand-white/15 p-3 text-xs"><strong>{{ $item['kind'] }} · {{ $item['owner'] }}</strong><p>{{ $item['state'] }}</p>@if($item['due'])<p>Due: {{ $item['due'] }}</p>@endif @if($item['settled'])<p>Settlement recorded: {{ $item['settled'] }}</p>@endif<p class="text-brand-ash">Updated {{ $item['updated']->format('d M Y H:i') }}</p></article>@endforeach</section>@endif
    <section aria-label="Shared team comments and decisions" class="space-y-3">
        <h5 class="font-semibold">Team comments & decisions</h5>
        <p class="text-xs text-brand-ash">Latest 30 shared updates from Creatives and Operations. Open the linked work for its full discussion and approval history.</p>
        @forelse($accountability['shared_updates'] as $update)
            <article class="rounded-xl border border-brand-white/15 p-3 text-xs">
                <strong>{{ $update['team'] }} · {{ $update['kind'] }}</strong>
                <p class="mt-1 text-brand-ash">{{ $update['actor'] }} · {{ $update['at']->timezone('Africa/Accra')->format('d M Y · H:i:s') }}</p>
                <p class="mt-2 whitespace-pre-wrap break-words">{{ $update['body'] }}</p>
                <a href="{{ $update['url'] }}" class="mt-2 inline-block text-teal-200 underline">Open shared work / reply ↗</a>
            </article>
        @empty
            <p class="text-xs text-brand-ash">No shared team comments recorded yet.</p>
        @endforelse
    </section>
    <details class="rounded-xl border border-brand-white/15 p-3"><summary class="cursor-pointer font-semibold">Dates, handovers & change history</summary>
        <p class="mt-2 text-xs text-brand-ash">Times are recorded in Accra time. Portal timestamps are distinct from the reporting week and any earlier date supplied by staff. Earlier deadlines remain in the change history.</p>
        <ol class="mt-3 space-y-3 border-l border-teal-300/30 pl-4">
            @foreach($accountability['timeline'] as $milestone)<li class="text-xs"><strong>{{ $milestone['label'] }}</strong><p class="mt-1 text-brand-ash">{{ $milestone['at']->timezone('Africa/Accra')->format('d M Y · H:i:s') }} · {{ $milestone['owner'] }}</p></li>@endforeach
        </ol>
        @if(count($accountability['original_deadlines']))<div class="mt-4 rounded-lg border border-amber-300/20 p-3 text-xs"><strong>Original requested deadlines</strong>@foreach($accountability['original_deadlines'] as $key=>$at)<p class="mt-1">{{ ucwords(str_replace('_',' ',$key)) }}: {{ \Illuminate\Support\Carbon::parse($at)->timezone('Africa/Accra')->format('d M Y · H:i') }}</p>@endforeach</div>@endif
        <div class="mt-4 space-y-3">@foreach($accountability['history'] as $event)<div class="rounded-lg border border-brand-white/10 p-3 text-xs"><strong>{{ $event['action'] }}</strong><p class="mt-1 text-brand-ash">{{ \Illuminate\Support\Carbon::parse($event['at'])->timezone('Africa/Accra')->format('d M Y · H:i:s') }} · {{ $event['actor'] }}</p>@foreach($event['changes'] as $change)<p class="mt-1">{{ $change['field'] }}: {{ $change['before'] ?? 'Previously unset' }} → {{ $change['after'] ?? 'Cleared' }}</p>@endforeach @if($event['reason'])<p class="mt-2">{{ $event['reason'] }}</p>@endif</div>@endforeach</div>
        @php($historyPages=$accountability['history_pagination'])
        <nav class="mt-3 flex flex-wrap items-center gap-3 text-xs" aria-label="Project audit history pages">
            <span>{{ $historyPages['total'] }} retained events · page {{ $historyPages['page'] }} / {{ $historyPages['last'] }}</span>
            @if($historyPages['page']>1)<button type="button" class="underline" data-connected-history-url="{{ $historyPages['url'] }}?history_page={{ $historyPages['page']-1 }}">Newer events</button>@endif
            @if($historyPages['page']<$historyPages['last'])<button type="button" class="underline" data-connected-history-url="{{ $historyPages['url'] }}?history_page={{ $historyPages['page']+1 }}">Older events</button>@endif
        </nav>
    </details>
</section>
@endif
