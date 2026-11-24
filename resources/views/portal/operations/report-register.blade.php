@php
    $columns = match ($kind) {
        'project' => ['brand', 'project_status', 'rag_status'],
        'activation' => ['brand', 'location', 'starts_on', 'ends_on', 'report_shared'],
        'posm_deployment' => ['asset_code', 'quantity', 'location', 'current_location', 'expected_return_on', 'return_status'],
        'team_allocation' => ['outlet', 'location', 'setup_on', 'activation_dates_as_reported', 'client_service', 'technical_support', 'handyman', 'transport'],
        'fabrication' => ['quantity', 'vendor', 'artwork_shared', 'starts_on', 'due_on', 'delivered_on'],
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
    <header class="br-table-heading"><h4>{{ !empty($undated) ? 'POSM with no deployment date' : $labels[$kind] }} <small>{{ $entries->total() }}</small></h4>
        @if($member && empty($undated))<a class="ops-button ops-primary" href="{{ $workspaceUrl(['ops_add' => $kind]) }}">Add {{ $kind === 'weekly_update' ? 'Weekly Update' : \Illuminate\Support\Str::singular($labels[$kind]) }}</a>@endif
    </header>
    @if(!empty($undated))<p>Source records without a deployment date. They appear separately and do not affect period totals or warehouse balances.</p>@endif
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
                    $canonicalProject=!empty($data['crm_project_id'])?\App\Models\CrmPipelineProject::find($data['crm_project_id']):null;
                    $derived = $tracker->derived($kind, $data);
                    $campaignId = (int) ($data['campaign_id'] ?? 0);
                    $linked = $kind === 'activation' ? $linkedMovements->filter(fn ($movement) => $movement->operations_activation_id === $entry->id || ($campaignId && $movement->campaign_id === $campaignId && ! $movement->operations_activation_id)) : collect();
                    $canEdit = empty($undated) && $member && ($manager || $entry->owner_id === auth()->id() || $entry->submitted_by === auth()->id());
                    $status = $editStatus($entry->status);
                @endphp
                <tbody x-data="{ expanded: false }">
                <tr>
                    <td class="ops-title"><button type="button" class="ops-expand" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="ops-register-{{ $entry->id }}"><span aria-hidden="true" x-text="expanded ? '−' : '+'">+</span> {{ $canonicalProject?->project_name ?? $entry->title }}</button>@if($canonicalProject && $entry->title!==$canonicalProject->project_name)<small>Activation / work: {{ $entry->title }}</small>@endif</td>
                    <td>{{ $canonicalProject?->client_name ?? ($entry->client ?: '—') }}</td>
                    <td>@include('portal.operations.collaborator-badges', ['people' => $staff->whereIn('id', $data['collaborator_ids'] ?? [])])</td>
                    <td>{{ $data['project_in_charge'] ?? $data['source_project_lead'] ?? (empty($data['import_no_owner']) ? $staff->firstWhere('id', $entry->owner_id)?->name : null) ?? '—' }}</td>
                    @foreach($columns as $column)
                        <td @class(['br-rich-cell' => $column === 'deliverables'])>
                            @php($value = $data[$column] ?? $derived[$column] ?? null)
                            @if($column === 'deliverables' && filled($value))<div class="weekly-rich-content">{!! \App\Support\ReportRichText::html($value) !!}</div>
                            @elseif(is_bool($value)){{ $value ? 'Yes' : 'No' }}
                            @else{{ is_scalar($value) ? html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8').($column === 'progress_percent' ? '%' : '') : '—' }}@endif
                        </td>
                    @endforeach
                    <td><span class="br-badge" data-ops-status="{{ \Illuminate\Support\Str::slug($status, '_') }}">{{ $status }}</span></td>
                    <td class="br-date-cell">{{ !empty($undated) ? 'Date not recorded' : $entry->period_start->format('d M Y') }}</td>
                </tr>
                <tr id="ops-register-{{ $entry->id }}" class="ops-detail-row" x-show="expanded" style="display:none">
                    <td colspan="{{ 6 + count($columns) }}">
                        <div class="br-table-heading"><strong>{{ $entry->reference }}</strong>@if($canEdit)<a class="ops-button ops-primary" href="{{ $workspaceUrl(['ops_edit' => $entry->id]) }}">Edit Entry</a>@endif</div>
                        @if(!empty($data['operations_handover_id']))
                            @php($linkedHandover=\App\Models\OperationsHandover::find($data['operations_handover_id']))
                            @if($linkedHandover)
                                <aside class="my-3 rounded-xl border border-orange-300/30 p-3 text-sm"><strong>From approved handover · OPS-{{ $linkedHandover->id }}</strong><p>Handover meeting: {{ $linkedHandover->meeting_held_at?->format('d M Y H:i') ?? 'Awaiting recorded date' }} · HOD approved: {{ $linkedHandover->handover_approved_at?->format('d M Y H:i') ?? 'Review required' }}</p><p>Client Relations setup deadline: {{ $linkedHandover->setup_due_at?->format('d M Y H:i') ?? 'To be confirmed' }} · Operations HOD setup commitment: {{ $linkedHandover->agreed_setup_due_at?->format('d M Y H:i') ?? 'Needs agreement' }}</p><p>Execution completion requested: {{ $linkedHandover->execution_due_at?->format('d M Y H:i') ?? 'To be confirmed' }} · Operations HOD commitment: {{ $linkedHandover->agreed_execution_due_at?->format('d M Y H:i') ?? 'Needs agreement' }}</p>@if($data['handover_review_required']??false)<p class="text-amber-200">Handover revised · both teams and HOD must reconfirm.</p>@endif<a class="ops-button" href="{{ route('portal.operations.handovers.index',['handover'=>$linkedHandover->id]) }}">Open signed handover & timeline</a></aside>
                            @endif
                        @endif
                        @include('portal.operations.report-fields', ['detailFields' => array_intersect_key($data, $safeFields($kind)) + $derived + ['project' => $entry->project, 'source_reference' => $data['import_source'] ?? null, 'duration_as_reported' => $data['duration_as_reported'] ?? null, 'source_serial' => $data['source_serial'] ?? null, 'evidence_link' => $modules && $member ? $entry->evidence_url : null]])
                        @if($kind==='activation')
                            @php($sharedExecution=app(\App\Services\ExecutionPlan::class)->forActivation($entry))
                            @if($sharedExecution && (auth()->user()->canViewExecutiveActivity() || $manager || (int)$entry->owner_id===auth()->id() || in_array(auth()->id(),$sharedExecution->data['participant_ids']??[],true)))
                                <aside class="my-3 rounded-xl border border-sky-300/30 p-3"><strong>Published execution plan</strong><p class="text-sm">View the entered staffing, schedule, crew, compliance, external assets and task commitments together.</p><a class="ops-button" href="{{ route('portal.executive.controls.show',$sharedExecution) }}">View shared execution commitments ↗</a></aside>
                            @endif
                        @endif
                        @if(!empty($data['crm_project_id']) && ($connectedProject=\App\Models\CrmPipelineProject::find($data['crm_project_id'])))
                            <details data-connected-project="{{ route('portal.project-journey.accountability',$connectedProject) }}"><summary class="cursor-pointer text-sm font-semibold">Connected project · teams, dates & accountability</summary><div data-connected-project-content></div></details>
                        @endif
                        @if($member && $kind!=='activation')
                            @include('portal.operations.report-movements',['rows'=>\App\Models\AssetWarehouseRequest::with('asset','requester','custodian','project')->where('operations_register_id',$entry->id)->latest('id')->get(),'emptyMessage'=>'No warehouse requests linked to this report.'])
                        @endif
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
