@php
    $navItems = [
        ['label' => 'Services', 'route' => 'services'],
        ['label' => 'Portfolio', 'route' => 'portfolio'],
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'News', 'route' => 'news'],
        ['label' => 'Contact Us', 'route' => 'contact'],
    ];
@endphp

<header class="sticky top-0 z-50 border-b border-brand-white/10 bg-brand-black/70 backdrop-blur">
    <div class="mx-auto flex items-center justify-between px-6 py-4 lg:px-10 max-w-7xl">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-application-logo class="h-9" />
        </a>

        <nav class="hidden lg:flex items-center gap-6 text-xs uppercase tracking-[0.3em] text-brand-ash">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" class="transition {{ request()->routeIs($item['route']) ? 'text-brand-white' : 'text-brand-ash hover:text-brand-white' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <button type="button" data-theme-toggle class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-white/20 text-brand-white/70 transition hover:text-brand-white" aria-pressed="false">
                <span class="sr-only">Toggle theme</span>
                <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4.5"></circle>
                    <path d="M12 2.5v2.5M12 19v2.5M4.5 12H2M22 12h-2.5M5.8 5.8l1.8 1.8M16.4 16.4l1.8 1.8M18.2 5.8l-1.8 1.8M7.6 16.4l-1.8 1.8"></path>
                </svg>
                <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z"></path>
                </svg>
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/30">
                    Staff Dashboard
                </a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/30">
                        Staff Login
                    </a>
                @endif
            @endauth
            <button type="button" data-menu-toggle class="inline-flex items-center gap-2 rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white lg:hidden" aria-expanded="false">
                Menu
            </button>
        </div>
    </div>

        <div data-menu-panel class="hidden border-t border-brand-white/10 bg-brand-black/90 lg:hidden">
        <div class="px-6 py-5 space-y-4 text-xs uppercase tracking-[0.3em] text-brand-ash">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" class="block transition {{ request()->routeIs($item['route']) ? 'text-brand-white' : 'text-brand-ash hover:text-brand-white' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/30">
                    Staff Dashboard
                </a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-5 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/30">
                        Staff Login
                    </a>
                @endif
            @endauth
        </div>
    </div>
</header>


