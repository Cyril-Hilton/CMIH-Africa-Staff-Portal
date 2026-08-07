@props(['paginator', 'itemLabel' => 'rows'])

@if($paginator && $paginator->total() > 0)
    <div class="flex flex-col gap-3 rounded-2xl border border-brand-white/10 bg-brand-black/35 px-4 py-3 text-xs text-brand-white/60 sm:flex-row sm:items-center sm:justify-between">
        <p>
            Showing
            <span class="font-semibold text-brand-white">{{ $paginator->firstItem() }}</span>
            to
            <span class="font-semibold text-brand-white">{{ $paginator->lastItem() }}</span>
            of
            <span class="font-semibold text-brand-white">{{ $paginator->total() }}</span>
            {{ $itemLabel }}
        </p>

        @if($paginator->hasPages())
            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();

                if ($lastPage <= 7) {
                    $pages = range(1, $lastPage);
                } else {
                    $windowStart = max(2, $currentPage - 2);
                    $windowEnd = min($lastPage - 1, $currentPage + 2);
                    $pages = [1];

                    if ($windowStart > 2) {
                        $pages[] = '...';
                    }

                    foreach (range($windowStart, $windowEnd) as $page) {
                        $pages[] = $page;
                    }

                    if ($windowEnd < $lastPage - 1) {
                        $pages[] = '...';
                    }

                    $pages[] = $lastPage;
                }
            @endphp
            <nav class="flex flex-wrap items-center gap-1" aria-label="Table pagination">
                @if($paginator->onFirstPage())
                    <span class="rounded-lg border border-brand-white/5 px-3 py-2 text-brand-white/25">Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-brand-white/10 px-3 py-2 text-brand-white/70 transition hover:border-brand-red/40 hover:bg-brand-red/10 hover:text-brand-white">Prev</a>
                @endif

                @foreach($pages as $page)
                    @if($page === '...')
                        <span class="px-2 py-2 text-brand-white/30">...</span>
                    @elseif($page === $currentPage)
                        <span class="rounded-lg border border-brand-red bg-brand-red/20 px-3 py-2 font-bold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="rounded-lg border border-brand-white/10 px-3 py-2 text-brand-white/70 transition hover:border-brand-red/40 hover:bg-brand-red/10 hover:text-brand-white">{{ $page }}</a>
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-brand-white/10 px-3 py-2 text-brand-white/70 transition hover:border-brand-red/40 hover:bg-brand-red/10 hover:text-brand-white">Next</a>
                @else
                    <span class="rounded-lg border border-brand-white/5 px-3 py-2 text-brand-white/25">Next</span>
                @endif
            </nav>
        @endif
    </div>
@endif
