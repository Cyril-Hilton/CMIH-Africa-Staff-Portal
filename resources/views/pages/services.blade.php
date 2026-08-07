@extends('layouts.site')

@section('title', 'Services - CMIH Africa')
@section('description', 'Integrated marketing solutions bridging global strategy and local African impact.')

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

        <div class="mx-auto max-w-7xl px-6 relative z-10">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Services</p>
                <h1 class="text-4xl font-display text-brand-white">Integrated Solutions, Total Impact.</h1>
                <p class="text-sm text-brand-white/70">From insight to execution, we orchestrate campaigns that deliver measurable momentum across Africa.</p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @php
                    $servicesRaw = \App\Models\SiteContent::getValue('services.list', 
"Event Management|Turning vision into reality through flawless end-to-end execution.
Online & Social Media Marketing|Driving engagement, conversation, and growth in the digital space.
Management of Sponsored Events|Maximizing brand visibility through strategic event partnerships.
POP Deployment & Activations|Creating high-impact Point of Purchase moments that drive action.
Brand Management Channel|Orchestrating brand consistency across distribution channels.
Instore & Shopper Marketing|Influencing the moment of truth at the point of purchase.
Commercial Supply Chains Solutions|Seamless logistics and optimization for marketing collateral.
Campus Activations|Connecting with the youth demographic through authentic experiences.
Road Shows|Bringing the brand directly to regions with mobile experiences.
Town Storming|Hyper-local, high-intensity community engagement to dominate markets.
Street Level Promotion|Guerrilla-style marketing that captures attention in the urban landscape."
                    );

                    $services = collect(explode("\n", $servicesRaw))->map(function($line) {
                        $parts = explode('|', $line, 2);
                        return [
                            'title' => trim($parts[0]),
                            'detail' => isset($parts[1]) ? trim($parts[1]) : ''
                        ];
                    })->filter(fn($s) => !empty($s['title']));
                @endphp

                @foreach ($services as $service)
                    <article class="glass-panel rounded-2xl p-6 reveal">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Service</p>
                        <h3 class="mt-3 text-xl font-semibold text-brand-white">{{ $service['title'] }}</h3>
                        <p class="mt-3 text-sm text-brand-white/70">{{ $service['detail'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection

