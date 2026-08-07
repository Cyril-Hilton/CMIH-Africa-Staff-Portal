@extends('layouts.site')

@section('title', 'Portfolio - CMIH Africa')
@section('description', 'Results That Speak for Themselves. Event-focused portfolio showcasing African market activations.')

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
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portfolio</p>
                <h1 class="text-4xl font-display text-brand-white">Results That Speak for Themselves.</h1>
                <p class="text-sm text-brand-white/70">Explore high-impact activations and event experiences across Africa.</p>
            </div>

            @if($albums->isEmpty())
                <div class="mt-10 rounded-2xl border border-brand-white/10 bg-brand-white/5 p-8 text-center">
                    <p class="text-brand-white/70">Our portfolio is currently being updated. Check back soon!</p>
                </div>
            @else
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($albums as $album)
                        <a
                            href="{{ route('portfolio.show', $album) }}"
                            class="group flex h-full flex-col overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5"
                        >
                            <div class="aspect-[4/3] overflow-hidden">
                                @if($album->cover_image)
                                    <img
                                        src="{{ asset('storage/' . $album->cover_image) }}"
                                        alt="{{ $album->title }}"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-brand-white/5">
                                        <span class="text-brand-white/20">No Cover</span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex h-full flex-col gap-2 p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $album->brand }}</p>
                                <h3 class="text-base font-semibold text-brand-white">{{ $album->title }}</h3>
                                @if($album->date)
                                    <p class="text-[10px] text-brand-white/40">{{ $album->date->format('M Y') }}</p>
                                @endif
                                <p class="mt-auto text-xs uppercase tracking-[0.3em] text-brand-white/60">View album</p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-8">
                    {{ $albums->links() }}
                </div>
            @endif
        </div>
    </section>

    <section class="section-padding">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="glass-panel rounded-2xl p-6 reveal">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Case Study</p>
                    <h2 class="mt-4 text-2xl font-display text-brand-white">Pan-African Product Launch</h2>
                    <p class="mt-3 text-sm text-brand-white/70">Client: Global Beverage Co. - Objective: Spark regional demand across five capitals.</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Reach</p>
                            <p class="mt-2 text-lg text-brand-white">2.4M</p>
                        </div>
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Engagement</p>
                            <p class="mt-2 text-lg text-brand-white">38%</p>
                        </div>
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Conversion</p>
                            <p class="mt-2 text-lg text-brand-white">+22%</p>
                        </div>
                    </div>
                </div>
                <div class="glass-panel rounded-2xl p-4 reveal">
                    @php
                        $portfolioLight = \App\Models\SiteContent::getImageUrl('portfolio.cover_image', asset('images/optimized/guinness-influencer-soiree-4a.jpg'));
                        $portfolioDark = \App\Models\SiteContent::getImageUrl('portfolio.cover_image_dark', $portfolioLight);
                    @endphp
                    <img
                        src="{{ $portfolioDark }}"
                        data-theme-src-light="{{ $portfolioLight }}"
                        data-theme-src-dark="{{ $portfolioDark }}"
                        alt="Portfolio highlight"
                        class="h-full w-full rounded-xl object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding relative overflow-hidden bg-brand-black border-t border-brand-white/10" id="make-payment">
        {{-- Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-24 -left-24 w-[700px] h-[700px] bg-brand-red/20 rounded-full blur-[130px] aurora-blend"></div>
            <div class="absolute -bottom-24 -right-24 w-[700px] h-[700px] bg-brand-red-dark/30 rounded-full blur-[130px] aurora-blend"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-4xl px-6">
            <div class="text-center max-w-2xl mx-auto space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Requests & Checkout</p>
                <h2 class="text-3xl font-display text-brand-white">Order a Service or Asset</h2>
                <p class="text-sm text-brand-white/70">
                    Need a custom brief, design mockup, or brand profile? Use the secure form below to initiate your order and pay instantly via Paystack.
                </p>
            </div>

            <div class="mt-12 glass-panel rounded-3xl p-8 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden reveal">
                
                @if(session('error'))
                    <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 p-4 text-xs text-brand-white flex items-start gap-3">
                        <svg class="h-5 w-5 text-brand-red shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs text-brand-white flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                {{-- Currency fallback notice — shown when submitted currency was not supported --}}
                @if(session('currency_notice'))
                    <div class="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-xs text-brand-white flex items-start gap-3">
                        <svg class="h-5 w-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('currency_notice') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('portfolio.pay') }}" class="grid gap-6 md:grid-cols-2">
                    @csrf

                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Full Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            value="{{ old('name') }}"
                            placeholder="e.g. Jane Doe"
                            class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required 
                            value="{{ old('email') }}"
                            placeholder="e.g. jane@example.com"
                            class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                        />
                        @error('email')
                            <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-data="{ selectedItem: '{{ old('item', '') }}' }" class="space-y-3">
                        <label for="item" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Requested Item / Service</label>
                        <select
                            id="item"
                            name="item"
                            x-model="selectedItem"
                            required
                            class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                        >
                            <option value="" disabled>Select an option</option>
                            <option value="company_profile" @selected(old('item') === 'company_profile')>Company Profile</option>
                            <option value="design_brief" @selected(old('item') === 'design_brief')>Design Brief</option>
                            <option value="mockup" @selected(old('item') === 'mockup')>Mockup</option>
                            <option value="buy_a_plan" @selected(old('item') === 'buy_a_plan')>Buy a Plan</option>
                            <option value="buy_a_catalogue" @selected(old('item') === 'buy_a_catalogue')>Buy a Catalogue</option>
                            <option value="other" @selected(old('item') === 'other')>Other (specify below)</option>
                        </select>

                        {{-- Custom item field — shown only when "other" is selected --}}
                        <div x-show="selectedItem === 'other'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display:none">
                            <label for="custom_item" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/50">
                                Please describe what you need
                            </label>
                            <input
                                type="text"
                                id="custom_item"
                                name="custom_item"
                                value="{{ old('custom_item') }}"
                                placeholder="e.g. Event Flyer Design, Video Production..."
                                :required="selectedItem === 'other'"
                                class="mt-2 w-full rounded-lg border border-brand-red/30 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-brand-red"
                            />
                            @error('custom_item')
                                <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                            @enderror
                        </div>

                        @error('item')
                            <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-1">
                            <label for="currency" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Currency</label>
                            <select 
                                id="currency" 
                                name="currency" 
                                required 
                                class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-3 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                            >
                                <option value="GHS" @selected(old('currency', $currency) === 'GHS')>GHS — Ghana Cedis (₵)</option>
                                <option value="NGN" @selected(old('currency', $currency) === 'NGN')>NGN — Nigerian Naira (₦)</option>
                                <option value="KES" @selected(old('currency', $currency) === 'KES')>KES — Kenyan Shilling (Ksh)</option>
                                <option value="ZAR" @selected(old('currency', $currency) === 'ZAR')>ZAR — South African Rand (R)</option>
                                <option value="USD" @selected(old('currency', $currency) === 'USD')>USD — US Dollar ($)</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label for="amount" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Amount</label>
                            <input 
                                type="number" 
                                id="amount" 
                                name="amount" 
                                step="0.01" 
                                min="0.01"
                                required 
                                value="{{ old('amount') }}"
                                placeholder="0.00"
                                class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                            />
                        </div>
                        @error('currency')
                            <div class="col-span-3">
                                <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('amount')
                            <div class="col-span-3">
                                <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Brief Description / Requirements</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="4" 
                            placeholder="Provide brief details about your design requirements or plans..."
                            class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-brand-red">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 mt-4">
                        <button 
                            type="submit" 
                            class="w-full flex justify-center items-center gap-3 rounded-full bg-brand-red px-6 py-4 text-xs font-semibold uppercase tracking-[0.25em] text-white hover:bg-brand-red/90 transition-all shadow-lg shadow-brand-red/20 font-display"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                            </svg>
                            Proceed to Payment
                        </button>
                        <p class="text-center mt-3 text-[10px] text-brand-white/40">
                            🔒 Secured by Paystack. Card, Mobile Money, and Bank Transfer accepted.
                        </p>
                    </div>

                </form>
            </div>
        </div>
    </section>
@endsection
