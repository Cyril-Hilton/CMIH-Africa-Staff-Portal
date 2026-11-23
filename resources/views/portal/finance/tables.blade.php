@foreach($f_tables as $tableKind=>$tableRows)
@php
$tableLabel=\App\Services\FinanceDashboard::LABELS[$tableKind];
$columns=\App\Services\FinanceDashboard::COLUMNS[$tableKind];
$tableEdit=$f_edit && $f_edit->kind===$tableKind ? $f_edit : null;
$openEntry=$tableEdit || request('finance_kind')===$tableKind || ($errors->any() && old('kind')===$tableKind);
@endphp
<section class="fd-panel space-y-4" data-finance-table="{{ $tableKind }}" x-data x-init="@if($openEntry)$nextTick(() => $refs.entry.showModal())@endif">
<header class="fd-table-heading"><div><p class="fd-kicker">Finance entries</p><h4>{{ $tableLabel }} <span class="fd-badge">{{ $tableRows->total() }}</span></h4></div>
@if($fWrite)<button type="button" @click="$refs.entry.showModal()" aria-haspopup="dialog">+ Add {{ match($tableKind){'revenue'=>'Finance Revenue','revenue_target'=>'Monthly Revenue Target','commercial_risk'=>'Commercial Risk','cash'=>'Cash Balance',default=>ucwords(str_replace('_',' ',$tableKind))} }}</button>@endif</header>
<div class="fd-table-wrap"><table aria-label="{{ $tableLabel }}"><thead><tr>@foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Review / actions</th></tr></thead><tbody>
@forelse($tableRows as $row)
<tr id="finance-record-{{ $row->id }}">
@foreach($columns as $field=>$label)
<td>@include('portal.finance.table-value')</td>
@endforeach
<td><span class="fd-badge {{ $row->approved_at?'fd-good':'' }}">{{ $row->approved_at?'Published':'Awaiting Precious’s review' }}</span><p class="fd-muted">{{ $f_names[$row->owner_id]??'Retained owner' }}</p>
@if($fWrite && ((int)$row->submitted_by===auth()->id()||\App\Services\PerformanceAccess::manages(auth()->user(),'finance')))<a class="fd-link mt-2" href="{{ $fUrl(['finance_edit'=>$row->id]) }}">Edit entry</a>@endif
<details class="mt-3"><summary>Details & review</summary>@include('portal.finance.record-details')</details></td></tr>
@empty<tr><td colspan="{{ count($columns)+1 }}"><div class="fd-empty"><strong>No {{ strtolower($tableLabel) }} in this selection.</strong><p class="fd-muted">{{ $fWrite?'Use the entry button above to add a record, or adjust your filters.':'Adjust the filters to view recorded entries.' }}</p></div></td></tr>@endforelse
</tbody></table></div>{{ $tableRows->links() }}
@if($fWrite)
<dialog class="fd-dialog" x-ref="entry" aria-labelledby="finance-entry-title-{{ $tableKind }}" @click="if($event.target===$el)$el.close()">
<div class="fd-dialog-header"><div><p class="fd-kicker">Finance / {{ $tableLabel }}</p><h4 id="finance-entry-title-{{ $tableKind }}">{{ $tableEdit?'Edit entry':'Add '.match($tableKind){'commercial_risk'=>'commercial risk','cash'=>'cash balance',default=>str_replace('_',' ',$tableKind)} }}</h4></div><button type="button" class="fd-close" @click="$refs.entry.close()" aria-label="Close {{ $tableLabel }} form">×</button></div>
@include('portal.finance.entry',['f_kind'=>$tableKind,'f_edit'=>$tableEdit])
</dialog>
@endif
</section>
@endforeach
