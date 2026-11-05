@if(count($brandsData['summary']))<div class="br-summary">@foreach($brandsData['summary'] as [$label,$value,$format])<div><small>{{ $label }}</small><strong>{{ $value===null?'Not recorded':number_format($value,is_float($value)?1:0) }}{{ $value!==null&&$format==='percent'?'%':($value!==null&&$format==='hours'?' h':'') }}</strong></div>@endforeach</div>@endif
<p class="br-note">{{ $brandsData['notice'] }}</p>
@foreach($brandsData['tables'] as $table)
<section class="br-table-section" data-br-table-section="{{ $table['id'] }}">
    <header class="br-table-heading"><h4>{{ $table['title'] }} <small>{{ $table['total'] }}</small></h4><span class="br-table-hint">Click a column heading to sort · Swipe wide tables to see more</span></header>
    <form class="br-table-tools" data-br-table-filter="{{ $table['id'] }}" data-no-ajax data-no-silent>
        <label>Filter column<select name="column"><option value="">All columns</option>@foreach($table['columns'] as $column)<option value="{{ $column['key'] }}" @selected($table['filter']['column']===$column['key'])>{{ $column['label'] }}</option>@endforeach</select></label>
        <label class="br-table-search">Search this table<input name="q" type="search" maxlength="150" value="{{ $table['filter']['q'] }}" placeholder="Search across all {{ $table['available'] }} entries"></label>
        <button type="submit" class="br-filter-button">Filter Table</button><button type="button" data-br-table-reset="{{ $table['id'] }}">Clear</button>
    </form>
    <div class="br-table-wrap" role="region" aria-label="{{ $table['title'] }}" tabindex="0">
        <table class="br-table"><caption class="sr-only">{{ $table['title'] }}. Sortable columns; filters apply before pagination.</caption><thead><tr>
            @foreach($table['columns'] as $column)<th scope="col" aria-sort="{{ $table['filter']['sort']===$column['key']?($table['filter']['direction']==='desc'?'descending':'ascending'):'none' }}" class="{{ in_array($column['format'],['number','percent'])?'br-numeric':'' }}"><button type="button" class="br-sort-button" data-br-table="{{ $table['id'] }}" data-br-column-sort="{{ $column['key'] }}" aria-label="Sort by {{ $column['label'] }}">{{ $column['label'] }}<span aria-hidden="true">{{ $table['filter']['sort']===$column['key']?($table['filter']['direction']==='desc'?'↓':'↑'):'↕' }}</span></button></th>@endforeach
            <th scope="col" class="br-action-heading">Actions</th>
        </tr></thead><tbody>
            @forelse($table['rows'] as $row)<tr>
                @foreach($table['columns'] as $column)<td class="{{ $column['format']==='rich'?'br-rich-cell':($column['format']==='people'?'br-people-cell':(in_array($column['format'],['number','percent'])?'br-numeric':($column['format']==='date'?'br-date-cell':''))) }} {{ in_array($column['key'],['title','project','location','posm_type','client','definition','source','brand','partner','cohort_label'])?'br-description-cell':'' }}">@include('portal.brands.cell')</td>@endforeach
                <td class="br-action-cell">@if($row['source_type']??null)<button type="button" class="br-row-action" data-br-detail="{{ $row['source_type'] }}:{{ $row['id'] }}">View Details <span aria-hidden="true">↗</span></button>@else<span class="br-help">—</span>@endif</td>
            </tr>@empty<tr><td class="br-empty-cell" colspan="{{ count($table['columns'])+1 }}">No entries match these filters. Clear the table filter or choose another reporting period.</td></tr>@endforelse
        </tbody></table>
    </div>
</section>
@endforeach
