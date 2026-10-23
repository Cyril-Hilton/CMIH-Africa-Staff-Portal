@php
    $record = $accountabilityItem ?? null;
    $fieldValue = fn ($key, $default = '') => $record ? ($record->{$key} ?? $default) : old($key, $default);
    $templates = config('department_kpis.' . ($record?->department ?? $weeklyDepartmentFilter), []);
@endphp
<fieldset class="mt-4 min-w-0 rounded-xl border border-brand-white/10 bg-brand-black/30 p-4">
    <legend class="px-2 text-sm font-semibold text-brand-white">Outcome & accountability</legend>
    <p class="mb-3 text-xs text-brand-ash">Use one measurable result per row. Keep different units and KPIs on separate rows.</p>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach(['objective' => 'Business objective', 'kpi_name' => 'Department KPI', 'measurement_unit' => 'Unit (GHS, hours, jobs, %)', 'target_value' => 'Approved target', 'actual_value' => 'Actual result', 'evidence_url' => 'Evidence link'] as $key => $label)
            <label class="min-w-0 text-xs text-brand-ash">{{ $label }}
                <input name="{{ $key }}" value="{{ $fieldValue($key) }}" type="{{ in_array($key, ['target_value', 'actual_value']) ? 'number' : ($key === 'evidence_url' ? 'url' : 'text') }}"
                    @if(in_array($key, ['target_value', 'actual_value'])) min="0" max="99999999999999" step="0.0001" @endif
                    @if($key === 'kpi_name') list="kpis-{{ $record?->id ?? 'new' }}" @endif
                    class="mt-1 w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-brand-white">
            </label>
        @endforeach
        <datalist id="kpis-{{ $record?->id ?? 'new' }}">
            @foreach($templates as $template)<option value="{{ $template['unit'] }}">{{ $template['unit'] }} · {{ $template['direction'] }} is better</option>@endforeach
        </datalist>
        <label class="text-xs text-brand-ash">Success direction
            <select name="measurement_direction" class="mt-1 w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-brand-white">
                <option value="higher" @selected($fieldValue('measurement_direction', 'higher') === 'higher')>Higher is better</option>
                <option value="lower" @selected($fieldValue('measurement_direction', 'higher') === 'lower')>Lower is better</option>
            </select>
        </label>
        <label class="text-xs text-brand-ash">Corrective-action owner
            <select name="action_owner_id" class="mt-1 w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-brand-white">
                <option value="">Not assigned</option>
                @foreach($allStaff as $staff)<option value="{{ $staff->id }}" @selected((string) $fieldValue('action_owner_id') === (string) $staff->id)>{{ $staff->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs text-brand-ash">Corrective-action due date
            <input type="date" name="action_due_date" value="{{ $record ? $record->action_due_date?->format('Y-m-d') : old('action_due_date') }}" class="mt-1 w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-brand-white">
        </label>
        @foreach(['issue_impact' => 'Issue / root cause & business impact', 'corrective_action' => 'Corrective action / management decision needed'] as $key => $label)
            <label class="min-w-0 text-xs text-brand-ash">{{ $label }}<textarea name="{{ $key }}" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-brand-white">{{ $fieldValue($key) }}</textarea></label>
        @endforeach
    </div>
    <details class="mt-3 text-xs text-brand-ash"><summary class="cursor-pointer">Department KPI definitions & sources</summary>
        <dl class="mt-3 space-y-3">@foreach($templates as $template)<div><dt class="font-semibold text-brand-white">{{ $template['name'] }} · {{ $template['unit'] }} · {{ $template['direction'] }} is better</dt><dd>{{ $template['definition'] }} Source: {{ $template['source'] }}. Target: set by management.</dd></div>@endforeach</dl>
    </details>
</fieldset>
