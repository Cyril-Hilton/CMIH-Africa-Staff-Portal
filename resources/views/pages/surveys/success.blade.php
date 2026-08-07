@extends('layouts.site')

@section('title', 'Thank You - ' . $survey->title . ' | CMIH Africa')

@section('content')
    <section class="section-padding relative overflow-hidden bg-brand-black min-h-[75vh] flex items-center">
        {{-- Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-20 -right-20 w-[600px] h-[600px] bg-brand-red/30 rounded-full blur-[130px] aurora-blend animate-pulse-slow"></div>
            <div class="absolute -bottom-20 -left-20 w-[500px] h-[500px] bg-brand-red-dark/40 rounded-full blur-[120px] aurora-blend opacity-75"></div>
            <div class="absolute inset-0 opacity-20 brightness-100 contrast-150 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-xl px-6 w-full py-10">
            <div class="glass-panel rounded-3xl p-8 border border-brand-white/10 bg-brand-black/60 shadow-2xl space-y-6">

                {{-- Dual Logo Header --}}
                @if($survey->cmih_logo_path || $survey->client_logo_path || $survey->client_logo_path_2)
                    <div class="flex items-center justify-between gap-4 pb-5 border-b border-brand-white/10">
                        {{-- CMIH Logo --}}
                        <div class="flex flex-col items-start gap-1">
                            @if($survey->cmih_logo_path)
                                <img src="{{ Storage::url($survey->cmih_logo_path) }}" alt="CMIH" class="h-10 w-auto object-contain max-w-[100px]">
                            @endif
                        </div>

                        @if($survey->client_logo_path || $survey->client_logo_path_2)
                            <div class="flex-1 border-t border-dashed border-brand-white/10"></div>
                            <div class="flex items-center gap-4">
                                @if($survey->client_logo_path)
                                    <div class="flex flex-col items-center gap-1">
                                        <img src="{{ Storage::url($survey->client_logo_path) }}" alt="{{ $survey->client_brand_name ?? 'Partner' }}" class="h-10 w-auto object-contain max-w-[90px]">
                                        @if($survey->client_brand_name)
                                            <span class="text-[8px] uppercase tracking-[0.2em] text-brand-white/40">{{ $survey->client_brand_name }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($survey->client_logo_path_2)
                                    <div class="flex flex-col items-center gap-1">
                                        <img src="{{ Storage::url($survey->client_logo_path_2) }}" alt="{{ $survey->client_brand_name_2 ?? 'Partner' }}" class="h-10 w-auto object-contain max-w-[90px]">
                                        @if($survey->client_brand_name_2)
                                            <span class="text-[8px] uppercase tracking-[0.2em] text-brand-white/40">{{ $survey->client_brand_name_2 }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Success Icon --}}
                <div class="flex justify-center">
                    <div class="h-20 w-20 rounded-full border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-center">
                        <svg class="w-9 h-9 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>

                {{-- ── MESSAGE: Custom or Default ─────────────────────── --}}
                @if($survey->success_message)
                    {{-- Custom message set by the creator --}}
                    <div class="space-y-3 text-center">
                        <p class="text-xs uppercase tracking-[0.3em] text-emerald-400 font-semibold">Submitted!</p>
                        <div class="text-sm text-brand-white/80 leading-relaxed text-left bg-brand-white/5 border border-brand-white/10 rounded-2xl p-5 whitespace-pre-line">
                            {!! nl2br(e($survey->success_message)) !!}
                        </div>
                    </div>
                @else
                    {{-- Default fallback message --}}
                    <div class="space-y-2 text-center">
                        <p class="text-xs uppercase tracking-[0.3em] text-emerald-400 font-semibold">Thank You!</p>
                        <h1 class="text-3xl font-display text-brand-white">Submission Received</h1>
                        <p class="text-sm text-brand-white/70">
                            Your responses have been successfully recorded. We appreciate your participation and will be in touch soon!
                        </p>
                    </div>
                @endif

                {{-- ── LOCATION CARD (if enabled) ─────────────────────── --}}
                @if($survey->location_enabled && ($survey->location_url || $survey->location_label))
                    <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-full bg-brand-red/10 border border-brand-red/20 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            @if($survey->location_label)
                                <p class="text-sm font-semibold text-brand-white truncate">{{ $survey->location_label }}</p>
                            @endif
                            @if($survey->location_url)
                                <a href="{{ $survey->location_url }}" target="_blank" rel="noopener"
                                   class="text-xs text-brand-red hover:text-brand-red-light underline underline-offset-2 transition mt-0.5 inline-block">
                                    📍 View on Google Maps →
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ── Linked Event Card ───────────────────────────────── --}}
                @if($survey->event)
                    <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-5 text-left space-y-3">
                        <p class="text-[10px] uppercase tracking-wider text-brand-ash">Associated Activation / Event</p>
                        <h3 class="text-base font-semibold text-brand-white leading-snug">{{ $survey->event->title }}</h3>
                        <div class="space-y-1.5 text-xs text-brand-white/70 font-mono">
                            <p class="flex items-center gap-2">
                                <span>📅</span>
                                <span>{{ $survey->event->starts_at->format('M d, Y') }}@if($survey->event->ends_at) — {{ $survey->event->ends_at->format('M d, Y') }}@endif</span>
                            </p>
                            @if($survey->event->location)
                                <p class="flex items-center gap-2">
                                    <span>📍</span>
                                    <span>{{ $survey->event->location }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Action --}}
                <div class="pt-2 text-center">
                    <a href="{{ route('home') }}" class="inline-block rounded-full bg-brand-white text-brand-black hover:bg-brand-white/90 px-8 py-3 text-xs font-semibold uppercase tracking-[0.2em] transition">
                        Back to Home
                    </a>
                </div>

            </div>
        </div>
    </section>
@endsection
