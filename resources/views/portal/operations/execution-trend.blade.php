@php
    $current = $executionTrend['current'];
    $previous = $executionTrend['previous'];
    $change = $current - $previous;
    $comparisonMax = max($current, $previous, 1);
    $points = $executionTrend['points'];
    $trendMax = max(1, ...array_values($points ?: [0]));
@endphp
<section class="ops-executions" aria-labelledby="ops-execution-title">
    <div class="br-table-heading">
        <h4 id="ops-execution-title">Activation Executions</h4>
        <strong class="br-badge" data-ops-status="{{ $change > 0 ? 'completed' : ($change < 0 ? 'blocked' : '') }}">
            @if($change > 0)↑ {{ $change }} more
            @elseif($change < 0)↓ {{ abs($change) }} fewer
            @else No change
            @endif
            @if($previous > 0 && $change !== 0) ({{ sprintf('%+.1f%%', $change / $previous * 100) }})@endif
        </strong>
    </div>
    <p class="br-note">Completed activations by reporting week. Uses approved reports and follows the staff and search filters.</p>
    <dl class="ops-execution-comparison">
        @foreach(['current' => 'Selected period', 'previous' => 'Previous period'] as $key => $label)
            <div>
                <dt>{{ $label }} <small>{{ $executionTrend[$key.'_label'] }}</small></dt>
                <dd><span aria-hidden="true" class="ops-execution-track"><i class="{{ $key }}" style="width:{{ $executionTrend[$key] / $comparisonMax * 100 }}%"></i></span><strong>{{ $executionTrend[$key] }}</strong></dd>
            </div>
        @endforeach
    </dl>
    @if($points && $current > 0)
        <h5 class="mt-5 mb-3 font-semibold">{{ request('report_period') === 'year' ? 'Executions by Month' : 'Executions by Reporting Week' }}</h5>
        <dl class="ops-execution-trend">
            @foreach($points as $label => $count)
                <div><dt>{{ $label }}</dt><dd><span class="ops-execution-track" aria-hidden="true"><i style="width:{{ $count / $trendMax * 100 }}%"></i></span><strong>{{ $count }}</strong></dd></div>
            @endforeach
        </dl>
    @endif
</section>
