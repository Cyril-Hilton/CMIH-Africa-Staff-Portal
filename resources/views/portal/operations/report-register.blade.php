@php
    $columns = match ($kind) {
        'project' => ['brand', 'project_stage', 'project_status', 'progress_percent', 'rag_status', 'due_on'],
        'activation' => ['brand', 'location', 'starts_on', 'ends_on', 'report_shared'],
        'posm_deployment' => ['asset_code', 'quantity', 'current_location', 'expected_return_on', 'return_status'],
        'procurement' => ['item_service', 'supplier', 'quantity', 'required_on', 'delivery_status'],
        'supplier' => ['supplier_name', 'delivery_on', 'performance'],
        'production' => ['production_item', 'due_on', 'quality_check'],
        'client_approval' => ['item_for_approval', 'approval_status', 'next_follow_up_on'],
        'project_report' => ['due_on', 'report_status', 'shared_on'],
        'risk_issue' => ['category', 'priority', 'due_on', 'rag_status'],
        'weekly_update' => ['progress_percent', 'deliverables'],
        default => [],
    };
    $tracker = app(\App\Services\OperationsTracker::class);
@endphp
<section class="br-table-section">
    <header class="br-table-heading"><h4>{{ $labels[$kind] }} <small>{{ $entries->total() }}</small></h4>
        @if($member)<a class="ops-button ops-primary" href="{{ $workspaceUrl(['ops_add' => $kind]) }}">Add {{ $kind === 'weekly_update' ? 'Weekly Update' : \Illuminate\Support\Str::singular($labels[$kind]) }}</a>@endif
    </header>
    <div class="br-table-wrap" tabindex="0" role="region" aria-label="{{ $labels[$kind] }}">
        <table class="br-table ops-table">
            <thead><tr>
                @include('portal.operations.report-sort', ['column' => $kind === 'activation' ? 'Activation' : 'Activity', 'sortKey' => 'title'])
                <th scope="col">Client</th>
                <th scope="col">Supporting Staff</th>
                @include('portal.operations.report-sort', ['column' => 'Owner', 'sortKey' => 'owner'])
                @foreach($columns as $column)<th scope="col">{{ \Illuminate\Support\Str::headline($column) }}</th>@endforeach
                @include('portal.operations.report-sort', ['column' => 'Status', 'sortKey' => 'status'])
                <th scope="col">Reporting Week</th>
            </tr></thead>
            @forelse($entries as $entry)
                @php
                    $data = $entry->data ?? [];
                    $derived = $tracker->derived($kind, $data);
                    $campaignId = (int) ($data['campaign_id'] ?? 0);
                    $linked = $kind === 'activation' ? $linkedMovements->filter(fn ($movement) => $movement->operations_activation_id === $entry->id || ($campaignId && $movement->campaign_id === $campaignId && ! $movement->operations_activation_id)) : collect();
                    $canEdit = $member && ($manager || $entry->owner_id === auth()->id() || $entry->submitted_by === auth()->id());
                    $status = $editStatus($entry->status);
                @endphp
                <tbody x-data="{ expanded: false }">
                <tr>
                    <td class="ops-title"><button type="button" class="ops-expand" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="ops-register-{{ $entry->id }}"><span aria-hidden="true" x-text="expanded ? '−' : '+'">+</span> {{ $entry->title }}</button></td>
                    <td>{{ $entry->client ?: '—' }}</td>
                    <td>@include('portal.operations.collaborator-badges', ['people' => $staff->whereIn('id', $data['collaborator_ids'] ?? [])])</td>
                    <td>{{ $staff->firstWhere('id', $entry->owner_id)?->name ?? '—' }}</td>
                    @foreach($columns as $column)
                        <td @class(['br-rich-cell' => $column === 'deliverables'])>
                            @php($value = $data[$column] ?? $derived[$column] ?? null)
                            @if($column === 'deliverables' && filled($value))<div class="weekly-rich-content">{!! \App\Support\ReportRichText::html($value) !!}</div>
                            @elseif(is_bool($value)){{ $value ? 'Yes' : 'No' }}
                            @else{{ is_scalar($value) ? html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8').($column === 'progress_percent' ? '%' : '') : '—' }}@endif
                        </td>
                    @endforeach
                    <td><span class="br-badge" data-ops-status="{{ \Illuminate\Support\Str::slug($status, '_') }}">{{ $status }}</span></td>
                    <td class="br-date-cell">{{ $entry->period_start->format('d M Y') }}</td>
                </tr>
                <tr id="ops-register-{{ $entry->id }}" class="ops-detail-row" x-show="expanded" style="display:none">
                    <td colspan="{{ 6 + count($columns) }}">
                        <div class="br-table-heading"><strong>{{ $entry->reference }}</strong>@if($canEdit)<a class="ops-button ops-primary" href="{{ $workspaceUrl(['ops_edit' => $entry->id]) }}">Edit Entry</a>@endif</div>
                        @include('portal.operations.report-fields', ['detailFields' => array_intersect_key($data, $safeFields($kind)) + $derived + ['project' => $entry->project, 'evidence_link' => $modules && $member ? $entry->evidence_url : null]])
                        @if($kind === 'activation' && $member)
                            @include('portal.operations.report-linked-assets', ['linked' => $linked, 'linkedLedger' => $linkedPosm->get($campaignId, collect()), 'linkedLogs' => $checkoutLogs->get($campaignId, collect()), 'activationId' => $entry->id, 'campaignId' => null])
                        @endif
                    </td>
                </tr>
                </tbody>
            @empty
                <tbody><tr><td class="br-empty-cell" colspan="{{ 6 + count($columns) }}">No published {{ strtolower($labels[$kind]) }} in this view.</td></tr></tbody>
            @endforelse
        </table>
    </div>
    @include('portal.operations.report-pagination', ['page' => $entries, 'paginationLabel' => $labels[$kind]])
</section>
