@php
    $tabs = config('operations_tracker.tabs');
    $labels = config('operations_tracker.labels');
    $states = \App\Services\OperationsReporting::STATUSES;
    $period = request('report_period', 'week');
    $periodDate = \Carbon\CarbonImmutable::parse($from);
    $shift = fn ($n) => match($period) {
        'month' => $periodDate->addMonthsNoOverflow($n),
        'year' => $periodDate->addYears($n),
        default => $periodDate->addWeeks($n),
    };
    $queryKeys = ['ops_tab', 'ops_owner', 'ops_q', 'ops_sort', 'ops_direction', 'ops_status', 'ops_rows', 'ops_warehouse'];
    $editStatus = fn ($status) => $states[$status] ?? \Illuminate\Support\Str::headline($status ?: 'not recorded');
    $safeFields = fn ($kind) => \App\Services\OperationsReporting::fields($kind, $manager && $modules);
@endphp
<section class="brands-reports ops-reporting" @if($modules) id="operations-reporting" @endif data-operations-reporting data-no-loader data-no-silent data-table-enhancer-skip data-existing-confirmation aria-label="Operations and Projects reports">
    <header class="br-heading">
        <div>
            <h3>Operations &amp; Projects</h3>
            <p>{{ $periodDate->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
        </div>
        @if($member)
            <div class="br-actions">
                <a class="ops-button" href="{{ $workspaceUrl(['ops_review' => 0]) }}">{{ $manager ? 'Team Entries & Reviews' : 'My Entries & Reviews' }}</a>
                @if($modules)
                    <a class="ops-button" href="{{ route('dashboard', request()->only([...$queryKeys, 'report_period', 'report_date']) + ['weekly_department' => 'operations_projects']) }}#staff-weekly-performance">Weekly Presentation</a>
                @else
                    <a class="ops-button" href="{{ route('portal.operations', request()->only([...$queryKeys, 'report_period', 'report_date'])) }}#operations-reporting">Operations Module</a>
                @endif
            </div>
        @endif
    </header>

    @if(session('status') && ! $modules)<p role="status" class="br-status">{{ session('status') }}</p>@endif
    @if($errors->any() && ! $modules)<ul role="alert" class="br-error mb-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <nav class="br-tabs ops-tabs" aria-label="Operations reporting categories">
        @foreach($tabs as $id => $label)
            <a class="ops-button" href="{{ $workspaceUrl(['ops_tab' => $id, 'ops_status' => null, 'ops_warehouse' => null]) }}" @if($id === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <nav class="br-staff ops-staff" aria-label="Filter Operations reports by staff">
        <a class="ops-button" href="{{ $workspaceUrl(['ops_owner' => null]) }}" @unless($owner) aria-current="true" @endunless>All Operations Staff</a>
        @foreach($staff as $person)
            <a class="ops-button" href="{{ $workspaceUrl(['ops_owner' => $person->id]) }}" @if((int) $owner === $person->id) aria-current="true" @endif>
                <img src="{{ $person->profilePhotoUrl() }}" alt="" loading="lazy" width="28" height="28">{{ $person->name }}
            </a>
        @endforeach
    </nav>
    <form class="br-filters" method="get" action="{{ $modules ? route('portal.operations').'#operations-reporting' : route('dashboard').'#staff-weekly-performance' }}" data-no-ajax data-no-silent>
        @if($modules)<input type="hidden" name="module_view" value="overview">@else<input type="hidden" name="weekly_department" value="operations_projects">@endif
        <input type="hidden" name="ops_tab" value="{{ $tab }}">
        <input type="hidden" name="ops_owner" value="{{ $owner }}">
        @if(request()->filled('ops_warehouse'))<input type="hidden" name="ops_warehouse" value="{{ request('ops_warehouse') }}">@endif
        @if($modules)
            <label>Reporting Period<select name="report_period">@foreach(['week'=>'Week','month'=>'Month','year'=>'Year'] as $mode=>$text)<option value="{{ $mode }}" @selected($mode === $period)>{{ $text }}</option>@endforeach</select></label>
            <label>Date in Period<input type="date" name="report_date" value="{{ $from }}" required></label>
        @else
            <input type="hidden" name="report_period" value="{{ $period }}">
            <input type="hidden" name="report_date" value="{{ $from }}">
        @endif
        <label>Search<input type="search" name="ops_q" maxlength="150" value="{{ $filters['ops_q'] ?? '' }}" placeholder="Search this view"></label>
        @if($tab !== 'kpis' && ! ($tab === 'assets' && request('ops_warehouse', 'inventory') === 'inventory'))<label>Status<select name="ops_status"><option value="">All statuses</option>@foreach($states as $id=>$label)<option value="{{ $id }}" @selected(($filters['ops_status'] ?? null) === $id)>{{ $label }}</option>@endforeach</select></label>@endif
        @if($tab !== 'kpis')<label>Sort By<select name="ops_sort">@foreach(['newest'=>'Latest dates first','oldest'=>'Earliest dates first','title'=>'Title A–Z','owner'=>'Owner A–Z','status'=>'Status A–Z'] as $id=>$label)<option value="{{ $id }}" @selected(($filters['ops_sort'] ?? 'newest') === $id)>{{ $label }}</option>@endforeach</select></label>
        <label>Rows per Table<select name="ops_rows">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected((int) ($filters['ops_rows'] ?? 10) === $size)>{{ $size }}</option>@endforeach</select></label>@endif
        <button class="ops-button ops-primary">Apply Filters</button>
        <a class="ops-button" href="{{ $workspaceUrl(['ops_q'=>null,'ops_status'=>null,'ops_owner'=>null,'ops_sort'=>null,'ops_direction'=>null,'ops_rows'=>null]) }}">Clear Filters</a>
    </form>
    @if($modules)
        <nav class="br-period-nav" aria-label="Operations reporting period navigation">
            <a class="ops-button" href="{{ $workspaceUrl(['report_date' => $shift(-1)->toDateString()]) }}">Previous {{ ucfirst($period) }}</a>
            <a class="ops-button" href="{{ $workspaceUrl(['report_date' => today()->toDateString()]) }}">This {{ ucfirst($period) }}</a>
            <a class="ops-button" href="{{ $workspaceUrl(['report_date' => $shift(1)->toDateString()]) }}">Next {{ ucfirst($period) }}</a>
        </nav>
    @endif

    @if($newKind || $edit)
        <section class="ops-entry" id="operations-entry" aria-label="Operations report entry">
            <header class="br-table-heading"><h4>{{ $edit ? 'Edit' : 'Add' }} {{ $labels[$edit?->kind ?? $newKind] }}</h4><a class="ops-button" href="{{ $workspaceUrl() }}">Cancel</a></header>
            @include('portal.operations.report-form', ['formKind' => $edit?->kind ?? $newKind, 'formRecord' => $edit])
        </section>
    @endif

    @if($submissions !== null)
        @include('portal.operations.report-reviews')
    @endif

    @if($tab === 'overview')
        @include('portal.operations.report-overview')
    @endif

    @foreach($registers as $kind => $entries)
        @include('portal.operations.report-register')
    @endforeach

    @if($tab === 'projects')
        @include('portal.operations.report-campaigns')
    @elseif($tab === 'assets' && $member)
        @include('portal.operations.report-assets')
    @elseif($tab === 'weekly')
        @include('portal.operations.report-weekly')
    @elseif($tab === 'kpis')
        @include('portal.operations.report-kpis')
    @endif
</section>
