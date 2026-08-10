@extends('layouts.site')

@push('head')
    @php
        $heroLight = \App\Models\SiteContent::getImageUrl('home.hero_image', asset('images/optimized/guinness-influencer-soiree-1a.jpg'));
    @endphp
    <link rel="preload" as="image" href="{{ \App\Models\SiteContent::getImageUrl('home.hero_image_dark', $heroLight) }}" fetchpriority="high">
@endpush

@section('title', 'CMIH Africa - We Make It Happen')
@section('description', 'Integrated marketing solutions that bridge the gap between global strategy and local African impact.')

@section('content')
    <section class="relative overflow-hidden bg-hero-grid">
        <div class="absolute inset-0">
            <div class="absolute -left-20 top-10 h-64 w-64 rounded-full bg-brand-red/30 blur-3xl animate-glow"></div>
            <div class="absolute right-0 top-32 h-72 w-72 rounded-full bg-brand-red-dark/30 blur-3xl animate-glow"></div>
            <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-brand-white/5 blur-3xl"></div>
        </div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 py-20 lg:grid-cols-[1.2fr_0.8fr] lg:py-28">
            <div class="space-y-8">
                <div class="reveal">
                    <p class="text-xs uppercase tracking-[0.4em] text-brand-ash">CMIH Africa</p>
                    <h1 class="mt-4 text-4xl font-display leading-[0.95] text-brand-white sm:text-5xl lg:text-6xl">
                        {{ \App\Models\SiteContent::getValue('home.hero_headline', 'Unlocking the Power of the African Market.') }}
                    </h1>
                    <p class="mt-4 text-sm text-brand-white/70 sm:text-base">
                        {{ \App\Models\SiteContent::getValue('home.hero_subheadline', 'Integrated marketing solutions that bridge the gap between global strategy and local impact. CMIH Africa: We Make It Happen.') }}
                    </p>
                </div>

                <div class="reveal flex flex-wrap gap-4 delay-150">
                    <a href="{{ route('contact') }}" class="inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-6 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-white shadow-lg shadow-brand-red/30">
                        Start a Project
                    </a>
                    <a href="{{ route('portfolio') }}" class="inline-flex items-center rounded-full border border-brand-white/20 px-6 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-brand-white/70 transition hover:text-brand-white">
                        View Portfolio
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-3 reveal delay-300">
                    <div class="glass-panel card-glow rounded-2xl p-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Coverage</p>
                        <p class="mt-2 text-lg font-semibold">Pan-African execution</p>
                    </div>
                    <div class="glass-panel card-glow rounded-2xl p-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Impact</p>
                        <p class="mt-2 text-lg font-semibold">Results-driven activations</p>
                    </div>
                    <div class="glass-panel card-glow rounded-2xl p-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Insight</p>
                        <p class="mt-2 text-lg font-semibold">Local intelligence + global standards</p>
                    </div>
                </div>
            </div>

            <div class="relative reveal delay-300">
                <div class="absolute -left-8 -top-10 h-24 w-24 rounded-full border border-brand-white/20"></div>
                <div class="absolute -bottom-10 right-6 h-16 w-16 rounded-full border border-brand-white/20"></div>
                <div class="glass-panel rounded-3xl p-4">
                    @php
                        $heroLight = \App\Models\SiteContent::getImageUrl('home.hero_image', asset('images/optimized/guinness-influencer-soiree-1a.jpg'));
                        $heroDark = \App\Models\SiteContent::getImageUrl('home.hero_image_dark', $heroLight);
                    @endphp
                    <img
                        src="{{ $heroDark }}"
                        data-theme-src-light="{{ $heroLight }}"
                        data-theme-src-dark="{{ $heroDark }}"
                        alt="CMIH Africa event activation"
                        class="h-full w-full rounded-2xl object-cover shadow-2xl"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                    />
                </div>
                <div class="absolute bottom-6 left-6 rounded-full bg-brand-white/10 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/80">
                    We Make It Happen
                </div>
            </div>
        </div>
    </section>

    <section class="relative bg-brand-black overflow-hidden py-20 lg:py-24">
        {{-- Aurora Background (Mesh Gradient) --}}
        {{-- Intense Red Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            {{-- Top Right - Reduced intensity for text contrast --}}
            <div class="absolute -top-20 -right-20 w-[900px] h-[900px] bg-brand-red/50 rounded-full blur-[130px] aurora-blend animate-pulse-slow"></div>
            {{-- Bottom Left - Reduced intensity --}}
            <div class="absolute -bottom-20 -left-20 w-[800px] h-[800px] bg-brand-red-dark/60 rounded-full blur-[120px] aurora-blend opacity-80"></div>
            {{-- Center Glow - Reduced intensity --}}
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-red/20 rounded-full blur-[150px] aurora-blend"></div>

            {{-- Noise Overlay for Texture --}}
            <div class="absolute inset-0 opacity-20 brightness-100 contrast-150 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
        </div>
        
        <div class="mx-auto max-w-7xl px-6 relative z-10">
            <div class="grid lg:grid-cols-[1.1fr_0.9fr] gap-12 sm:gap-20 items-center">
                
                {{-- Text Content --}}
                <div class="reveal">
                    <div class="inline-flex items-center gap-2 mb-8 px-4 py-1.5 rounded-full border border-brand-white/10 bg-brand-white/5 backdrop-blur-sm">
                        <span class="flex h-2 w-2 rounded-full bg-brand-red shadow-[0_0_10px_rgba(226,28,30,0.6)]"></span>
                        <span class="text-[0.65rem] uppercase tracking-[0.3em] font-semibold text-brand-white/90">Vision 2026</span>
                    </div>

                    <h2 class="font-display font-bold tracking-tighter text-brand-white">
                        <span class="block text-8xl sm:text-9xl lg:text-[6rem] leading-[0.85] text-transparent bg-clip-text bg-gradient-to-br from-brand-white via-brand-white to-brand-ash">BOLDER</span>
                        <div class="flex items-center gap-6 py-4">
                            <span class="text-7xl sm:text-7xl lg:text-7xl font-serif italic text-brand-red font-light">&</span>
                            <span class="text-8xl sm:text-9xl lg:text-[6rem] leading-[0.85] text-gradient-premium drop-shadow-2xl">BETTER</span>
                        </div>
                    </h2>
                    
                    <p class="mt-10 text-lg sm:text-xl text-brand-white/70 max-w-lg leading-relaxed font-light">
                        We are engineering the future of African marketing. <br class="hidden sm:block"> Precise strategy. Flawless execution. <span class="text-brand-white font-medium">Unmatched impact.</span>
                    </p>

                    <div class="mt-12 flex flex-wrap gap-4">
                         <a href="{{ route('contact') }}" class="group inline-flex items-center gap-3 px-10 py-5 rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark text-white text-xs font-bold uppercase tracking-[0.25em] transition hover:scale-105 hover:shadow-brand-red/20 shadow-lg">
                            Start Project
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                         </a>
                    </div>
                </div>

                {{-- Glass Prism Visual --}}
                <div class="relative hidden lg:flex items-center justify-center reveal delay-150 perspective-1000">
                     {{-- Floating stack effect --}}
                     <div class="relative w-72 h-64 group">
                        {{-- Back Card (Glow) --}}
                        <div class="absolute inset-0 bg-gradient-to-tr from-brand-red to-brand-red-dark rounded-3xl opacity-20 blur-2xl transform translate-x-4 translate-y-4 scale-95 transition-all duration-700 group-hover:scale-105 group-hover:opacity-30"></div>
                        
                        {{-- Main Glass Card --}}
                        <div class="absolute inset-0 bg-brand-charcoal/40 backdrop-blur-xl border border-brand-white/10 rounded-3xl shadow-2xl flex items-center justify-center p-8 overflow-hidden transform transition-all duration-700 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(0,0,0,0.5)]">
                            {{-- Internal lighting --}}
                            <div class="absolute inset-0 bg-gradient-to-br from-brand-white/10 to-transparent opacity-50 pointer-events-none"></div>
                            
                            {{-- Content inside Glass --}}
                            <div class="text-center relative z-10 space-y-4">
                                <div class="w-16 h-16 mx-auto rounded-xl bg-gradient-to-br from-brand-red to-brand-red-dark flex items-center justify-center shadow-lg shadow-brand-red/20 text-white">
                                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5L12 22l10-8.5-5-2.5-5 2.5z"/>
                                    </svg>
                                </div>
                                <div class="space-y-1">
                                    <div class="h-1 w-12 bg-brand-white/20 mx-auto rounded-full mb-3"></div>
                                    <p class="text-[0.55rem] uppercase tracking-[0.25em] text-brand-white/60">CMIH Standard</p>
                                    <p class="text-lg font-display font-medium text-brand-white">Excellence</p>
                                </div>
                            </div>
                        </div>

                        {{-- Floating Badge --}}
                        
                     </div>
                </div>

            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="space-y-2 reveal">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Upcoming Events</p>
                    <h2 class="text-3xl font-display text-brand-white">Join the Conversation.</h2>
                </div>
                <a href="{{ route('news') }}" class="reveal inline-flex items-center rounded-full border border-brand-white/20 px-5 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                    View All Events
                </a>
            </div>

            @php
                $fallbackEvents = [
                    ['title' => 'Brand Storytelling Masterclass', 'date' => 'Mar 12, 2026', 'image' => 'optimized/guinness-influencer-soiree-5b.jpg', 'summary' => 'Interactive sessions with field leaders, strategy architects, and brand storytellers.', 'location' => 'Accra, Ghana'],
                    ['title' => 'CMIH Strategy Summit', 'date' => 'Apr 08, 2026', 'image' => 'optimized/guinness-influencer-soiree-6b.jpg', 'summary' => 'Strategy deep-dives for leadership teams expanding across Africa.', 'location' => 'Nairobi, Kenya'],
                    ['title' => 'Digital Impact Workshop', 'date' => 'May 02, 2026', 'image' => 'optimized/guinness-influencer-soiree-2b.jpg', 'summary' => 'Hands-on digital growth tactics for modern brands.', 'location' => 'Lagos, Nigeria'],
                ];
            @endphp

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @if ($events->isEmpty())
                    @foreach ($fallbackEvents as $event)
                        <article class="glass-panel rounded-2xl overflow-hidden reveal hover-lift">
                            <img src="{{ asset('images/'.$event['image']) }}" alt="{{ $event['title'] }}" class="w-full aspect-[3/4] object-contain bg-brand-black/40" loading="lazy" />
                            <div class="p-5 space-y-3">
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $event['date'] }}</p>
                                <h3 class="text-lg font-semibold text-brand-white">{{ $event['title'] }}</h3>
                                <p class="text-sm text-brand-white/70">{{ $event['summary'] }}</p>
                                <p class="text-xs text-brand-white/60">{{ $event['location'] }}</p>
                                <a href="https://9yttrybe.com/" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:text-brand-white">
                                    Attend Event
                                </a>
                            </div>
                        </article>
                    @endforeach
                @else
                    @foreach ($events as $event)
                        @php
                            $fallbackImage = $fallbackEvents[$loop->index % count($fallbackEvents)]['image'];
                            $imageUrl = $event->image_path ? asset('storage/'.$event->image_path) : asset('images/'.$fallbackImage);
                        @endphp
                        <article class="glass-panel rounded-2xl overflow-hidden reveal hover-lift">
                            <img src="{{ $imageUrl }}" alt="{{ $event->title }}" class="w-full aspect-[3/4] object-contain bg-brand-black/40" loading="lazy" />
                            <div class="p-5 space-y-3">
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $event->starts_at->format('M d, Y') }}</p>
                                <h3 class="text-lg font-semibold text-brand-white">{{ $event->title }}</h3>
                                <div class="text-sm text-brand-white/70">{!! $event->summary ?? 'Upcoming CMIH engagement for marketing leaders.' !!}</div>
                                <p class="text-xs text-brand-white/60">{{ $event->location ?? 'Location TBD' }}</p>
                                @if ($event->registration_url)
                                    <a href="{{ $event->registration_url }}" target="_blank" rel="noreferrer" class="inline-flex text-xs uppercase tracking-[0.3em] text-brand-red">Register</a>
                                @endif
                                <a href="https://9yttrybe.com/" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:text-brand-white">
                                    Attend Event
                                </a>
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    <section class="section-padding bg-brand-red/5">
        <div class="mx-auto max-w-7xl px-6">
            <div class="space-y-2 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Brands We Work With</p>
                <h2 class="text-3xl font-display text-brand-white">Trusted by Ambitious Teams.</h2>
            </div>

            <div class="mt-10 grid grid-cols-2 gap-4 text-center sm:grid-cols-3 lg:grid-cols-6">
                @forelse ($brands as $brand)
                    @if(is_string($brand))
                         {{-- Fallback for existing string-based logic if any --}}
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-6 text-xs uppercase tracking-[0.3em] text-brand-white/50 transition hover:text-brand-white hover-lift">
                            {{ $brand }}
                        </div>
                    @else
                        {{-- Eloquent Model --}}
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-6 flex flex-col items-center justify-center gap-2 text-center transition hover:border-brand-red/30 hover:bg-brand-white/10 hover-lift group">
                            @if($brand->logo_path)
                                @php
                                    $lightLogo = $brand->logoUrl();
                                    $darkLogo = $brand->logoUrl('dark') ?: $lightLogo;
                                @endphp
                                <img
                                    src="{{ $lightLogo }}"
                                    data-theme-src-light="{{ $lightLogo }}"
                                    data-theme-src-dark="{{ $darkLogo }}"
                                    alt="{{ $brand->name }}"
                                    class="h-8 w-auto opacity-50 grayscale transition group-hover:opacity-100 group-hover:grayscale-0"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @endif
                            <span class="text-[0.6rem] uppercase tracking-[0.3em] text-brand-white/60 group-hover:text-brand-white">{{ $brand->name }}</span>
                        </div>
                    @endif
                @empty
                    {{-- Fallback if DB is empty --}}
                     @php
                        $fallbackBrands = ['Global Beverage Co.', 'Tech Nova', 'Nile Finance', 'Atlas Energy', 'Urban Pulse', 'Apex FMCG'];
                    @endphp
                    @foreach ($fallbackBrands as $fb)
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-6 text-xs uppercase tracking-[0.3em] text-brand-white/50 transition hover:text-brand-white hover-lift">
                            {{ $fb }}
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    <section class="section-padding bg-brand-red/10">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="space-y-3 reveal">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Customer Satisfaction</p>
                    <h2 class="text-3xl font-display text-brand-white">
                        {{ \App\Models\SiteContent::getValue('home.ratings_headline', 'Rated 4.9 by Enterprise Teams.') }}
                    </h2>
                    <p class="text-sm text-brand-white/70">
                        {{ \App\Models\SiteContent::getValue('home.ratings_subheadline', 'Client reviews highlight precision execution, local intelligence, and measurable performance.') }}
                    </p>
                </div>

                @php
                    $ratingScoreText = \App\Models\SiteContent::getValue('home.ratings_score', '4.9');
                    $ratingScoreValue = (float) preg_replace('/[^0-9.]/', '', $ratingScoreText);
                    $ratingScoreValue = $ratingScoreValue > 0 ? $ratingScoreValue : 4.9;
                    $ratingScoreDecimals = 0;
                    if (str_contains($ratingScoreText, '.')) {
                        $parts = explode('.', $ratingScoreText);
                        $ratingScoreDecimals = isset($parts[1]) ? min(2, strlen($parts[1])) : 1;
                    }

                    $ratingCountText = \App\Models\SiteContent::getValue('home.ratings_count', '150+ reviews');
                    preg_match('/\d+/', $ratingCountText, $ratingCountMatches);
                    $ratingCountValue = (int) ($ratingCountMatches[0] ?? 0);
                    $ratingCountSuffix = trim(preg_replace('/\d+/', '', $ratingCountText));
                @endphp

                <div class="glass-panel card-glow rounded-2xl p-5 reveal hover-lift">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Average Rating</p>
                    <div class="mt-3 flex items-center gap-4">
                        <p class="text-4xl font-display text-brand-white" data-count-target="{{ $ratingScoreValue }}" data-count-decimals="{{ $ratingScoreDecimals }}">
                            {{ $ratingScoreText }}
                        </p>
                        <div>
                            <div class="flex items-center gap-1 text-brand-red rating-stars">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M10 1.5l2.5 5.3 5.8.8-4.2 4.1 1 5.9L10 14.9 4.9 17.6l1-5.9L1.7 7.6l5.8-.8L10 1.5z"></path>
                                    </svg>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs uppercase tracking-[0.3em] text-brand-white/70" data-count-target="{{ $ratingCountValue }}" data-count-suffix="{{ $ratingCountSuffix }}">
                                {{ $ratingCountText }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $reviewsRaw = \App\Models\SiteContent::getValue('home.reviews',
                    "CMIH delivered our pan-African launch with flawless execution and local relevance.|Marketing Director|Global Beverage Co.\n" .
                    "Their field teams moved with speed, and the reporting kept every stakeholder aligned.|Head of Brand|Urban Pulse\n" .
                    "Strategic, responsive, and results driven. We saw measurable uplift across markets.|CMO|Tech Nova"
                );
                $reviews = collect(explode("\n", $reviewsRaw))->map(function($line) {
                    $parts = explode('|', $line, 3);
                    return [
                        'quote' => trim($parts[0]),
                        'author' => isset($parts[1]) ? trim($parts[1]) : 'Client',
                        'company' => isset($parts[2]) ? trim($parts[2]) : ''
                    ];
                })->filter(fn($r) => !empty($r['quote']));

                $metricsRaw = \App\Models\SiteContent::getValue('home.metrics',
                    "Satisfaction|96|%|Client satisfaction score\n" .
                    "On-Time Delivery|94|%|Campaigns delivered on schedule\n" .
                    "Engagement Lift|31|%|Average activation uplift"
                );
                $metrics = collect(explode("\n", $metricsRaw))->map(function($line) {
                    $parts = explode('|', $line, 4);
                    return [
                        'label' => trim($parts[0]),
                        'value' => isset($parts[1]) ? trim($parts[1]) : '0',
                        'suffix' => isset($parts[2]) ? trim($parts[2]) : '',
                        'detail' => isset($parts[3]) ? trim($parts[3]) : ''
                    ];
                })->filter(fn($m) => !empty($m['label']));
            @endphp

            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                @foreach ($reviews as $review)
                    <article class="glass-panel rounded-2xl border border-brand-red/20 p-5 reveal hover-lift">
                        <div class="flex items-center gap-1 text-brand-red">
                            @for ($i = 0; $i < 5; $i++)
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10 1.5l2.5 5.3 5.8.8-4.2 4.1 1 5.9L10 14.9 4.9 17.6l1-5.9L1.7 7.6l5.8-.8L10 1.5z"></path>
                                </svg>
                            @endfor
                        </div>
                        <p class="mt-3 text-sm text-brand-white/80">"{{ $review['quote'] }}"</p>
                        <p class="mt-4 text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $review['author'] }}</p>
                        <p class="text-xs text-brand-white/60">{{ $review['company'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach ($metrics as $metric)
                    <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 p-4 hover-lift">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $metric['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-brand-white" data-count-target="{{ $metric['value'] }}" data-count-suffix="{{ $metric['suffix'] }}" data-count-prefix="{{ $metric['prefix'] ?? '' }}">
                            {{ $metric['prefix'] ?? '' }}{{ $metric['value'] }}{{ $metric['suffix'] }}
                        </p>
                        <p class="text-xs text-brand-white/70">{{ $metric['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-[0.9fr_1.1fr]">
            @php
                $advantageTitle = \App\Models\SiteContent::getValue('home.advantage_title', 'The CMIH Advantage');
                $advantageCopy = \App\Models\SiteContent::getValue('home.advantage', 'A results-driven partner connecting strategy, execution, and measurable impact.');
                $advantageCta = \App\Models\SiteContent::getValue('home.advantage_cta_label', 'About CMIH');
            @endphp
            <div class="relative reveal">
                <div class="glass-panel rounded-3xl p-4">
                    @php
                        $advantageLight = \App\Models\SiteContent::getImageUrl('home.advantage_image', asset('images/optimized/guinness-influencer-soiree-3b.jpg'));
                        $advantageDark = \App\Models\SiteContent::getImageUrl('home.advantage_image_dark', $advantageLight);
                    @endphp
                    <img
                        src="{{ $advantageDark }}"
                        data-theme-src-light="{{ $advantageLight }}"
                        data-theme-src-dark="{{ $advantageDark }}"
                        alt="CMIH Advantage"
                        class="h-full w-full rounded-2xl object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <div class="absolute -right-6 bottom-6 rounded-full border border-brand-white/20 bg-brand-black/60 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/80">
                    {{ $advantageTitle }}
                </div>
            </div>

            <div class="space-y-6 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $advantageTitle }}</p>
                <h2 class="text-3xl font-display text-brand-white">{{ $advantageTitle }}</h2>
                <p class="text-sm text-brand-white/70">{{ $advantageCopy }}</p>
                <a href="{{ route('about') }}" class="inline-flex items-center rounded-full border border-brand-white/20 px-6 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-brand-white/70">
                    {{ $advantageCta }}
                </a>
            </div>
        </div>
    </section>
@endsection
