<section id="executive-activity" class="glass-panel rounded-2xl p-5 sm:p-6 mb-6" aria-labelledby="executive-activity-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 id="executive-activity-heading" class="text-xl font-semibold">Executive Activity</h2>
            <p class="mt-1 text-sm text-brand-ash">Choose a department, then narrow its reports and tasks by staff or reporting period.</p>
        </div>
        <nav class="flex flex-wrap gap-3 text-sm" aria-label="Activity shortcuts">
            <a href="{{ route('portal.project-journey') }}" class="underline underline-offset-4">Project Journey</a>
            @if(auth()->user()->canAuditHrAndFinance())
                <a href="{{ route('portal.hr') }}" class="underline underline-offset-4">HR Audit</a>
                <a href="{{ route('portal.finance') }}" class="underline underline-offset-4">Finance Audit</a>
            @endif
            <a href="#staff-weekly-performance" class="underline underline-offset-4">Department reports</a>
            <a href="#mega-table-disclosure" class="underline underline-offset-4" onclick="document.getElementById('mega-table-disclosure').open = true">Daily Mega Table</a>
        </nav>
    </div>
    @php
        $selectedDepartment = $executiveActivity['filters']['activity_department'] ?? '';
        $selectedStaff = (int) ($executiveActivity['filters']['activity_staff'] ?? 0);
        $activityParams = request()->only(['activity_department', 'activity_staff', 'activity_date', 'activity_section', 'report_period', 'report_date', 'weekly_department']);
        $activityLink = fn ($replace = [], $anchor = 'executive-activity') => route('dashboard', array_filter(array_replace($activityParams, $replace), fn ($value) => filled($value))).'#'.$anchor;
        $tabStyle = fn ($active) => 'rounded-xl border px-3 py-2 text-sm '.($active ? 'border-sky-400/50 bg-sky-400/15 font-semibold text-brand-white' : 'border-brand-white/15 text-brand-ash hover:text-brand-white');
    @endphp
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Executive departments">
        @foreach(['' => 'All Departments'] + $executiveActivity['departments'] as $key => $name)
            <a class="{{ $tabStyle($selectedDepartment === $key) }}" @if($selectedDepartment === $key) aria-current="page" @endif href="{{ $activityLink(['activity_department' => $key, 'activity_staff' => null, 'activity_section' => null]) }}">{{ $name }}</a>
        @endforeach
    </nav>
    @if($selectedDepartment !== '')
        <nav class="mb-4 flex flex-wrap gap-2" aria-label="Department staff">
            <a class="{{ $tabStyle(!$selectedStaff) }}" @if(!$selectedStaff) aria-current="page" @endif href="{{ $activityLink(['activity_staff' => null]) }}">All Staff</a>
            @foreach($executiveActivity['staff'] as $person)
                <a class="{{ $tabStyle($selectedStaff === $person->id) }}" @if($selectedStaff === $person->id) aria-current="page" @endif href="{{ $activityLink(['activity_staff' => $person->id]) }}">{{ $person->name }}</a>
            @endforeach
        </nav>
    @endif
    <form method="get" action="{{ route('dashboard') }}#executive-activity" class="mb-5 flex flex-wrap items-end gap-3" data-no-ajax data-no-silent>
        @foreach(array_intersect_key($activityParams, array_flip(['activity_department', 'activity_section', 'weekly_department'])) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        @if($selectedDepartment && $selectedStaff)
            <input type="hidden" name="activity_staff" value="{{ $selectedStaff }}">
        @elseif(!$selectedDepartment)
            <label class="text-sm">Staff
                <select name="activity_staff" class="mt-1 block max-w-full rounded-lg bg-brand-black p-2.5">
                    <option value="">All staff</option>
                    @foreach($executiveActivity['staff'] as $person)
                        <option value="{{ $person->id }}" @selected($selectedStaff === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="text-sm">Reporting period
            <select name="report_period" class="mt-1 block rounded-lg bg-brand-black p-2.5">
                @foreach(['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $mode => $label)
                    <option value="{{ $mode }}" @selected(request('report_period') === $mode)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Date in period
            <input type="date" name="report_date" value="{{ request('report_date') }}" class="mt-1 block rounded-lg bg-brand-black p-2.5">
        </label>
        <label class="text-sm">Task entry date<input type="date" name="activity_date" value="{{ $executiveActivity['filters']['activity_date'] ?? '' }}" class="mt-1 block rounded-lg bg-brand-black p-2.5"></label>
        <button class="rounded-lg border border-brand-white/20 px-4 py-2.5 text-sm">Filter Activity</button>
        <a href="{{ route('dashboard') }}#executive-activity" class="p-2.5 text-sm underline underline-offset-4">Clear</a>
    </form>
    @include('portal.partials.executive-source-records')
    <details id="executive-daily-tasks" class="mt-5 rounded-xl border border-brand-white/15 p-3" @if(!$executiveActivity['records']['sections'] || request()->filled('activity_date') || request()->filled('activity_page')) open @endif>
    <summary class="mb-3 cursor-pointer text-lg font-semibold">Daily task activity <span class="ml-2 text-sm font-normal text-brand-ash">({{ $executiveActivity['rows']->total() }})</span></summary>
    <div class="overflow-auto rounded-xl border border-brand-white/10" tabindex="0" role="region" aria-label="Task tracking results">
        <table class="w-full min-w-[45rem] text-left text-sm">
            <thead class="bg-brand-white/5">
                <tr>@foreach(['Task', 'Assigned To', 'Department', 'Status', 'Entered', 'Due'] as $label)<th scope="col" class="px-3 py-2.5">{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse($executiveActivity['rows'] as $task)
                    <tr class="border-t border-brand-white/10">
                        <th scope="row" class="px-3 py-2.5 font-semibold">
                            @if($task->shared_title)<div>{{ $task->shared_title }}</div>@endif
                            <div class="text-xs text-brand-ash">#{{ $task->id }}</div>
                        </th>
                        <td class="px-3 py-2.5">
                            {{ $task->assignee?->name ?? 'Unassigned' }}
                            @php($supportNames = collect($task->supporting_staff_ids ?? [])->map(fn ($id) => $executiveActivity['support']->get((int) $id))->filter()->implode(', '))
                            @if($supportNames)<div class="mt-1 text-xs text-brand-ash">Support: {{ $supportNames }}</div>@endif
                        </td>
                        <td class="px-3 py-2.5">{{ \App\Models\User::departmentLabel($task->department ?: $task->assignee?->department) }}</td>
                        <td class="px-3 py-2.5">{{ match ($task->completion_review_status) { 'pending' => 'Awaiting review', 'reverted' => 'Returned for correction', default => $task->status ?: 'Not recorded' } }}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap">{{ $task->created_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap">{{ $task->due_on?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-brand-ash">No activity matches the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($executiveActivity['rows']->hasPages())<div class="mt-4">{{ $executiveActivity['rows']->links() }}</div>@endif
    </details>
</section>
