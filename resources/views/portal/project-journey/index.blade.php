<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Project Journey</h1>
            @if(auth()->user()->canViewExecutiveActivity())
                <a class="underline" href="{{ route('dashboard') }}#executive-activity">Executive Activity</a>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5">
        <div class="glass-panel rounded-2xl border border-brand-white/10 p-4 sm:p-5">
            <p class="mb-4 text-sm text-brand-ash">Follow client projects from the brief through creative sign-off, handover, execution and closeout.</p>
            @if(session('status'))<p class="mb-3 text-sm text-emerald-300" role="status">{{ session('status') }}</p>@endif
            @if($errors->any())<p class="mb-3 text-sm text-brand-red" role="alert">{{ $errors->first() }}</p>@endif
            @if($focus)
                <div class="flex flex-wrap items-center gap-4">
                    <a class="underline" href="{{ route('portal.project-journey') }}">All project journeys</a>
                    @if($linkOptions->isNotEmpty())
                        <form class="flex flex-wrap items-end gap-2" method="POST" action="{{ route('portal.project-journey.link') }}">
                            @csrf
                            <input name="project_id" type="hidden" value="{{ $focus }}">
                            <label class="text-sm">Link an existing creative brief
                                <select name="brief_id" required class="mt-1 block w-full max-w-md rounded-lg bg-brand-black p-2.5">
                                    <option value="">Select a brief</option>
                                    @foreach($linkOptions as $brief)<option value="{{ $brief->id }}">{{ $brief->reference }} · {{ $brief->title }}</option>@endforeach
                                </select>
                            </label>
                            <button class="rounded-lg border border-brand-white/20 px-4 py-2.5 text-sm">Link brief</button>
                        </form>
                    @endif
                </div>
            @else
                <nav class="mb-4 flex flex-wrap gap-2" aria-label="Project journey views">
                    @foreach(['journeys'=>'Journeys','upcoming'=>'Upcoming','insights'=>'Insights'] as $key => $label)
                        <a @class(['rounded-xl border px-3 py-2 text-sm', 'border-sky-400/50 bg-sky-400/15 font-semibold' => $view === $key, 'border-brand-white/15' => $view !== $key])
                           @if($view === $key) aria-current="page" @endif href="{{ route('portal.project-journey', array_replace(request()->except('page'), ['view'=>$key, 'stage'=>null])) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('portal.project-journey') }}">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <label class="text-sm">Search by client, brand or project<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 block rounded-lg bg-brand-black p-2.5"></label>
                    <label class="text-sm">Period
                        <select name="period" class="mt-1 block rounded-lg bg-brand-black p-2.5">
                            @foreach(['week'=>'Week','month'=>'Month','year'=>'Year'] as $key => $label)<option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm">Date in period<input type="date" name="date" value="{{ $date->toDateString() }}" class="mt-1 block rounded-lg bg-brand-black p-2.5"></label>
                    @if($view !== 'insights')
                        <label class="text-sm">Stage
                            <select name="stage" class="mt-1 block rounded-lg bg-brand-black p-2.5">
                                <option value="">All stages</option>
                                @foreach(\App\Services\ProjectJourney::STAGES as $key => [$label])<option value="{{ $key }}" @selected(($filters['stage'] ?? null) === $key)>{{ $label }}</option>@endforeach
                                <option value="complete" @selected(($filters['stage'] ?? null) === 'complete')>Verified complete</option>
                                <option value="cancelled" @selected(($filters['stage'] ?? null) === 'cancelled')>Closed or cancelled</option>
                            </select>
                        </label>
                    @endif
                    <button class="rounded-lg border border-brand-white/20 px-4 py-2.5 text-sm">Apply filters</button>
                    <a class="px-2 py-2 text-sm underline" href="{{ route('portal.project-journey', ['view'=>$view]) }}">Clear</a>
                </form>
            @endif
        </div>
        <div id="project-journey-live" data-fragment="{{ route('portal.project-journey', array_replace(request()->query(), ['fragment'=>1])) }}">
            @include('portal.project-journey.results')
        </div>
        <noscript><p class="text-sm text-brand-ash">Refresh this page for the latest project updates.</p></noscript>
    </div>
    <script>
        (() => {
            const results = document.getElementById('project-journey-live');
            if (!results || results.dataset.polling) return;
            results.dataset.polling = 'true';
            let updating = false;
            const refresh = async () => {
                if (updating || document.hidden || !results.isConnected || results.contains(document.activeElement)) return;
                updating = true;
                try {
                    const response = await fetch(results.dataset.fragment, { cache: 'no-store', credentials: 'same-origin' });
                    if (!response.ok || response.redirected) return;
                    const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const fresh = documentFragment.querySelector('[data-journey-results]');
                    if (!fresh || results.contains(document.activeElement)) return;
                    const expanded = new Set([...results.querySelectorAll('details[open][data-journey-detail]')].map(detail => detail.dataset.journeyDetail));
                    fresh.querySelectorAll('[data-journey-detail]').forEach(detail => {
                        if (expanded.has(detail.dataset.journeyDetail)) detail.open = true;
                    });
                    results.replaceChildren(fresh);
                } catch {
                    // Preserve the last successful results until the next refresh.
                } finally {
                    updating = false;
                }
            };
            const timer = setInterval(() => {
                if (!results.isConnected) return clearInterval(timer);
                refresh();
            }, 45000);
        })();
    </script>
</x-app-layout>
