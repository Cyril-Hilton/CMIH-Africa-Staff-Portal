<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="site-theme" content="{{ $site_theme ?? 'BOLDER and BETTER' }}">

        <title>@yield('title', 'CMIH Africa - We Make It Happen')</title>
        <meta name="description" content="@yield('description', 'Integrated marketing solutions that bridge the gap between global strategy and local African impact.')">
        <meta property="og:title" content="@yield('title', 'CMIH Africa - We Make It Happen')">
        <meta property="og:description" content="@yield('description', 'Integrated marketing solutions that bridge the gap between global strategy and local African impact.')">
        <meta property="og:type" content="website">
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
        @stack('head')

        @if (config('services.ga4.id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga4.id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{{ config('services.ga4.id') }}');
            </script>
        @endif
    </head>
    <body class="bg-brand-black text-brand-white font-sans antialiased">
        <div class="min-h-screen flex flex-col bg-inked">
            @include('partials.site-header')

            <main class="flex-1">
                @yield('content')
            </main>

            @include('partials.site-footer')
        </div>
        @stack('scripts')
        <x-notification />
    </body>
</html>
