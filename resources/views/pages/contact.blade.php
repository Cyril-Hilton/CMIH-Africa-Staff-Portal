@extends('layouts.site')

@section('title', 'Contact CMIH Africa - We Make It Happen')
@section('description', "Let's grow together. Reach out to CMIH Africa to start a conversation.")

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

        <div class="relative z-10 mx-auto grid max-w-7xl gap-10 px-6 lg:grid-cols-[1fr_1fr]">
            <div class="space-y-4 reveal">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Contact Us</p>
                <h1 class="text-4xl font-display text-brand-white">Let's Grow Together.</h1>
                <p class="text-sm text-brand-white/70">Tell us about your goals, and we'll design the activation that makes it happen.</p>

                <div class="space-y-4 text-sm text-brand-white/70">
                    <div class="space-y-2">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Ghana Office</p>
                        <p><span class="text-brand-ash">Email:</span> {{ \App\Models\SiteContent::getValue('contact.email', 'info@cmihgh.com') }}</p>
                        <p><span class="text-brand-ash">Phone:</span> {{ \App\Models\SiteContent::getValue('contact.phone', '+233 542204282') }}</p>
                        <p><span class="text-brand-ash">Location:</span> {{ \App\Models\SiteContent::getValue('contact.address', 'No. 7 Afum Street, North Legon. Accra - Ghana') }}</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Nigeria Office</p>
                        <p><span class="text-brand-ash">Office:</span> {{ \App\Models\SiteContent::getValue('contact.nigeria_name', 'CONCEPTS MAKE IT HAPPEN LTD, NIGERIA') }}</p>
                        <p><span class="text-brand-ash">Phone:</span> {{ \App\Models\SiteContent::getValue('contact.nigeria_phone', '+234 8065776473') }}</p>
                        <p><span class="text-brand-ash">Location:</span> {{ \App\Models\SiteContent::getValue('contact.nigeria_address', '25, Ajanaku Street, Awuse Estates, Opebi Ikeja, Lagos, Nigeria.') }}</p>
                    </div>
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-6 reveal">
                <form class="space-y-5">
                    <div>
                        <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">Name</label>
                        <input type="text" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Full name" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">Company</label>
                        <input type="text" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Company name" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">Industry</label>
                        <input type="text" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Industry" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">Service of Interest</label>
                        <select class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option>Event Management</option>
                            <option>Online & Social Media Marketing</option>
                            <option>Management of Sponsored Events</option>
                            <option>POP Deployment & Activations</option>
                            <option>Brand Management Channel</option>
                            <option>Instore & Shopper Marketing</option>
                            <option>Commercial Supply Chains Solutions</option>
                            <option>Campus Activations</option>
                            <option>Road Shows</option>
                            <option>Town Storming</option>
                            <option>Street Level Promotion</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">Message</label>
                        <textarea rows="4" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Tell us about your project"></textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-6 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-white">
                        Submit Request
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
