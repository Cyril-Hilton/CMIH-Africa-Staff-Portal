@php
 $sourceEntry=true;
 $executionControl=app(\App\Services\ExecutionPlan::class)->forActivation($formRecord)??(!empty($selectedMaster)?\App\Models\ProjectExecutionControl::where('crm_project_id',$selectedMaster->id)->first():null);
 $proposed=$restore?old('execution',[]):($payload['data']['execution_controls']??[]);
 $submittedRevision=(int)($restore?old('execution_revision',0):($payload['data']['execution_revision']??$executionControl?->revision??0));
 $stalePlan=$executionControl&&$submittedRevision!==$executionControl->revision;
 if($stalePlan)$proposed=[];
 $masterPlan=$executionControl?->data??[];
 $staff=\App\Services\Creative\CreativeAccess::collaborators();$names=$staff->keyBy('id');
 $datetime=fn($value)=>$value?\Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i'):'';
 $formatDate=fn($value)=>$value?\Illuminate\Support\Carbon::parse($value)->format('d M Y H:i'):'Date setup needed';
 $sourceSchedule=array_filter(['brand'=>$values['brand']??null,'venue'=>$values['location']??null,'starts_at'=>$values['starts_on']??null,'ends_at'=>$values['ends_on']??null,'operations_owner_id'=>$selectedOwner],fn($v)=>$v!==null);
 $selectedSections=$restore?old('execution_sections',[]):array_keys($proposed);
@endphp
@include('portal.executive.input-styles')
<fieldset class="execution-inputs ops-narrative rounded-xl border border-sky-300/30 p-4" x-data="{}"><legend class="px-2 font-semibold">Execution plan · shared with the executive tracker</legend><p class="br-help">Enter each fact once. Your start/end dates, location and staffing below feed the activation record and executive calendar after HOD publication. Warehouse stock, issue, return and custody come directly from the warehouse checklist in this entry. External assets below are only for hired / client-supplied items.</p>
@if($stalePlan)<p class="br-help">The master execution plan changed after this submission. Its latest values are shown below. Reconcile them before resubmitting; the earlier proposal remains in the submission history.</p>@endif
@foreach(($proposed['resources']['rows']??$masterPlan['resources']['rows']??[]) as $booking)@if(!empty($booking['staff_id'])&&!empty($booking['start_at']))@php
 $conflictingAbsence=app(\App\Services\ApprovedAvailability::class)->windows(\Illuminate\Support\Carbon::parse($booking['start_at']),\Illuminate\Support\Carbon::parse($booking['end_at']??$booking['start_at']))->where('user_id',(int)$booking['staff_id'])->isNotEmpty();
@endphp @if($conflictingAbsence)<p class="br-help">Approved absence overlaps {{ $names->get((int)$booking['staff_id'])?->name }}’s booking for {{ $booking['description'] }}. Review cover or change the assignment before publication.</p>@endif @endif @endforeach
<input type="hidden" name="execution_revision" value="{{ $executionControl?->revision??0 }}">
@foreach(['schedule'=>'Schedule, deadlines & staffing','resources'=>'People, crew, vendors & vehicle bookings','assets'=>'Other assets · hired / client-supplied only','compliance'=>'Compliance clearance & evidence','tasks'=>'Execution task assignments'] as $section=>$sectionTitle)
@php
 $sectionData=$proposed[$section]??$masterPlan[$section]??($section==='schedule'?$sourceSchedule:[]);
 if(in_array($section,['resources','assets'])&&isset($sectionData['rows']))foreach($sectionData['rows'] as &$editRow){$prior=collect($masterPlan[$section]['rows']??[])->firstWhere('id',$editRow['id']??null);if(!array_key_exists('unit_rate',$editRow)&&isset($prior['unit_rate']))$editRow['unit_rate']=$prior['unit_rate'];}unset($editRow);
 $includeSection=in_array($section,$selectedSections,true)||($section==='schedule');
 $fieldHtml=view('portal.executive.fields',get_defined_vars())->render();
 $fieldHtml=preg_replace('/(\bname="(?:\x27)?)data\[/','$1execution['.$section.'][',$fieldHtml);
@endphp
<details class="mt-3 rounded-xl border border-brand-white/15 p-4" @if($section==='schedule') open @endif><summary class="cursor-pointer font-semibold">{{ $sectionTitle }}</summary><label class="mt-3 block text-sm"><input type="checkbox" name="execution_sections[]" value="{{ $section }}" @checked($includeSection)> Include this section in the entry for HOD review</label><div class="ops-form mt-4">{!! $fieldHtml !!}</div></details>
@endforeach</fieldset>
