<section class="br-table-section" aria-label="Imported Operations workbooks">
    <header class="br-table-heading"><h4>Imported Source Workbooks</h4></header>
    <p>Review the original cell values, formulas and notes from the import. Use the workbook links for original formatting and charts.</p>
    @if(!$sourceArchive)
        <p>No workbook snapshot has been imported.</p>
    @else
        @php
            $index = array_key_exists((int) request('ops_source'), $sourceArchive) ? (int) request('ops_source') : 0;
            $source = $sourceArchive[$index];
            $sourceRows=app(\App\Services\OperationsTableQuery::class)->collection(collect($source['rows']),request(),'sources');$sourcePerPage=(int)(request('ops_tables.sources.rows',25));
            $page = new \Illuminate\Pagination\LengthAwarePaginator(
                $sourceRows->forPage(max(1,(int)request('ops_source_page',1)),$sourcePerPage)->values(),
                $sourceRows->count(), $sourcePerPage, (int) request('ops_source_page', 1),
                ['path' => url()->current(), 'pageName' => 'ops_source_page']);
            $page->appends(request()->query()+['ops_tab' => 'sources', 'ops_source' => $index])->fragment('operations-reporting');
        @endphp
        <nav class="br-staff" aria-label="Source worksheet">
            @foreach($sourceArchive as $id => $sheet)
                <a class="ops-button" href="{{ $workspaceUrl(['ops_source' => $id]) }}" @if($id === $index) aria-current="page" @endif>{{ $sheet['sheet'] }}</a>
            @endforeach
        </nav>
        <header class="br-table-heading">
            <h5>{{ $source['workbook'] }} · {{ $source['sheet'] }} @if($source['hidden']) (hidden in source) @endif</h5>
            <a class="ops-button" href="{{ $source['url'] }}" rel="noopener noreferrer">Open Original Workbook</a>
        </header>
        @include('portal.operations.table-filters',['tableKey'=>'sources','tableLabel'=>'Original source rows'])
        <div id="ops-table-sources" class="br-table-wrap" tabindex="0" role="region" aria-label="Source cell values">
            <table class="br-table ops-table" data-table-enhancer="off">
                <thead><tr><th scope="col">Sheet row</th><th scope="col">Original cells</th></tr></thead>
                <tbody>
                    @foreach($page as $row)
                        <tr><td>{{ $row['number'] }}</td><td>
                            @foreach($row['cells'] as $cell)
                                <p class="mb-2"><strong>{{ $cell['address'] }}</strong>
                                    <span class="whitespace-pre-wrap">{{ $cell['value'] }}</span>
                                    @if(isset($cell['cached'])) <small>Stored result: {{ $cell['cached'] }}</small>@endif
                                    @if(isset($cell['note'])) <small>Note: {{ $cell['note'] }}</small>@endif
                                    @if(isset($cell['link'])) <small>Link: {{ $cell['link'] }}</small>@endif
                                </p>
                            @endforeach
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('portal.operations.report-pagination', ['paginationLabel' => $source['sheet']])
    @endif
</section>
