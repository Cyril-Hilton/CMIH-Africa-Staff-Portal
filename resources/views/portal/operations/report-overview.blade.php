@include('portal.operations.execution-trend')
@php
    $metricGroups = [
        'activity' => [
            'projects' => 'Projects',
            'activations' => 'Activations',
            'activations_completed' => 'Completed Activations',
            'activation_reports_shared' => 'Activation Reports Shared',
            'deployed_quantity' => 'POSM Units Deployed',
        ],
        'follow-up' => [
            'overdue_returns' => 'Overdue POSM Returns',
            'reports_overdue' => 'Overdue Reports',
            'pending_approvals' => 'Pending Client Approvals',
            'open_issues' => 'Open Issues',
        ],
    ];
@endphp
<section class="ops-metrics" aria-label="Approved period activity">
    @foreach($metricGroups as $group => $metrics)
        <dl class="ops-metrics-row ops-metrics-row--{{ $group }}">
            @foreach($metrics as $id => $label)
                <div class="ops-metric" @if($group === 'follow-up' && $summary[$id] > 0) data-attention @endif>
                    <dt>{{ $label }}</dt>
                    <dd>{{ number_format($summary[$id]) }}</dd>
                </div>
            @endforeach
        </dl>
    @endforeach
</section>
<p class="br-note">These figures use approved Operations tracker entries. Current stock and warehouse transactions are under Assets &amp; POSM.</p>
<div class="ops-breakdowns">
    @foreach($breakdowns as $label => $groups)
        @continue($groups->isEmpty())
        <section class="br-table-section">
            <header class="br-table-heading"><h4>Activations by {{ $label }}</h4></header>
            <div class="br-table-wrap" tabindex="0" role="region" aria-label="Activations by {{ $label }}">
                <table class="br-table ops-table">
                    <thead><tr><th>{{ $label }}</th><th>Activations</th></tr></thead>
                    <tbody>@foreach($groups as $name=>$count)<tr><td>{{ $name }}</td><td>{{ $count }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
