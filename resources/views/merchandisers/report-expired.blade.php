<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Expired — CMIH Africa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-black font-sans antialiased text-brand-white flex items-center justify-center min-h-screen p-4">

    <div class="glass-panel max-w-md w-full rounded-2xl p-8 border border-brand-white/10 text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-brand-red/10 border border-brand-red/20 flex items-center justify-center mx-auto text-brand-red text-3xl">
            ⏳
        </div>
        <div class="space-y-2">
            <h1 class="text-xl font-bold font-display text-brand-white">Link Expired</h1>
            <p class="text-xs text-brand-ash">The shared report link is no longer valid. For security and real-time accuracy, external client access links are limited to 24 hours.</p>
        </div>
        <div class="pt-4">
            <p class="text-[10px] text-brand-ash/60">Please request a new link from your CMIH Africa brand manager.</p>
        </div>
    </div>

</body>
</html>
