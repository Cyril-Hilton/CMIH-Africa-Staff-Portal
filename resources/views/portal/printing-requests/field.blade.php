@php($fieldName=$group.'['.$key.']')
@php($fieldValue=old($group.'.'.$key,$record->{$group}[$key]??''))
<label class="print-field"><span>{{ $label }}@if($group==='information'||in_array($type,['yesno','select'])) <b>*</b>@endif</span>
@if($type==='select'||$type==='yesno')<select name="{{ $fieldName }}" required><option value="">Select</option>@foreach($type==='yesno'?['Yes','No']:config('printing_requests.options.'.$key) as $option)<option value="{{ $option }}" @selected($fieldValue===$option)>{{ $option }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{ $fieldName }}" rows="3" maxlength="1500">{{ $fieldValue }}</textarea>
@else<input type="{{ $type }}" name="{{ $fieldName }}" value="{{ $fieldValue }}" @if($group==='information' && $key==='requested_by' && !auth()->user()->canManageOperationsPrintingRequests()) readonly aria-readonly="true" @endif @required($group==='information') @if($type==='number') min="0" max="999999999999" step="0.01" @else maxlength="250" @endif>@endif</label>
