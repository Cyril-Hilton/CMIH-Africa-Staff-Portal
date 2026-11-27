<details class="ops-entry mb-4"><summary class="ops-button">Request Excel / PDF export</summary><p class="br-help mt-3">Exports use the approved activations matching the current period, staff and table filters. The HOD approves the exact snapshot before download.</p>
<form method="POST" action="{{ route('portal.operations.activation-transfers.export') }}" class="br-filters" data-no-silent data-no-ajax>@csrf
@include('portal.partials.query-hidden',['queryFields'=>request()->except(['ops_add','ops_edit','ops_review','columns','format','_token'])])
<label>File type<select name="format"><option value="xlsx">Excel workbook</option><option value="pdf">PDF report</option></select></label>
<details><summary class="ops-button">Choose columns</summary><div class="ops-form">@foreach(app(\App\Services\OperationsActivationImport::class)->columns() as $key=>$label)<label><input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key,['title','client','project','owner','week','brand','location','starts_on','ends_on','status']))> {{ $label }}</label>@endforeach</div></details>
<button class="ops-button ops-primary">Send export for HOD approval</button>
</form></details>
