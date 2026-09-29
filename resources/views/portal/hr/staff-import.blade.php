<x-app-layout>
    <x-slot name="header"><p class="text-xs uppercase tracking-widest text-brand-ash">HR & Admin</p><h2 class="text-3xl font-display text-brand-white">Import staff updates</h2></x-slot>
    <div class="space-y-6 text-brand-white">
        <a href="{{ route('portal.hr.staff.index') }}" class="underline">Back to staff register</a>
        @if($errors->any())<div role="alert" class="rounded-xl bg-red-500/10 p-4 text-red-300">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <section class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6 space-y-4">
            <h3 class="text-xl font-display">Update existing staff from a CSV</h3>
            <p class="text-sm text-brand-ash">Export the staff and fields you want to update, edit the CSV, then upload it here. Include Record ID, Staff ID or Email. If you include more than one identifier, they must all match the same person. New accounts are never created.</p>
            <p class="text-sm text-brand-ash">Blank cells keep the existing value. Dates must use YYYY-MM-DD. Save as UTF-8 CSV and keep phone, bank and ID numbers as text to preserve leading zeros. Maximum 1,000 rows and 5 MB. Review all changes before confirming.</p>
            <p class="text-sm text-brand-ash">Identifiers, access roles, job levels, account settings, uploaded files and historical records are read-only during import. Changes that alter privileged access are rejected.</p>
            <a href="{{ route('portal.hr.staff.import.template') }}" class="inline-block underline">Download blank import template</a>
            <details><summary class="cursor-pointer">Fields you can update</summary><p class="mt-3 text-sm text-brand-ash">{{ collect(array_keys($importer->rules()))->map(fn($field) => $record->label($field))->implode(', ') }}</p></details>
            <form method="POST" action="{{ route('portal.hr.staff.import.preview') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">@csrf<label class="text-sm">Staff updates CSV<input class="mt-2 block" type="file" name="file" accept=".csv,text/csv" required></label><button class="rounded-xl bg-brand-red px-5 py-3 font-semibold text-white">Upload and preview</button></form>
        </section>
        @if($preview)
            <section class="rounded-2xl border border-brand-white/10 p-6 space-y-4">
                <h3 class="text-xl font-display">Review before saving</h3>
                <p class="text-sm">{{ count($preview['rows']) }} staff matched. {{ collect($preview['rows'])->filter(fn($row) => count($row['updates']))->count() }} staff will change. No changes have been saved.</p>
                @if($preview['ignored'])<p class="text-sm text-amber-300">Read-only columns ignored: {{ collect($preview['ignored'])->map(fn($field) => $record->label($field))->implode(', ') }}.</p>@endif
                @if($preview['errors'])<div role="alert" class="rounded-xl bg-red-500/10 p-4 text-red-300"><p class="font-semibold">Fix these errors and upload the CSV again. Nothing can be saved until every row is valid.</p><ul class="mt-3 list-disc pl-5">@foreach($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @foreach($preview['rows'] as $row)
                    <details class="rounded-xl bg-brand-white/5 p-4" @if($row['updates']) open @endif><summary class="cursor-pointer">Row {{ $row['row'] }} · {{ $row['name'] }} · {{ count($row['updates']) }} field(s) changing</summary>
                        @if($row['updates'])<div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Field</th><th class="p-2">Current value</th><th class="p-2">New value</th></tr></thead><tbody>@foreach($row['updates'] as $field => $value)<tr class="border-t border-brand-white/10"><td class="p-2">{{ $record->label($field) }}</td><td class="p-2 break-words">{{ $row['before'][$field] !== '' ? $row['before'][$field] : 'Not recorded' }}</td><td class="p-2 break-words">{{ $row['after'][$field] }}</td></tr>@endforeach</tbody></table></div>@else<p class="mt-3 text-sm text-brand-ash">No changes needed.</p>@endif
                    </details>
                @endforeach
                @if(! $preview['errors'] && collect($preview['rows'])->contains(fn($row) => count($row['updates']) > 0))
                    <form method="POST" action="{{ route('portal.hr.staff.import.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $preview['token'] }}"><p class="mb-4 text-sm text-brand-ash">This preview expires after 30 minutes. If any staff record changes before confirmation, you will need to upload again.</p><button class="rounded-xl bg-brand-red px-5 py-3 font-semibold text-white">Confirm and save these updates</button></form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
