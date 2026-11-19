@if(count($rows))
<div class="br-table-wrap" tabindex="0" role="region" aria-label="Warehouse movements">
    <table class="br-table ops-table">
        <thead><tr><th>Asset</th><th>Requested by / Custodian</th><th>Quantity</th><th>Destination</th><th>Issued</th><th>Returned</th><th>Closed</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($rows as $movement)
            <tr>
                <td>{{ $movement->asset?->name ?? '—' }}<br><small>{{ $movement->request_code }}</small></td>
                <td>{{ $movement->requester?->name ?? '—' }}<br><strong>Custodian: {{ $movement->custodian?->name ?? $movement->requester?->name ?? 'Owner needed' }}</strong>@if($movement->project)<br>{{ $movement->project->project_name }}@endif</td>
                <td>{{ $movement->requested_quantity }}</td>
                <td>{{ $movement->destination_location }}</td>
                <td>{{ $movement->issued_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $movement->returned_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $movement->closed_at?->format('d M Y') ?? '—' }}</td>
                <td><span class="br-badge" data-ops-status="{{ $movement->status }}">{{ $movement::statusLabel($movement->status) }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
    <p class="br-empty">{{ $emptyMessage }}</p>
@endif
