@extends('layouts.site')

@section('title', 'Legal Notice - CMIH Africa')
@section('description', 'Official legal notice and company information for CMIH Africa.')

@section('content')
    <section class="relative overflow-hidden section-padding bg-brand-black/70">
        <div class="absolute inset-0 opacity-40 bg-hero-grid"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Legal</p>
                <h1 class="text-4xl font-display text-brand-white">Legal Notice</h1>
                <p class="text-sm text-brand-white/70">
                    This notice provides company identification, ownership, and contact details
                    for CMIH Africa and its digital services.
                </p>
            </div>
            <div class="glass-panel rounded-3xl p-6 reveal hover-lift">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Head Office</p>
                <p class="mt-2 text-lg font-semibold text-brand-white">Accra, Ghana</p>
                <p class="mt-3 text-sm text-brand-white/70">No. 7 Afum Street, North Legon</p>
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ([
                    ['title' => 'Company Details', 'body' => 'CMIH Africa provides integrated marketing services, brand activation, and operational support for regional campaigns.'],
                    ['title' => 'Ownership', 'body' => 'All content, imagery, and brand assets on this site are owned by CMIH Africa unless otherwise stated.'],
                    ['title' => 'Contact', 'body' => 'Email: info@cmihgh.com | Phone: +233 542204282'],
                ] as $block)
                    <div class="glass-panel rounded-2xl p-6 reveal hover-lift">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $block['title'] }}</p>
                        <p class="mt-4 text-sm text-brand-white/70">{{ $block['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 glass-panel rounded-2xl p-6 reveal hover-lift">
                <h2 class="text-2xl font-display text-brand-white">Compliance</h2>
                <p class="mt-3 text-sm text-brand-white/70">
                    We follow applicable local regulations, data protection practices, and professional standards
                    across the markets we serve.
                </p>
            </div>
        </div>
    </section>
@endsection
