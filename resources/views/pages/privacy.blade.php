@extends('layouts.site')

@section('title', 'Privacy Policy - CMIH Africa')
@section('description', 'How CMIH Africa collects, uses, and protects your information.')

@section('content')
    <section class="relative overflow-hidden section-padding bg-brand-black/70">
        <div class="absolute inset-0 opacity-40 bg-hero-grid"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Legal</p>
                <h1 class="text-4xl font-display text-brand-white">Privacy Policy</h1>
                <p class="text-sm text-brand-white/70">
                    CMIH Africa is committed to protecting the personal data of our clients, partners, and staff.
                    This policy explains what we collect, why we collect it, and how we keep it secure.
                </p>
            </div>
            <div class="glass-panel rounded-3xl p-6 reveal hover-lift">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Effective</p>
                <p class="mt-2 text-lg font-semibold text-brand-white">January 2026</p>
                <p class="mt-3 text-sm text-brand-white/70">Applies to cmih.africa, the portal, and related services.</p>
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => 'Data We Collect', 'items' => ['Contact details and business info', 'Portal account details and logins', 'Event registrations and RSVPs']],
                    ['title' => 'Why We Collect It', 'items' => ['Deliver services and project updates', 'Provide portal access and security', 'Improve performance and reporting']],
                    ['title' => 'How We Protect It', 'items' => ['Access control and secure storage', 'Limited internal access', 'Routine audits and monitoring']],
                ] as $block)
                    <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $block['title'] }}</p>
                        <ul class="mt-4 space-y-2 text-sm text-brand-white/70">
                            @foreach ($block['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                    <h2 class="text-2xl font-display text-brand-white">Your Rights</h2>
                    <p class="mt-3 text-sm text-brand-white/70">
                        You may request access, correction, or deletion of your information. Requests are handled
                        by our operations team within standard response windows.
                    </p>
                </div>
                <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                    <h2 class="text-2xl font-display text-brand-white">Contact</h2>
                    <p class="mt-3 text-sm text-brand-white/70">
                        For privacy questions, email <a href="mailto:info@cmihgh.com" class="text-brand-white underline decoration-brand-red/60">info@cmihgh.com</a>
                        or call +233 542204282.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
