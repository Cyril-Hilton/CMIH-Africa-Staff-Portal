@php
 $sourceClientEntry=true;$sourceEntry=true;
 $control=$record?\App\Models\ProjectExecutionControl::where('crm_project_id',$record->id)->first():null;
 $section='process';$sectionData=$useOld?old('execution.process',$control?->data['process']??[]):($control?->data['process']??[]);
 $staff=\App\Services\Creative\CreativeAccess::collaborators();$names=$staff->keyBy('id');
 $datetime=fn($value)=>$value?\Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i'):'';
 $fieldHtml=view('portal.executive.fields',get_defined_vars())->render();
 $fieldHtml=preg_replace('/(\bname="(?:\x27)?)data\[/','$1execution[process][',$fieldHtml);
@endphp
@include('portal.executive.input-styles')
<details class="execution-inputs rounded-xl border border-sky-300/30 p-4"><summary class="cursor-pointer font-semibold">Process milestones · brief review, proposal, presentation & budget</summary><p class="mt-3 text-xs text-brand-ash">The client brief receipt date comes from the existing connected-project section. These additional commitments feed the executive tracker after manager publication; no second executive entry is required.</p><input type="hidden" name="execution_revision" value="{{ $control?->revision??0 }}"><label class="mt-3"><input type="checkbox" name="execution_process_present" value="1" @checked($useOld&&old('execution_process_present'))> Include the process commitments below in this entry for review</label><div class="mt-4">{!! $fieldHtml !!}</div></details>
