@php
    $submission = $formSubmission ?? null;
    $restore = old('kind') === $formKind
        && (int) old('source', 0) === (int) $formRecord?->id
        && (int) old('submission', 0) === (int) $submission?->id;
    $previous = fn ($key, $default) => $restore ? old($key, $default) : $default;
    $payload = $submission?->payload ?? [];
    $values = $payload['data'] ?? $formRecord?->data ?? [];
    $week = $payload['period_start'] ?? $formRecord?->period_start?->toDateString() ?? \Carbon\Carbon::parse($from)->startOfWeek()->toDateString();
    $selectedOwner = (int) $previous('owner_id', $payload['owner_id'] ?? $formRecord?->owner_id ?? auth()->id());
    $selectedStatus = $previous('status', $payload['status'] ?? $formRecord?->status ?? 'planned');
@endphp
<form method="post" class="ops-form" action="{{ route('portal.operations.reporting.save') }}" data-no-ajax data-no-silent>
    @csrf
    <input type="hidden" name="module_entry" value="{{ $modules ? 1 : 0 }}">
    <input type="hidden" name="kind" value="{{ $formKind }}">
    @if($submission)
        <input type="hidden" name="submission" value="{{ $submission->id }}">
        <input type="hidden" name="revision" value="{{ $submission->revision }}">
    @elseif($formRecord)
        <input type="hidden" name="source" value="{{ $formRecord->id }}">
        <input type="hidden" name="source_version" value="{{ \App\Services\OperationsReporting::version($formRecord) }}">
    @endif
    <label>Activity title<input name="title" maxlength="255" required value="{{ $previous('title', $payload['title'] ?? $formRecord?->title) }}"></label>
    <label>Client<input name="client" maxlength="255" value="{{ $previous('client', $payload['client'] ?? $formRecord?->client) }}"></label>
    <label>Project / Activation<input name="project" maxlength="255" value="{{ $previous('project', $payload['project'] ?? $formRecord?->project) }}"></label>
    <label>Reporting week<input type="date" name="week" required value="{{ $previous('week', $week) }}" @readonly($formRecord || $submission)></label>
    @if($manager)
        <label>Owner<select name="owner_id">
            <option value="" @selected(! $staff->where('status', 'active')->contains('id', $selectedOwner))>{{ $formRecord || $submission ? 'Keep current owner' : 'Myself' }}</option>
            @foreach($staff->where('status', 'active') as $person)
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
        <label>Existing Campaign <small>(optional)</small><select name="campaign_id"><option value="">No linked campaign</option>@foreach($campaignOptions as $campaign)<option value="{{ $campaign->id }}" @selected((int) $previous('campaign_id', $values['campaign_id'] ?? 0) === $campaign->id)>{{ $campaign->name }}</option>@endforeach</select></label>
    @endif
    @foreach($safeFields($formKind) as $field => $type)
        @php
            $options = config('operations_tracker.options.'.$field);
            $value = $previous('data.'.$field, $values[$field] ?? '');
            $narrative = in_array($field, ['deliverables','remarks','description','supporting_team','kpi_commitments','reported_results','issue','action_required','resolution','resolution_action','key_issue','primary_requirement','team_handyman_requirement','comments','client_feedback','outstanding_actions','follow_up_action','mitigation','impact'], true);
        @endphp
        <label @class(['ops-narrative' => $narrative])>{{ \Illuminate\Support\Str::headline($field) }}
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
