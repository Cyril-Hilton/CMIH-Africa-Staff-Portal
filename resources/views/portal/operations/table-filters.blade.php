@php
 $tq=app(\App\Services\OperationsTableQuery::class);$tv=$tq->values(request(),$tableKey);$tf=$tq->fields($tableKey);
 $keep=request()->except(array_filter(array_keys(request()->query()),fn($k)=>str_ends_with($k,'_page')||$k==='page'));$tables=$keep['ops_tables']??[];unset($tables[$tableKey]);unset($keep['ops_tables']);
@endphp
<form method="GET" action="{{ url()->current() }}#ops-table-{{ $tableKey }}" class="br-table-tools" data-no-silent data-no-ajax aria-label="Filter {{ $tableLabel??$tableKey }}">
 @foreach($keep as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
 @foreach($tables as $key=>$values)@foreach($values as $field=>$value)@if(is_scalar($value))<input type="hidden" name="ops_tables[{{ $key }}][{{ $field }}]" value="{{ $value }}">@endif @endforeach @endforeach
 <label>Search this table<input type="search" name="ops_tables[{{ $tableKey }}][q]" maxlength="150" value="{{ $tv['q']??'' }}" placeholder="Across all pages"></label>
 @if($tableKey==='activation')
 <label>Brand<input type="search" name="ops_tables[activation][brand]" maxlength="150" value="{{ $tv['brand']??'' }}" placeholder="All brands"></label>
 <label>Location<input type="search" name="ops_tables[activation][location]" maxlength="150" value="{{ $tv['location']??'' }}" placeholder="All locations"></label>
 <label>Project lead<input type="search" name="ops_tables[activation][lead]" maxlength="150" value="{{ $tv['lead']??'' }}" placeholder="Name of project lead"></label>
 <details><summary class="ops-button">More filters</summary>
 @endif
 <label>Filter column<select name="ops_tables[{{ $tableKey }}][field]"><option value="">All columns</option>@foreach($tf as $key=>$label)<option value="{{ $key }}" @selected(($tv['field']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
 <label>Matching value<input type="search" name="ops_tables[{{ $tableKey }}][value]" maxlength="150" value="{{ $tv['value']??'' }}" placeholder="Brand, location, owner, status…"></label>
 @if($tableKey==='activation')</details>@endif
 <label>Sort this table<select name="ops_tables[{{ $tableKey }}][sort]"><option value="">Default order</option>@foreach($tf as $key=>$label)<option value="{{ $key }}" @selected(($tv['sort']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
 <label>Order<select name="ops_tables[{{ $tableKey }}][direction]"><option value="asc" @selected(($tv['direction']??'asc')==='asc')>Ascending</option><option value="desc" @selected(($tv['direction']??'')==='desc')>Descending</option></select></label>
 <label>Rows<select name="ops_tables[{{ $tableKey }}][rows]">@foreach([10,25,50] as $n)<option @selected((int)($tv['rows']??($tableKey==='sources'?25:request('ops_rows',10)))===$n)>{{ $n }}</option>@endforeach</select></label>
 <button class="ops-button">Apply to this table</button><a class="ops-button" href="{{ url()->current().'?'.http_build_query($keep+($tables?['ops_tables'=>$tables]:[])) }}#ops-table-{{ $tableKey }}">Reset this table</a>
</form>
