@php
$existing=$activity??null;
$activityOwners=$staff->pluck('name','id');
if($existing?->owner) $activityOwners->put($existing->owner_id,$existing->owner->name);
$activityProjects=$scorecardProjects->pluck('project_name','id');
if($existing?->project) $activityProjects->put($existing->project_id,$existing->project->project_name);
@endphp
<form method="POST" action="{{ $existing ? route('portal.crm.record.update',$existing) : route('portal.crm.record') }}" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
@csrf<input type="hidden" name="kind" value="{{ $kind }}">
@if($existing)<input type="hidden" name="revision" value="{{ $existing->revision }}">@endif
@foreach(['reference','client_name'] as $field)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'text','value'=>$existing?->$field,'required'=>true,'label'=>null,'useOld'=>false])
@endforeach
@include('portal.performance.field',['name'=>'owner_id','options'=>auth()->user()->isLineManager()?$activityOwners->all():[auth()->id()=>auth()->user()->name],'type'=>'text','value'=>$existing?->owner_id??auth()->id(),'required'=>true,'label'=>'Accountable Owner','useOld'=>false])
@include('portal.performance.field',['name'=>'project_id','options'=>$activityProjects->all(),'type'=>'text','value'=>$existing?->project_id,'required'=>false,'label'=>'Linked Project','useOld'=>false])
@include('portal.performance.field',['name'=>'status','options'=>array_combine($spec['statuses'],$spec['statuses']),'type'=>'text','value'=>$existing?->status,'required'=>true,'label'=>null,'useOld'=>false])
@foreach($spec['fields'] as $key=>$type)
@include('portal.performance.field',['name'=>'data['.$key.']','options'=>null,'type'=>$type,'value'=>$existing?->data[$key]??null,'required'=>false,'label'=>ucwords(str_replace('_',' ',$key)),'useOld'=>false])
@endforeach
@include('portal.performance.field',['name'=>'notes','options'=>null,'type'=>'textarea','value'=>$existing?->notes,'required'=>false,'label'=>'Private Notes','useOld'=>false])
@if($existing)
@include('portal.performance.field',['name'=>'reason','options'=>null,'type'=>'textarea','value'=>null,'required'=>true,'label'=>'Update Reason / Confirmation Source','useOld'=>false])
@endif
<button type="submit" class="rounded-xl bg-brand-red p-3">{{ $existing?'Save Activity Update':'Record Source' }}</button>
</form>
