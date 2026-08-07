@extends('layouts.site')

@section('title', 'About CMIH Africa - We Make It Happen')
@section('description', 'African Roots. Global Standards. CMIH Africa provides deep cultural intelligence for market impact.')

@section('content')
    <section class="relative overflow-hidden section-padding bg-brand-black">
        {{-- Intense Red Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            {{-- Top Right - More intense --}}
            <div class="absolute -top-20 -right-20 w-[900px] h-[900px] bg-brand-red/70 rounded-full blur-[130px] aurora-blend animate-pulse-slow"></div>
            {{-- Bottom Left - More intense --}}
            <div class="absolute -bottom-20 -left-20 w-[800px] h-[800px] bg-brand-red-dark/80 rounded-full blur-[120px] aurora-blend opacity-90"></div>
            {{-- Center Glow - New --}}
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-red/30 rounded-full blur-[150px] aurora-blend"></div>
            
            <div class="absolute inset-0 opacity-20 brightness-100 contrast-150 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
        </div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[1fr_1fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">About CMIH Africa</p>
                <h1 class="text-4xl font-display text-brand-white">African Roots. Global Standards.</h1>
                <p class="text-sm text-brand-white/70">
                    {{ \App\Models\SiteContent::getValue('about.story', 'CMIH Africa provides deep cultural intelligence, ensuring messages are never "lost in translation."') }}
                </p>
            </div>
            <div class="glass-panel rounded-3xl p-4 reveal">
                @php
                    $coverLight = \App\Models\SiteContent::getImageUrl('about.cover_image', asset('images/optimized/guinness-influencer-soiree-2.jpg'));
                    $coverDark = \App\Models\SiteContent::getImageUrl('about.cover_image_dark', $coverLight);
                @endphp
                <img
                    src="{{ $coverDark }}"
                    data-theme-src-light="{{ $coverLight }}"
                    data-theme-src-dark="{{ $coverDark }}"
                    alt="CMIH Africa team"
                    class="h-full w-full rounded-2xl object-cover"
                    loading="lazy"
                    decoding="async"
                />
            </div>
        </div>
    </section>

    <section class="section-padding bg-brand-red/5">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[0.85fr_1.15fr]">
            <div class="glass-panel rounded-3xl p-4 reveal">
                @php
                    $cvoLight = \App\Models\SiteContent::getImageUrl('about.cvo_image', asset('images/optimized/guinness-influencer-soiree-2a.jpg'));
                    $cvoDark = \App\Models\SiteContent::getImageUrl('about.cvo_image_dark', $cvoLight);
                @endphp
                <img
                    src="{{ $cvoDark }}"
                    data-theme-src-light="{{ $cvoLight }}"
                    data-theme-src-dark="{{ $cvoDark }}"
                    alt="{{ \App\Models\SiteContent::getValue('about.cvo_name', 'Solomon Nanfa') }}"
                    class="h-full w-full rounded-2xl object-cover"
                    loading="lazy"
                    decoding="async"
                />
            </div>
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Leadership Spotlight</p>
                <h2 class="text-3xl font-display text-brand-white">
                    {{ \App\Models\SiteContent::getValue('about.cvo_name', 'Solomon Nanfa') }}
                </h2>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-red">
                    {{ \App\Models\SiteContent::getValue('about.cvo_title', 'Chief Visionary Officer (CVO)') }}
                </p>
                <p class="text-sm text-brand-white/70">
                    {{ \App\Models\SiteContent::getValue('about.cvo_bio', 'A pan-African marketing leader focused on building locally fluent strategies that deliver measurable impact across diverse markets.') }}
                </p>
                <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-brand-white/80">
                    {{ \App\Models\SiteContent::getValue('about.cvo_quote', '“We build momentum where global ambition meets local insight.”') }}
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto grid max-w-7xl gap-8 px-6 lg:grid-cols-3">
            <div class="glass-panel rounded-2xl p-6 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Our Mission</p>
                <h2 class="mt-4 text-2xl font-display text-brand-white">Empower Growth</h2>
                <p class="mt-3 text-sm text-brand-white/70">
                    {{ \App\Models\SiteContent::getValue('about.mission', 'Empower brands for sustainable growth in Africa.') }}
                </p>
            </div>
            <div class="glass-panel rounded-2xl p-6 reveal delay-150">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Our Vision</p>
                <h2 class="mt-4 text-2xl font-display text-brand-white">Lead the Renaissance</h2>
                <p class="mt-3 text-sm text-brand-white/70">
                    {{ \App\Models\SiteContent::getValue('about.vision', 'To be the most influential marketing catalyst for the African economic Renaissance.') }}
                </p>
            </div>
            <div class="glass-panel rounded-2xl p-6 reveal delay-300">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Our Promise</p>
                <h2 class="mt-4 text-2xl font-display text-brand-white">We Make It Happen</h2>
                <p class="mt-3 text-sm text-brand-white/70">A results-driven partner connecting strategy, execution, and measurable impact.</p>
            </div>
        </div>
    </section>

    <section class="section-padding bg-brand-black/70">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['label' => 'Authoritative', 'detail' => 'Strategic counsel backed by local intelligence.'],
                    ['label' => 'Innovative', 'detail' => 'Creative activations rooted in technology and culture.'],
                    ['label' => 'Results-Driven', 'detail' => 'Measurable impact at every touchpoint.'],
                ] as $value)
                    <div class="glass-panel rounded-2xl p-6 reveal">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $value['label'] }}</p>
                        <p class="mt-4 text-sm text-brand-white/70">{{ $value['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
