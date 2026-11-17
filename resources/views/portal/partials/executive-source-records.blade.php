@if($executiveActivity['records']['sections'])
    @php($reports = $executiveActivity['records'])
    <section id="executive-source-reports" class="space-y-4 rounded-2xl border border-brand-white/15 p-4" aria-label="Department source reports">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold">{{ $executiveActivity['departments'][$selectedDepartment] }}</h3>
                <p class="text-sm text-brand-ash">{{ \Illuminate\Support\Carbon::parse(request('weekly_from'))->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse(request('weekly_to'))->format('d M Y') }}</p>
            </div>
            <a class="text-sm underline" href="{{ route('dashboard', array_replace($activityParams, ['weekly_department' => $selectedDepartment])).'#staff-weekly-performance' }}">Open weekly department dashboard</a>
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="Department reports">
            @foreach($reports['sections'] as $key => $label)
                <a class="{{ $tabStyle($reports['section'] === $key) }}" @if($reports['section'] === $key) aria-current="page" @endif href="{{ $activityLink(['activity_section' => $key], 'executive-source-reports') }}">{{ $label }}</a>
            @endforeach
        </nav>
        @foreach($reports['groups'] as $group)
            <div class="space-y-2">
                <h4 class="font-semibold">{{ $group['label'] }} <span class="text-sm font-normal text-brand-ash">({{ $group['rows']->total() }})</span></h4>
                @forelse($group['rows'] as $row)
                    <details class="rounded-xl border border-brand-white/10 p-3" @if(request()->filled('activity_activation') && $reports['section'] === 'activations') open @endif>
                        <summary class="cursor-pointer rounded-lg px-2 py-1">
                            <strong>{{ $row['title'] }}</strong>
                            <span class="ml-2 text-sm text-brand-ash">{{ $row['owner'] ?? 'Owner not recorded' }}@if($row['status']) · {{ $row['status'] }}@endif</span>
                        </summary>
                        @include('portal.partials.executive-record-fields', ['fields' => $row['fields']])
                        @if($row['url'])<a class="mt-3 inline-block text-sm underline" href="{{ $row['url'] }}">Open source record</a>@endif
                        @if($row['linked'])
                            <h5 class="mt-5 text-sm font-semibold">Linked assets and records</h5>
                            @foreach($row['linked'] as $linked)
                                <details class="mt-2 rounded-lg border border-brand-white/15 p-3">
                                    <summary class="cursor-pointer text-sm">{{ $linked['title'] }} · {{ $linked['status'] }}</summary>
                                    @include('portal.partials.executive-record-fields', ['fields' => $linked['fields'] + ['Recorded by' => $linked['owner']]])
                                </details>
                            @endforeach
                        @elseif($selectedDepartment === 'operations_projects' && $reports['section'] === 'activations')
                            <p class="mt-4 text-sm text-brand-ash">No warehouse movements or other source records are linked to this activation.</p>
                        @endif
                    </details>
                @empty
                    <p class="rounded-lg border border-brand-white/10 p-3 text-sm text-brand-ash">No records match this period and staff filter.</p>
                @endforelse
                @if($group['rows']->hasPages()){{ $group['rows']->links() }}@endif
            </div>
        @endforeach
    </section>
@endif
