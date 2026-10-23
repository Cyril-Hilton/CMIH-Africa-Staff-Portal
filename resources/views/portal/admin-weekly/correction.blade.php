@if($line && $report->status==='returned' && ((int)$report->prepared_by===auth()->id() || auth()->user()->hasFullHrAccess()))
<details class="mt-3"><summary class="cursor-pointer">Correct row</summary><form method="POST" action="{{ route('portal.admin-weekly.correct',[$report,$line]) }}" class="mt-3 min-w-[18rem] space-y-3">@csrf<input type="hidden" name="revision" value="{{ $report->revision }}">
<label class="block">Availability<select name="value_state" class="w-full rounded-xl bg-brand-black p-3">@foreach(['reported'=>'Reported','not_reported'=>'Not reported','not_applicable'=>'Not applicable'] as $value=>$label)<option value="{{ $value }}" @selected($line->value_state===$value)>{{ $label }}</option>@endforeach</select></label>
@include('portal.performance.field',['name'=>$field['type']==='number'?'numeric_value':'text_value','type'=>$field['type'],'options'=>null,'value'=>$field['type']==='number'?$line->numeric_value:$line->text_value,'label'=>'Corrected value','required'=>false])
@foreach(['status','issue','action','due_date'] as $key)
@include('portal.performance.field',['name'=>$key,'type'=>$key==='due_date'?'date':'text','options'=>null,'value'=>$key==='due_date'?$line->due_date?->toDateString():$line->{$key},'label'=>ucfirst(str_replace('_',' ',$key)),'required'=>false])
@endforeach
@include('portal.performance.field',['name'=>'owner_id','type'=>'text','options'=>$staff->pluck('name','id')->all(),'value'=>$line->owner_id,'label'=>'Owner','required'=>false])
<label class="block">Correction reason<input name="reason" required maxlength="2000" class="w-full rounded-xl bg-brand-black p-3"></label><button class="rounded-xl bg-brand-red p-3">Save correction</button></form></details>
@endif
