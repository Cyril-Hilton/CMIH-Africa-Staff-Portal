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
<div class="ops-breakdowns">
    @foreach($breakdowns as $label => $groups)
        @php($breakdownKey=['Brand'=>'breakdown_brand','Location'=>'breakdown_location','Project Lead'=>'breakdown_lead'][$label])
        @php($breakdownRows=app(\App\Services\OperationsTableQuery::class)->collection($groups->map(fn($count,$name)=>['name'=>$name,'count'=>$count])->values(),request(),$breakdownKey))
        <section class="br-table-section" id="ops-table-{{ $breakdownKey }}">
            <header class="br-table-heading"><h4>Activations by {{ $label }}</h4></header>
            @include('portal.operations.table-filters',['tableKey'=>$breakdownKey,'tableLabel'=>'Activations by '.$label])
            <div class="br-table-wrap" tabindex="0" role="region" aria-label="Activations by {{ $label }}">
                <table class="br-table ops-table" data-table-enhancer="off">
                    <thead><tr><th>{{ $label }}</th><th>Activations</th></tr></thead>
                    <tbody>@forelse($breakdownRows as $group)<tr><td>{{ $group['name'] }}</td><td>{{ $group['count'] }}</td></tr>@empty<tr><td colspan="2">No matching categories.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
