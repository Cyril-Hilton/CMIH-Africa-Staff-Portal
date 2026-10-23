<style>
.hr-workspace{--hr-accent:#bca476;margin:0 0 1.5rem;border:1px solid rgba(150,150,150,.22);border-radius:20px;overflow:hidden;background:rgba(127,127,127,.025)}
.hr-workspace-intro{padding:24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid rgba(127,127,127,.15)}
.hr-workspace-intro h3{font-size:23px;font-weight:650;margin:0}.hr-workspace-intro p{font-size:13px;opacity:.65;margin:6px 0 0;max-width:580px;line-height:1.6}
.hr-workspace-nav{display:flex;gap:5px;overflow-x:auto;padding:10px;scrollbar-width:thin}.hr-workspace-nav button{flex:0 0 auto;border:1px solid transparent;border-radius:11px;padding:12px 16px;font-size:13px;font-weight:650;white-space:nowrap;color:inherit;opacity:.65}
.hr-workspace-nav button[aria-selected=true]{background:rgba(188,164,118,.13);border-color:rgba(188,164,118,.45);opacity:1}.hr-workspace-nav button:focus-visible{outline:2px solid var(--hr-accent);outline-offset:2px}
.hr-workspace-panel{min-width:0;display:grid;gap:18px;scroll-margin-top:100px}.hr-workspace-panel[hidden],.hr-workspace-nav button[hidden]{display:none!important}
.hr-workspace-panel-intro{padding:4px 2px 8px}.hr-workspace-panel-intro h3{font-size:20px;font-weight:650}.hr-workspace-panel-intro p{font-size:13px;opacity:.65;margin-top:5px;line-height:1.6}.hr-workspace-panel details{margin-top:0!important;min-width:0}.hr-workspace-panel summary{padding:20px 24px}.hr-workspace-panel .overflow-x-auto{max-width:100%}.hr-workspace-panel table{min-width:640px}.hr-workspace-tool{min-width:0}
@media(max-width:640px){.hr-workspace-intro{padding:18px}.hr-workspace-intro h3{font-size:20px}.hr-workspace-nav button{padding:11px 12px}.hr-workspace-panel summary{padding:17px}.hr-workspace-panel details>div{padding:16px}.hr-workspace-panel form{min-width:0}.hr-workspace-panel input,.hr-workspace-panel select,.hr-workspace-panel textarea{max-width:100%}}
@media print{.hr-workspace-nav{display:none}.hr-workspace-panel[hidden]{display:block!important}.hr-workspace-panel{break-inside:auto}}
</style>
@php($hrWorkspaceTabs=[
'people'=>['People & Employment','Staff records, identity checks, employment milestones and appraisal tools.'],
'leave'=>['Leave & Attendance','Manage requests, leave entitlements and attendance reporting.'],
'pay'=>['Pay & Benefits','Salary advance terms, staff agreements and repayment management.'],
'workplace'=>['Workplace Services','Manage visitor arrivals, scheduled visits and the contact directory.'],
'communications'=>['Communications & Resources','Staff announcements, templates and reference tools.'],
'reporting'=>['Reports & Presentation','Prepare HR and Administrator reports, then present approved results on the weekly dashboard.'],
])
<div class="hr-workspace" data-hr-workspace>
    <div class="hr-workspace-intro"><div><h3>People, Work & Workplace</h3><p>Choose an area below. Each workspace keeps related tools together and opens the details only when you need them.</p></div><a class="rounded-xl border border-brand-white/15 px-4 py-2 text-sm" href="{{ route('dashboard',['weekly_department'=>'hr_admin']).'#staff-weekly-performance' }}">Weekly Presentation</a></div>
    <nav class="hr-workspace-nav" role="tablist" aria-label="HR & Admin Workspaces">
        @foreach($hrWorkspaceTabs as $key=>$tab)<button type="button" role="tab" id="hr-tab-{{ $key }}" aria-controls="hr-panel-{{ $key }}" aria-selected="false" tabindex="-1" data-hr-tab="{{ $key }}">{{ $tab[0] }}</button>@endforeach
    </nav>
</div>
<div data-hr-workspace-panels class="space-y-6">
@foreach($hrWorkspaceTabs as $key=>$tab)
    <section role="tabpanel" id="hr-panel-{{ $key }}" aria-labelledby="hr-tab-{{ $key }}" tabindex="0" data-hr-panel="{{ $key }}" class="hr-workspace-panel">
        <div class="hr-workspace-panel-intro"><h3>{{ $tab[0] }}</h3><p>{{ $tab[1] }}</p></div>
        @if($key==='leave' && (auth()->user()->hasFullHrAccess() || in_array(auth()->user()->hrLevel(),[1,2],true)))
        <a href="{{ route('portal.hr-reporting.index',['attendance_period'=>'weekly']).'#hr-attendance' }}" class="hr-workspace-tool rounded-xl border border-brand-white/10 p-4"><strong>Attendance Insights</strong><span class="block text-xs text-brand-ash mt-2">Weekly, monthly and yearly punctuality, working hours, lateness and overtime.</span></a>
        @endif
    </section>
@endforeach
</div>
