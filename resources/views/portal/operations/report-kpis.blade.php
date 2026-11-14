<section class="br-table-section">
    <header class="br-table-heading"><h4>KPI Targets &amp; Results</h4></header>
    <div class="br-table-wrap" tabindex="0" role="region" aria-label="KPI targets and results">
        <table class="br-table ops-table">
            <thead><tr><th>KPI</th><th>Owner</th><th>Target</th><th>Approved Result</th><th>Achievement</th></tr></thead>
            <tbody>@forelse($kpiRows as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['owner'] }}</td><td>{{ $row['target'] }}</td><td>{{ $row['actual'] }}</td><td><span class="br-badge" data-ops-status="{{ $row['rag'] }}">{{ $row['achievement'] === null ? 'Awaiting Result' : $row['achievement'].'%' }}</span></td></tr>
            @empty<tr><td colspan="5" class="br-empty-cell">No approved targets in this view.</td></tr>@endforelse</tbody>
        </table>
    </div>
</section>
<section class="br-table-section">
    <header class="br-table-heading"><h4>Delivery Commitments</h4></header>
    <div class="br-table-wrap" tabindex="0" role="region" aria-label="Delivery commitments">
        <table class="br-table ops-table">
            <thead><tr><th>Project</th><th>Owner</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>@forelse($deliveryRows as $row)
                <tr><td>{{ $row['project'] }}</td><td>{{ $row['owner'] }}</td><td>{{ $row['due'] ?? '—' }}</td><td><span class="br-badge" data-ops-status="{{ $row['overdue'] ? 'overdue' : $row['state'] }}">{{ $editStatus($row['state']) }}{{ $row['overdue'] ? ' · Overdue' : '' }}</span></td></tr>
            @empty<tr><td colspan="4" class="br-empty-cell">No delivery commitments in this view.</td></tr>@endforelse</tbody>
        </table>
    </div>
</section>
