@if($page->hasPages())
    <nav class="br-pagination" aria-label="{{ $paginationLabel }} pagination">
        <span>{{ $page->firstItem() ?? 0 }}–{{ $page->lastItem() ?? 0 }} of {{ $page->total() }} · Page {{ $page->currentPage() }} of {{ $page->lastPage() }}</span>
        <div>
            @if($page->previousPageUrl())<a class="ops-button" href="{{ $page->previousPageUrl() }}" rel="prev">Previous</a>@endif
            @if($page->nextPageUrl())<a class="ops-button" href="{{ $page->nextPageUrl() }}" rel="next">Next</a>@endif
        </div>
    </nav>
@endif
