@extends('layouts.site')

@section('title', 'News & Insights - CMIH Africa')
@section('description', 'The African Perspective. Thought leadership, events, and market insights from CMIH Africa.')

@section('content')
    <section class="section-padding relative overflow-hidden bg-brand-black">
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

        <div class="relative z-10 mx-auto max-w-7xl px-6">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">News & Insights</p>
                <h1 class="text-4xl font-display text-brand-white">The African Perspective.</h1>
                <p class="text-sm text-brand-white/70">Thought leadership and market intelligence from the teams driving impact on the ground.</p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => 'Bridging Global Strategy with Local Insight', 'date' => 'Feb 18, 2026'],
                    ['title' => 'Scaling Experiential Marketing Across Five Regions', 'date' => 'Mar 03, 2026'],
                    ['title' => 'The New Playbook for African Brand Growth', 'date' => 'Mar 25, 2026'],
                ] as $post)
                    <article class="glass-panel rounded-2xl p-6 reveal">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $post['date'] }}</p>
                        <h3 class="mt-3 text-xl font-semibold text-brand-white">{{ $post['title'] }}</h3>
                        <p class="mt-3 text-sm text-brand-white/70">Curated insights on strategy, activation, and growth in the African market.</p>
                        <span class="mt-4 inline-flex text-xs uppercase tracking-[0.3em] text-brand-red">Read More</span>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="space-y-2 reveal">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Events Calendar</p>
                    <h2 class="text-3xl font-display text-brand-white">Upcoming & Past Events</h2>
                </div>
                <a href="{{ route('contact') }}" class="reveal inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-white">
                    Subscribe to Alerts
                </a>
            </div>

            <div class="mt-10 space-y-4">
                @forelse ($events as $event)
                    <div class="glass-panel rounded-2xl p-5 reveal">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $event->starts_at->format('M d, Y') }}@if ($event->ends_at) - {{ $event->ends_at->format('M d, Y') }}@endif</p>
                                <p class="mt-2 text-lg font-semibold text-brand-white">{{ $event->title }}</p>
                                <p class="text-sm text-brand-white/70">{{ $event->location ?? 'Location TBD' }}</p>
                            </div>
                            @if ($event->registration_url)
                                <a href="{{ $event->registration_url }}" target="_blank" rel="noreferrer" class="inline-flex rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">Register</a>
                            @else
                                <span class="inline-flex rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">View Details</span>
                            @endif
                        </div>
                        @if ($event->summary)
                            <div class="mt-3 text-sm text-brand-white/70">{!! $event->summary !!}</div>
                        @endif
                    </div>
                @empty
                    <div class="glass-panel rounded-2xl p-6 text-sm text-brand-white/60">
                        No events published yet.
                    </div>
                @endforelse
            </div>

            @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-6">
                    {{ $events->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
