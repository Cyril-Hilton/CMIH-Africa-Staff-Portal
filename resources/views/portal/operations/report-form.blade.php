@php
    $submission = $formSubmission ?? null;
    $restore = old('kind') === $formKind
        && (int) old('source', 0) === (int) $formRecord?->id
        && (int) old('submission', 0) === (int) $submission?->id;
    $previous = fn ($key, $default) => $restore ? old($key, $default) : $default;
    $payload = $submission?->payload ?? [];
    $sourceHandover=null;
    if(!$submission&&!$formRecord&&request('ops_handover')){
        $sourceHandover=\App\Models\OperationsHandover::with('project')->whereNotNull('handover_approved_at')->whereNotNull('lead_id')->when(!$manager,fn($q)=>$q->where('lead_id',auth()->id()))->find(request('ops_handover'));
        if($sourceHandover)$payload=['title'=>$sourceHandover->project->project_name,'client'=>$sourceHandover->project->client_name,'project'=>$sourceHandover->project->project_name,'owner_id'=>$sourceHandover->lead_id,'data'=>['operations_handover_id'=>$sourceHandover->id,'crm_project_id'=>$sourceHandover->project_id,'brand'=>$sourceHandover->project->client_name,'location'=>$sourceHandover->location,'starts_on'=>$sourceHandover->event_at?->toDateString(),'ends_on'=>($sourceHandover->agreed_execution_due_at??$sourceHandover->execution_due_at)?->toDateString(),'remarks'=>$sourceHandover->plan]];
    }

    $selectedMaster=(!$submission&&!$formRecord&&$formKind==='activation'&&!$sourceHandover)?\App\Models\CrmPipelineProject::where('source_period_confirmed',true)->find(request()->integer('ops_project')):null;
    if($selectedMaster)$payload=['title'=>$selectedMaster->project_name,'client'=>$selectedMaster->client_name,'project'=>$selectedMaster->project_name,'owner_id'=>auth()->id(),'data'=>['crm_project_id'=>$selectedMaster->id]];
    $values = $payload['data'] ?? $formRecord?->data ?? [];
    $fromHandover=!empty($values['operations_handover_id']);
    $week = $payload['period_start'] ?? $formRecord?->period_start?->toDateString() ?? \Carbon\Carbon::parse($from)->startOfWeek()->toDateString();
    $selectedOwner = (int) $previous('owner_id', $payload['owner_id'] ?? $formRecord?->owner_id ?? auth()->id());
    $selectedStatus = $previous('status', $payload['status'] ?? $formRecord?->status ?? 'planned');
@endphp
@if(!$submission&&!$formRecord&&$formKind==='activation'&&!$fromHandover)
<form method="get" class="ops-form mb-4" action="{{ url()->current() }}#operations-entry">@foreach(request()->except('ops_project') as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label>Start with an existing Client Relations project · optional<select name="ops_project"><option value="">Direct Operations activation</option>@foreach(app(\App\Services\ClientRelationsCentre::class)->latest(\App\Models\CrmPipelineProject::where('source_period_confirmed',true)->with('owner:id,name')->get()) as $master)<option value="{{ $master->id }}" @selected($selectedMaster?->id===$master->id)>{{ $master->client_name }} · {{ $master->project_name }}</option>@endforeach</select></label><button class="ops-button" type="submit">Load project & current execution plan</button><p class="br-help">Choose this before filling the entry. Project identity and current commitments are loaded once.</p></form>
@endif
<form method="post" class="ops-form" action="{{ route('portal.operations.reporting.save') }}" data-no-ajax data-no-silent>
    @csrf
    <input type="hidden" name="module_entry" value="{{ $modules ? 1 : 0 }}">
    <input type="hidden" name="kind" value="{{ $formKind }}">
    @if($submission)
        <input type="hidden" name="submission" value="{{ $submission->id }}">
        <input type="hidden" name="revision" value="{{ $submission->revision }}">
        @if(isset($reviewSource) && $reviewSource)
            <input type="hidden" name="source_version" value="{{ \App\Services\OperationsReporting::version($reviewSource) }}">
        @endif
    @elseif($formRecord)
        <input type="hidden" name="source" value="{{ $formRecord->id }}">
        <input type="hidden" name="source_version" value="{{ \App\Services\OperationsReporting::version($formRecord) }}">
    @endif
    @if($fromHandover)<input type="hidden" name="handover_id" value="{{ $values['operations_handover_id'] }}"><aside class="br-help">Reference handover: project, client and assigned owner come from the master tracker. Prepare the reporting details and results yourself; this report is published only after HOD review.</aside>@endif
    <label>Activity title<input name="title" maxlength="255" required value="{{ $previous('title', $payload['title'] ?? $formRecord?->title) }}"></label>
    <label>Client<input @readonly($fromHandover) name="client" maxlength="255" value="{{ $previous('client', $payload['client'] ?? $formRecord?->client) }}"></label>
    <label>Project / Activation<input @readonly($fromHandover) name="project" maxlength="255" value="{{ $previous('project', $payload['project'] ?? $formRecord?->project) }}"></label>
    <label>Reporting week<input type="date" name="week" required value="{{ $previous('week', $week) }}" @readonly($formRecord || $submission)></label>
    @if($manager)
        @if($fromHandover)<input type="hidden" name="owner_id" value="{{ $selectedOwner }}">@endif
        <label>Owner<select name="owner_id" @disabled($fromHandover)>
            <option value="" @selected(! $teamStaff->where('status', 'active')->contains('id', $selectedOwner))>{{ $formRecord || $submission ? 'Keep current owner' : 'Myself' }}</option>
            @foreach($teamStaff->where('status', 'active') as $person)
                <option value="{{ $person->id }}" @selected($selectedOwner === $person->id)>{{ $person->name }}</option>
            @endforeach
        </select></label>
    @endif
    @php
        $selectedCollaborators = array_map('intval', (array) ($restore && old('collaborators_present') ? old('collaborator_ids', []) : ($values['collaborator_ids'] ?? [])));
    @endphp
    <fieldset class="ops-narrative rounded-xl border border-brand-white/20 px-4 pb-3">
        <legend class="px-2 text-sm font-semibold">Collaborators / Supporting Staff</legend>
        <input type="hidden" name="collaborators_present" value="1">
        <details @if($selectedCollaborators) open @endif>
            <summary class="cursor-pointer text-sm">Select supporting staff <small>(optional)</small></summary>
            <div class="mt-2 grid max-h-56 grid-cols-1 gap-2 overflow-auto sm:grid-cols-2 lg:grid-cols-3">
                @foreach($collaborationStaff->merge($staff->whereIn('id', $selectedCollaborators))->merge($reviewCollaborators->whereIn('id', $selectedCollaborators))->unique('id') as $collaborator)
                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" class="!m-0 !inline-block !h-4 !min-h-0 !w-4 !p-0" name="collaborator_ids[]" value="{{ $collaborator->id }}" @checked(in_array($collaborator->id, $selectedCollaborators, true))>
                        <span>{{ $collaborator->name }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    </fieldset>
    <label>Status<select name="status" required>@foreach($states as $id => $label)<option value="{{ $id }}" @selected($selectedStatus === $id)>{{ $label }}</option>@endforeach</select></label>
    @if($modules)<label>Evidence link <small>(optional)</small><input name="evidence_url" type="url" maxlength="2000" value="{{ $previous('evidence_url', $payload['evidence_url'] ?? $formRecord?->evidence_url) }}"></label>@endif
    @if($formKind === 'activation')
        @include('portal.operations.execution-fields')
    @endif
    @if($formKind === 'activation')
        <label>Existing Campaign <small>(optional)</small><select name="campaign_id"><option value="">No linked campaign</option>@foreach($campaignOptions as $campaign)<option value="{{ $campaign->id }}" @selected((int) $previous('campaign_id', $values['campaign_id'] ?? 0) === $campaign->id)>{{ $campaign->name }}</option>@endforeach</select></label>
    @endif
    @if($formKind === 'activation')
        @if($selectedMaster)<input type="hidden" name="crm_project_id" value="{{ $selectedMaster->id }}">@endif
        <label>Client Relations project <small>(optional)</small>
            <select name="crm_project_id" @disabled($fromHandover||$selectedMaster)>
                <option value="">No linked project</option>
                @foreach(\App\Models\CrmPipelineProject::where('source_period_confirmed', true)->with('owner:id,name')->latest('week_start')->get(['id','project_name','client_name','week_start','owner_id']) as $project)
                    <option value="{{ $project->id }}" @selected((int) $previous('crm_project_id', $values['crm_project_id'] ?? 0) === $project->id)>{{ $project->client_name }} · {{ $project->project_name }} · {{ $project->owner?->name }} · {{ $project->week_start?->format('d M Y') }}</option>
                @endforeach
            </select>
        </label>
    @else
        <label>Related activation <small>(optional)</small>
            <select name="activation_id">
                <option value="">No linked activation</option>
                @foreach(\App\Services\OperationsReporting::activations()->latest('period_start')->get(['id','title','period_start','reference']) as $activation)
                    <option value="{{ $activation->id }}" @selected((int) $previous('activation_id', $values['activation_id'] ?? 0) === $activation->id)>{{ $activation->title }} · {{ $activation->period_start?->format('d M Y') }} · {{ $activation->reference }}</option>
                @endforeach
            </select>
        </label>
    @endif
    @if(in_array($formKind,['activation','project','team_allocation']))
    <fieldset class="ops-narrative rounded-xl border border-sky-300/30 p-4">
        <legend class="px-2 font-semibold">Warehouse assets · checklist & quantities</legend>
        <p class="br-help">Select the actual inventory items needed. Publishing this report queues requests for warehouse review. Stock and custody change only after issue. The report owner is the project custodian.</p>
        <input type="hidden" name="warehouse_assets_present" value="1">
        @php
            $assetSelections=collect($restore?old('warehouse_assets',[]):($values['warehouse_assets']??[]))->keyBy('asset_id');
        @endphp
        <div class="max-h-80 space-y-2 overflow-auto">
            @forelse(\App\Models\Asset::where('is_warehouse_tracked',true)->orderBy('name')->get(['id','name','brand','warehouse_quantity']) as $warehouseAsset)
                @php
                    $selection=$assetSelections->get($warehouseAsset->id);
                @endphp
                <div class="grid grid-cols-[auto_minmax(0,1fr)_6rem] items-center gap-3 rounded-lg border border-brand-white/10 p-2">
                    <input type="hidden" name="warehouse_assets[{{ $warehouseAsset->id }}][asset_id]" value="{{ $warehouseAsset->id }}">
                    <input class="!w-4 !min-h-0" type="checkbox" name="warehouse_assets[{{ $warehouseAsset->id }}][selected]" value="1" @checked($selection && (!$restore || !empty($selection['selected']))) aria-label="Select {{ $warehouseAsset->name }}">
                    <span>{{ $warehouseAsset->name }} · {{ $warehouseAsset->brand }}<small class="block">{{ $warehouseAsset->warehouse_quantity }} currently in warehouse</small></span>
                    <label>Quantity<input type="number" name="warehouse_assets[{{ $warehouseAsset->id }}][quantity]" value="{{ $selection['quantity']??'' }}" min="1" max="9999999" aria-label="Quantity for {{ $warehouseAsset->name }}"></label>
                </div>
            @empty<p class="br-help">No approved warehouse assets yet. Submit an inventory entry to the HOD first.</p>@endforelse
        </div>
    </fieldset>
    @endif
    @foreach($safeFields($formKind) as $field => $type)
        @continue($formKind==='activation'&&in_array($field,['starts_on','ends_on','location','brand','activation_status','planned_assets']))
        @continue(in_array($formKind,['project','team_allocation'])&&$field==='planned_assets')
        @php
            $options = config('operations_tracker.options.'.$field);
            $value = $previous('data.'.$field, $values[$field] ?? '');
            $narrative = in_array($field, ['planned_assets','deliverables','remarks','description','supporting_team','kpi_commitments','reported_results','issue','action_required','resolution','resolution_action','key_issue','primary_requirement','team_handyman_requirement','comments','client_feedback','outstanding_actions','follow_up_action','mitigation','impact'], true);
        @endphp
        <label @class(['ops-narrative' => $narrative])>{{ \Illuminate\Support\Str::headline($field) }}
            @if($field === 'planned_assets')<small>List one asset per line. The warehouse manager reviews these after the report is published.</small>@endif
            @if($options)
                <select name="data[{{ $field }}]"><option value="">Choose…</option>@foreach($options as $option)<option @selected($value === $option)>{{ $option }}</option>@endforeach</select>
            @elseif($narrative)
                <textarea class="wysiwyg-editor" rows="3" name="data[{{ $field }}]">{{ $value }}</textarea>
            @else
                <input name="data[{{ $field }}]" value="{{ $type === 'text' && is_string($value) ? html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8') : $value }}" @if($type === 'date') type="date" @elseif($type === 'number' || $type === 'money') type="number" step="any" min="0" @else maxlength="2000" @endif>
            @endif
        </label>
    @endforeach
    <div class="br-actions ops-narrative"><button type="submit" class="ops-button ops-primary">{{ $manager ? 'Publish Report' : ($submission ? 'Resubmit for Review' : 'Submit for Review') }}</button></div>
</form>
