@include('portal.operations.overview-charts')
@php
    $metricGroups = [
        'activity' => [
            'activation_targets_met' => 'Activation Targets Met',
            'activations' => 'Activations',
            'activations_completed' => 'Completed Activations',
            'activation_reports_shared' => 'Activation Reports Shared',
            'deployed_quantity' => 'POSM Units Deployed',
        ],
        'follow-up' => [
            'overdue_returns' => 'Overdue POSM Returns',
            'pending_procurements' => 'Procurements Still Open',
            'overdue_fabrications' => 'Overdue Fabrication Deliveries',
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
