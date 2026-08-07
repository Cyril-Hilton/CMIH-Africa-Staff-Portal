@extends('layouts.site')

@section('title', 'Disclaimer - CMIH Africa')
@section('description', 'General disclaimers for content and services provided by CMIH Africa.')

@section('content')
    <section class="relative overflow-hidden section-padding bg-brand-black/70">
        <div class="absolute inset-0 opacity-40 bg-hero-grid"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Legal</p>
                <h1 class="text-4xl font-display text-brand-white">Disclaimer</h1>
                <p class="text-sm text-brand-white/70">
                    Information on this site is provided for general guidance. Results may vary based on market
                    conditions and client execution factors.
                </p>
            </div>
            <div class="glass-panel rounded-3xl p-6 reveal hover-lift">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Scope</p>
                <p class="mt-2 text-lg font-semibold text-brand-white">Public Site + Portal</p>
                <p class="mt-3 text-sm text-brand-white/70">Applies to marketing updates, assets, and events.</p>
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => 'No Guarantees', 'body' => 'Performance projections are not guarantees. Campaign results depend on market variables and partner execution.'],
                    ['title' => 'Third Party Links', 'body' => 'External links are provided for convenience. CMIH Africa is not responsible for third-party content.'],
                    ['title' => 'Operational Changes', 'body' => 'Services, schedules, and assets may change due to operational requirements.'],
                ] as $item)
                    <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $item['title'] }}</p>
                        <p class="mt-4 text-sm text-brand-white/70">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 glass-panel rounded-2xl p-6 reveal hover-lift">
                <h2 class="text-2xl font-display text-brand-white">Questions?</h2>
                <p class="mt-3 text-sm text-brand-white/70">
                    Reach our compliance team at <a href="mailto:info@cmihgh.com" class="text-brand-white underline decoration-brand-red/60">info@cmihgh.com</a>
                    for clarifications.
                </p>
            </div>
        </div>
    </section>
@endsection
