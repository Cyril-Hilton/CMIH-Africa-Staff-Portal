@php
    $filters = request()->only(['report_period','report_date']) + ['weekly_department' => 'hr_admin', 'hr_tab' => $tab, 'hr_period' => $period, 'hr_from' => $anchorDate, 'hr_to' => $to, 'hr_owner' => $owner ?: null];
    $tableFilters = ['hr_q' => $search ?: null, 'hr_category' => $category ?: null, 'hr_status' => $status ?: null, 'hr_sort' => $sort, 'hr_direction' => $direction];
    $dashboardUrl = fn (array $overrides = []) => route('dashboard', array_replace($filters, $tableFilters, $overrides)).'#staff-weekly-performance';
    $full = auth()->user()->hasFullHrAccess();
    $adminTab = in_array($tab, ['overview', 'operations'], true);
    $entryLabel = $adminTab ? 'Administrator' : 'HR';
    $entryClass = $adminTab ? 'hr-entry-admin' : 'hr-entry-private';
    $edit = $activities->first(fn ($row) => $row->id === request()->integer('hr_edit') && ($full || in_array(auth()->id(), [$row->created_by, $row->assigned_to])));
    $links = match ($tab) {
        'overview' => $full ? ['Attendance & Punctuality' => 'portal.hr-reporting.attendance', 'Leave Requests & Balances' => 'portal.leaves'] : [],
        'people' => ['Staff Records' => 'portal.hr.staff.index', 'Leave Requests & Balances' => 'portal.leaves', 'Attendance & Punctuality' => 'portal.hr-reporting.attendance'],
        'performance' => ['Appraisals' => 'portal.appraisals.index', 'Periodic HR Reports' => 'portal.hr-reporting.index'],
        'operations' => ['Fleet Requests' => 'portal.fleet-requests', 'Office Asset Register' => 'portal.assets'],
        default => [],
    };
@endphp
<section class="brands-reports hr-dashboard" data-hr-dashboard aria-label="HR and Admin dashboard">
    <header class="br-heading">
        <div><h3>HR &amp; Admin</h3><p>{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p></div>
        <nav class="br-actions" aria-label="Earlier reports">
            @if($full)
                <a class="hr-report-control" href="{{ route('dashboard', ['weekly_department' => 'hr_admin', 'hr_existing' => 1]) }}#staff-weekly-performance">Existing HR Reports</a>
            @endif
            <a class="hr-report-control" href="{{ route('portal.admin-weekly.index') }}">Administrator Reports</a>
        </nav>
    </header>
    <nav class="br-tabs" aria-label="HR and Admin reporting views">
        @foreach($tabs as $id => [$label])
            <a class="hr-report-control" aria-current="{{ $tab === $id ? 'page' : 'false' }}" href="{{ $dashboardUrl(['hr_tab' => $id, 'hr_q' => null, 'hr_category' => null, 'hr_status' => null, 'hr_sort' => null, 'hr_direction' => null]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <nav class="br-staff hr-staff-filters" aria-label="Filter by HR and Admin team member">
        <a class="hr-report-control" aria-current="{{ !$owner ? 'true' : 'false' }}" href="{{ $dashboardUrl(['hr_owner' => null]) }}">All HR / Admin Staff</a>
        @foreach($team as $member)
            <a class="hr-report-control" aria-current="{{ $owner === $member->id ? 'true' : 'false' }}" href="{{ $dashboardUrl(['hr_owner' => $member->id]) }}">
                <img src="{{ $member->profilePhotoUrl() }}" alt="" loading="lazy">{{ $member->name }}
            </a>
        @endforeach
    </nav>
    <p class="br-notice">Activities opened in this period, deadlines in this period and earlier open follow-ups. Status shows the latest recorded update.</p>
    @if($stats)
        <div class="br-summary">
            @foreach($stats as $label => $count)
                <div><small>{{ $label }}</small><strong>{{ number_format($count) }}</strong></div>
            @endforeach
        </div>
        <p class="br-note">Figures come from portal records. Headcount is a current snapshot.</p>
    @endif
    @if($links)
        <nav class="br-actions" aria-label="Source records">
            @foreach($links as $label => $route)
                <a class="hr-report-control" href="{{ route($route) }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    @if(session('status'))<p class="br-status" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="br-error" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <section class="br-table-section" aria-label="{{ $tabs[$tab][0] }} activities">
        <header class="br-table-heading"><h4>{{ $tabs[$tab][0] }} <small>{{ $activities->total() }}</small></h4><span class="br-table-hint">Scroll wide tables to see more</span></header>
        <details class="mb-4" @if($edit || $errors->any()) open @endif>
            <summary class="hr-report-control {{ $entryClass }}">{{ $edit ? 'Edit' : 'Add' }} {{ $entryLabel }} Entry</summary>
            @if($adminTab)<p class="br-help mt-2">Use this form for administrative work. Keep confidential staff records in the restricted HR tabs.</p>@endif
            <form class="hr-activity-form mt-4" method="POST" enctype="multipart/form-data"
                  action="{{ $edit ? route('portal.hr-dashboard.activities.update', $edit) : route('portal.hr-dashboard.activities.store') }}" data-no-ajax data-no-silent>
                @csrf
                @if($edit)
                    @method('PATCH')
                    <input type="hidden" name="revision" value="{{ $edit->revision }}">
                @endif
                <input type="hidden" name="tab" value="{{ $tab }}">
                @foreach($filters + $tableFilters as $key => $value)
                    @continue($key === 'weekly_department' || $key === 'hr_tab')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label>Activity type
                    <select name="category" required><option value="">Choose activity type</option>
                        @foreach($tabs[$tab][1] as $entryCategory)<option @selected(old('category', $edit?->category) === $entryCategory)>{{ $entryCategory }}</option>@endforeach
                    </select>
                </label>
                <label>Activity<input name="activity" maxlength="255" required value="{{ old('activity', $edit?->activity) }}"></label>
                <label>Staff involved
                    <select name="staff_id"><option value="">None specified</option>
                        @if($edit?->staff_id && !$people->has($edit->staff_id))
                            <option value="{{ $edit->staff_id }}" selected>{{ $edit->staff?->name }}</option>
                        @endif
                        @foreach($people as $id => $name)<option value="{{ $id }}" @selected((int) old('staff_id', $edit?->staff_id) === $id)>{{ $name }}</option>@endforeach
                    </select>
                </label>
                <label>Department involved
                    <select name="department"><option value="">None specified</option>
                        @foreach(config('performance.departments') as $id => $label)<option value="{{ $id }}" @selected(old('department', $edit?->department) === $id)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label>Date opened<input type="date" name="opened_on" required value="{{ old('opened_on', $edit?->opened_on ?? today()->toDateString()) }}"></label>
                <label>Deadline<input type="date" name="deadline" value="{{ old('deadline', $edit?->deadline) }}"></label>
                <label>Assigned to
                    <select name="assigned_to" required><option value="">Choose HR / Admin staff</option>
                        @foreach($team->filter(fn ($person) => $person->isActive() || $edit?->assigned_to === $person->id) as $person)
                            <option value="{{ $person->id }}" @selected((int) old('assigned_to', $edit?->assigned_to ?? auth()->id()) === $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Status
                    <select name="status">@foreach(\App\Models\HrDashboardActivity::STATUSES as $entryStatus)<option @selected(old('status', $edit?->status) === $entryStatus)>{{ $entryStatus }}</option>@endforeach</select>
                </label>
                <label class="hr-activity-wide">Remarks<textarea name="remarks" rows="3" maxlength="6000">{{ old('remarks', $edit?->remarks) }}</textarea></label>
                <label class="hr-activity-wide">Attachment <span class="br-help">(optional, up to 10 MB)</span><input type="file" name="attachment" accept=".pdf,.docx,.xlsx,.pptx,.txt,.jpg,.jpeg,.png,.webp"></label>
                <div class="br-actions hr-activity-wide">
                    <button type="submit" class="hr-report-control {{ $entryClass }}">Save {{ $entryLabel }} Entry</button>
                    @if($edit)<a class="hr-report-control" href="{{ $dashboardUrl(['hr_page' => $activities->currentPage()]) }}">Cancel Edit</a>@endif
                </div>
            </form>
        </details>
        <form class="br-table-tools" method="GET" action="{{ route('dashboard') }}#hr-activity-table" data-no-ajax data-no-silent>
            @foreach($filters + array_intersect_key($tableFilters, array_flip(['hr_sort', 'hr_direction'])) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <label class="br-table-search">Search activities<input type="search" name="hr_q" maxlength="150" value="{{ $search }}" placeholder="Activity, remarks or staff"></label>
            <label>Activity type<select name="hr_category"><option value="">All types</option>
                @foreach($tabs[$tab][1] as $option)<option @selected($category === $option)>{{ $option }}</option>@endforeach
            </select></label>
            <label>Status<select name="hr_status"><option value="">All statuses</option>
                @foreach(\App\Models\HrDashboardActivity::STATUSES as $option)<option @selected($status === $option)>{{ $option }}</option>@endforeach
            </select></label>
            <button type="submit" class="br-filter-button">Filter Table</button>
            <a class="hr-report-control" href="{{ $dashboardUrl(['hr_q' => null, 'hr_category' => null, 'hr_status' => null]) }}">Clear Filters</a>
        </form>
        <div id="hr-activity-table" class="br-table-wrap" role="region" aria-label="{{ $tabs[$tab][0] }} activities table" tabindex="0">
            <table class="br-table" data-table-enhancer="off">
                <caption class="sr-only">{{ $tabs[$tab][0] }} activities opened in the selected period</caption>
                <thead><tr>
                    @foreach(\App\Services\HrDashboard::SORT_COLUMNS as $column => $label)
                        <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="br-sort-button" href="{{ str_replace('#staff-weekly-performance', '#hr-activity-table', $dashboardUrl(['hr_sort' => $column, 'hr_direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc'])) }}">
                                {{ $label }} <span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th scope="col" class="br-action-heading">Attachment</th>
                    <th scope="col" class="br-action-heading">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($activities as $row)
                    @php($tone = match ($row->status) {'Completed' => 'success', 'Overdue' => 'danger', 'In Progress', 'Pending Approval', 'On Hold' => 'attention', default => 'info'})
                    <tr>
                        <td class="br-description-cell"><strong>{{ $row->activity }}</strong><p class="br-help">{{ $row->category }}</p></td>
                        <td>{{ $row->staff?->name ?? '—' }}@if($row->department)<p>{{ config('performance.departments.'.$row->department, $row->department) }}</p>@endif</td>
                        <td class="br-date-cell">{{ $row->opened_on }}</td><td class="br-date-cell">{{ $row->deadline ?? '—' }}</td>
                        <td>{{ $row->assignee?->name ?? '—' }}</td>
                        <td><span class="br-status-badge br-status-{{ $tone }}">{{ $row->status }}</span></td>
                        <td class="br-description-cell whitespace-pre-wrap">{{ $row->remarks ?? '—' }}</td>
                        <td>@if($row->attachment)<a class="underline" href="{{ route('portal.hr-dashboard.activities.attachment', $row) }}">Download</a>@else — @endif</td>
                        <td>@if($full || in_array(auth()->id(), [$row->created_by, $row->assigned_to]))<a class="hr-report-control" href="{{ $dashboardUrl(['hr_edit' => $row->id, 'hr_page' => $activities->currentPage()]) }}">Edit {{ $entryLabel }} Entry</a>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td class="br-empty-cell" colspan="9">No activities match the selected date, staff, and table filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-dashboard-pagination :paginator="$activities" item-label="activities" /></div>
    </section>
</section>
