@php($record=$project ?? null)
<form method="POST" action="{{ $record?route('portal.crm.update',$record):route('portal.crm.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@csrf
@if($record)<input type="hidden" name="revision" value="{{ $record->revision }}">@endif
@foreach(['client_name','brand','project_name','contact_name','contact_position','contact_info','po_status','invoice_status','source_reference'] as $field)
@include('portal.performance.field',array_merge(['options'=>null,'type'=>'text','value'=>$record?->{$field},'required'=>in_array($field,['client_name','project_name']),'label'=>null],['name'=>$field]))
@endforeach
@include('portal.performance.field',['name'=>'owner_id','options'=>$staff->pluck('name','id')->all(),'type'=>'text','value'=>$record?->owner_id??auth()->id(),'required'=>true,'label'=>'Account owner'])
@include('portal.performance.field',['name'=>'stage','options'=>collect(config('crm_pipeline.stages'))->pluck('label')->combine(array_keys(config('crm_pipeline.stages')))->flip()->all(),'type'=>'text','value'=>$record?->stage,'required'=>true,'label'=>'Sales stage'])
@foreach(['rate_card_value','execution_value','proposed_amount','po_amount','target_amount','actual_amount','invoiced_amount','collected_amount'] as $field)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'number','value'=>$record?->{$field},'required'=>false,'label'=>ucwords(str_replace('_',' ',$field)).' (GHS · private)'])
@endforeach
@foreach(['description'=>'Project Description','shared_bottlenecks'=>'Bottlenecks','shared_next_action'=>'Next Line of Action','comments'=>'Comments'] as $field=>$label)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'textarea','value'=>$record?->{$field},'required'=>false,'label'=>$label.' · visible to all staff'])
@endforeach
<p class="text-xs text-brand-ash sm:col-span-2 xl:col-span-3">Shared notes appear on the weekly table. Keep amounts and confidential details in the private notes below.</p>
@foreach(['bottlenecks','next_action','remarks'] as $field)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'textarea','value'=>$record?->{$field},'required'=>false,'label'=>ucwords(str_replace('_',' ',$field)).' (private)'])
@endforeach
@include('portal.performance.field',['name'=>'public_action','options'=>config('crm_pipeline.public_actions'),'type'=>'text','value'=>$record?->public_action,'required'=>false,'label'=>'Staff-visible action category'])
@include('portal.performance.field',['name'=>'week_start','options'=>null,'type'=>'date','value'=>($record?->import_key && !$record->source_period_confirmed) ? null : ($record?->week_start?->toDateString()??now()->startOfWeek()->toDateString()),'required'=>true,'label'=>'Reporting week start'])
@if($record?->import_key && !$record->source_period_confirmed)
<div class="rounded-xl border border-amber-400/30 p-3 sm:col-span-2 xl:col-span-3"><p class="text-sm text-amber-400">Action Required · Confirm Reporting Date</p><p class="mt-2 text-xs text-brand-ash">The original date is ambiguous. This record stays outside totals and the shared weekly table until a manager checks the date against the source.</p>@if(auth()->user()->isLineManager())<label class="mt-3 block text-sm"><input type="checkbox" name="confirm_source_period" value="1"> I checked the source and confirm the reporting date entered above.</label>@endif</div>
@endif
@include('portal.performance.field',['name'=>'expected_close_date','options'=>null,'type'=>'date','value'=>$record?->expected_close_date?->toDateString(),'required'=>false,'label'=>'Expected close date'])
@include('portal.performance.field',['name'=>'reason','options'=>null,'type'=>'text','value'=>null,'required'=>true,'label'=>'Change / source note (private audit)'])
@include('portal.performance.field',['name'=>'po_state','options'=>['unknown'=>'Not recorded','not_requested'=>'PO not requested','requested'=>'PO requested / outstanding','received'=>'PO received','not_required'=>'PO not required'],'type'=>'text','value'=>$record?->po_state,'required'=>false,'label'=>'PO tracking state'])
<label class="text-xs text-brand-ash"><input type="hidden" name="new_business" value="0"><input type="checkbox" name="new_business" value="1" @checked($record?->new_business)> New business opportunity</label>
<button type="submit" class="rounded-xl bg-brand-red px-4 py-3 font-semibold text-white">Save Project & Update Weekly Table</button>
</form>

@if($project?->import_key)<details class="mt-3 rounded-xl border border-brand-white/20 p-3"><summary>Original source snapshot</summary><p class="mt-2 text-xs">{{ $project->source_reference }} · {{ $project->source_period_label }}</p><p class="mt-2 text-xs">Original forecast: {{ $project->source_forecast===null?'Not supplied':'GHS '.number_format((float)$project->source_forecast,2) }}. Kept separately from the recalculated stage forecast and actual revenue.</p><pre class="mt-2 max-h-64 overflow-auto text-xs">{{ json_encode($project->source_values['source_values']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
