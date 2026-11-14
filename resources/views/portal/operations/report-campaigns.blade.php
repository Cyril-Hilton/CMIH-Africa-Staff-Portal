@if($member)
<section class="br-table-section">
    <header class="br-table-heading"><h4>Campaigns <small>{{ $campaignRows->total() }}</small></h4><a class="ops-button" href="{{ route('portal.operations', ['module_view' => 'projects']) }}">Campaign Tools</a></header>
    <div class="br-table-wrap" role="region" tabindex="0" aria-label="Campaigns">
        <table class="br-table ops-table">
            <thead><tr>
                @include('portal.operations.report-sort', ['column'=>'Campaign','sortKey'=>'title'])
                <th>Client</th>@include('portal.operations.report-sort', ['column'=>'Project Lead','sortKey'=>'owner'])
                <th>Start</th><th>End</th>@include('portal.operations.report-sort', ['column'=>'Status','sortKey'=>'status'])
            </tr></thead>
            @forelse($campaignRows as $campaign)
            <tbody x-data="{expanded: false}">
                <tr>
                    <td class="ops-title"><button type="button" class="ops-expand" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="ops-campaign-{{ $campaign->id }}"><span x-text="expanded ? '−' : '+'" aria-hidden="true">+</span> {{ $campaign->name }}</button></td>
                    <td>{{ $campaign->client_name }}</td><td>{{ $campaign->projectLead?->name ?? '—' }}</td><td>{{ $campaign->start_date?->format('d M Y') ?? '—' }}</td><td>{{ $campaign->end_date?->format('d M Y') ?? '—' }}</td>
                    <td><span class="br-badge" data-ops-status="{{ \Illuminate\Support\Str::slug($campaign->status, '_') }}">{{ $editStatus($campaign->status) }}</span></td>
                </tr>
                <tr id="ops-campaign-{{ $campaign->id }}" class="ops-detail-row" x-show="expanded" style="display:none"><td colspan="6">
                    @include('portal.operations.report-fields', ['detailFields' => ['location_brief' => $campaign->location_brief, 'duration_days' => $campaign->duration, 'status_update' => $campaign->status_update]])
                    @include('portal.operations.report-linked-assets', [
                        'linked' => $linkedMovements->where('campaign_id', $campaign->id),
                        'linkedLedger' => $linkedPosm->get($campaign->id, collect()),
                        'linkedLogs' => $checkoutLogs->get($campaign->id, collect()),
                        'activationId' => null,
                        'campaignId' => $campaign->id,
                    ])
                </td></tr>
            </tbody>
            @empty
                <tbody><tr><td class="br-empty-cell" colspan="6">No campaigns in this period.</td></tr></tbody>
            @endforelse
        </table>
    </div>
    @include('portal.operations.report-pagination', ['page' => $campaignRows, 'paginationLabel' => 'Campaigns'])
</section>
@endif
