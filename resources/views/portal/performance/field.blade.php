<label class="min-w-0 text-xs text-brand-ash">{{ $label ?? (['kpi'=>'KPI','kpi_id'=>'KPI','owner_id'=>'Accountable Owner','reviewer_id'=>'Reviewer','objective_id'=>'Objective','evidence_url'=>'Evidence Link','from'=>'Reporting From','to'=>'Reporting Through'][$name] ?? ucwords(str_replace('_', ' ', $name))) }}
@if(isset($options))
<select name="{{ $name }}" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2.5 text-brand-white" @required($required ?? true)>
<option value="">Choose…</option>@foreach($options as $key=>$text)<option value="{{ $key }}" @selected((string)(($useOld??true)?old($name, $value ?? ''):($value??''))===(string)$key)>{{ $text }}</option>@endforeach
</select>
@elseif(($type ?? '') === 'textarea')
<textarea name="{{ $name }}" rows="3" maxlength="15000" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2.5 text-brand-white" @required($required ?? true)>{{ (($useOld??true)?old($name,$value ?? ''):($value??'')) }}</textarea>
@else
<input name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ (($useOld??true)?old($name,$value ?? ''):($value??'')) }}" @if(($type ?? '')==='number') step="0.0001" min="0" max="99999999999999" @endif class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2.5 text-brand-white" @required($required ?? true)>
@endif
</label>
