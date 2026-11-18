@php($navigationTrail=app(\App\Services\PortalBreadcrumbs::class)->build(request()))
@if(count($navigationTrail)>1)
<nav aria-label="Breadcrumbs" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-white/20 px-4 py-3 text-sm" style="background:rgba(128,128,128,.08)">
 <ol class="flex min-w-0 flex-wrap items-center gap-2">@foreach($navigationTrail as $crumb)<li class="flex min-w-0 items-center gap-2">@if(!$loop->first)<span aria-hidden="true">›</span>@endif @if($crumb['url'])<a class="underline underline-offset-4 break-words" href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>@else<span aria-current="page" class="font-semibold break-words">{{ $crumb['label'] }}</span>@endif</li>@endforeach</ol>
 @php($parentCrumb=collect($navigationTrail)->filter(fn($c)=>$c['url'])->last())
 @if($parentCrumb)<a class="shrink-0 rounded-lg border border-brand-white/20 px-3 py-2 font-semibold" href="{{ $parentCrumb['url'] }}">← Back to {{ $parentCrumb['label'] }}</a>@endif
</nav>
@endif
