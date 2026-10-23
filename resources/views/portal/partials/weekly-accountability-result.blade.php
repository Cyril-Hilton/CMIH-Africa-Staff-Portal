<details class="mt-3 rounded-lg border border-sky-400/20 p-2 text-xs text-brand-ash">
    <summary class="cursor-pointer font-semibold text-sky-200">Result & accountability · WCT-{{ $item->id }}{{ $item->isOverdue() ? ' · OVERDUE' : '' }}</summary>
    <dl class="mt-2 space-y-2 break-words">
        <div><dt>Objective</dt><dd class="text-brand-white">{{ $item->objective ?: 'Not recorded' }}</dd></div>
        <div><dt>KPI</dt><dd class="text-brand-white">{{ $item->kpi_name ?: 'Not recorded' }} · {{ $item->measurement_unit ?: 'Unit not recorded' }} · {{ $item->measurement_direction ?: 'higher' }} is better</dd></div>
        <div><dt>Target / actual</dt><dd class="text-brand-white">{{ $item->target_value ?? 'Not recorded' }} / {{ $item->actual_value ?? 'Not recorded' }}</dd></div>
        <div><dt>Achievement / variance (actual − target)</dt><dd class="text-brand-white">{{ $item->achievementPercent() === null ? 'Not measurable' : $item->achievementPercent() . '%' }} / {{ $item->variance() ?? 'Not measurable' }}</dd></div>
        <div><dt>Completed</dt><dd>{{ $item->completed_at?->format('d M Y H:i') ?? 'Not recorded' }}</dd></div>
        <div><dt>Issue / impact</dt><dd>{{ $item->issue_impact ?: 'Not recorded' }}</dd></div>
        <div><dt>Corrective action</dt><dd>{{ $item->corrective_action ?: 'Not recorded' }}</dd></div>
        <div><dt>Action owner / deadline</dt><dd>{{ $item->actionOwner?->name ?? 'Unassigned' }} / {{ $item->action_due_date?->format('d M Y') ?? 'Not recorded' }}</dd></div>
        @if($item->evidence_url && preg_match('/^https?:\/\//i', $item->evidence_url))<div><a href="{{ $item->evidence_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-200 underline">Open supporting evidence</a></div>@endif
    </dl>
</details>
