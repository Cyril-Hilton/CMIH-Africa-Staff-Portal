<x-app-layout><x-slot name="header"><div class="flex flex-wrap justify-between gap-4"><h2 class="text-2xl font-semibold">Published department source</h2><a class="underline" href="{{ $returnTo }}">Back to executive tracker</a></div></x-slot>
@include('portal.executive.styles')
<section class="exec"><p class="eyebrow">{{ \App\Models\User::departmentLabel($record->department) }} / {{ ucwords(str_replace('_',' ',$record->kind)) }} / #{{ $record->id }}</p><h2>{{ $record->title }}</h2><p>{{ $record->client }} · {{ $record->project }}</p><p class="muted">Responsible: {{ $record->owner?->name??'Assignment needed' }} · Status: {{ $record->status }} · Published {{ $record->approved_at->format('d M Y H:i') }}</p><div class="panel formgrid">@foreach($sourceData as $key=>$value)@if($value!==null&&$value!=='')<div><p class="muted">{{ ucwords(str_replace('_',' ',$key)) }}</p><p>{{ strip_tags((string)$value) }}</p></div>@endif @endforeach</div>
@if($record->kind==='activation'&&$record->department==='operations_projects')@php
 $plan=app(\App\Services\ExecutionPlan::class)->forActivation($record);
@endphp @if($plan)<a class="button" href="{{ route('portal.executive.controls.show',$plan) }}">Open shared execution commitments ↗</a>@endif @endif
@if(\App\Services\OperationsReporting::member(auth()->user())&&\App\Models\User::normalizeDepartmentKey($record->department)==='operations_projects')<a class="button" href="{{ route('portal.operations',['ops_edit'=>$record->id]) }}">Open Operations entry ↗</a>@endif
</section></x-app-layout>
