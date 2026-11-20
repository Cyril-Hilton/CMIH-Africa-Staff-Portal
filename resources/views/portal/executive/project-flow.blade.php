<div class="panel project-flow" data-executive-flow>
 <div class="between"><div><p class="eyebrow">Live project journey</p><h3>From brief to delivery.</h3><p class="muted">Connected departments, their current stages and the people responsible. Updates every minute while this page is visible.</p></div><div><button type="button" data-flow-refresh>Refresh progress ↻</button><p class="muted" data-flow-status aria-live="polite">Checked {{ now()->format('d M Y, H:i:s') }}</p></div></div>
 <p class="report-note">✓ Recorded milestone · ● Current work · ○ Awaiting evidence · Optional stages are skipped only when explicitly marked unnecessary. Teams can work in parallel.</p>
 <div data-executive-flow-body>
 @forelse($portfolioPage as $row)
 @php
  $flow=$row['flow'];$flowId='flow-'.str_replace('-','n',(string)$row['project']->id);$current=collect($flow['sections'])->where('state','current')->pluck('label')->implode(' / ');
 @endphp
 <article class="flow-project" aria-label="{{ $row['project']->project_name }} delivery journey">
  <div class="between"><div><p class="eyebrow">{{ $flow['connected']?'Connected project':(($row['source_kind']??'')==='creative'?'Direct Creative brief':'Independent Operations activation') }}</p><h3><a href="{{ $projectLink($row) }}">{{ $row['project']->project_name }}</a></h3><p class="muted">{{ $row['project']->client_name }} · Source owner: {{ $row['project']->owner?->name??'Assignment needed' }}</p></div><span class="badge {{ $flow['cancelled']?'danger':'' }}">{{ $flow['cancelled']?'Closed / cancelled':($current?:'Review recorded milestones below') }}</span></div>
  <div class="flow-scroll"><ol class="flow-stages" style="--flow-count:{{ count($flow['sections']) }}" aria-label="Departmental journey">
  @foreach($flow['sections'] as $key=>$stage)
   <li data-state="{{ $stage['state'] }}"><button type="button" class="flow-stop" data-flow-stage="{{ $flowId.'-'.$key }}" aria-label="Show {{ $stage['label'] }} stages"><span>{{ $stage['state']==='done'?'✓':($stage['state']==='skipped'?'↷':$loop->iteration) }}</span><strong>{{ $stage['label'] }}</strong><small>{{ $stage['team'] }}</small><em>{{ match($stage['state']){'done'=>'Milestone recorded','current'=>'Work is here','skipped'=>'Not required',default=>'Awaiting evidence'} }}</em></button></li>
  @endforeach</ol></div>
  @foreach($flow['sections'] as $key=>$stage)
   <details id="{{ $flowId.'-'.$key }}" data-flow-detail="{{ $flowId.'-'.$key }}" class="flow-detail"><summary>{{ $stage['label'] }} · smaller stages @if(count($stage['records'])) · {{ count($stage['records']) }} linked {{ \Illuminate\Support\Str::plural('record',count($stage['records'])) }} @endif</summary>
    <p class="muted">{{ $stage['detail'] }}</p>
    @foreach($stage['records'] as $record)
     <section class="flow-record"><div class="between"><div><h4>{{ $record['title'] }}</h4><p class="muted">{{ $record['owner']??'Responsible manager · assignment needed' }} · {{ $record['state'] }}</p></div>@if($record['url'])<a class="button" href="{{ $record['url'] }}">Open workflow ↗</a>@endif</div>
     <ol class="flow-substages">@foreach($record['steps'] as $sub)<li data-state="{{ $sub['state'] }}"><span>{{ $sub['state']==='done'?'✓':($sub['state']==='current'?'●':'○') }}</span><strong>{{ $sub['label'] }}</strong>@if($sub['at'])<small>{{ $formatDate($sub['at']) }}</small>@endif</li>@endforeach</ol>
     @if(!empty($record['requested'])||!empty($record['agreed']))<p class="muted">Requested deadline: {{ $formatDate($record['requested']??null) }} · Department commitment: {{ $formatDate($record['agreed']??null) }}</p>@endif
     </section>
    @endforeach
    @if(!count($stage['records'])&&$stage['state']!=='skipped')<p class="muted">No linked workflow evidence for this stage yet. Later work does not prove this stage was completed.</p>@endif
   </details>
  @endforeach
 </article>
 @empty<p class="empty">0 published projects or briefs match these filters. Daily Mega Table tasks are excluded.</p>@endforelse
 </div>
</div>
<style>
.exec .flow-project{border:1px solid #4a6473;border-radius:20px;padding:24px;margin-top:24px;background:linear-gradient(125deg,#253b4999,#152530);box-shadow:inset 0 1px #dfedf522}.exec .flow-scroll{overflow:auto;margin:25px 0 12px;padding-bottom:8px}.exec .flow-stages{display:grid;grid-template-columns:repeat(var(--flow-count),minmax(155px,1fr));list-style:none;margin:0;padding:0;min-width:calc(var(--flow-count)*155px)}.exec .flow-stages>li{position:relative}.exec .flow-stages>li:not(:last-child):after{content:'→';position:absolute;top:10px;right:-9px;color:#8babb7;z-index:1;font-size:20px}.exec .flow-stop{width:100%;border:0;background:transparent;white-space:normal;text-align:left;padding:0 15px 10px;line-height:1.5}.exec .flow-stop>span{display:grid;place-items:center;width:35px;height:35px;border:1px solid #72909e;border-radius:50%;margin-bottom:12px;background:#1b2c38;color:#9cb0c0}.exec .flow-stop strong,.exec .flow-stop small,.exec .flow-stop em{display:block}.exec .flow-stop small{color:var(--muted);font-size:11px;min-height:32px}.exec .flow-stop em{font-size:11px;color:#a8beca;font-style:normal;margin-top:8px}.exec [data-state=done]>.flow-stop>span{color:#103129;background:#97e3bf;border-color:#b6f7d7}.exec [data-state=current]>.flow-stop>span{background:#a8e8ed;color:#132e39;box-shadow:0 0 0 6px #91e2e51a}.exec [data-state=current]>.flow-stop strong{color:#bdf7f5}.exec [data-state=skipped]>.flow-stop{opacity:.6}.exec .flow-detail{border-top:1px solid #526c7855;padding:0 5px}.exec .flow-record{border:1px solid #526c7866;border-radius:13px;padding:16px;margin:14px 0}.exec .flow-substages{display:flex;gap:12px;flex-wrap:wrap;list-style:none;padding:0;margin:18px 0}.exec .flow-substages li{flex:1 1 120px;min-width:0;border-top:2px solid #526574;padding-top:9px;font-size:12px}.exec .flow-substages strong,.exec .flow-substages small{display:block}.exec .flow-substages small{color:var(--muted);font-size:10px;margin-top:5px}.exec .flow-substages [data-state=done]{border-color:#92ddb3}.exec .flow-substages [data-state=current]{border-color:#98e6eb;color:#b8f3f4}@media(prefers-reduced-motion:no-preference){.exec [data-state=current]>.flow-stop>span{animation:flow-beacon 2.8s ease-in-out infinite}@keyframes flow-beacon{50%{box-shadow:0 0 0 9px #91e2e508}}}
</style>
<script>
(()=>{const root=document.querySelector('[data-executive-flow]');if(!root)return;let loading=false;const status=root.querySelector('[data-flow-status]');
 root.addEventListener('click',event=>{const button=event.target.closest('[data-flow-stage]');if(!button)return;const detail=document.getElementById(button.dataset.flowStage);if(detail){detail.open=!detail.open;if(detail.open)detail.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'nearest'});}});
 async function refresh(){if(loading||document.hidden||!root.isConnected)return;loading=true;try{const response=await fetch(location.href,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},cache:'no-store'});if(!response.ok||response.redirected)throw Error();const doc=new DOMParser().parseFromString(await response.text(),'text/html');const next=doc.querySelector('[data-executive-flow-body]');if(!next)throw Error();const open=[...root.querySelectorAll('[data-flow-detail][open]')].map(x=>x.dataset.flowDetail);root.querySelector('[data-executive-flow-body]').replaceWith(next);root.querySelectorAll('[data-flow-detail]').forEach(x=>x.open=open.includes(x.dataset.flowDetail));status.textContent='Updated '+new Date().toLocaleTimeString('en-GB',{timeZone:'Africa/Accra'});}catch{status.textContent='Update unavailable · showing last loaded progress';}finally{loading=false;}}
 root.querySelector('[data-flow-refresh]').addEventListener('click',refresh);setInterval(refresh,60000);
})();
</script>
