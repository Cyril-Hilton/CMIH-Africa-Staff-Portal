<x-app-layout>
    <x-slot name="header"><p class="text-xs uppercase tracking-widest text-brand-ash">HR staff record</p><h2 class="text-3xl font-display text-brand-white">{{ $staffMember->name }}</h2></x-slot>
    <div class="space-y-6 text-brand-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('portal.hr.staff.index') }}" class="underline">Back to staff register</a>
            <form method="POST" action="{{ route('portal.hr.staff.export') }}" class="flex gap-3">@csrf<input type="hidden" name="scope" value="selected"><input type="hidden" name="ids[]" value="{{ $staffMember->id }}">@foreach($record->fields() as $field)<input type="hidden" name="fields[]" value="{{ $field }}">@endforeach<button name="format" value="csv" class="rounded-lg border border-brand-white/20 px-4 py-2">Export profile CSV</button><button name="format" value="zip" class="rounded-lg bg-brand-red text-white px-4 py-2">Download full record</button></form>
        </div>
        <p class="text-sm text-brand-ash">{{ $staffMember->staff_id_number ?: 'No staff ID' }} · {{ ucfirst($staffMember->status) }} · {{ \App\Models\User::departmentLabel($staffMember->department) }}</p>
        @foreach(\App\Services\HrStaffRecord::GROUPS as $group => $fields)
            <section class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6"><h3 class="mb-5 text-xl font-display">{{ $group }}</h3><dl class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($fields as $field)<div class="min-w-0"><dt class="text-xs uppercase tracking-wide text-brand-ash">{{ $record->label($field) }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">@if($group === 'Documents')@if($record->document($staffMember, $field))<a class="underline" href="{{ route('portal.hr.staff.document', [$staffMember, $field]) }}">Download {{ strtolower($record->label($field)) }}</a>@else{{ $staffMember->{$field} ? 'File missing from storage' : 'Not supplied' }}@endif @else{{ $record->value($staffMember, $field) !== '' ? $record->value($staffMember, $field) : 'Not recorded' }}@endif</dd></div>@endforeach
            </dl></section>
        @endforeach
        <section class="rounded-2xl border border-brand-white/10 p-6">
            <h3 class="text-xl font-display">HR and employment history</h3>
            <nav aria-label="Staff history sections" class="my-5 flex flex-wrap gap-3">@foreach(\App\Services\HrStaffRecord::HISTORIES as $key => [$label])<a @if($section === $key) aria-current="page" @endif class="rounded-lg border border-brand-white/20 px-3 py-2 text-sm {{ $section === $key ? 'bg-brand-red text-white' : '' }}" href="{{ route('portal.hr.staff.show', [$staffMember, 'section' => $key]) }}">{{ $label }}</a>@endforeach</nav>
            <h4 class="mb-4 font-semibold">{{ \App\Services\HrStaffRecord::HISTORIES[$section][0] }} ({{ $history->total() }})</h4>
            <div class="space-y-3">@forelse($history as $entry)<details class="rounded-xl bg-brand-white/5 p-4"><summary class="cursor-pointer">Record #{{ $entry->id }} @if(isset($entry->status)) · {{ ucfirst($entry->status) }} @endif @if(isset($entry->created_at)) · {{ $entry->created_at }} @endif</summary><dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach((array) $entry as $field => $value)<div class="min-w-0"><dt class="text-xs text-brand-ash">{{ $record->label($field) }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm">{{ $record->text($value) !== '' ? $record->text($value) : 'Not recorded' }}</dd></div>@endforeach</dl></details>@empty<p class="py-6 text-brand-ash">No records in this section.</p>@endforelse</div>
            <div class="mt-5">{{ $history->links() }}</div>
        </section>
    </div>
</x-app-layout>
