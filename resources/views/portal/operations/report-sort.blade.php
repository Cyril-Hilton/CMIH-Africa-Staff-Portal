@php($selectedSort = ($filters['ops_sort'] ?? 'newest') === $sortKey)
<th scope="col" @if($selectedSort) aria-sort="{{ $sortKey === 'newest' || ($filters['ops_direction'] ?? 'asc') === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <a class="ops-sort" href="{{ $workspaceUrl(['ops_sort' => $sortKey, 'ops_direction' => $selectedSort && ($filters['ops_direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">{{ $column }} <span aria-hidden="true">{{ $selectedSort ? (($filters['ops_direction'] ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span></a>
</th>
