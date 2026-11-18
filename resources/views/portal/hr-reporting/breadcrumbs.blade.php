@php
 $hrFull=\App\Services\HrLiveRegisters::full(auth()->user());
 $hrCanNavigate=$hrFull||auth()->user()->isLineManager();
 $hrCrumbKind=request()->route('kind');
 $hrCrumbActivity=request()->route('activity');
 $hrCrumbTab=request('hr_tab')??($hrCrumbKind?config('hr_workspace.registers.'.$hrCrumbKind.'.tab'):null)??(is_object($hrCrumbActivity)?$hrCrumbActivity->tab:null)??'overview';
 if($hrCrumbKind==='staff')$hrCrumbTab='people';if($hrCrumbKind==='asset')$hrCrumbTab='assets';if($hrCrumbTab==='operations')$hrCrumbTab='facilities';
 $hrCrumbLabel=config('hr_workspace.tabs.'.$hrCrumbTab.'.label','Overview');
 $hrHomeTab=$hrFull?'overview':'people';
 $hrHomeUrl=route('portal.hr-workspace',request()->only(['hr_period','hr_from','hr_to'])+['hr_tab'=>$hrHomeTab]);
 $hrCrumbPage=request()->routeIs('portal.hr-registers.history')?'Change history':(request()->routeIs('portal.hr-registers.import*')?'Import register':(request()->routeIs('portal.hr-dashboard.activities.show')?'Activity details':(request()->boolean('hr_existing')?'HR Tools':null)));
@endphp
<nav aria-label="HR and Admin breadcrumbs" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-white/15 px-4 py-3 text-sm" style="background:rgba(168,85,247,.08)">
 <ol class="flex flex-wrap items-center gap-2"><li><a class="underline underline-offset-4" href="{{ route('dashboard') }}">Staff Dashboard</a></li>
 @if($hrCanNavigate)<li aria-hidden="true">›</li><li><a class="underline underline-offset-4" href="{{ $hrHomeUrl }}">HR &amp; Admin Overview</a></li>
 @if($hrCrumbTab!=='overview')<li aria-hidden="true">›</li><li>@if($hrCrumbPage)<a class="underline underline-offset-4" href="{{ route('portal.hr-workspace',request()->only(['hr_period','hr_from','hr_to'])+['hr_tab'=>$hrCrumbTab]) }}">{{ $hrCrumbLabel }}</a>@else<span aria-current="page">{{ $hrCrumbLabel }}</span>@endif</li>@endif
 @endif
 @if($hrCrumbPage)<li aria-hidden="true">›</li><li aria-current="page">{{ $hrCrumbPage }}</li>@endif</ol>
 <a class="rounded-lg border border-brand-white/20 px-3 py-2 font-semibold" href="{{ $hrCanNavigate?$hrHomeUrl:route('dashboard') }}">← {{ $hrCanNavigate?'Back to HR Overview':'Back to Dashboard' }}</a>
</nav>
