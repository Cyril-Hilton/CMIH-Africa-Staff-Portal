<section class="br-table-section">
    <header class="br-table-heading"><h4>Saved Weekly Workstream Reports <small>{{ $weeklyRows->total() }}</small></h4>
        @if($manager)<button type="button" data-copy-week data-department="operations_projects" data-source-week="{{ $periodDate->startOfWeek()->lt(now()->startOfWeek()) ? $periodDate->startOfWeek()->toDateString() : now()->subWeek()->startOfWeek()->toDateString() }}" data-target-week="{{ now()->startOfWeek()->toDateString() }}" data-preview-url="{{ route('portal.dashboard.weekly-consolidated.copy-week.preview') }}" data-store-url="{{ route('portal.dashboard.weekly-consolidated.copy-week.store') }}">Copy Saved Weekly Reports</button>@endif
    </header>
    <div class="br-table-wrap" tabindex="0" role="region" aria-label="Saved weekly workstream reports">
        <table class="br-table ops-table">
            <thead><tr><th>Reporting Week</th>@include('portal.operations.report-sort', ['column'=>'Workstream','sortKey'=>'title'])<th>Client</th>@include('portal.operations.report-sort', ['column'=>'Owner','sortKey'=>'owner'])<th scope="col">Supporting Staff</th>@include('portal.operations.report-sort', ['column'=>'Status','sortKey'=>'status'])</tr></thead>
            @forelse($weeklyRows as $saved)
                <tbody data-saved-weekly="{{ $saved->id }}" x-data="{expanded: false}">
                    <tr>
                        <td>{{ $saved->week_start->format('d M Y') }}</td>
                        <td class="ops-title"><button type="button" class="ops-expand" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="ops-weekly-{{ $saved->id }}"><span x-text="expanded ? '−' : '+'" aria-hidden="true">+</span> {{ $saved->campaign_name ?: ($saved->client_name ?: 'Weekly Update') }}</button></td>
                        <td>{{ $saved->client_name ?: '—' }}</td><td>{{ $saved->leadStaff?->name ?? '—' }}</td><td>@include('portal.operations.collaborator-badges', ['people' => $staff->whereIn('id', $saved->supporting_staff_ids ?? [])])</td><td><span class="br-badge" data-ops-status="{{ \Illuminate\Support\Str::slug($saved->status ?? '', '_') }}">{{ $saved->status }}</span></td>
                    </tr>
                    <tr id="ops-weekly-{{ $saved->id }}" class="ops-detail-row" x-show="expanded" style="display:none"><td colspan="6">
                        <strong>WCT-{{ $saved->id }}</strong>
                        @include('portal.operations.report-fields', ['detailFields' => ['deliverables' => $saved->deliverables, 'kpi_commitments' => $saved->target_breakdown, 'reported_results' => $saved->achieved_breakdown, 'gaps_and_follow_up' => $saved->gap_breakdown, 'notes' => $saved->notes]])
                        @if($member)@include('portal.partials.weekly-entry-actions', ['item' => $saved])@endif
                    </td></tr>
                </tbody>
            @empty
                <tbody><tr><td class="br-empty-cell" colspan="6">No saved weekly workstream reports in this view.</td></tr></tbody>
            @endforelse
        </table>
    </div>
    @include('portal.operations.report-pagination', ['page' => $weeklyRows, 'paginationLabel' => 'Saved weekly reports'])
</section>
