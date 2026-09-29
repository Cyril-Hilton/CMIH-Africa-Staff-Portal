<x-app-layout>
    <x-slot name="header">
        <p class="text-xs uppercase tracking-widest text-brand-ash">HR & Admin</p>
        <h2 class="text-3xl font-display text-brand-white">Staff register</h2>
    </x-slot>
    <div class="space-y-6 text-brand-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><a class="text-sm text-brand-ash underline" href="{{ route('portal.hr') }}">Back to HR & Admin</a><p class="mt-2 text-sm">{{ $staff->total() }} internal staff match your filters. Click a name to view their record.</p></div>
            <a href="{{ route('portal.hr.staff.import') }}" class="rounded-xl border border-brand-white/20 px-4 py-3 text-sm font-semibold">Import staff updates</a>
        </div>
        @if(session('status'))<p role="status" class="rounded-xl bg-emerald-500/10 p-4 text-emerald-300">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-xl bg-red-500/10 p-4 text-red-300">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="GET" class="flex flex-wrap items-end gap-4 rounded-2xl bg-brand-white/5 p-5">
            <label class="flex-1 text-sm">Search staff<input name="q" value="{{ request('q') }}" placeholder="Name, email or staff ID" class="mt-2 block w-full rounded-lg bg-brand-black border-brand-white/20"></label>
            <label class="text-sm">Department<select name="department" class="mt-2 block rounded-lg bg-brand-black border-brand-white/20"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ \App\Models\User::departmentLabel($department) }}</option>@endforeach</select></label>
            <label class="text-sm">Status<select name="status" class="mt-2 block rounded-lg bg-brand-black border-brand-white/20"><option value="">All statuses</option>@foreach($statuses as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
            <button class="rounded-lg bg-brand-red text-white px-4 py-2">Apply filters</button><a href="{{ route('portal.hr.staff.index') }}" class="py-2 underline">Reset</a>
        </form>
        <form method="POST" action="{{ route('portal.hr.staff.export') }}" x-data="{ selected: [], fields: @js($record->fields()), format: 'csv', scope: 'selected', pageIds: @js($staff->pluck('id')->map(fn($id) => (string) $id)->values()) }" class="space-y-5">
            @csrf
            @foreach(['q', 'department', 'status'] as $filter)<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endforeach
            <div class="overflow-x-auto rounded-2xl border border-brand-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-brand-white/5"><tr><th class="p-4"><input type="checkbox" aria-label="Select all staff on this page" @change="selected = $event.target.checked ? [...pageIds] : []" :checked="pageIds.length > 0 && selected.length === pageIds.length"></th><th class="p-4">Staff member</th><th class="p-4">Department / position</th><th class="p-4">Date of birth</th><th class="p-4">Status</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($staff as $member)
                            <tr><td class="p-4"><input type="checkbox" name="ids[]" value="{{ $member->id }}" x-model="selected" aria-label="Select {{ $member->name }}"></td><td class="p-4"><a class="font-semibold underline" href="{{ route('portal.hr.staff.show', $member) }}">{{ $member->name }}</a><p class="text-xs text-brand-ash">{{ $member->staff_id_number ?: 'No staff ID' }} · {{ $member->email }}</p></td><td class="p-4">{{ \App\Models\User::departmentLabel($member->department) }}<p class="text-xs text-brand-ash">{{ $member->position_title ?: $member->job_title }}</p></td><td class="p-4">{{ $member->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</td><td class="p-4">{{ ucfirst($member->status) }}</td></tr>
                        @empty<tr><td colspan="5" class="p-8 text-center text-brand-ash">No staff match these filters.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            {{ $staff->links() }}
            <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6 space-y-5">
                <h3 class="text-xl font-display">Export staff information</h3>
                <p class="text-sm text-brand-ash">Selections apply to this page. Choose all matching staff to include every page of the filtered results, or all staff to ignore filters.</p>
                <div class="flex flex-wrap gap-5">
                    <label class="text-sm">Staff to include<select name="scope" x-model="scope" class="mt-2 block rounded-lg bg-brand-black border-brand-white/20"><option value="selected">Selected staff on this page</option><option value="filtered">All matching staff ({{ $staff->total() }})</option><option value="all">All internal staff</option></select></label>
                    <label class="text-sm">Download format<select name="format" x-model="format" class="mt-2 block rounded-lg bg-brand-black border-brand-white/20"><option value="csv">Spreadsheet (CSV): chosen fields</option><option value="zip">Full staff records (ZIP)</option></select></label>
                </div>
                <div x-show="format === 'csv'" class="space-y-4">
                    <p class="text-sm text-brand-ash">Choose the information you need. Record ID, staff ID and name are always included. CSV opens in Excel.</p>
                    <div class="flex flex-wrap gap-4 text-sm"><button type="button" class="underline" @click="fields = @js($record->fields())">Select all fields</button><button type="button" class="underline" @click="fields = ['staff_id_number', 'name']">Clear optional fields</button><button type="button" class="underline" @click="fields = ['staff_id_number', 'name', 'date_of_birth', 'birthday_month', 'birthday_day', 'department']">Birthdays only</button></div>
                    @foreach(\App\Services\HrStaffRecord::GROUPS as $group => $columns)
                        <details class="rounded-xl border border-brand-white/10 p-4"><summary class="cursor-pointer font-semibold">{{ $group }}</summary><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($columns as $field)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="fields[]" value="{{ $field }}" x-model="fields">{{ $record->label($field) }}</label>@endforeach</div></details>
                    @endforeach
                </div>
                <p x-show="format === 'zip'" class="text-sm text-brand-ash">Includes every profile field, available profile documents, and all recorded leave, attendance, appraisals, payslips, salary advances, repayments, claims, assets, transport, tasks and awards. Missing documents are listed. Passwords, authentication tokens and private messages are excluded.</p>
                <div class="flex flex-wrap items-center gap-4"><button class="rounded-xl bg-brand-red text-white px-5 py-3 font-semibold disabled:opacity-40" :disabled="(scope === 'selected' && selected.length === 0) || (format === 'csv' && fields.length === 0)">Download export</button><span class="text-sm text-brand-ash" x-text="selected.length + ' staff selected on this page'"></span></div>
            </div>
        </form>
    </div>
</x-app-layout>
