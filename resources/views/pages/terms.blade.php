@extends('layouts.site')

@section('title', 'Terms of Service - CMIH Africa')
@section('description', 'Terms governing use of the CMIH Africa website and portal.')

@section('content')
    <section class="relative overflow-hidden section-padding bg-brand-black/70">
        <div class="absolute inset-0 opacity-40 bg-hero-grid"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Legal</p>
                <h1 class="text-4xl font-display text-brand-white">Terms of Service</h1>
                <p class="text-sm text-brand-white/70">
                    By accessing the CMIH Africa website and portal, you agree to the terms below.
                    These guidelines help protect our clients, staff, and partners.
                </p>
            </div>
            <div class="glass-panel rounded-3xl p-6 reveal hover-lift">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Last Updated</p>
                <p class="mt-2 text-lg font-semibold text-brand-white">January 2026</p>
                <p class="mt-3 text-sm text-brand-white/70">Applies to all public and internal digital services.</p>
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => 'Account Access', 'body' => 'Portal access is granted to approved staff only. You are responsible for keeping credentials secure.'],
                    ['title' => 'Acceptable Use', 'body' => 'You agree not to misuse the portal, disrupt services, or attempt unauthorized access.'],
                    ['title' => 'Intellectual Property', 'body' => 'All content, brand assets, and strategy deliverables remain the property of CMIH Africa.'],
                ] as $item)
                    <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $item['title'] }}</p>
                        <p class="mt-4 text-sm text-brand-white/70">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                    <h2 class="text-2xl font-display text-brand-white">Service Availability</h2>
                    <p class="mt-3 text-sm text-brand-white/70">
                        We may update or pause services to improve performance or security. Notice will be provided
                        when possible.
                    </p>
                </div>
                <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                    <h2 class="text-2xl font-display text-brand-white">Termination</h2>
                    <p class="mt-3 text-sm text-brand-white/70">
                        CMIH Africa may suspend or terminate access for policy violations, security risks, or
                        operational requirements.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
