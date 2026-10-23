<nav aria-label="People & Teams" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-brand-white/10 bg-brand-white/5 p-3">
@php
$peopleSection=request()->route('section') ?: (request()->routeIs('admin.users*')?'accounts':(request()->routeIs('portal.hr.staff*')?'records':'directory'));
$peopleTabs=['directory'=>'Team Directory'];
if(auth()->user()->hasFullHrAccess())$peopleTabs['records']='Staff Records';
if(auth()->user()->hasFullHrAccess() || auth()->user()->hasRole(['admin','super_admin']) || auth()->user()->isCvoOrSuperAdmin())$peopleTabs['accounts']='Accounts & Access';
@endphp
@foreach($peopleTabs as $section=>$label)<a href="{{ route('portal.people',['section'=>$section]) }}" @if($peopleSection===$section)aria-current="page"@endif class="rounded-xl px-4 py-3 text-sm font-semibold {{ $peopleSection===$section?'bg-brand-red text-white':'text-brand-ash hover:bg-brand-white/10' }}">{{ $label }}</a>@endforeach
</nav>
