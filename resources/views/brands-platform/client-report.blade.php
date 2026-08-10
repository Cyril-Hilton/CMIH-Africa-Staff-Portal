@extends('layouts.site')

@section('title', $brand->name.' Client Report')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Client Live Report</p>
                        <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">{{ $brand->name }}</h1>
                        <p class="mt-2 text-sm text-brand-white/60">{{ $activation->name }}</p>
                    </div>
                    @if($brand->logoUrl())
                        <img src="{{ $brand->logoUrl('dark') ?: $brand->logoUrl() }}" alt="{{ $brand->name }}" class="h-16 max-w-32 object-contain">
                    @endif
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach([
                        'Reach' => number_format($metrics['reached']),
                        'Target' => number_format($metrics['target']),
                        'Reach Rate' => $metrics['reach_rate'].'%',
                        'Verified' => $metrics['verification_rate'].'%',
                        'Conversions' => number_format($metrics['conversions']),
                    ] as $label => $value)
                        <div class="rounded-lg border border-brand-white/10 bg-brand-black/40 p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">High Intent</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $metrics['high_intent_rate'] }}%</p>
                    </div>
                    <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">New Audience</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $metrics['new_audience_rate'] }}%</p>
                    </div>
                    <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Marketing Consent</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $metrics['marketing_consent_rate'] }}%</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Recent Consumer Entries</p>
                        <div class="mt-4 space-y-3">
                            @forelse($activation->consumerEntries->sortByDesc('created_at')->take(8) as $entry)
                                <div class="rounded-md bg-brand-white/[0.04] px-3 py-2 text-xs text-brand-white/70">
                                    <strong class="text-brand-white">{{ $entry->location ?: 'Unknown location' }}</strong>
                                    <span class="text-brand-white/40"> - {{ $entry->result_type ?: 'Entry captured' }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40">No consumer entries have been captured yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Recent Field Updates</p>
                        <div class="mt-4 space-y-3">
                            @forelse($activation->fieldActivities->sortByDesc('created_at')->take(8) as $activity)
                                <div class="rounded-md bg-brand-white/[0.04] px-3 py-2 text-xs text-brand-white/70">
                                    <strong class="text-brand-white">{{ $activity->location ?: 'Unknown location' }}</strong>
                                    <span class="text-brand-white/40"> - {{ \Illuminate\Support\Str::headline($activity->activity_type) }} by {{ $activity->user?->name ?: 'field team' }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/40">No field updates have been captured yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-lg border border-brand-white/10 bg-brand-black/35 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Evidence Images For Visual Report</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse($reportImages as $activity)
                            <article class="overflow-hidden rounded-md border border-brand-white/10 bg-brand-white/[0.04]">
                                <img src="{{ \App\Http\Controllers\Brands\BrandsPlatformController::storageUrl($activity->evidence_path) }}" alt="{{ $brand->name }} field evidence" class="aspect-[4/3] w-full object-cover" loading="lazy">
                                <div class="p-3 text-xs text-brand-white/60">
                                    <p class="font-semibold text-brand-white">{{ $activity->location ?: 'No location' }}</p>
                                    <p>{{ \Illuminate\Support\Str::headline($activity->activity_type) }} - {{ $activity->created_at?->format('M d, H:i') }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="text-sm text-brand-white/40 sm:col-span-2 lg:col-span-3">No evidence images have been added to this activation yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
