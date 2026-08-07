<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="site-theme" content="{{ $site_theme ?? 'BOLDER and BETTER' }}">

        <title>{{ config('app.name', 'CMIH Africa') }} - Portal</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
        <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        @php
            $viteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
        @endphp
        @if ($viteReady)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                * { box-sizing: border-box; }
                body { margin: 0; font-family: 'Sora', sans-serif; background: #000; color: #fff; }
                a { color: inherit; text-decoration: none; }
            </style>
        @endif
    </head>
    <body class="bg-brand-black font-sans antialiased text-brand-white">
        <div class="min-h-screen bg-hero-grid">
            <div class="mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="flex flex-col justify-between gap-10">
                    <div class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                                <x-application-logo class="h-10 w-auto" />
                                <span class="text-xs uppercase tracking-[0.4em] text-brand-ash">Portal</span>
                            </a>
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
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm uppercase tracking-[0.4em] text-brand-ash">We Make It Happen</p>
                            <h1 class="text-4xl font-display leading-[0.9] text-brand-white sm:text-5xl">Inside the CMIH Operating Hub</h1>
                            <p class="text-sm text-brand-white/70 sm:text-base">Track campaigns, coordinate teams, and keep every market activation moving in sync. Built for speed, visibility, and accountability.</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="glass-panel rounded-2xl p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Progress</p>
                            <p class="mt-2 text-lg font-semibold">Live dashboards for every activation.</p>
                        </div>
                        <div class="glass-panel rounded-2xl p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Collaboration</p>
                            <p class="mt-2 text-lg font-semibold">Shared updates, tasks, and assets.</p>
                        </div>
                        <div class="glass-panel rounded-2xl p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Performance</p>
                            <p class="mt-2 text-lg font-semibold">Visibility into wins and risks.</p>
                        </div>
                        <div class="glass-panel rounded-2xl p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Governance</p>
                            <p class="mt-2 text-lg font-semibold">Admin control for approvals and roles.</p>
                        </div>
                    </div>
                </div>

                <div class="w-full">
                    <div class="rounded-2xl border border-brand-white/10 bg-brand-black/70 p-8 shadow-2xl">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>


