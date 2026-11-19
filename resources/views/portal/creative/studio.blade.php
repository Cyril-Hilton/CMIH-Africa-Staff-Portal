<x-app-layout>
<x-slot name="header"><div><p class="text-xs uppercase tracking-[.25em] text-brand-ash">One Team · Shared Accountability</p><h2 class="text-3xl font-display text-brand-white">Creative Studio</h2></div></x-slot>
@include('portal.creative.workspace',['embedded'=>false])
@if(auth()->user()->isCvoOrSuperAdmin()||\App\Models\User::normalizeDepartmentKey(auth()->user()->department)==='creatives')<details class="mt-6 rounded-2xl border border-brand-white/15 p-5"><summary class="cursor-pointer font-semibold">KPI Targets, Results & Measurement Guide</summary>@include('portal.modules.kpis',['kpiDepartment'=>'creatives'])</details>@endif
</x-app-layout>
