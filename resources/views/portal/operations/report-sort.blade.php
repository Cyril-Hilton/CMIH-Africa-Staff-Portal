@if(isset($tableKey))
@php
 $sortField=$sortKey==='owner'?'owner_id':$sortKey;$ts=request('ops_tables.'.$tableKey,[]);$selectedSort=($ts['sort']??'')===$sortField;$ts['sort']=$sortField;$ts['direction']=$selectedSort&&($ts['direction']??'asc')==='asc'?'desc':'asc';$allTables=request('ops_tables',[]);$allTables[$tableKey]=$ts;
@endphp
<th scope="col" @if($selectedSort) aria-sort="{{ $ts['direction']==='desc'?'ascending':'descending' }}" @endif><a class="ops-sort" href="{{ $workspaceUrl(['ops_tables'=>$allTables,'ops_'.$tableKey.'_page'=>null]) }}">{{ $column }} ↕</a></th>
@else
@php($selectedSort = ($filters['ops_sort'] ?? 'newest') === $sortKey)
<th scope="col" @if($selectedSort) aria-sort="{{ $sortKey === 'newest' || ($filters['ops_direction'] ?? 'asc') === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <a class="ops-sort" href="{{ $workspaceUrl(['ops_sort' => $sortKey, 'ops_direction' => $selectedSort && ($filters['ops_direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">{{ $column }} <span aria-hidden="true">{{ $selectedSort ? (($filters['ops_direction'] ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span></a>
</th>

@endif
