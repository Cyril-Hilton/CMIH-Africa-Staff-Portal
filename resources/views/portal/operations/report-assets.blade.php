@php($warehouseTab = request('ops_warehouse', 'inventory'))
<section class="br-table-section">
    <header class="br-table-heading"><h4>Warehouse Assets</h4><a class="ops-button ops-primary" href="{{ route('portal.assets.warehouse.index') }}">Open Warehouse</a></header>
    <nav class="br-staff ops-staff" aria-label="Warehouse views">
        @foreach(['inventory' => 'Current Inventory', 'movements' => 'Issue & Return History'] as $view => $label)
            <a class="ops-button" href="{{ $workspaceUrl(['ops_warehouse' => $view]) }}" @if($warehouseTab === $view) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if($warehouseTab === 'inventory')
        <p class="br-note">Stock and warehouse locations are current. Select Issue &amp; Return History to see movements in the reporting period.</p>
        <div class="br-table-wrap" tabindex="0" role="region" aria-label="Current warehouse inventory">
            <table class="br-table ops-table">
                <thead><tr><th>Asset</th><th>Code</th><th>Brand / Category</th><th>On Hand</th><th>Warehouse Location</th><th>Recorded Location</th><th>Custodian</th><th>Status</th></tr></thead>
                <tbody>@forelse($inventory as $asset)
                    <tr><td>{{ $asset->name }}</td><td>{{ $asset->assetTagOrCode }}</td><td>{{ $asset->brand ?? '—' }} / {{ $asset->category ?? '—' }}</td><td>{{ $asset->warehouse_quantity ?? '—' }}</td><td>{{ $asset->warehouse_location ?? '—' }}</td><td>{{ $asset->location ?? '—' }}</td><td>{{ $asset->assignee?->name ?? '—' }}</td><td><span class="br-badge" data-ops-status="{{ \Illuminate\Support\Str::slug($asset->status ?? '', '_') }}">{{ $asset->status ?? '—' }}</span></td></tr>
                @empty<tr><td class="br-empty-cell" colspan="8">No assets match this search.</td></tr>@endforelse</tbody>
            </table>
        </div>
        @include('portal.operations.report-pagination', ['page' => $inventory, 'paginationLabel' => 'Warehouse inventory'])
    @else
        @unless($warehouseHistory)<p class="br-note">Showing warehouse movements from your requests.</p>@endunless
        @include('portal.operations.report-movements', ['rows' => $movements, 'emptyMessage' => 'No issued or returned assets in this view.'])
        @include('portal.operations.report-pagination', ['page' => $movements, 'paginationLabel' => 'Warehouse movements'])
    @endif
</section>
