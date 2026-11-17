<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Appraisal Record</h1></x-slot>
    <div class="space-y-5">
        <section class="glass-panel rounded-2xl p-5">
            <a class="underline" href="{{ route('portal.appraisals.index') }}">Back to Appraisals</a>
            <h2 class="mt-3 text-lg font-semibold">{{ $appraisal->employee?->name ?? 'Staff record' }}</h2>
            <p>{{ $appraisal->quarter }} {{ $appraisal->year }} · {{ $appraisal->status_label }}</p>
        </section>
        @foreach(['Self assessment' => $appraisal->self_assessment, 'Manager review' => $appraisal->manager_review, 'Self assessment tables' => $appraisal->self_table_data, 'Manager review tables' => $appraisal->manager_table_data] as $label => $record)
            <section class="glass-panel space-y-3 rounded-2xl p-5">
                <h2 class="text-lg font-semibold">{{ $label }}</h2>
                @forelse($record ?? [] as $field => $value)
                    <div>
                        <h3 class="font-semibold">{{ \Illuminate\Support\Str::headline($field) }}</h3>
                        @if(is_array($value))
                            <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach($value as $key => $entry)
                                    <div><dt class="text-sm text-brand-ash">{{ $field === 'scores' ? ($metrics->firstWhere('id', $key)?->name ?? $key) : \Illuminate\Support\Str::headline($key) }}</dt><dd class="whitespace-pre-wrap">{{ is_array($entry) ? json_encode($entry, JSON_UNESCAPED_UNICODE) : $entry }}</dd></div>
                                @endforeach
                            </dl>
                        @else
                            <p class="whitespace-pre-wrap">{{ $value }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-brand-ash">No entry recorded.</p>
                @endforelse
            </section>
        @endforeach
    </div>
</x-app-layout>
