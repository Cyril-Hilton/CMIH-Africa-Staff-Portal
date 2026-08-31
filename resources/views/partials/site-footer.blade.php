<footer class="border-t border-brand-white/10 bg-brand-black/80">
    <div class="mx-auto grid gap-10 px-6 py-12 lg:px-10 md:grid-cols-4 max-w-7xl">
        <div class="space-y-4">
            <p class="text-sm uppercase tracking-[0.4em] text-brand-ash">CMIH Africa</p>
            <p class="text-lg font-display text-brand-white">We Make It Happen.</p>
            <p class="text-sm text-brand-white/70">Integrated marketing solutions bridging global strategy and local African impact.</p>
        </div>

        <div class="space-y-4 text-sm text-brand-white/70">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Contact</p>
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Ghana Office</p>
                <p>Email: <a href="mailto:{{ \App\Models\SiteContent::getValue('contact.email', 'info@cmihgh.com') }}" class="text-brand-white hover:text-brand-ash">{{ \App\Models\SiteContent::getValue('contact.email', 'info@cmihgh.com') }}</a></p>
                <p>Phone: <a href="tel:{{ preg_replace('/\s+/', '', \App\Models\SiteContent::getValue('contact.phone', '+233 542204282')) }}" class="text-brand-white hover:text-brand-ash">{{ \App\Models\SiteContent::getValue('contact.phone', '+233 542204282') }}</a></p>
                <p>Location: {{ \App\Models\SiteContent::getValue('contact.address', 'No. 7 Afum Street, North Legon. Accra - Ghana') }}</p>
            </div>
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Nigeria Office</p>
                <p>{{ \App\Models\SiteContent::getValue('contact.nigeria_name', 'CONCEPTS MAKE IT HAPPEN LTD, NIGERIA') }}</p>
                <p>Phone: <a href="tel:{{ preg_replace('/\s+/', '', \App\Models\SiteContent::getValue('contact.nigeria_phone', '+234 8065776473')) }}" class="text-brand-white hover:text-brand-ash">{{ \App\Models\SiteContent::getValue('contact.nigeria_phone', '+234 8065776473') }}</a></p>
                <p>Location: {{ \App\Models\SiteContent::getValue('contact.nigeria_address', '25, Ajanaku Street, Awuse Estates, Opebi Ikeja, Lagos, Nigeria.') }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Quick Links</p>
            <div class="space-y-2 text-sm text-brand-white/70">
                <a href="{{ route('services') }}" class="block hover:text-brand-white">Services</a>
                <a href="{{ route('portfolio') }}" class="block hover:text-brand-white">Portfolio</a>
                <a href="{{ route('about') }}" class="block hover:text-brand-white">About</a>
                <a href="{{ route('news') }}" class="block hover:text-brand-white">News</a>
                <a href="{{ route('contact') }}" class="block hover:text-brand-white">Contact</a>
                <a href="{{ Route::has('login') ? route('login') : '#' }}" class="block hover:text-brand-white">Internal Portal Login</a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Resources</p>
                <a href="https://drive.google.com/file/d/1gPAhRytk3oYYn6avE4dZBK9P4hhmaSu0/view?usp=drive_link" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 text-sm text-brand-white hover:text-brand-ash">
                    Download Company Profile (PDF)
                </a>
            </div>

            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Legal</p>
                <div class="space-y-2 text-sm text-brand-white/70">
                    <a href="{{ route('privacy') }}" class="block hover:text-brand-white">Privacy Policy</a>
                    <a href="{{ route('terms') }}" class="block hover:text-brand-white">Terms of Service</a>
                    <a href="{{ route('legal') }}" class="block hover:text-brand-white">Legal Notice</a>
                    <a href="{{ route('disclaimer') }}" class="block hover:text-brand-white">Disclaimer</a>
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Follow</p>
                <div class="flex items-center gap-3">
                    <a href="https://www.linkedin.com/company/concepts-make-it-happen" target="_blank" rel="noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-white/20 text-xs font-semibold uppercase text-brand-white hover:border-brand-white/60" aria-label="LinkedIn">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                            <rect x="2" y="9" width="4" height="12"></rect>
                            <circle cx="4" cy="4" r="2"></circle>
                        </svg>
                    </a>
                    <a href="https://www.instagram.com/cmih_africa/?hl=en" target="_blank" rel="noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-white/20 text-xs font-semibold uppercase text-brand-white hover:border-brand-white/60" aria-label="Instagram">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37a4 4 0 1 1-2.63-2.63"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <a href="https://www.facebook.com/cmih.ghana/" target="_blank" rel="noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-white/20 text-xs font-semibold uppercase text-brand-white hover:border-brand-white/60" aria-label="Facebook">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                    </a>
                    <a href="https://www.tiktok.com/@cmih.africa" target="_blank" rel="noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-white/20 text-xs font-semibold uppercase text-brand-white hover:border-brand-white/60" aria-label="TikTok">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M16.7 3c.5 2 2.1 3.6 4.1 4.1v3.2c-1.5 0-2.9-.5-4.1-1.3v6.1a5.8 5.8 0 1 1-4.9-5.7v3.1a2.6 2.6 0 1 0 1.8 2.5V3h3.1z"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-brand-white/10">
        <div class="mx-auto flex flex-col gap-4 px-6 py-6 text-xs uppercase tracking-[0.3em] text-brand-white/60 md:flex-row md:items-center md:justify-between lg:px-10 max-w-7xl">
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('privacy') }}" class="hover:text-brand-white">Privacy Policy</a>
                <a href="{{ route('legal') }}" class="hover:text-brand-white">Legal Notice</a>
                <a href="{{ route('disclaimer') }}" class="hover:text-brand-white">Disclaimer</a>
                <a href="{{ route('terms') }}" class="hover:text-brand-white">Terms</a>
                <a href="{{ Route::has('login') ? route('login') : '#' }}" class="hover:text-brand-white">Internal Portal Login</a>
                <a href="#" class="hover:text-brand-white">Career Opportunities</a>
            </div>
            <p class="text-[0.65rem]">&copy; 2026 CMIH Africa. All Rights Reserved.</p>
        </div>
    </div>
</footer>
