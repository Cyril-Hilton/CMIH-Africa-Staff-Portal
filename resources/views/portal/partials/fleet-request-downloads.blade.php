<div class="mb-2 flex flex-wrap gap-3 text-xs">
    <a class="underline" href="{{ route('portal.fleet-requests.download', [$fleetRequest, 'xlsx']) }}">Excel</a>
    <a class="underline" href="{{ route('portal.fleet-requests.download', [$fleetRequest, 'pdf']) }}">PDF</a>
</div>
