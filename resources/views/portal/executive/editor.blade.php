@php
 $sectionData=$control?->data[$section]??($section==='schedule'?($focus['schedule']??[]):[]);
 $datetime=fn($value)=>$value?\Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i'):'';
@endphp
<details class="panel"><summary>{{ $sectionTitle }} · edit recorded plan</summary><form method="post" action="{{ ($focus['source_kind']??'crm')==='activation'?route('portal.executive.activations.save',$focus['native_id']):route('portal.executive.controls.save',$control?->crm_project_id??$project->id) }}">@csrf
<input type="hidden" name="section" value="{{ $section }}"><input type="hidden" name="revision" value="{{ $revision }}">
@include('portal.executive.fields')
<label>Reason for this update<textarea name="reason" required maxlength="2000" placeholder="What changed, why, and the source of the commitment"></textarea></label><button class="primary" style="margin-top:12px">Save {{ $section }} & record history</button>
</form></details>
