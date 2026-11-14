<section class="ops-linked">
    <div class="br-table-heading"><h4>Activation Assets</h4>
        <a class="ops-button" href="{{ route('portal.assets.warehouse.index', array_filter(['operations_activation_id' => $activationId, 'campaign_id' => $campaignId])) }}#warehouse-inventory">Request Warehouse Asset</a>
    </div>
    @include('portal.operations.report-movements', ['rows' => $linked, 'emptyMessage' => 'No warehouse requests linked to this activation.'])
    @if($linkedLedger->isNotEmpty() || $linkedLogs->isNotEmpty())
        <h5 class="mt-5 mb-2 font-semibold">Other Recorded Movements</h5>
        <ul class="space-y-2">
            @foreach($linkedLedger as $movement)
                <li>{{ $movement->occurred_on?->format('d M Y') ?? $movement->created_at?->format('d M Y') }} · {{ $movement->item_name }} · In {{ $movement->quantity_in }} / Out {{ $movement->quantity_out }} · {{ $movement->location ?: 'Location unrecorded' }} · {{ $movement->creator?->name }}</li>
            @endforeach
            @foreach($linkedLogs as $movement)
                <li>{{ $movement->created_at?->format('d M Y') }} · {{ $movement->asset?->name }} · {{ \Illuminate\Support\Str::headline($movement->action) }} · {{ $movement->user?->name }}</li>
            @endforeach
        </ul>
    @endif
</section>
