<form method="POST" enctype="multipart/form-data" action="{{ $record ? route('portal.hr-registers.update',[$kind,$record['id']]) : route('portal.hr-registers.store',$kind) }}" class="hr-activity-form mt-4" data-no-ajax data-no-silent>
@csrf @if($record)@method('PATCH')<input type="hidden" name="revision" value="{{ $record['revision'] }}">@endif
@foreach(['hr_period'=>$period,'hr_from'=>$from,'hr_to'=>$to] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
@foreach($schema['fields'] as $field=>$f)
    @php($value=old('data.'.$field,$record['data'][$field]??null))
    @php($type=$f['type'])
    <label @class(['hr-activity-wide'=>in_array($type,['textarea','staff_multi'])])>{{ $f['label'] }}
    @if($type==='textarea')<textarea name="data[{{ $field }}]" rows="2" maxlength="6000">{{ $value }}</textarea>
    @elseif($type==='checkbox')<input type="hidden" name="data[{{ $field }}]" value="0"><input type="checkbox" name="data[{{ $field }}]" value="1" @checked($value)>
    @elseif(in_array($type,['select','staff','staff_multi','department'])||str_starts_with($type,'record='))
        @php($options=match(true){$type==='select'=>array_combine($f['options'],$f['options']),$type==='department'=>config('performance.departments'),$type==='staff'||$type==='staff_multi'=>$people->all(),default=>$registers->options(substr($type,7),auth()->user())})
        <select name="data[{{ $field }}]{{ $type==='staff_multi'?'[]':'' }}" @if($type==='staff_multi') multiple size="5" @endif><option value="">Choose {{ strtolower($f['label']) }}</option>@foreach($options as $id=>$name)<option value="{{ $id }}" @selected($type==='staff_multi'?in_array((string)$id,array_map('strval',(array)$value),true):(string)$value===(string)$id)>{{ $name }}</option>@endforeach</select>
    @else<input type="{{ in_array($type,['date','datetime-local','email','number'])?$type:'text' }}" name="data[{{ $field }}]" value="{{ $value }}" @if($type==='number') min="0" step="any" @else maxlength="255" @endif>
    @endif
    </label>
@endforeach
@if(!in_array($kind,['staff','asset','vendor']))<label class="hr-activity-wide">Supporting document or photo (optional)<input type="file" name="attachment" accept=".pdf,.docx,.xlsx,.pptx,.txt,.jpg,.jpeg,.png,.webp"><small>Private upload, maximum 10 MB.</small></label>@endif
<div class="hr-activity-wide"><button class="hr-report-control hr-entry-private">Save record</button></div>
</form>
