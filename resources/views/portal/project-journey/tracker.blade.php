<section id="executive-project-tracker" class="ept" aria-labelledby="ept-heading">
    <style>
        .ept{--ept-line:rgba(159,183,205,.22);--ept-text:#f2f5fb;--ept-muted:#b4bfd1;margin:1.5rem 0;border:1px solid var(--ept-line);border-radius:24px;padding:clamp(16px,2vw,28px);color:var(--ept-text);background:radial-gradient(ellipse at 0 0,rgba(210,24,45,.17),transparent 50%),linear-gradient(130deg,#171820,#0c131b 65%,#111e24);box-shadow:0 20px 55px #0003}
        .ept a:focus-visible,.ept button:focus-visible,.ept summary:focus-visible{outline:2px solid #70e8d5;outline-offset:4px}.ept-head{display:flex;justify-content:space-between;align-items:start;gap:16px;flex-wrap:wrap}.ept-kicker{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#f38c94;font-weight:700}.ept h3{font-size:clamp(23px,2.2vw,34px);line-height:1.2;margin:8px 0}.ept p.ept-intro{font-size:14px;line-height:1.6;color:var(--ept-muted);max-width:70ch}.ept-live{font-size:12px;color:#89edda;display:flex;align-items:center;gap:8px}.ept-live:before{content:'';width:7px;height:7px;background:#71e7bd;border-radius:50%;box-shadow:0 0 14px #71e7bd}.ept-sub{font-size:12px;color:var(--ept-muted)}.ept-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.ept-tab{border:1px solid var(--ept-line);border-radius:12px;padding:9px 14px;font-size:13px;color:var(--ept-muted);display:flex;gap:10px;align-items:center}.ept-tab[aria-current=page]{background:#3c242b;border-color:#ed5c6b;color:#fff}.ept-tab span{font-size:11px;background:#ffffff12;padding:2px 7px;border-radius:7px}.ept-controls{display:flex;flex-wrap:wrap;align-items:end;gap:10px;margin-bottom:18px}.ept-controls label{font-size:12px;color:var(--ept-muted)}.ept-controls input,.ept-controls select{display:block;margin-top:5px;background:#111923;color:#f3f6fb;border:1px solid var(--ept-line);border-radius:10px;padding:9px 12px;max-width:100%}.ept-controls button,.ept-refresh{border:1px solid #ffffff30;border-radius:10px;padding:9px 14px;font-size:12px;background:#ffffff08}.ept-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:18px 0}.ept-stat{border:1px solid var(--ept-line);border-radius:14px;padding:14px 16px;background:#ffffff03}.ept-stat strong{display:block;font-size:27px;line-height:1.25;margin-top:4px}.ept-stat span{font-size:12px;color:var(--ept-muted)}.ept-stat[aria-current=true]{border-color:#7ce3d1}.ept-card{border:1px solid var(--ept-line);border-radius:18px;overflow:hidden;margin-top:14px;background:#ffffff03}.ept-card[data-attention=true]{border-color:#d8a34b70}.ept-card-head{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:18px 20px 12px}.ept-card h4{font-size:19px;line-height:1.35;font-weight:650;margin:5px 0}.ept-badge{display:inline-flex;border:1px solid #68e1cd40;background:#68e1cd0c;border-radius:7px;padding:4px 8px;color:#a1efdf;font-size:11px;margin-right:5px}.ept-warning{color:#f5ca7a;border-color:#f5ca7a60;background:#f5ca7a0c}.ept-owner{font-size:13px;text-align:right}.ept-owner strong{display:block;margin-top:4px}.ept-steps{display:flex;padding:18px 20px 20px;overflow:auto;list-style:none;counter-reset:stop;gap:0}.ept-step{position:relative;flex:1;min-width:145px;padding-right:12px;counter-increment:stop}.ept-step:last-child{padding-right:0}.ept-step:after{content:'';position:absolute;top:15px;left:32px;right:0;height:2px;background:#ffffff18}.ept-step:last-child:after{display:none}.ept-step[data-state=done]:after{background:#66d6c38c}.ept-stop{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;border:1px solid #73819666;background:#17212d;position:relative;z-index:1;font-size:11px;color:#8c9aaf}.ept-step[data-state=done] .ept-stop{background:#1c4943;border-color:#75dec9;color:#baf9e8}.ept-step[data-state=current] .ept-stop{color:#fff;border:2px solid #f25c70;background:#642e3b;box-shadow:0 0 0 5px #f25c7015,0 0 25px #f25c7030;animation:ept-pulse 2.4s ease-in-out infinite}.ept-step b{display:block;font-size:12px;margin-top:12px;line-height:1.4}.ept-step small{display:block;font-size:11px;color:var(--ept-muted);line-height:1.5;margin-top:4px}.ept-step em{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-style:normal;margin-top:7px;color:#8e9bb0}.ept-step[data-state=current] em{color:#ffadb7}.ept-step[data-state=done] em{color:#86e5ce}.ept-team{padding:13px 20px;border-top:1px solid var(--ept-line);display:flex;align-items:center;flex-wrap:wrap;gap:7px}.ept-person{display:inline-flex;gap:6px;align-items:center;border:1px solid #9b8ee74a;border-radius:9px;background:#9b8ee70a;padding:5px 8px;font-size:11px;color:#d5cff6}.ept-person[data-owner=true]{border-color:#73d5c67a;background:#73d5c610;color:#c0f0e7}.ept-card details{border-top:1px solid var(--ept-line);padding:12px 20px}.ept-card summary{font-size:12px;cursor:pointer;color:#c8d5e4}.ept-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:14px 0}.ept-detail-grid h5{font-size:12px;font-weight:700;margin-bottom:8px}.ept-source{display:block;padding:8px 0;font-size:12px;border-bottom:1px solid #ffffff0a}.ept-source span{display:block;color:var(--ept-muted);font-size:11px;margin-top:3px}.ept-issue{font-size:12px;line-height:1.6;margin:6px 0;color:#f1ce8c}.ept-chart{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0}.ept-chart-row{min-width:160px;flex:1}.ept-chart-row>div:first-child{display:flex;justify-content:space-between;gap:10px;font-size:11px;color:var(--ept-muted);margin-bottom:6px}.ept-chart-track{height:4px;border-radius:4px;background:#ffffff12;overflow:hidden}.ept-chart-track i{display:block;height:100%;background:linear-gradient(90deg,#d74964,#79dacb);border-radius:4px}.ept-empty{padding:30px;border:1px dashed var(--ept-line);border-radius:16px;color:var(--ept-muted);font-size:14px}.ept-footer{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--ept-muted);margin-top:16px}.ept [data-tracker-refresh-state]{min-height:16px;font-size:11px;color:var(--ept-muted)}
        @keyframes ept-pulse{50%{box-shadow:0 0 0 8px #f25c7008,0 0 30px #f25c7050}}@media(prefers-reduced-motion:reduce){.ept-step[data-state=current] .ept-stop{animation:none}}@media(max-width:650px){.ept-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.ept-owner{text-align:left}.ept-detail-grid{grid-template-columns:1fr}.ept-step{min-width:140px}.ept-controls input{width:100%}}
    </style>
    <div class="ept-head">
        <div><div class="ept-kicker">Connected teams · Recorded milestones</div><h3 id="ept-heading">Live Project & Team Tracker</h3><p class="ept-intro">Follow the work from brief to delivery. See the current stage, responsible team, collaborators and the next handover alongside the weekly reports.</p></div>
        <div><div class="ept-live">Live updates · every 45 seconds</div><p class="ept-sub" style="margin-top:8px">{{ $tracker['from']->format('d M') }} – {{ $tracker['to']->format('d M Y') }}</p><a class="ept-sub underline" href="{{ route('portal.project-journey', ['period'=>$tracker['period'],'date'=>$tracker['from']->toDateString()]) }}">Open full project journeys ↗</a></div>
    </div>
    <form method="get" class="ept-controls" action="{{ route('dashboard') }}#executive-project-tracker" data-no-ajax data-no-silent>
        @foreach(request()->except(['tracker_q','tracker_status','tracker_page','fragment']) as $name=>$value)
            @if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <label>Find a project / brand<input type="search" name="tracker_q" value="{{ request('tracker_q') }}" placeholder="Project or client name"></label>
        <label>Show<select name="tracker_status">@foreach(['all'=>'All progress','active'=>'Active work','attention'=>'Needs attention','complete'=>'Completed milestones'] as $key=>$label)<option value="{{ $key }}" @selected($tracker['status']===$key)>{{ $label }}</option>@endforeach</select></label>
        <button>Filter tracker</button><button type="button" class="ept-refresh" data-tracker-refresh>Refresh now</button>
    </form>
    <div id="executive-tracker-live" data-tracker-url="{{ route('portal.project-journey.tracker', request()->query()) }}">
        @include('portal.project-journey.tracker-results')
    </div>
    <p data-tracker-refresh-state role="status" aria-live="polite"></p>
    <script>
        window.initExecutiveProjectTracker = () => {
            const host = document.getElementById('executive-project-tracker');
            const target = host?.querySelector('#executive-tracker-live');
            if (!target || target.dataset.polling) return;
            target.dataset.polling = 'true';
            let busy = false;
            const message = host.querySelector('[data-tracker-refresh-state]');
            const refresh = async (manual = false) => {
                if (busy || !target.isConnected || (!manual && (document.hidden || target.contains(document.activeElement)))) return;
                busy = true;
                if (manual) message.textContent = 'Checking the latest department records…';
                try {
                    const response = await fetch(target.dataset.trackerUrl, {cache:'no-store',credentials:'same-origin',headers:{'Accept':'text/html'}});
                    if (!response.ok || response.redirected) throw new Error('Unavailable');
                    const doc = new DOMParser().parseFromString(await response.text(),'text/html');
                    const fresh = doc.querySelector('[data-executive-tracker-results]');
                    if (!fresh) throw new Error('Unavailable');
                    if (!manual && target.contains(document.activeElement)) return;
                    const open = new Set([...target.querySelectorAll('details[open]')].map(el=>el.dataset.trackerDetail));
                    fresh.querySelectorAll('details').forEach(el=>{if(open.has(el.dataset.trackerDetail))el.open=true;});
                    target.replaceChildren(fresh);
                    message.textContent = 'Tracker refreshed from saved department records.';
                } catch {message.textContent = 'Live refresh is temporarily unavailable. The last loaded records remain visible; try Refresh now.';}
                finally {busy=false;}
            };
            host.querySelector('[data-tracker-refresh]').addEventListener('click',()=>refresh(true));
            const timer=setInterval(()=>{if(!target.isConnected){clearInterval(timer);return;}refresh();},45000);
        };
        window.initExecutiveProjectTracker();
    </script>
</section>
