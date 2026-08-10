<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    @php
        $activeAdminTab = $activeTab ?? request('tab', 'overview');
        $adminTabUrl = fn (string $tab, array $params = []) => route('merchandisers.admin.tab', array_merge(['adminTab' => $tab], $params));
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Merchandiser Admin Hub — CMIH Africa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(in_array($activeAdminTab, ['overview', 'routes'], true))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endif
    @if($activeAdminTab === 'tracking')
        <script>
            function initGoogleMaps() { window._googleMapsReady = true; window.dispatchEvent(new Event('google-maps-ready')); }
        </script>
        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initGoogleMaps" async defer></script>
    @endif
    <style>
        #admin-map { height: 540px; width: 100%; border-radius: 1rem; overflow: hidden; }
        [x-cloak] { display: none !important; }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,0.4); }
        .nav-item { transition: all 0.18s ease; }
        .nav-item.active { background: linear-gradient(135deg, rgba(220,38,38,0.2), rgba(220,38,38,0.08)); border-left: 3px solid #dc2626; color: #fff; }
        .nav-item:not(.active):hover { background: rgba(255,255,255,0.05); }
        .kpi-glow-red   { box-shadow: 0 0 20px rgba(220,38,38,0.15); }
        .kpi-glow-green { box-shadow: 0 0 20px rgba(34,197,94,0.15); }
        .kpi-glow-blue  { box-shadow: 0 0 20px rgba(59,130,246,0.15); }
        .kpi-glow-amber { box-shadow: 0 0 20px rgba(245,158,11,0.15); }
        .status-pill-active   { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }
        .status-pill-pending  { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.25); }
        .status-pill-suspended{ background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
        table { border-collapse: separate; border-spacing: 0; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.04); }
        .modal-overlay { backdrop-filter: blur(6px); }
        main > [x-show] { width: 100%; min-width: 0; max-width: 100%; }
        main .glass-panel { max-width: 100%; }
        main .overflow-x-auto { max-width: 100%; }
        @media (max-width: 640px) {
            #admin-map { height: 420px; border-radius: 0.75rem; }
            main { padding-left: 1rem !important; padding-right: 1rem !important; }
        }
        /* Desktop sidebars stay fixed to viewport height while main content scrolls independently. */
        @media (min-width: 1024px) {
            aside { position: static !important; transform: none !important; }
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-brand-black font-sans antialiased text-brand-white">

<div class="h-screen overflow-hidden bg-inked" x-data="{
    sidebarOpen: false,
    activeTab: @js($activeAdminTab),
    kdModalOpen: false,
    outletModalOpen: false,
    selectedKd: null,
    merch_search: '',
    merch_filter: 'all',
    reassignModal: false,
    selectedMerch: null,
    payrollModal: false,
    payrollMerch: null,
    notifTab: 'leaves',
    toast: { show: false, message: '', type: 'success' },
    toastTimer: null,
    showToast(message, type = 'success') {
        clearTimeout(this.toastTimer);
        this.toast = { show: true, message, type };
        this.toastTimer = setTimeout(() => this.toast.show = false, 3000);
    },
    copyShareLink(url) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url)
                .then(() => this.showToast('Share link copied to clipboard.'))
                .catch(() => this.fallbackCopyShareLink(url));
            return;
        }

        this.fallbackCopyShareLink(url);
    },
    fallbackCopyShareLink(url) {
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            this.showToast('Share link copied to clipboard.');
        } catch (error) {
            this.showToast('Copy failed. Please select and copy the link manually.', 'error');
        } finally {
            document.body.removeChild(textarea);
        }
    }
}"
     @keydown.escape.window="sidebarOpen = false"
     x-effect="document.body.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024)">

    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed right-4 top-4 z-[80] w-[min(92vw,24rem)] rounded-2xl border px-4 py-3 shadow-2xl backdrop-blur-xl"
         :class="toast.type === 'error' ? 'border-red-400/30 bg-red-950/90 text-red-100' : 'border-emerald-400/30 bg-emerald-950/90 text-emerald-100'">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 text-base" x-text="toast.type === 'error' ? '⚠️' : '✅'"></span>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] opacity-70" x-text="toast.type === 'error' ? 'Action needed' : 'Success'"></p>
                <p class="mt-1 text-sm font-semibold leading-snug" x-text="toast.message"></p>
            </div>
            <button type="button" @click="toast.show = false" class="ml-auto shrink-0 text-white/50 hover:text-white">×</button>
        </div>
    </div>

    <!-- ── Layout Shell ──────────────────────────────────────────────────── -->
    <div class="flex h-full min-h-0 w-full overflow-hidden">

        <!-- Mobile overlay backdrop -->
        <div x-show="sidebarOpen" x-cloak
             class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
             @click="sidebarOpen = false"></div>

        <!-- Sidebar (desktop: always visible static; mobile: slides in/out) -->
        <aside id="merchandiser-admin-sidebar"
            aria-label="Merchandiser admin navigation"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 flex h-full max-h-screen min-h-0 w-72 shrink-0 flex-col
                   border-r border-brand-white/10 bg-brand-black/98 backdrop-blur-xl
                   overflow-y-auto overscroll-contain scrollbar-none transition-transform duration-300 ease-in-out
                   lg:static lg:h-screen lg:translate-x-0">

            <!-- Logo -->
            <div class="flex items-center justify-between px-6 py-6 border-b border-brand-white/10">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-7 w-auto" />
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash font-semibold">Admin Hub</p>
                        <p class="text-xs text-brand-white font-bold">Merchandiser Portal</p>
                    </div>
                </div>
                <button type="button" @click="sidebarOpen = false" class="lg:hidden text-brand-white/50 hover:text-brand-white p-1">✕</button>
            </div>

            <!-- Admin Badge -->
            <div class="mx-4 mt-4 px-4 py-3 rounded-xl bg-brand-red/10 border border-brand-red/20 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-red/20 flex items-center justify-center text-brand-red text-sm font-bold shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-brand-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-brand-red uppercase tracking-wider font-bold">{{ auth()->user()->access_role === 'super_admin' ? 'Super Admin' : 'Admin' }}</p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="mt-5 px-3 space-y-1 flex-1">
                <button @click="window.location.href = @js($adminTabUrl('overview')); sidebarOpen = false"
                    :class="activeTab === 'overview' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">🏠</span>
                    <span>Dashboard</span>
                    @php $totalPending = $pendingLeaves + $pendingClaims + $pendingLoans; @endphp
                    @if($totalPending > 0)
                        <span class="ml-auto bg-brand-red text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $totalPending }}</span>
                    @endif
                </button>

                <button @click="window.location.href = @js($adminTabUrl('tracking')); sidebarOpen = false"
                    :class="activeTab === 'tracking' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">🗺️</span>
                    <span>Live Tracking</span>
                    <span class="ml-auto text-[10px] text-brand-ash">{{ $liveLocationCount }} live</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('kds')); sidebarOpen = false"
                    :class="activeTab === 'kds' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">🏢</span>
                    <span>Manage Key Distributors</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('routes')); sidebarOpen = false"
                    :class="activeTab === 'routes' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">RP</span>
                    <span>Route Planning</span>
                    <span class="ml-auto text-[10px] text-brand-ash">{{ $routeSummary['pending'] }}</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('skus')); sidebarOpen = false"
                    :class="activeTab === 'skus' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">📦</span>
                    <span>SKU AI Catalog</span>
                    <span class="ml-auto text-[10px] text-brand-ash">{{ $skuReferenceCount }}/{{ $skuCount }}</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('forms')); sidebarOpen = false"
                    :class="activeTab === 'forms' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">FP</span>
                    <span>Forms & Planograms</span>
                    <span class="ml-auto text-[10px] text-brand-ash">{{ $googleFormsCount }}/{{ $planogramsCount }}</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('merchandisers')); sidebarOpen = false"
                    :class="activeTab === 'merchandisers' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">👤</span>
                    <span>Manage Merchandisers</span>
                    @if($pendingMerchandisers > 0)
                        <span class="ml-auto bg-amber-500 text-black text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingMerchandisers }}</span>
                    @endif
                </button>

                <button @click="window.location.href = @js($adminTabUrl('supervisors')); sidebarOpen = false"
                    :class="activeTab === 'supervisors' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">🧭</span>
                    <span>Supervisors / PJP</span>
                    <span class="ml-auto text-[10px] text-brand-ash">{{ $supervisorCount }}</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('assets')); sidebarOpen = false"
                    :class="activeTab === 'assets' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">📁</span>
                    <span>Asset Management</span>
                </button>

                <button @click="window.location.href = @js($adminTabUrl('notifications')); sidebarOpen = false"
                    :class="activeTab === 'notifications' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">🔔</span>
                    <span>Notifications</span>
                    @if($totalPending > 0)
                        <span class="ml-auto bg-brand-red text-white text-[10px] font-bold px-2 py-0.5 rounded-full animate-pulse">{{ $totalPending }}</span>
                    @endif
                </button>

                <button @click="window.location.href = @js($adminTabUrl('settings')); sidebarOpen = false"
                    :class="activeTab === 'settings' ? 'active' : ''"
                    class="nav-item w-full text-left px-4 py-3 rounded-xl flex items-center gap-3 text-sm text-brand-white/70 font-medium">
                    <span class="text-lg w-6 text-center">⏱️</span>
                    <span>Clock Settings</span>
                </button>
            </nav>

            <!-- Logout -->
            <div class="px-4 py-5 border-t border-brand-white/10 mt-auto">
                <form method="POST" action="{{ route('merchandisers.logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm text-brand-white/50 hover:text-brand-red hover:bg-brand-red/10 transition-all font-medium">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Log Out</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- ── Main Content ───────────────────────────────────────────────── -->
        <div class="flex min-h-0 flex-1 flex-col min-w-0">

            <!-- Top Header Bar -->
            <header class="border-b border-brand-white/10 bg-brand-black/70 backdrop-blur-md px-6 py-4 sticky top-0 z-40">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" @click.stop="sidebarOpen = true"
                            :aria-expanded="sidebarOpen.toString()"
                            aria-controls="merchandiser-admin-sidebar"
                            aria-label="Open navigation menu"
                            class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-xl border border-brand-white/20 text-brand-white/70 hover:text-brand-white transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] font-semibold text-brand-ash hidden sm:block">Merchandiser Admin Hub</p>
                            <p class="text-base font-display text-brand-white" x-text="{
                                overview: '🏠 Dashboard Overview',
                                tracking: '🗺️ Live Field Tracking',
                                kds: '🏢 Key Distributors',
                                routes: 'Route Planning',
                                skus: 'SKU AI Catalog',
                                merchandisers: '👤 Merchandiser Management',
                                supervisors: '🧭 Supervisor / PJP Accountability',
                                forms: 'Forms & Planograms',
                                assets: '📁 Asset Management',
                                notifications: '🔔 Notifications & Approvals',
                                settings: '⏱️ Clock Window Settings'
                            }[activeTab]"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Pending badge -->
                        @if($totalPending > 0)
                        <button @click="window.location.href = @js($adminTabUrl('notifications'))" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-brand-red/15 border border-brand-red/30 text-brand-red text-xs font-bold animate-pulse hover:bg-brand-red/25 transition">
                            🔔 {{ $totalPending }} pending
                        </button>
                        @endif
                        <!-- Date/time -->
                        <span class="text-xs text-brand-ash hidden md:block">{{ now()->format('D, d M Y') }}</span>
                        <!-- Theme toggle -->
                        <button type="button" data-theme-toggle class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-white/20 text-brand-white/70 transition hover:text-brand-white" aria-pressed="false">
                            <span class="sr-only">Toggle theme</span>
                            <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"></circle><path d="M12 2.5v2.5M12 19v2.5M4.5 12H2M22 12h-2.5M5.8 5.8l1.8 1.8M16.4 16.4l1.8 1.8M18.2 5.8l-1.8 1.8M7.6 16.4l-1.8 1.8"></path></svg>
                            <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z"></path></svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- ── Tab Content ────────────────────────────────────────────── -->
            <main id="merchandiser-admin-main"
                  data-silent-root
                  class="main-scrollbar-none min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain p-4 lg:p-8 space-y-6 min-w-0">

                <!-- Flash -->
                @if(session('success'))
                    <div class="flex items-center gap-3 rounded-xl border border-green-500/30 bg-green-500/10 px-4 py-3 text-green-400 text-sm">
                        <span>✅</span> {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-300">
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: OVERVIEW DASHBOARD
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'overview'" x-transition>
                    <div data-silent-region="merch-clock-overview">
                    <div class="mb-6 rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Clock-in filter</p>
                                <p class="mt-1 text-sm font-semibold text-brand-white">{{ $clockRangeLabel }}</p>
                            </div>
                            <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="grid w-full gap-3 sm:grid-cols-[repeat(2,minmax(0,1fr))_auto_auto] lg:w-auto">
                                <input type="hidden" name="tab" value="overview">
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">From</span>
                                    <input type="date" name="clock_from" value="{{ $clockFromInput }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">To</span>
                                    <input type="date" name="clock_to" value="{{ $clockToInput }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <button type="submit" class="self-end rounded-xl bg-brand-red px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-red-700 transition">
                                    Apply
                                </button>
                                <a href="{{ route('merchandisers.admin.dashboard', ['tab' => 'overview']) }}" data-silent-link class="self-end rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10 transition">
                                    Clear
                                </a>
                            </form>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                        <div class="stat-card kpi-glow-green glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Active Merchandisers</p>
                            <p class="text-4xl font-display text-green-400">{{ $activeMerchandisers }}</p>
                            <p class="text-xs text-brand-ash mt-1">of {{ $totalMerchandisers }} total</p>
                        </div>
                        <div class="stat-card kpi-glow-amber glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Pending Activation</p>
                            <p class="text-4xl font-display text-amber-400">{{ $pendingMerchandisers }}</p>
                            <p class="text-xs text-brand-ash mt-1">awaiting pairing</p>
                        </div>
                        <div class="stat-card kpi-glow-blue glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Clock-Ins</p>
                            <p class="text-4xl font-display text-blue-400">{{ $todayClockins }}</p>
                            <p class="text-xs text-brand-ash mt-1">{{ $clockRangeLabel }}</p>
                        </div>
                        <div class="stat-card kpi-glow-amber glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">PCM / PJP</p>
                            <p class="text-4xl font-display text-amber-400">{{ $clockPcmCount + $clockPjpCount }}</p>
                            <p class="text-xs text-brand-ash mt-1">{{ $clockPcmCount }} PCM · {{ $clockPjpCount }} PJP</p>
                        </div>
                        <div class="stat-card kpi-glow-red glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Pending Approvals</p>
                            <p class="text-4xl font-display text-brand-red">{{ $pendingLeaves + $pendingClaims + $pendingLoans }}</p>
                            <p class="text-xs text-brand-ash mt-1">{{ $pendingLeaves }}L · {{ $pendingClaims }}C · {{ $pendingLoans }}Ln</p>
                        </div>
                    </div>

                    @php
                        $perfectOverview = $perfectStoreSummary['overview'] ?? [];
                        $perfectTargets = $perfectStoreSummary['targets'] ?? [];
                        $metricLabel = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 1) . '%';
                        $perfectMetricLabels = ['Coverage', 'OSA', 'NPD', 'MHS', 'Planogram', 'Facing', 'SOS'];
                        $perfectMetricValues = collect(['coverage', 'osa', 'npd', 'mhs', 'planogram', 'facing', 'sos'])
                            ->map(fn ($metric) => $perfectOverview[$metric] === null ? 0 : (float) ($perfectOverview[$metric] ?? 0))
                            ->values();
                        $perfectTargetValues = collect(['coverage', 'osa', 'npd', 'mhs', 'planogram', 'facing', 'sos'])
                            ->map(fn ($metric) => (float) ($perfectTargets[$metric] ?? 100))
                            ->values();
                        $perfectMerchChart = collect($perfectStoreSummary['merchandisers'] ?? collect())->take(8);
                        $perfectKdChart = collect($perfectStoreSummary['kds'] ?? collect())->take(8);
                        $perfectMerchChartLabels = $perfectMerchChart->pluck('name')->values();
                        $perfectMerchChartScores = $perfectMerchChart->pluck('perfect_store_score')->map(fn ($value) => (float) $value)->values();
                        $perfectKdChartLabels = $perfectKdChart->pluck('name')->values();
                        $perfectKdChartScores = $perfectKdChart->pluck('perfect_store_score')->map(fn ($value) => (float) $value)->values();
                    @endphp
                    <div class="grid grid-cols-2 gap-4 mb-6 xl:grid-cols-5">
                        <div class="glass-panel rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Coverage</p>
                            <p class="text-3xl font-display text-emerald-300">{{ $metricLabel($perfectOverview['coverage'] ?? 0) }}</p>
                            <p class="text-xs text-brand-ash mt-1">{{ $perfectOverview['scored'] ?? 0 }} scored of {{ $perfectOverview['scheduled'] ?? 0 }} scheduled</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-sky-500/20 bg-sky-500/5 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">OSA</p>
                            <p class="text-3xl font-display text-sky-300">{{ $metricLabel($perfectOverview['osa'] ?? null) }}</p>
                            <p class="text-xs text-brand-ash mt-1">Target {{ $perfectTargets['osa'] ?? 95 }}%</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">NPD</p>
                            <p class="text-3xl font-display text-amber-300">{{ $metricLabel($perfectOverview['npd'] ?? null) }}</p>
                            <p class="text-xs text-brand-ash mt-1">All-or-nothing per store</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">MHS</p>
                            <p class="text-3xl font-display text-violet-300">{{ $metricLabel($perfectOverview['mhs'] ?? null) }}</p>
                            <p class="text-xs text-brand-ash mt-1">Must-have SKU compliance</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-red/25 bg-brand-red/10 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-2">Perfect Store Score</p>
                            <p class="text-3xl font-display text-brand-white">{{ $metricLabel($perfectOverview['perfect_store_score'] ?? 0) }}</p>
                            <p class="text-xs text-brand-ash mt-1">{{ $perfectOverview['visits'] ?? 0 }} scored visit(s)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 mb-6 xl:grid-cols-3">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">Perfect Store KPI Radar</p>
                            <div class="h-72">
                                <canvas id="perfectStoreMetricRadarChart"></canvas>
                            </div>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">Top Merchandiser Scores</p>
                            <div class="h-72">
                                <canvas id="perfectStoreMerchChart"></canvas>
                            </div>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">KD Execution Scores</p>
                            <div class="h-72">
                                <canvas id="perfectStoreKdChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 mb-6 xl:grid-cols-2">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] overflow-hidden">
                            <div class="border-b border-brand-white/10 px-5 py-4">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Merchandiser Perfect Store Roll-up</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-sm">
                                    <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-widest text-brand-ash">
                                        <tr>
                                            <th class="px-5 py-3 text-left">Name</th>
                                            <th class="px-5 py-3 text-right">Coverage</th>
                                            <th class="px-5 py-3 text-right">OSA</th>
                                            <th class="px-5 py-3 text-right">NPD</th>
                                            <th class="px-5 py-3 text-right">MHS</th>
                                            <th class="px-5 py-3 text-right">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($perfectStoreSummary['merchandisers'] ?? collect())->take(6) as $rollup)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 font-semibold text-brand-white">{{ $rollup['name'] }}</td>
                                                <td class="px-5 py-3 text-right text-emerald-300">{{ $metricLabel($rollup['coverage']) }}</td>
                                                <td class="px-5 py-3 text-right text-sky-300">{{ $metricLabel($rollup['osa']) }}</td>
                                                <td class="px-5 py-3 text-right text-amber-300">{{ $metricLabel($rollup['npd']) }}</td>
                                                <td class="px-5 py-3 text-right text-violet-300">{{ $metricLabel($rollup['mhs']) }}</td>
                                                <td class="px-5 py-3 text-right font-bold text-brand-white">{{ $metricLabel($rollup['perfect_store_score']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-brand-ash">No Perfect Store KPI activity in this range yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] overflow-hidden">
                            <div class="border-b border-brand-white/10 px-5 py-4">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">KD Perfect Store Roll-up</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-sm">
                                    <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-widest text-brand-ash">
                                        <tr>
                                            <th class="px-5 py-3 text-left">Key Distributor</th>
                                            <th class="px-5 py-3 text-right">Coverage</th>
                                            <th class="px-5 py-3 text-right">OSA</th>
                                            <th class="px-5 py-3 text-right">NPD</th>
                                            <th class="px-5 py-3 text-right">MHS</th>
                                            <th class="px-5 py-3 text-right">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($perfectStoreSummary['kds'] ?? collect())->take(6) as $rollup)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 font-semibold text-brand-white">{{ $rollup['name'] }}</td>
                                                <td class="px-5 py-3 text-right text-emerald-300">{{ $metricLabel($rollup['coverage']) }}</td>
                                                <td class="px-5 py-3 text-right text-sky-300">{{ $metricLabel($rollup['osa']) }}</td>
                                                <td class="px-5 py-3 text-right text-amber-300">{{ $metricLabel($rollup['npd']) }}</td>
                                                <td class="px-5 py-3 text-right text-violet-300">{{ $metricLabel($rollup['mhs']) }}</td>
                                                <td class="px-5 py-3 text-right font-bold text-brand-white">{{ $metricLabel($rollup['perfect_store_score']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-brand-ash">No KD Perfect Store KPI activity in this range yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 mb-6 xl:grid-cols-2">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] overflow-hidden">
                            <div class="border-b border-brand-white/10 px-5 py-4">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Regional KPI Roll-up</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-sm">
                                    <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-widest text-brand-ash">
                                        <tr>
                                            <th class="px-5 py-3 text-left">Region</th>
                                            <th class="px-5 py-3 text-right">Coverage</th>
                                            <th class="px-5 py-3 text-right">OSA</th>
                                            <th class="px-5 py-3 text-right">NPD</th>
                                            <th class="px-5 py-3 text-right">MHS</th>
                                            <th class="px-5 py-3 text-right">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($perfectStoreSummary['regions'] ?? collect())->take(6) as $rollup)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 font-semibold text-brand-white">{{ $rollup['name'] }}</td>
                                                <td class="px-5 py-3 text-right text-emerald-300">{{ $metricLabel($rollup['coverage']) }}</td>
                                                <td class="px-5 py-3 text-right text-sky-300">{{ $metricLabel($rollup['osa']) }}</td>
                                                <td class="px-5 py-3 text-right text-amber-300">{{ $metricLabel($rollup['npd']) }}</td>
                                                <td class="px-5 py-3 text-right text-violet-300">{{ $metricLabel($rollup['mhs']) }}</td>
                                                <td class="px-5 py-3 text-right font-bold text-brand-white">{{ $metricLabel($rollup['perfect_store_score']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-brand-ash">No regional Perfect Store activity in this range yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] overflow-hidden">
                            <div class="border-b border-brand-white/10 px-5 py-4">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Brand KPI Roll-up</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-sm">
                                    <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-widest text-brand-ash">
                                        <tr>
                                            <th class="px-5 py-3 text-left">Brand</th>
                                            <th class="px-5 py-3 text-right">OSA</th>
                                            <th class="px-5 py-3 text-right">NPD</th>
                                            <th class="px-5 py-3 text-right">MHS</th>
                                            <th class="px-5 py-3 text-right">SOS</th>
                                            <th class="px-5 py-3 text-right">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($perfectStoreSummary['brands'] ?? collect())->take(6) as $rollup)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 font-semibold text-brand-white">{{ $rollup['name'] }}</td>
                                                <td class="px-5 py-3 text-right text-sky-300">{{ $metricLabel($rollup['osa']) }}</td>
                                                <td class="px-5 py-3 text-right text-amber-300">{{ $metricLabel($rollup['npd']) }}</td>
                                                <td class="px-5 py-3 text-right text-violet-300">{{ $metricLabel($rollup['mhs']) }}</td>
                                                <td class="px-5 py-3 text-right text-pink-300">{{ $metricLabel($rollup['sos']) }}</td>
                                                <td class="px-5 py-3 text-right font-bold text-brand-white">{{ $metricLabel($rollup['perfect_store_score']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-brand-ash">No brand-level SKU scoring in this range yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 mb-6 xl:grid-cols-2">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">Alerts and Bottlenecks</p>
                            <div class="space-y-3">
                                @forelse(($perfectStoreSummary['alerts'] ?? collect()) as $alert)
                                    <div class="rounded-xl border {{ ($alert['level'] ?? '') === 'critical' ? 'border-brand-red/40 bg-brand-red/10' : 'border-amber-400/25 bg-amber-400/10' }} p-3">
                                        <p class="text-sm font-bold text-brand-white">{{ $alert['title'] }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-brand-white/55">{{ $alert['detail'] }}</p>
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-3 py-4 text-sm text-emerald-200">No critical Perfect Store bottlenecks detected in this range.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">AI Coaching Prompts</p>
                            <div class="space-y-3">
                                @forelse(($perfectStoreSummary['coaching'] ?? collect()) as $tip)
                                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/35 p-3">
                                        <p class="text-sm font-bold text-brand-white">{{ $tip['name'] }} - {{ $tip['title'] }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-brand-white/55">{{ $tip['detail'] }}</p>
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-brand-white/10 bg-brand-black/35 px-3 py-4 text-sm text-brand-white/45">Coaching prompts will appear after visits or route coverage activity is available.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

                        <!-- Attendance Trend -->
                        <div class="lg:col-span-2 glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">Daily Attendance - {{ $clockRangeLabel }}</p>
                            <canvas id="attendanceChart" height="120" data-chart-labels='@json(array_keys($attendanceChart))' data-chart-values='@json(array_values($attendanceChart))'></canvas>
                        </div>

                        <!-- KD & Outlet Summary -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">🏢 Infrastructure</p>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-brand-white/70">Key Distributors</span>
                                    <span class="text-2xl font-display text-brand-white">{{ $totalKds }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-brand-white/70">Outlets</span>
                                    <span class="text-2xl font-display text-brand-white">{{ $totalOutlets }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-brand-white/70">Regions</span>
                                    <span class="text-2xl font-display text-brand-white">{{ $regions->count() }}</span>
                                </div>
                                <hr class="border-brand-white/10">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-brand-white/70">Total Assets Deployed</span>
                                    <span class="text-2xl font-display text-brand-white">{{ $allAssetsTotal }}</span>
                                </div>
                            </div>
                            @if($googleForms->hasPages())
                                <div class="border-t border-brand-white/10 px-5 py-4">
                                    {{ $googleForms->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    </div>

                    <!-- Additional Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
                        <!-- Merchandiser Status Pie Chart -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 flex flex-col">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">👥 Merchandiser Status Breakdown</p>
                            <div class="h-64 relative flex-1">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>

                        <!-- Visits by KD Bar Chart -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 flex flex-col">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">🏬 Visits by Key Distributor</p>
                            <div class="h-64 relative flex-1">
                                <canvas id="kdVisitsChart"></canvas>
                            </div>
                        </div>

                        <!-- Asset POSM items Pie Chart -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 flex flex-col">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">📁 POSM / Gear Deployments</p>
                            <div class="h-64 relative flex-1">
                                <canvas id="assetsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performers Table -->
                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex items-center justify-between">
                            <p class="text-xs uppercase tracking-widest text-brand-ash">🏆 Top Performers — This Month</p>
                            <button @click="window.location.href = @js($adminTabUrl('merchandisers'))" class="text-xs text-brand-red hover:underline">View All →</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">#</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">KD</th>
                                        <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Visits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topPerformers as $i => $m)
                                    <tr class="border-b border-brand-white/5">
                                        <td class="px-5 py-3 text-brand-ash font-mono">{{ $i + 1 }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-brand-white/10 flex items-center justify-center text-xs font-bold text-brand-white">{{ strtoupper(substr($m->name,0,1)) }}</div>
                                                <div>
                                                    <p class="font-medium text-brand-white">{{ $m->name }}</p>
                                                    <p class="text-[10px] text-brand-ash">{{ $m->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-brand-ash text-xs">{{ $m->merchandiserKd->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-green-500/10 text-green-400 text-xs font-bold">{{ $m->merchandiser_visits_count }}</span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="4" class="px-5 py-8 text-center text-brand-ash text-sm">No visit data yet for this month.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Broadcast Notification -->
                    <div class="glass-panel rounded-2xl p-5 border border-brand-white/10">
                        <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">📣 Broadcast Notification to All Merchandisers</p>
                        <form method="POST" action="{{ route('merchandisers.admin.notifications.broadcast') }}" class="space-y-3">
                            @csrf
                            <input type="text" name="title" placeholder="Notification title…" required
                                class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                            <textarea name="message" rows="3" placeholder="Message body…" required
                                class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash resize-none"></textarea>
                            <button type="submit" class="px-6 py-2.5 bg-brand-red text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition">
                                Send Broadcast
                            </button>
                        </form>
                    </div>

                    <!-- Exports & Client Share Panels -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                        <!-- Data Export Panel -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 flex flex-col justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">📥 Export Merchandiser Portal Data</p>
                                <p class="text-xs text-brand-ash mb-4">Select operations data to download in spreadsheet format.</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-2">
                                        <p class="text-[10px] uppercase font-bold text-brand-ash tracking-wider">CSV Formats</p>
                                        <a href="{{ route('merchandisers.admin.export', 'merchandisers') }}?format=csv" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">👤 Merchandisers List</a>
                                        <a href="{{ route('merchandisers.admin.export', 'attendance') }}?format=csv" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">📅 Attendance logs</a>
                                        <a href="{{ route('merchandisers.admin.export', 'assets') }}?format=csv" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">📁 POSM / Gear Deployments</a>
                                    </div>
                                    <div class="space-y-2">
                                        <p class="text-[10px] uppercase font-bold text-brand-ash tracking-wider">Excel Formats</p>
                                        <a href="{{ route('merchandisers.admin.export', 'leaves') }}?format=excel" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">🍂 Leave applications</a>
                                        <a href="{{ route('merchandisers.admin.export', 'claims') }}?format=excel" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">💰 Petty cash claims</a>
                                        <a href="{{ route('merchandisers.admin.export', 'loans') }}?format=excel" class="block w-full text-left px-3 py-2 bg-brand-white/5 hover:bg-brand-white/10 rounded-xl text-xs transition">💵 Salary advances</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Client Link Share Generator -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">🔗 Generate Time-Limited Client Share Link</p>
                            <p class="text-xs text-brand-ash mb-3">Links remain valid for 24 hours. Toggle sections to show/hide sensitive metrics.</p>

                            @if(session('share_url'))
                            <div class="mb-4 p-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center justify-between gap-3 text-xs text-green-400">
                                <span class="truncate font-mono">{{ session('share_url') }}</span>
                                <button type="button" @click="copyShareLink(@js(session('share_url')))"
                                    class="shrink-0 px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-[10px] font-bold">Copy Link</button>
                            </div>
                            @endif

                            <form method="POST" action="{{ route('merchandisers.admin.share.generate') }}" class="space-y-4">
                                @csrf
                                <div>
                                    <input type="text" name="label" placeholder="Client label (e.g. Unilever Client Review)" required
                                        class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-xs px-3 py-2 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_overview" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Operations Summary</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_tracking" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Real-Time Map</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_attendance_chart" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Attendance Trend</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_top_performers" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Top Performers</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_assets" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Field Gear Logs</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_kds" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                        <span>Show Key Distributors</span>
                                    </label>
                                </div>

                                <button type="submit" class="w-full py-2 bg-brand-red text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition">
                                    Generate Shareable Link
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Active Share Links List -->
                    @if($recentReports->count() > 0)
                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">🔗 Active Shared Reports</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-brand-white/10 bg-brand-white/3">
                                        <th class="px-5 py-2.5 text-left text-[10px] uppercase tracking-widest text-brand-ash">Report Label</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] uppercase tracking-widest text-brand-ash">Status / Expiration</th>
                                        <th class="px-5 py-2.5 text-center text-[10px] uppercase tracking-widest text-brand-ash">Views</th>
                                        <th class="px-5 py-2.5 text-right text-[10px] uppercase tracking-widest text-brand-ash">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentReports as $rep)
                                    <tr class="border-b border-brand-white/5">
                                        <td class="px-5 py-2">
                                            <p class="font-medium text-brand-white">{{ $rep->label }}</p>
                                            <p class="text-[10px] text-brand-ash font-mono truncate max-w-[200px]">{{ route('merchandisers.report.view', $rep->token) }}</p>
                                        </td>
                                        <td class="px-5 py-2">
                                            @if($rep->isValid())
                                                <span class="text-green-400 text-xs font-semibold">Active — expires {{ $rep->expires_at->diffForHumans() }}</span>
                                            @else
                                                <span class="text-brand-ash text-xs">Expired / Revoked</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2 text-center text-xs text-brand-white font-semibold">{{ $rep->view_count }} views</td>
                                        <td class="px-5 py-2 text-right">
                                            @if($rep->isValid())
                                            <form method="POST" action="{{ route('merchandisers.admin.share.revoke', $rep) }}">
                                                @csrf
                                                <button type="submit" class="text-[10px] px-2.5 py-1 bg-brand-red/20 text-brand-red rounded-lg hover:bg-brand-red/45 transition">Revoke</button>
                                            </form>
                                            @else
                                            <span class="text-brand-ash/40">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    </div>

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: LIVE TRACKING
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'settings'" x-transition>
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                        <div class="xl:col-span-2 glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-5">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-brand-ash">Merchandiser attendance windows</p>
                                    <h3 class="text-xl font-display text-brand-white mt-1">Clock-in / Clock-out Times</h3>
                                    <p class="text-xs text-brand-ash mt-1">Update these anytime when company attendance windows change. The field dashboard and clock-in validation use these values immediately.</p>
                                </div>
                                <span class="inline-flex w-fit rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                                    No code deploy needed
                                </span>
                            </div>

                            <form method="POST" action="{{ route('merchandisers.admin.clock-settings.update') }}" class="space-y-4">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    @foreach($clockSettings as $slot => $setting)
                                        <div class="rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4">
                                            <div class="mb-4">
                                                <p class="text-lg">{{ $setting['icon'] }}</p>
                                                <p class="mt-1 text-sm font-bold text-brand-white">{{ $setting['label'] }}</p>
                                                <p class="text-[10px] uppercase tracking-wider text-brand-ash">{{ $setting['range'] }}</p>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <label class="space-y-1">
                                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">Start</span>
                                                    <input type="time" name="{{ $slot }}_start" value="{{ old($slot.'_start', $setting['start']) }}" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                </label>
                                                <label class="space-y-1">
                                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">End</span>
                                                    <input type="time" name="{{ $slot }}_end" value="{{ old($slot.'_end', $setting['end']) }}" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="rounded-xl bg-brand-red px-6 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">
                                        Save Clock Windows
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash">How this works</p>
                            <div class="mt-4 space-y-3 text-sm text-brand-white/70 leading-relaxed">
                                <p>Morning and midday are normal check-in windows.</p>
                                <p>Clock-out / COB controls the final end-of-day clock-out window, so changing it to 17:00 or 18:00 is handled here.</p>
                                <p>Payroll lateness uses each configured start time plus the existing 5-minute grace period.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'skus'" x-transition>
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                        <div class="xl:col-span-1 glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <div class="mb-5">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">AI Reference Catalog</p>
                                <h3 class="text-xl font-display text-brand-white mt-1">Add SKU Reference</h3>
                                <p class="text-xs text-brand-ash mt-1">Capture brand, category, product image, and aliases so AI can recognize the SKU in shelf photos.</p>
                            </div>

                            <div class="mb-4 rounded-xl border {{ $skuAiConfigured ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/25 bg-amber-500/10 text-amber-200' }} px-3 py-2 text-xs">
                                @if($skuAiConfigured)
                                    OpenAI vision is configured. Shelf detection can run.
                                @else
                                    OpenAI API key is not configured yet. Add OPENAI_API_KEY to .env to run real detection.
                                @endif
                            </div>

                            <form method="POST" action="{{ route('merchandisers.admin.skus.store') }}" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">SKU Name</span>
                                    <input name="name" required placeholder="e.g. Guinness Smooth 330ml" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">Brand</span>
                                    <select name="brand_id" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                        <option value="">Select brand</option>
                                        @foreach($brandOptions as $brand)
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">New Brand If Not Listed</span>
                                    <input name="new_brand_name" placeholder="Type brand name to add it" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">Category</span>
                                    <input name="category" list="sku-category-options" placeholder="e.g. Beverage, Oral Care, Skincare" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">New Category If Not Listed</span>
                                    <input name="new_category" placeholder="Type category to add it" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <div class="grid gap-3 rounded-2xl border border-brand-white/10 bg-brand-black/35 p-3 sm:grid-cols-3">
                                    <label class="block">
                                        <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                            <input type="checkbox" name="track_osa" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                            OSA
                                        </span>
                                        <input name="osa_drop_size" type="number" min="1" value="1" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    </label>
                                    <label class="block">
                                        <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                            <input type="checkbox" name="track_npd" value="1" class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                            NPD
                                        </span>
                                        <input name="npd_drop_size" type="number" min="1" value="1" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    </label>
                                    <label class="block">
                                        <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                            <input type="checkbox" name="track_mhs" value="1" class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                            MHS
                                        </span>
                                        <input name="mhs_drop_size" type="number" min="1" value="1" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    </label>
                                </div>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">Reference Image</span>
                                    <input name="reference_image" type="file" accept="image/*" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white file:mr-3 file:rounded-lg file:border-0 file:bg-brand-red file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">Aliases / Pack Names</span>
                                    <input name="aliases" placeholder="comma separated names the field team may use" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">AI Notes</span>
                                    <textarea name="ai_reference_notes" rows="3" placeholder="Label color, bottle/can shape, pack size, common lookalikes…" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0"></textarea>
                                </label>
                                <datalist id="sku-category-options">
                                    @foreach($skuCategories as $category)
                                        <option value="{{ $category }}"></option>
                                    @endforeach
                                    @foreach(['Beverage', 'Oral Care', 'Skin Care', 'Home Care', 'Foods', 'Pharmacy', 'Cosmetics'] as $category)
                                        <option value="{{ $category }}"></option>
                                    @endforeach
                                </datalist>
                                <button type="submit" class="w-full rounded-xl bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">
                                    Save SKU Reference
                                </button>
                            </form>
                        </div>

                        <div class="xl:col-span-2 glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-brand-ash">Configured SKU References</p>
                                    <p class="text-sm text-brand-white mt-0.5">{{ $skuReferenceCount }} of {{ $skuCount }} SKUs have reference images</p>
                                </div>
                                <span class="rounded-full border border-sky-400/20 bg-sky-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-sky-200">
                                    Human correction loop enabled
                                </span>
                            </div>

                            <div class="divide-y divide-brand-white/10">
                                @forelse($skus as $sku)
                                    <div class="p-5" x-data="{ editing: false }">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="flex gap-4 min-w-0">
                                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5">
                                                    @if($sku->reference_image_path)
                                                        <img src="{{ Storage::disk('public')->url($sku->reference_image_path) }}" alt="{{ $sku->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        <div class="flex h-full w-full items-center justify-center text-2xl opacity-40">📦</div>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-brand-white">{{ $sku->name }}</p>
                                                    <div class="mt-1 flex flex-wrap gap-2">
                                                        <span class="rounded-full border border-brand-white/10 bg-brand-white/5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-white/70">
                                                            {{ $sku->brand?->name ?? 'No brand' }}
                                                        </span>
                                                        <span class="rounded-full border border-brand-white/10 bg-brand-white/5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-white/70">
                                                            {{ $sku->category ?: 'No category' }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @if($sku->track_osa)
                                                            <span class="rounded-full border border-sky-400/20 bg-sky-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-200">OSA {{ $sku->osa_drop_size }}</span>
                                                        @endif
                                                        @if($sku->track_npd)
                                                            <span class="rounded-full border border-amber-400/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-200">NPD {{ $sku->npd_drop_size }}</span>
                                                        @endif
                                                        @if($sku->track_mhs)
                                                            <span class="rounded-full border border-violet-400/20 bg-violet-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-violet-200">MHS {{ $sku->mhs_drop_size }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-xs text-brand-ash">
                                                        @if($sku->aliases)
                                                            Aliases: {{ implode(', ', $sku->aliases) }}
                                                        @else
                                                            No aliases yet
                                                        @endif
                                                    </p>
                                                    @if($sku->ai_reference_notes)
                                                        <p class="mt-1 text-xs text-brand-white/50">{{ $sku->ai_reference_notes }}</p>
                                                    @endif
                                                    <p class="mt-2 text-[10px] uppercase tracking-wider {{ $sku->reference_image_path ? 'text-emerald-400' : 'text-amber-300' }}">
                                                        {{ $sku->reference_image_path ? 'Ready for AI matching' : 'Needs reference image' }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="flex gap-2">
                                                <button type="button" @click="editing = !editing" class="rounded-lg bg-brand-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/20">
                                                    Edit
                                                </button>
                                                <form method="POST" action="{{ route('merchandisers.admin.skus.destroy', $sku) }}" onsubmit="return confirm('Remove this SKU from the AI catalog?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-brand-red/20 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red hover:bg-brand-red/40">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <form x-show="editing" x-cloak x-transition method="POST" action="{{ route('merchandisers.admin.skus.update', $sku) }}" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4 md:grid-cols-2">
                                            @csrf @method('PUT')
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">SKU Name</span>
                                                <input name="name" value="{{ $sku->name }}" required class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            </label>
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">Brand</span>
                                                <select name="brand_id" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                    <option value="">Select brand</option>
                                                    @foreach($brandOptions as $brand)
                                                        <option value="{{ $brand->id }}" @selected((int) $sku->brand_id === (int) $brand->id)>{{ $brand->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">New Brand If Not Listed</span>
                                                <input name="new_brand_name" placeholder="Type brand name to add it" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            </label>
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">Category</span>
                                                <input name="category" list="sku-category-options" value="{{ $sku->category }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            </label>
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">New Category If Not Listed</span>
                                                <input name="new_category" placeholder="Type category to add it" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            </label>
                                            <div class="grid gap-3 rounded-2xl border border-brand-white/10 bg-brand-black/35 p-3 md:col-span-2 sm:grid-cols-3">
                                                <label class="block">
                                                    <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                                        <input type="checkbox" name="track_osa" value="1" @checked($sku->track_osa) class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                                        OSA
                                                    </span>
                                                    <input name="osa_drop_size" type="number" min="1" value="{{ $sku->osa_drop_size ?: 1 }}" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                </label>
                                                <label class="block">
                                                    <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                                        <input type="checkbox" name="track_npd" value="1" @checked($sku->track_npd) class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                                        NPD
                                                    </span>
                                                    <input name="npd_drop_size" type="number" min="1" value="{{ $sku->npd_drop_size ?: 1 }}" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                </label>
                                                <label class="block">
                                                    <span class="flex items-center gap-2 text-[10px] uppercase tracking-wider text-brand-ash">
                                                        <input type="checkbox" name="track_mhs" value="1" @checked($sku->track_mhs) class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-0">
                                                        MHS
                                                    </span>
                                                    <input name="mhs_drop_size" type="number" min="1" value="{{ $sku->mhs_drop_size ?: 1 }}" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                </label>
                                            </div>
                                            <label class="block">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">Replace Reference Image</span>
                                                <input name="reference_image" type="file" accept="image/*" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white file:mr-3 file:rounded-lg file:border-0 file:bg-brand-red file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                                            </label>
                                            <label class="block md:col-span-2">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">Aliases</span>
                                                <input name="aliases" value="{{ $sku->aliases ? implode(', ', $sku->aliases) : '' }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            </label>
                                            <label class="block md:col-span-2">
                                                <span class="text-[10px] uppercase tracking-wider text-brand-ash">AI Notes</span>
                                                <textarea name="ai_reference_notes" rows="2" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">{{ $sku->ai_reference_notes }}</textarea>
                                            </label>
                                            @if($sku->reference_image_path)
                                                <label class="flex items-center gap-2 text-xs text-brand-ash md:col-span-2">
                                                    <input type="checkbox" name="remove_reference_image" value="1" class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0">
                                                    Remove current reference image
                                                </label>
                                            @endif
                                            <div class="flex justify-end gap-2 md:col-span-2">
                                                <button type="button" @click="editing = false" class="rounded-lg bg-brand-white/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-brand-white">Cancel</button>
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-500">Save Reference</button>
                                            </div>
                                        </form>
                                    </div>
                                @empty
                                    <div class="p-8 text-center text-sm text-brand-ash">No SKUs configured yet.</div>
                                @endforelse
                            </div>
                            @if($skus->hasPages())
                                <div class="border-t border-brand-white/10 px-5 py-4">
                                    {{ $skus->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'tracking'" x-transition>
                    <div data-silent-region="merch-live-tracking">
                    <div class="mb-5 rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Live tracking clock-in filter</p>
                                <p class="mt-1 text-sm font-semibold text-brand-white">{{ $clockRangeLabel }}</p>
                                <p class="mt-1 text-xs text-brand-ash">{{ count(array_filter($merchandiserLocations, fn($m) => $m['clocked_in'])) }} of {{ count($merchandiserLocations) }} agents clocked in for this range</p>
                            </div>
                            <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="grid w-full gap-3 sm:grid-cols-[repeat(2,minmax(0,1fr))_auto_auto] lg:w-auto">
                                <input type="hidden" name="tab" value="tracking">
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">From</span>
                                    <input type="date" name="clock_from" value="{{ $clockFromInput }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="block">
                                    <span class="text-[10px] uppercase tracking-wider text-brand-ash">To</span>
                                    <input type="date" name="clock_to" value="{{ $clockToInput }}" class="mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <button type="submit" class="self-end rounded-xl bg-brand-red px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-red-700 transition">
                                    Apply
                                </button>
                                <a href="{{ route('merchandisers.admin.dashboard', ['tab' => 'tracking']) }}" data-silent-link class="self-end rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10 transition">
                                    Clear
                                </a>
                            </form>
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden mb-5">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Real-Time Field Positions</p>
                                <p class="text-sm text-brand-white mt-0.5">{{ count(array_filter($merchandiserLocations, fn($m) => $m['latitude'])) }} of {{ count($merchandiserLocations) }} agents transmitting GPS</p>
                                <p class="mt-1 text-xs text-brand-ash">{{ count(array_filter($merchandiserLocations, fn($m) => $m['clocked_in'])) }} clocked in between {{ $clockRangeLabel }}</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-brand-ash">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span> Clocked In This Range
                                <span class="w-2 h-2 bg-amber-400 rounded-full ml-3"></span> Not Clocked
                                <span class="w-2 h-2 bg-brand-white/20 rounded-full ml-3"></span> No GPS
                            </div>
                        </div>
                        <div id="admin-map"></div>
                        <script type="application/json" data-merchandiser-map-locations>@json($merchandiserLocations)</script>
                    </div>

                    <!-- Agent List -->
                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden w-full">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex items-center justify-between">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">📋 All Field Agents — Status Snapshot</p>
                            <span class="text-[10px] text-brand-ash">{{ count($merchandiserLocations) }} agents</span>
                        </div>
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-sm" style="min-width:720px">
                                <thead class="bg-brand-white/3">
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap w-56">Agent</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap w-28">Status</th>
                                        <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap w-40">Clock-In Range</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap w-40">Last GPS Ping</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Coordinates</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($merchandiserLocations as $m)
                                    <tr class="border-b border-brand-white/5 hover:bg-brand-white/3 transition {{ $m['latitude'] ? 'cursor-pointer' : '' }}"
                                        @if($m['latitude']) onclick="focusMerchandiserOnMap({{ $m['id'] }})" title="Zoom to {{ $m['name'] }} on the map" @endif>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-xs font-bold"
                                                    style="background:{{ $m['clocked_in'] ? 'rgba(34,197,94,0.15)' : 'rgba(255,255,255,0.08)' }};color:{{ $m['clocked_in'] ? '#4ade80' : '#9ca3af' }}">
                                                    {{ strtoupper(substr($m['name'],0,1)) }}
                                                </div>
                                                <div>
                                                    <p class="font-medium text-brand-white text-xs">{{ $m['name'] }}</p>
                                                    <p class="text-[10px] text-brand-ash">{{ $m['phone'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span class="status-pill-{{ $m['status'] }} text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">{{ $m['status'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            @if($m['clocked_in'])
                                                <span class="inline-flex items-center gap-1 text-green-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>{{ $m['last_clock_in'] ?? 'Clocked In' }}</span>
                                            @else
                                                <span class="text-brand-ash/60 text-xs">No clock-in in range</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-brand-ash text-xs whitespace-nowrap">{{ $m['last_seen'] }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            @if($m['latitude'])
                                                <span class="text-brand-ash text-[10px] font-mono">{{ number_format($m['latitude'],5) }}, {{ number_format($m['longitude'],5) }}</span>
                                            @else
                                                <span class="text-brand-ash/40 text-xs">No GPS data</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: MANAGE KEY DISTRIBUTORS
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'kds'" x-transition x-data="{ kdTab: @js(request('kd_subtab', 'list')), editKdId: null, editOutletId: null }">

                    <!-- Sub-tabs -->
                    <div class="flex gap-2 mb-5 flex-wrap">
                        <button @click="kdTab = 'list'" :class="kdTab === 'list' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">🏢 Key Distributors</button>
                        <button @click="kdTab = 'outlets'" :class="kdTab === 'outlets' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">🏪 Outlets</button>
                        <button @click="kdTab = 'pairings'" :class="kdTab === 'pairings' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">🔗 Pairings</button>
                    </div>

                    <!-- KD List Tab -->
                    <div x-show="kdTab === 'list'" x-transition>
                        <!-- Add KD Form -->
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 mb-5" x-data="{ newRegion: false }">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">➕ Add New Key Distributor</p>
                            <form method="POST" action="{{ route('merchandisers.admin.kds.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" data-gps-coordinate-scope>
                                @csrf
                                <input type="text" name="name" placeholder="KD Name *" required class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">

                                <!-- Region selector with Other option -->
                                <div class="flex flex-col gap-2">
                                    <select name="region_id" @change="newRegion = $event.target.value === '__new__'" class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0">
                                        <option value="">Select Region *</option>
                                        @foreach($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                                        <option value="__new__">✏️ Other — Add New Region</option>
                                    </select>
                                    <input x-show="newRegion" x-transition type="text" name="new_region" placeholder="Type new region name…"
                                        class="rounded-xl border border-brand-red/40 bg-brand-red/10 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                </div>

                                <input type="text" name="address" placeholder="Address" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="number" step="0.00000001" name="latitude" placeholder="Latitude * e.g. 10.7829344" required data-gps-latitude class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="number" step="0.00000001" name="longitude" placeholder="Longitude * e.g. -0.8510496" required data-gps-longitude class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <div class="flex flex-col gap-2 rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-xs text-brand-white/60 sm:col-span-2 lg:col-span-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span data-gps-status>Capture GPS while you are at the KD location, or enter verified coordinates manually.</span>
                                        <button type="button" data-gps-capture class="rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-green-300 hover:bg-green-500/20 transition">
                                            Capture Current Location
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="px-6 py-2.5 bg-brand-red text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition">Add KD</button>
                            </form>
                        </div>

                        <!-- KD Table -->
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">{{ $kds->count() }} Key Distributors Enrolled</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">KD Name</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Region</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Address</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assigned Merch.</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Outlets</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($kds as $kd)
                                        <tr class="border-b border-brand-white/5" x-data="{ editing: false, assigning: false }" data-gps-coordinate-scope>
                                            <td class="px-5 py-3">
                                                <div x-show="!editing" class="font-medium text-brand-white">{{ $kd->name }}</div>
                                                <input x-show="editing" x-cloak form="kd-edit-form-{{ $kd->id }}" type="text" name="name" value="{{ $kd->name }}" required class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 w-44 focus:border-brand-red focus:ring-0">
                                            </td>
                                            <td class="px-5 py-3 text-brand-ash text-xs">{{ $kd->region->name ?? '—' }}</td>
                                            <td class="px-5 py-3 text-brand-ash text-xs">
                                                <div class="min-w-[190px] max-w-[260px] space-y-1">
                                                    <p class="leading-snug">{{ $kd->address ?? '—' }}</p>
                                                    @if(! is_null($kd->latitude) && ! is_null($kd->longitude))
                                                        <p class="inline-flex items-center gap-1 rounded-full border border-green-500/20 bg-green-500/10 px-2 py-0.5 font-mono text-[10px] text-green-300">
                                                            📍 {{ number_format((float) $kd->latitude, 7) }}, {{ number_format((float) $kd->longitude, 7) }}
                                                        </p>
                                                    @else
                                                        <p class="inline-flex items-center gap-1 rounded-full border border-brand-red/30 bg-brand-red/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-red">
                                                            Missing GPS — PCM blocked
                                                        </p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div x-show="!editing">
                                                @if($kd->merchandisers->count() > 0)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($kd->merchandisers->take(3) as $merch)
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-green-500/10 text-green-400 border border-green-500/20">{{ $merch->name }}</span>
                                                        @endforeach
                                                        @if($kd->merchandisers->count() > 3)
                                                        <span class="text-[10px] text-brand-ash">+{{ $kd->merchandisers->count() - 3 }} more</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-[10px] text-amber-400/70 italic">Unassigned</span>
                                                @endif
                                                </div>
                                                <div x-show="editing" x-cloak class="mt-2 grid min-w-[260px] gap-2">
                                                    <label class="text-[9px] uppercase tracking-wider text-brand-ash">Region</label>
                                                    <select form="kd-edit-form-{{ $kd->id }}" name="region_id" required class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        @foreach($regions as $region)
                                                            <option value="{{ $region->id }}" {{ (int) $kd->region_id === (int) $region->id ? 'selected' : '' }}>{{ $region->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label class="text-[9px] uppercase tracking-wider text-brand-ash">Address</label>
                                                    <input form="kd-edit-form-{{ $kd->id }}" type="text" name="address" value="{{ $kd->address }}" placeholder="Address" class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        <div class="grid gap-1">
                                                            <label class="text-[9px] uppercase tracking-wider text-brand-ash">Latitude *</label>
                                                            <input form="kd-edit-form-{{ $kd->id }}" type="number" step="0.00000001" name="latitude" value="{{ $kd->latitude }}" required placeholder="e.g. 10.7829344" data-gps-latitude class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        </div>
                                                        <div class="grid gap-1">
                                                            <label class="text-[9px] uppercase tracking-wider text-brand-ash">Longitude *</label>
                                                            <input form="kd-edit-form-{{ $kd->id }}" type="number" step="0.00000001" name="longitude" value="{{ $kd->longitude }}" required placeholder="e.g. -0.8510496" data-gps-longitude class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col gap-2 rounded-lg border border-brand-white/10 bg-brand-black/30 p-2">
                                                        <p class="text-[9px] text-brand-white/35" data-gps-status>PCM/KD clock-in stays blocked until both GPS values are saved.</p>
                                                        <button type="button" data-gps-capture class="w-fit rounded-lg border border-green-500/30 bg-green-500/10 px-2.5 py-1.5 text-[9px] font-bold uppercase tracking-wider text-green-300 hover:bg-green-500/20 transition">
                                                            Capture GPS
                                                        </button>
                                                    </div>
                                                    <label class="text-[9px] uppercase tracking-wider text-brand-ash">Assigned Merch</label>
                                                    <select form="kd-edit-form-{{ $kd->id }}" name="assigned_merchandiser_ids[]" multiple size="4" class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        @foreach($allMerchandisers as $am)
                                                            <option value="{{ $am->id }}" {{ $am->kd_id == $kd->id ? 'selected' : '' }}>
                                                                {{ $am->name }}{{ $am->kd_id && $am->kd_id != $kd->id ? ' — ' . ($am->merchandiserKd->name ?? 'Other KD') : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <p class="text-[9px] text-brand-white/35">Hold Ctrl/Cmd to select multiple. Unselected current merchandisers will be removed from this KD.</p>
                                                </div>
                                                <!-- Assign / Reassign inline form -->
                                                <div x-show="assigning && !editing" x-transition class="mt-2">
                                                    <form method="POST" action="" class="flex gap-2" id="kd-assign-{{ $kd->id }}">
                                                        @csrf
                                                        <select name="merchandiser_to_assign" onchange="document.getElementById('kd-assign-{{ $kd->id }}').action='/merchandisers/admin/pairings/'+this.value"
                                                            class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0 flex-1">
                                                            <option value="">Select Merchandiser…</option>
                                                            @foreach($allMerchandisers as $am)
                                                            <option value="{{ $am->id }}" {{ $am->kd_id == $kd->id ? 'data-current=1' : '' }}>
                                                                {{ $am->name }} {{ $am->kd_id == $kd->id ? '(current)' : '' }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                        <input type="hidden" name="kd_id" value="{{ $kd->id }}">
                                                        <input type="hidden" name="region_id" value="{{ $kd->region_id }}">
                                                        <button type="submit" class="text-[10px] px-2 py-1.5 bg-brand-red text-white rounded-lg hover:bg-red-700 font-bold">Assign</button>
                                                        <button type="button" @click="assigning=false" class="text-[10px] px-2 py-1.5 bg-brand-white/10 text-brand-white rounded-lg">✕</button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-center"><span class="text-xs font-bold text-blue-400">{{ $kd->outlets->count() }}</span></td>
                                            <td class="px-5 py-3 text-right">
                                                <form id="kd-edit-form-{{ $kd->id }}" method="POST" action="{{ route('merchandisers.admin.kds.update', $kd) }}" class="hidden">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="sync_assigned_merchandisers" value="1">
                                                </form>
                                                <div x-show="!editing" class="flex items-center justify-end gap-1.5 flex-wrap">
                                                    <button @click="assigning = !assigning; editing = false" class="text-[10px] px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 hover:bg-blue-500/40 transition font-bold">Assign</button>
                                                    <button @click="editing = !editing; assigning = false" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition">Edit</button>
                                                    <form method="POST" action="{{ route('merchandisers.admin.kds.destroy', $kd) }}" onsubmit="return confirm('Remove this KD and unlink all merchandisers?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-red/20 text-brand-red hover:bg-brand-red/40 transition">Remove</button>
                                                    </form>
                                                </div>
                                                <div x-show="editing" x-cloak class="flex items-center justify-end gap-1.5 flex-wrap">
                                                    <button type="submit" form="kd-edit-form-{{ $kd->id }}" class="text-[10px] px-2.5 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700 transition font-bold">Save</button>
                                                    <button type="button" @click="editing = false" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition">Cancel</button>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="6" class="px-5 py-8 text-center text-brand-ash text-sm">No Key Distributors enrolled yet. Add one above.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Outlets Tab -->
                    <div x-show="kdTab === 'outlets'" x-transition class="space-y-5">
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-brand-ash">Add Outlet to KD</p>
                                    <p class="mt-1 text-xs text-brand-white/45">Admin-created coordinates are locked immediately. Staff-created outlets can be captured once by GPS, then only admins can correct them.</p>
                                </div>
                                <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="flex flex-col gap-1 sm:min-w-[190px]">
                                    <input type="hidden" name="tab" value="kds">
                                    <input type="hidden" name="kd_subtab" value="outlets">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">Registered Day</span>
                                    <select name="outlet_day" onchange="this.form.submit()" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        @foreach($outletDayLabels as $dayValue => $dayLabel)
                                            <option value="{{ $dayValue }}" {{ $outletRegistrationDay === (string) $dayValue ? 'selected' : '' }}>{{ $dayLabel }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('merchandisers.admin.outlets.store') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" data-gps-coordinate-scope>
                                @csrf
                                <select name="kd_id" required class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0">
                                    <option value="">Select KD *</option>
                                    @foreach($kds as $kd)<option value="{{ $kd->id }}">{{ $kd->name }}</option>@endforeach
                                </select>
                                <input type="text" name="name" placeholder="Outlet / Store Name *" required class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="text" name="code" placeholder="Outlet code" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <select name="channel_type" class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0">
                                    <option value="">Channel</option>
                                    <option value="GT">GT</option>
                                    <option value="SSM">SSM</option>
                                </select>
                                <input type="text" name="address" placeholder="Address / landmark" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash xl:col-span-2">
                                <input type="text" name="latitude" placeholder="Latitude" data-gps-latitude class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="text" name="longitude" placeholder="Longitude" data-gps-longitude class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-xs text-brand-white/60 sm:col-span-2 xl:col-span-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span data-gps-status>Capture coordinates at the outlet location, or leave blank only when the outlet must be corrected later.</span>
                                        <button type="button" data-gps-capture class="rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-green-300 hover:bg-green-500/20 transition">
                                            Capture Current Location
                                        </button>
                                    </div>
                                </div>
                                <label class="space-y-1 sm:col-span-2 xl:col-span-3">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">Assign Merchandiser(s)</span>
                                    <select name="assigned_user_ids[]" multiple size="4" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        @foreach($allMerchandisers as $merchandiser)
                                            <option value="{{ $merchandiser->id }}">{{ $merchandiser->name }} - {{ $merchandiser->merchandiserKd?->name ?? 'No KD' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit" class="self-end px-6 py-2.5 bg-brand-red text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition">Add Outlet</button>
                            </form>
                        </div>

                        @foreach($outletManagementKds as $kd)
                            @if($kd->outlets->count() > 0)
                                <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                                    <div class="px-5 py-3 border-b border-brand-white/10 bg-brand-white/3">
                                        <p class="text-sm font-semibold text-brand-white">{{ $kd->name }} <span class="text-brand-ash text-xs">({{ $kd->region->name ?? '' }})</span></p>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm min-w-[1180px]">
                                            <thead>
                                                <tr class="border-b border-brand-white/5">
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Outlet</th>
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Channel / Code</th>
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Address</th>
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Coordinates</th>
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assigned Merchandisers</th>
                                                    <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Registered</th>
                                                    <th class="px-5 py-2 text-right text-[10px] uppercase tracking-widest text-brand-ash">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($kd->outlets as $outlet)
                                                    @php
                                                        $outletEditFormId = 'outlet-edit-' . $outlet->id;
                                                        $sameKdMerchandisers = $allMerchandisers->filter(fn($merchandiser) => (int) $merchandiser->kd_id === (int) $outlet->kd_id);
                                                        $assignedOutletUserIds = $outlet->assignedMerchandisers->pluck('id')->map(fn($id) => (int) $id)->all();
                                                    @endphp
                                                    <tr class="border-b border-brand-white/5 align-top" x-data="{ editing: false }" data-gps-coordinate-scope>
                                                        <td class="px-5 py-3">
                                                            <p x-show="!editing" class="text-xs font-semibold text-brand-white">{{ $outlet->name }}</p>
                                                            <div x-show="editing" class="space-y-2">
                                                                <select name="kd_id" form="{{ $outletEditFormId }}" class="w-full rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                                    @foreach($kds as $availableKd)
                                                                        <option value="{{ $availableKd->id }}" {{ (int) $outlet->kd_id === (int) $availableKd->id ? 'selected' : '' }}>{{ $availableKd->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <input type="text" name="name" form="{{ $outletEditFormId }}" value="{{ $outlet->name }}" class="w-full rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                            </div>
                                                        </td>
                                                        <td class="px-5 py-3">
                                                            <div x-show="!editing" class="space-y-1">
                                                                <span class="inline-flex rounded-full border border-brand-red/20 bg-brand-red/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-red">{{ $outlet->channel_type ?? 'N/A' }}</span>
                                                                <p class="text-[10px] font-mono text-brand-ash">{{ $outlet->code ?? 'No code' }}</p>
                                                            </div>
                                                            <div x-show="editing" class="space-y-2">
                                                                <select name="channel_type" form="{{ $outletEditFormId }}" class="w-full rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                                    <option value="">Channel</option>
                                                                    <option value="GT" {{ $outlet->channel_type === 'GT' ? 'selected' : '' }}>GT</option>
                                                                    <option value="SSM" {{ $outlet->channel_type === 'SSM' ? 'selected' : '' }}>SSM</option>
                                                                </select>
                                                                <input type="text" name="code" form="{{ $outletEditFormId }}" value="{{ $outlet->code }}" placeholder="Outlet code" class="w-full rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                            </div>
                                                        </td>
                                                        <td class="px-5 py-3">
                                                            <p x-show="!editing" class="max-w-[260px] text-xs text-brand-ash">{{ $outlet->address ?? 'No address' }}</p>
                                                            <textarea x-show="editing" name="address" form="{{ $outletEditFormId }}" rows="3" class="w-full min-w-[220px] rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">{{ $outlet->address }}</textarea>
                                                        </td>
                                                        <td class="px-5 py-3">
                                                            <div x-show="!editing" class="space-y-1">
                                                                <p class="text-[10px] font-mono text-brand-ash">{{ filled($outlet->latitude) && filled($outlet->longitude) ? number_format((float) $outlet->latitude, 6) . ', ' . number_format((float) $outlet->longitude, 6) : 'GPS needed' }}</p>
                                                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $outlet->coordinates_locked_at ? 'border-green-500/20 bg-green-500/10 text-green-300' : 'border-amber-500/20 bg-amber-500/10 text-amber-200' }}">
                                                                    {{ $outlet->coordinates_locked_at ? 'Locked' : 'Unlocked' }}
                                                                </span>
                                                            </div>
                                                            <div x-show="editing" class="grid min-w-[220px] gap-2 sm:grid-cols-2">
                                                                <input type="text" name="latitude" form="{{ $outletEditFormId }}" value="{{ $outlet->latitude }}" placeholder="Latitude" data-gps-latitude class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                                <input type="text" name="longitude" form="{{ $outletEditFormId }}" value="{{ $outlet->longitude }}" placeholder="Longitude" data-gps-longitude class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                                <div class="sm:col-span-2 flex flex-col gap-2">
                                                                    <p class="text-[10px] leading-relaxed text-brand-white/45" data-gps-status>Saving coordinates here re-locks the outlet for staff-side clock-in.</p>
                                                                    <button type="button" data-gps-capture class="w-fit rounded-lg border border-green-500/30 bg-green-500/10 px-2.5 py-1.5 text-[9px] font-bold uppercase tracking-wider text-green-300 hover:bg-green-500/20 transition">
                                                                        Capture GPS
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-5 py-3">
                                                            <div x-show="!editing" class="flex max-w-[280px] flex-wrap gap-1.5">
                                                                @forelse($outlet->assignedMerchandisers as $assignedMerchandiser)
                                                                    <span class="rounded-full border border-brand-white/10 bg-brand-white/[0.04] px-2 py-1 text-[10px] font-semibold text-brand-white">{{ $assignedMerchandiser->name }}</span>
                                                                @empty
                                                                    <span class="text-xs text-amber-200">Not assigned</span>
                                                                @endforelse
                                                            </div>
                                                            <select x-show="editing" name="assigned_user_ids[]" form="{{ $outletEditFormId }}" multiple size="4" class="w-full min-w-[240px] rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                                @foreach($sameKdMerchandisers as $merchandiser)
                                                                    <option value="{{ $merchandiser->id }}" {{ in_array((int) $merchandiser->id, $assignedOutletUserIds, true) ? 'selected' : '' }}>{{ $merchandiser->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td class="px-5 py-3">
                                                            <p class="text-xs text-brand-white">{{ $outlet->created_at?->format('D, d M Y') ?? 'No date' }}</p>
                                                            <p class="mt-1 text-[10px] text-brand-ash">{{ $outlet->registeredBy?->name ?? 'Admin/System' }}</p>
                                                        </td>
                                                        <td class="px-5 py-3 text-right">
                                                            <form id="{{ $outletEditFormId }}" method="POST" action="{{ route('merchandisers.admin.outlets.update', $outlet) }}">
                                                                @csrf
                                                                @method('PUT')
                                                            </form>
                                                            <div class="flex items-center justify-end gap-2">
                                                                <button x-show="!editing" type="button" @click="editing = true" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition">Edit</button>
                                                                <button x-show="editing" type="submit" form="{{ $outletEditFormId }}" class="text-[10px] px-2.5 py-1 rounded-lg bg-green-600 text-white hover:bg-green-500 transition">Save</button>
                                                                <button x-show="editing" type="button" @click="editing=false" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition">Cancel</button>
                                                                <form method="POST" action="{{ route('merchandisers.admin.outlets.destroy', $outlet) }}" onsubmit="return confirm('Remove outlet?')">
                                                                    @csrf @method('DELETE')
                                                                    <button type="submit" class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-red/20 text-brand-red hover:bg-brand-red/40 transition">Remove</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if(false)
                    <div x-show="false" x-cloak class="hidden">
                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 mb-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash mb-4">➕ Add Outlet to KD</p>
                            <form method="POST" action="{{ route('merchandisers.admin.outlets.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @csrf
                                <select name="kd_id" required class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0">
                                    <option value="">Select KD *</option>
                                    @foreach($kds as $kd)<option value="{{ $kd->id }}">{{ $kd->name }}</option>@endforeach
                                </select>
                                <input type="text" name="name" placeholder="Outlet / Store Name *" required class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="text" name="address" placeholder="Address" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="text" name="latitude" placeholder="Latitude" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <input type="text" name="longitude" placeholder="Longitude" class="rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                                <button type="submit" class="px-6 py-2.5 bg-brand-red text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition">Add Outlet</button>
                            </form>
                        </div>

                        @foreach($kds as $kd)
                        @if($kd->outlets->count() > 0)
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden mb-4">
                            <div class="px-5 py-3 border-b border-brand-white/10 bg-brand-white/3">
                                <p class="text-sm font-semibold text-brand-white">{{ $kd->name }} <span class="text-brand-ash text-xs">({{ $kd->region->name ?? '' }})</span></p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="border-b border-brand-white/5">
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Name</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Code</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Channel</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Address</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Coords</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Registered By</th>
                                        <th class="px-5 py-2 text-left text-[10px] uppercase tracking-widest text-brand-ash">Registered</th>
                                        <th class="px-5 py-2 text-right text-[10px] uppercase tracking-widest text-brand-ash">Actions</th>
                                    </tr></thead>
                                    <tbody>
                                    @foreach($kd->outlets as $outlet)
                                    <tr class="border-b border-brand-white/5" x-data="{ editing: false }">
                                        <td class="px-5 py-2">
                                            <div x-show="!editing" class="text-brand-white">{{ $outlet->name }}</div>
                                            <div x-show="editing">
                                                <form method="POST" action="{{ route('merchandisers.admin.outlets.update', $outlet) }}" class="flex gap-2">
                                                    @csrf @method('PUT')
                                                    <input type="text" name="name" value="{{ $outlet->name }}" class="rounded-lg border border-brand-white/20 bg-brand-black text-brand-white text-xs px-2 py-1 w-32 focus:border-brand-red focus:ring-0">
                                                    <button type="submit" class="text-[10px] px-2 py-1 bg-green-600 text-white rounded-lg">Save</button>
                                                    <button type="button" @click="editing=false" class="text-[10px] px-2 py-1 bg-brand-white/10 text-brand-white rounded-lg">Cancel</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="px-5 py-2 text-brand-ash text-[10px] font-mono">{{ $outlet->code ?? '—' }}</td>
                                        <td class="px-5 py-2">
                                            <span class="inline-flex rounded-full border border-brand-red/20 bg-brand-red/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-red">{{ $outlet->channel_type ?? '—' }}</span>
                                        </td>
                                        <td class="px-5 py-2 text-brand-ash text-xs">{{ $outlet->address ?? '—' }}</td>
                                        <td class="px-5 py-2 text-brand-ash text-[10px] font-mono">{{ $outlet->latitude ? number_format($outlet->latitude,4).', '.number_format($outlet->longitude,4) : '—' }}</td>
                                        <td class="px-5 py-2 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="editing = !editing" class="text-[10px] px-2 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition">Edit</button>
                                                <form method="POST" action="{{ route('merchandisers.admin.outlets.destroy', $outlet) }}" onsubmit="return confirm('Remove outlet?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-[10px] px-2 py-1 rounded-lg bg-brand-red/20 text-brand-red hover:bg-brand-red/40 transition">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>

                    @endif

                    <!-- Pairings Tab -->
                    <div x-show="kdTab === 'pairings'" x-transition>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Assign Merchandisers to KDs & Regions</p>
                                <p class="text-xs text-brand-ash mt-1">Activates pending accounts and assigns them to a KD and Region.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Status</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Current KD</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assign / Reassign</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($allMerchandisers as $m)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3">
                                                <p class="font-medium text-brand-white">{{ $m->name }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $m->email }}</p>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="status-pill-{{ $m->status }} text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">{{ $m->status }}</span>
                                            </td>
                                            <td class="px-5 py-3 text-brand-ash text-xs">{{ $m->merchandiserKd->name ?? '—' }}</td>
                                            <td class="px-5 py-3">
                                                <form method="POST" action="{{ route('merchandisers.admin.pairings.pair', $m) }}" class="flex flex-wrap gap-2 items-center">
                                                    @csrf
                                                    <select name="kd_id" required class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        <option value="">KD *</option>
                                                        @foreach($kds as $kd)<option value="{{ $kd->id }}" {{ $m->kd_id == $kd->id ? 'selected' : '' }}>{{ $kd->name }}</option>@endforeach
                                                    </select>
                                                    <select name="region_id" required class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-1.5 focus:border-brand-red focus:ring-0">
                                                        <option value="">Region *</option>
                                                        @foreach($regions as $r)<option value="{{ $r->id }}" {{ $m->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>@endforeach
                                                    </select>
                                                    <button type="submit" class="text-[10px] px-3 py-1.5 bg-brand-red text-white rounded-lg hover:bg-red-700 transition font-bold">Pair & Activate</button>
                                                </form>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-brand-ash text-sm">No merchandisers registered yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'routes'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <input type="hidden" name="tab" value="routes">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash font-bold">Route Assignment Window</p>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">From</span>
                                    <input type="datetime-local" name="route_from" value="{{ $routeFromInput }}" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">To</span>
                                    <input type="datetime-local" name="route_to" value="{{ $routeToInput }}" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                            </div>
                            <button type="submit" class="mt-3 w-full rounded-xl bg-brand-white/10 px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-brand-white hover:bg-brand-white/15 transition">Apply Range</button>
                        </form>
                        <form method="POST" action="{{ route('merchandisers.admin.routes.generate') }}" class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            @csrf
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash font-bold">Generate Routes</p>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">From</span>
                                    <input type="datetime-local" name="generate_from" value="{{ $routeFromInput }}" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">To</span>
                                    <input type="datetime-local" name="generate_to" value="{{ $routeToInput }}" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                </label>
                            </div>
                            <button type="submit" class="mt-3 w-full rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">Generate</button>
                        </form>
                    </div>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 overflow-hidden">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Outlet Assignment Control</p>
                                <p class="mt-1 text-xs text-brand-white/45">Assign outlets by merchandiser ownership. Registered outlets are tied back to the staff member who created them.</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="flex flex-col gap-1 sm:min-w-[180px]">
                                    <input type="hidden" name="tab" value="routes">
                                    <span class="text-[10px] uppercase tracking-widest text-brand-ash">Registered Day</span>
                                    <select name="outlet_day" onchange="this.form.submit()" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        @foreach($outletDayLabels as $dayValue => $dayLabel)
                                            <option value="{{ $dayValue }}" {{ $outletRegistrationDay === (string) $dayValue ? 'selected' : '' }}>{{ $dayLabel }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('merchandisers.admin.outlet-assignments.registered') }}">
                                    @csrf
                                    <input type="hidden" name="outlet_day" value="{{ $outletRegistrationDay }}">
                                    <button type="submit" class="rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">
                                        Assign All Registered
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[1050px]">
                                <thead class="bg-brand-white/3">
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assigned KD</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assigned Outlets / Registered Days</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Assign Outlet(s)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($outletAssignmentMerchandisers as $merchandiser)
                                        @php
                                            $assignedOutletIds = $merchandiser->assignedMerchandiserOutlets->pluck('id')->map(fn($id) => (int) $id)->all();
                                            $candidateOutlets = $assignableOutlets
                                                ->filter(fn($outlet) => (int) $outlet->kd_id === (int) $merchandiser->kd_id && ! in_array((int) $outlet->id, $assignedOutletIds, true))
                                                ->values();
                                        @endphp
                                        <tr class="border-b border-brand-white/5 align-top">
                                            <td class="px-5 py-4">
                                                <p class="text-xs font-semibold text-brand-white">{{ $merchandiser->name }}</p>
                                                <p class="mt-1 text-[10px] text-brand-ash">{{ $merchandiser->email }}</p>
                                            </td>
                                            <td class="px-5 py-4 text-xs text-brand-white">
                                                {{ $merchandiser->merchandiserKd?->name ?? 'No KD assigned' }}
                                                @if($merchandiser->merchandiserRegion)
                                                    <p class="mt-1 text-[10px] text-brand-ash">{{ $merchandiser->merchandiserRegion->name }}</p>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="flex flex-wrap gap-2">
                                                    @forelse($merchandiser->assignedMerchandiserOutlets as $assignedOutlet)
                                                        <span class="inline-flex max-w-[260px] items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/[0.04] px-3 py-2">
                                                            <span class="min-w-0">
                                                                <span class="block truncate text-xs font-semibold text-brand-white">{{ $assignedOutlet->name }}</span>
                                                                <span class="block text-[10px] text-brand-white/45">{{ $assignedOutlet->created_at?->format('D, d M Y') ?? 'No date' }} · {{ $assignedOutlet->registeredBy?->name ?? 'Admin/System' }}</span>
                                                            </span>
                                                            <form method="POST" action="{{ route('merchandisers.admin.outlet-assignments.destroy', ['outlet' => $assignedOutlet, 'user' => $merchandiser]) }}" onsubmit="return confirm('Remove this outlet from {{ $merchandiser->name }}?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="rounded-lg bg-brand-red/20 px-2 py-1 text-[10px] font-bold text-brand-red hover:bg-brand-red/30">Remove</button>
                                                            </form>
                                                        </span>
                                                    @empty
                                                        <span class="text-xs text-brand-ash">No outlets assigned for this day filter.</span>
                                                    @endforelse
                                                </div>
                                            </td>
                                            <td class="px-5 py-4">
                                                @if($merchandiser->kd_id && $candidateOutlets->isNotEmpty())
                                                    <form method="POST" action="{{ route('merchandisers.admin.outlet-assignments.store') }}" class="flex min-w-[280px] flex-col gap-2">
                                                        @csrf
                                                        <input type="hidden" name="user_id" value="{{ $merchandiser->id }}">
                                                        <div class="max-h-44 space-y-1.5 overflow-y-auto rounded-xl border border-brand-white/10 bg-brand-black/60 p-2">
                                                            @foreach($candidateOutlets as $candidateOutlet)
                                                                <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-xs text-brand-white hover:bg-brand-white/5">
                                                                    <input type="checkbox" name="outlet_ids[]" value="{{ $candidateOutlet->id }}" class="mt-0.5 rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                                                    <span class="min-w-0">
                                                                        <span class="block truncate font-semibold">{{ $candidateOutlet->name }}</span>
                                                                        <span class="block text-[10px] text-brand-white/45">{{ $candidateOutlet->created_at?->format('D d M') }} - {{ $candidateOutlet->registeredBy?->name ?? 'Admin/System' }}</span>
                                                                    </span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                        <p class="text-[10px] leading-relaxed text-brand-white/45">Tick one or more outlets, then assign. Use Assign All Shown for the current day filter.</p>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <button type="submit" class="rounded-xl border border-green-500/20 bg-green-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-green-300 hover:bg-green-500/20">Assign Checked</button>
                                                            <button type="submit" onclick="this.form.querySelectorAll('input[name=&quot;outlet_ids[]&quot;]').forEach((input) => input.checked = true)" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Assign All Shown</button>
                                                        </div>
                                                    </form>
                                                @elseif(! $merchandiser->kd_id)
                                                    <span class="text-xs text-amber-200">Assign KD first</span>
                                                @else
                                                    <span class="text-xs text-brand-ash">No unassigned outlets for this filter.</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-brand-ash">No active merchandisers available for outlet assignment.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(method_exists($outletAssignmentMerchandisers, 'hasPages') && $outletAssignmentMerchandisers->hasPages())
                            <div class="border-t border-brand-white/10 px-5 py-4">
                                {{ $outletAssignmentMerchandisers->links() }}
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.04] p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash">Total Assignments</p>
                            <p class="mt-2 text-3xl font-display text-brand-white">{{ $routeSummary['total'] }}</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-green-500/20 bg-green-500/10 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-green-300">Completed</p>
                            <p class="mt-2 text-3xl font-display text-green-300">{{ $routeSummary['completed'] }}</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-sky-500/20 bg-sky-500/10 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-sky-200">Due Today</p>
                            <p class="mt-2 text-3xl font-display text-sky-200">{{ $routeSummary['pending_today'] }}</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-amber-500/20 bg-amber-500/10 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-amber-200">Future Planned</p>
                            <p class="mt-2 text-3xl font-display text-amber-200">{{ $routeSummary['future_planned'] }}</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-red-500/25 bg-red-500/10 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-red-200">Missed / Overdue</p>
                            <p class="mt-2 text-3xl font-display text-red-200">{{ $routeSummary['overdue'] }}</p>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            <p class="text-[10px] uppercase tracking-widest text-brand-ash">Completion Rate</p>
                            <p class="mt-2 text-3xl font-display text-brand-white">{{ $routeSummary['completion_rate'] }}%</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Daily Route Volume</p>
                            <div class="mt-4 h-72">
                                <canvas id="routeDailyChart"></canvas>
                            </div>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Route Status Breakdown</p>
                            <div class="mt-4 h-72">
                                <canvas id="routeStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Top Merchandiser Route Load</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm min-w-[620px]">
                                    <thead class="bg-brand-white/3">
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Total</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Completed</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Overdue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($routeMerchandiserStats as $row)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 text-xs font-semibold text-brand-white">{{ $row->name }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-brand-white">{{ $row->total }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-green-300">{{ $row->completed }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-red-200">{{ $row->overdue }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-brand-ash">No route workload data in this range.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">KD Route Coverage</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm min-w-[620px]">
                                    <thead class="bg-brand-white/3">
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">KD</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Total</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Completed</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Planned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($routeKdStats as $row)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 text-xs font-semibold text-brand-white">{{ $row->name }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-brand-white">{{ $row->total }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-green-300">{{ $row->completed }}</td>
                                                <td class="px-5 py-3 text-center text-xs text-amber-200">{{ $row->planned }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-brand-ash">No KD route data in this range.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Route Assignments</p>
                                <p class="text-xs text-brand-white/45 mt-1">Showing {{ $routeAssignments->firstItem() ?? 0 }}-{{ $routeAssignments->lastItem() ?? 0 }} of {{ $routeAssignmentsTotal }} rows for {{ $routeFrom->format('d M Y, H:i') }} to {{ $routeTo->format('d M Y, H:i') }}.</p>
                            </div>
                            <a href="{{ route('merchandisers.admin.dashboard', ['tab' => 'merchandisers']) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Edit Targets</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[900px]">
                                <thead class="bg-brand-white/3">
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Date</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Outlet</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">KD</th>
                                        <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Seq</th>
                                        <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($routeAssignments as $assignment)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3 text-xs text-brand-white">
                                                @if($assignment->assigned_start_at)
                                                    <p>{{ $assignment->assigned_start_at->format('D, d M') }}</p>
                                                    <p class="text-[10px] text-brand-ash">{{ $assignment->assigned_start_at->format('H:i') }} - {{ $assignment->assigned_end_at?->format('H:i') ?? '23:59' }}</p>
                                                @else
                                                    <p>{{ $assignment->assigned_date->format('D, d M') }}</p>
                                                    <p class="text-[10px] text-brand-ash">Legacy daily row</p>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3">
                                                <p class="text-xs font-semibold text-brand-white">{{ $assignment->user?->name ?? 'Unknown' }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $assignment->user?->email }}</p>
                                            </td>
                                            <td class="px-5 py-3">
                                                <p class="text-xs font-semibold text-brand-white">{{ $assignment->outlet?->name ?? 'Outlet removed' }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $assignment->outlet?->address ?? $assignment->outlet?->code }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-xs text-brand-ash">{{ $assignment->outlet?->keyDistributor?->name ?? 'N/A' }}</td>
                                            <td class="px-5 py-3 text-center text-xs text-brand-white">{{ $assignment->sequence }}</td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $assignment->status === 'completed' ? 'border-green-500/20 bg-green-500/10 text-green-300' : 'border-amber-500/20 bg-amber-500/10 text-amber-200' }}">{{ $assignment->status }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-brand-ash">No route assignments for this period yet. Generate routes to prepare the plan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-brand-white/10 px-5 py-4">
                            {{ $routeAssignments->links() }}
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'forms'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Assign Google Form</p>
                            <form method="POST" action="{{ route('merchandisers.admin.google-forms.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <input name="title" required placeholder="Form name" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2">
                                <input name="google_form_url" type="url" placeholder="Google Form URL" value="{{ old('google_form_url', 'https://docs.google.com/forms/d/e/1FAIpQLSfAKE-pKp82legHbJ5qza-R0lTVZ6fagvzG669Lc3PPDaHS6Q/viewform') }}" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2">
                                <select name="assigned_user_id" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">All merchandisers</option>
                                    @foreach($allMerchandisers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                                </select>
                                <select name="kd_id" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any KD</option>
                                    @foreach($kds as $kd)<option value="{{ $kd->id }}">{{ $kd->name }}</option>@endforeach
                                </select>
                                <select name="outlet_id" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any outlet</option>
                                    @foreach($kds as $kd)
                                        @foreach($kd->outlets as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach
                                    @endforeach
                                </select>
                                <select name="channel_type" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any channel</option>
                                    <option value="GT">GT</option>
                                    <option value="SSM">SSM</option>
                                    <option value="LMT">LMT</option>
                                    <option value="PHARMACY">Pharmacy</option>
                                    <option value="COSMETICS">Cosmetics</option>
                                </select>
                                <select name="brand_id" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any brand</option>
                                    @foreach($brandOptions as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                                </select>
                                <select name="campaign_id" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any campaign</option>
                                    @foreach($campaignOptions as $campaign)<option value="{{ $campaign->id }}">{{ $campaign->name }}</option>@endforeach
                                </select>
                                <input name="category" placeholder="Category e.g. Perfect Store / Price Check" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2">
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                                    <input type="hidden" name="google_enabled" value="0">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-brand-white">
                                        <input type="checkbox" name="google_enabled" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                        Allow Google Form
                                    </label>
                                </div>
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                                    <input type="hidden" name="native_enabled" value="0">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-brand-white">
                                        <input type="checkbox" name="native_enabled" value="1" checked class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                        Allow inbuilt form
                                    </label>
                                </div>
                                <select name="native_template_key" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0 sm:col-span-2">
                                    <option value="perfect_store_v1">Perfect Store 2.0 Native Mirror</option>
                                </select>
                                <input type="date" name="starts_on" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                <input type="date" name="ends_on" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                <input type="hidden" name="status" value="active">
                                <textarea name="description" rows="3" placeholder="Notes / instructions" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2"></textarea>
                                <button type="submit" class="rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition sm:col-span-2">Save Form Assignment</button>
                            </form>
                        </div>

                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Planogram Reference</p>
                            <form method="POST" action="{{ route('merchandisers.admin.planograms.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <input name="title" required placeholder="Planogram title" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2">
                                <input name="category" placeholder="Category e.g. Pharmacy / Cosmetics" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                <select name="channel_type" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Any channel</option>
                                    <option value="GT">GT</option>
                                    <option value="SSM">SSM</option>
                                    <option value="LMT">LMT</option>
                                    <option value="PHARMACY">Pharmacy</option>
                                    <option value="COSMETICS">Cosmetics</option>
                                </select>
                                <input type="file" name="reference_file" accept=".jpg,.jpeg,.png,.webp,.pdf,.ppt,.pptx" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white sm:col-span-2">
                                <textarea name="checklist_items" rows="4" placeholder="Checklist items, one per line" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2"></textarea>
                                <textarea name="playbook_notes" rows="3" placeholder="Playbook notes" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0 sm:col-span-2"></textarea>
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition sm:col-span-2">Save Planogram</button>
                            </form>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <div class="xl:col-span-2 glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash">Assigned Google Forms</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm min-w-[760px]">
                                    <thead class="bg-brand-white/3">
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Form</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Scope</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Completed</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($googleForms as $form)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3">
                                                    @if($form->google_form_url)
                                                        <a href="{{ $form->google_form_url }}" target="_blank" rel="noopener" class="text-xs font-semibold text-brand-white hover:text-brand-red">{{ $form->title }}</a>
                                                    @else
                                                        <p class="text-xs font-semibold text-brand-white">{{ $form->title }}</p>
                                                    @endif
                                                    <p class="text-[10px] text-brand-ash">{{ $form->starts_on?->format('d M') ?? 'Anytime' }} - {{ $form->ends_on?->format('d M Y') ?? 'Open' }}</p>
                                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                                        @if($form->google_enabled)
                                                            <span class="rounded-full border border-sky-400/20 bg-sky-500/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-sky-200">Google</span>
                                                        @endif
                                                        @if($form->native_enabled)
                                                            <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-200">Inbuilt</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-5 py-3 text-xs text-brand-ash">
                                                    {{ $form->assignedUser?->name ?? 'All merchandisers' }} /
                                                    {{ $form->keyDistributor?->name ?? 'Any KD' }} /
                                                    {{ $form->outlet?->name ?? 'Any outlet' }} /
                                                    {{ $form->channel_type ?? 'Any channel' }} /
                                                    {{ $form->brand?->name ?? 'Any brand' }} /
                                                    {{ $form->campaign?->name ?? 'Any campaign' }} /
                                                    {{ $form->category ?? 'Any category' }}
                                                </td>
                                                <td class="px-5 py-3 text-center text-xs font-bold text-green-300">
                                                    <span class="block">{{ $form->submissions_count }} Google</span>
                                                    <span class="mt-1 block text-emerald-300">{{ $form->native_submissions_count }} Inbuilt</span>
                                                </td>
                                                <td class="px-5 py-3 text-right">
                                                    <form method="POST" action="{{ route('merchandisers.admin.google-forms.destroy', $form) }}" onsubmit="return confirm('Deactivate this form assignment?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="rounded-lg bg-brand-red/20 px-2.5 py-1 text-[10px] font-bold text-brand-red hover:bg-brand-red/30">Deactivate</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-brand-ash">No Google Forms assigned yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-black/40 p-5 space-y-4">
                            <p class="text-xs uppercase tracking-widest text-brand-ash">Perfect Store References</p>
                            @foreach($perfectStoreGuides as $channel => $items)
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                                    <p class="text-xs font-bold text-brand-white">{{ $channel }}</p>
                                    <p class="mt-1 text-[11px] leading-relaxed text-brand-white/50">{{ implode(', ', $items) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                        <div class="px-5 py-4 border-b border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash">Saved Planograms</p>
                        </div>
                        <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                            @forelse($planograms as $planogram)
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-brand-white">{{ $planogram->title }}</p>
                                            <p class="text-[10px] text-brand-ash">{{ $planogram->category ?? 'General' }} / {{ $planogram->channel_type ?? 'Any channel' }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('merchandisers.admin.planograms.destroy', $planogram) }}" onsubmit="return confirm('Remove this planogram?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red text-xs font-bold">Remove</button>
                                        </form>
                                    </div>
                                    @if($planogram->reference_file_path)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($planogram->reference_file_path) }}" target="_blank" rel="noopener" class="mt-3 inline-flex rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Open Reference</a>
                                    @endif
                                    @if($planogram->checklist)
                                        <ul class="mt-3 space-y-1 text-[11px] text-brand-white/55">
                                            @foreach(array_slice($planogram->checklist, 0, 5) as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-brand-ash md:col-span-2 xl:col-span-3">No planogram references saved yet.</p>
                            @endforelse
                        </div>
                        @if($planograms->hasPages())
                            <div class="border-t border-brand-white/10 px-5 py-4">
                                {{ $planograms->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: MANAGE MERCHANDISERS
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'merchandisers'" x-transition>

                    <!-- Search & Filter -->
                    <div class="flex flex-wrap gap-3 mb-5">
                        <input x-model="merch_search" type="text" placeholder="Search by name or email…"
                            class="flex-1 min-w-0 rounded-xl border border-brand-white/10 bg-brand-white/5 text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0 placeholder-brand-ash">
                        <select x-model="merch_filter" class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-4 py-2.5 focus:border-brand-red focus:ring-0">
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-brand-white/10 bg-brand-black/35 p-2">
                            <input type="hidden" name="tab" value="merchandisers">
                            <label class="sr-only" for="coverage_month">Coverage month</label>
                            <input id="coverage_month" type="month" name="coverage_month" value="{{ $coverageMonth }}"
                                   class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-3 py-2 focus:border-brand-red focus:ring-0">
                            <label class="sr-only" for="coverage_week">Coverage week</label>
                            <input id="coverage_week" type="week" name="coverage_week" value="{{ $coverageWeek }}"
                                   class="rounded-xl border border-brand-white/10 bg-brand-black text-brand-white text-sm px-3 py-2 focus:border-brand-red focus:ring-0">
                            <button type="submit" class="rounded-xl bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">Filter Coverage</button>
                        </form>
                    </div>
                    <p class="mb-3 text-xs text-brand-white/45">
                        Outlet coverage period: {{ $coverageStart->format('d M Y') }} — {{ $coverageEnd->format('d M Y') }}
                    </p>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden w-full">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-sm" style="min-width:900px">
                                <thead class="bg-brand-white/3">
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Merchandiser</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Status</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">KD / Region</th>
                                        <th class="px-4 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Visits</th>
                                        <th class="px-4 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Outlets Covered</th>
                                        <th class="px-4 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Monthly Salary</th>
                                        <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                @forelse($allMerchandisers as $m)
                                <tbody x-data="{ expanded: false }">
                                    <tr class="border-b border-brand-white/5 hover:bg-brand-white/3 transition"
                                        x-show="
                                            (merch_filter === 'all' || merch_filter === '{{ $m->status }}') &&
                                            (merch_search === '' || '{{ strtolower($m->name . ' ' . $m->email) }}'.includes(merch_search.toLowerCase()))
                                        ">
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-brand-white/10 flex items-center justify-center text-xs font-bold text-brand-white shrink-0 border border-brand-white/10">{{ strtoupper(substr($m->name,0,1)) }}</div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-brand-white text-xs">{{ $m->name }}</p>
                                                    <p class="text-[10px] text-brand-ash truncate max-w-[160px]">{{ $m->email }}</p>
                                                    <p class="text-[10px] text-brand-ash">{{ $m->phone ?? '—' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span class="status-pill-{{ $m->status }} text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">{{ $m->status }}</span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <p class="text-xs font-medium text-brand-white">{{ $m->merchandiserKd->name ?? 'Unassigned' }}</p>
                                            <p class="text-[10px] text-brand-ash">{{ $m->merchandiserRegion->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-500/10 text-blue-400 text-xs font-bold">{{ $m->merchandiser_visits_count }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-500/10 text-green-400 text-xs font-bold">{{ $m->total_outlets_covered }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($m->salary)
                                                <p class="text-xs font-semibold text-brand-white">GHS {{ number_format($m->salary,2) }}</p>
                                            @else
                                                <p class="text-xs text-brand-ash/50 italic">Not set</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button @click="expanded = !expanded"
                                                    class="text-[10px] px-2.5 py-1 rounded-lg bg-brand-white/10 text-brand-white hover:bg-brand-white/20 transition shrink-0">
                                                    <span x-text="expanded ? '▲ Close' : '▼ Details'"></span>
                                                </button>
                                                @if($m->status === 'active')
                                                <form method="POST" action="{{ route('merchandisers.admin.merchandisers.suspend', $m) }}">
                                                    @csrf
                                                    <button type="submit" class="text-[10px] px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-400 hover:bg-amber-500/40 transition shrink-0">Suspend</button>
                                                </form>
                                                @else
                                                <form method="POST" action="{{ route('merchandisers.admin.merchandisers.activate', $m) }}">
                                                    @csrf
                                                    <button type="submit" class="text-[10px] px-2.5 py-1 rounded-lg bg-green-500/20 text-green-400 hover:bg-green-500/40 transition shrink-0">Activate</button>
                                                </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Expanded Details Row -->
                                    <tr x-show="expanded" x-transition class="border-b border-brand-white/5 bg-brand-white/[0.02]">
                                        <td colspan="7" class="px-4 py-5">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                                <!-- Set Salary -->
                                                <div class="bg-brand-black/50 rounded-xl p-4 border border-brand-white/10">
                                                    <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-3 font-semibold">💰 Set Monthly Payroll</p>
                                                    <form method="POST" action="{{ route('merchandisers.admin.payroll.set', $m) }}" class="flex gap-2">
                                                        @csrf
                                                        <input type="number" name="salary" step="0.01" min="0" value="{{ $m->salary }}"
                                                            placeholder="GHS amount"
                                                            class="flex-1 min-w-0 rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-3 py-2 focus:border-brand-red focus:ring-0">
                                                        <button type="submit" class="shrink-0 px-3 py-2 bg-brand-red text-white text-[10px] font-bold rounded-lg hover:bg-red-700 transition">Set</button>
                                                    </form>
                                                </div>
                                                <!-- Reassign -->
                                                <div class="bg-brand-black/50 rounded-xl p-4 border border-brand-white/10">
                                                    <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-3 font-semibold">🔗 Assign / Reassign</p>
                                                    <form method="POST" action="{{ route('merchandisers.admin.merchandisers.reassign', $m) }}" class="flex flex-col gap-2">
                                                        @csrf
                                                        <select name="kd_id" class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-2 focus:border-brand-red focus:ring-0">
                                                            <option value="">No KD</option>
                                                            @foreach($kds as $kd)<option value="{{ $kd->id }}" {{ $m->kd_id == $kd->id ? 'selected' : '' }}>{{ $kd->name }}</option>@endforeach
                                                        </select>
                                                        <select name="region_id" class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-2 focus:border-brand-red focus:ring-0">
                                                            <option value="">No Region</option>
                                                            @foreach($regions as $r)<option value="{{ $r->id }}" {{ $m->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>@endforeach
                                                        </select>
                                                        <button type="submit" class="w-full py-1.5 bg-brand-red text-white text-[10px] font-bold rounded-lg hover:bg-red-700 transition">Update Assignment</button>
                                                    </form>
                                                </div>
                                                <div class="bg-brand-black/50 rounded-xl p-4 border border-brand-white/10">
                                                    <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-3 font-semibold">Route Schedule</p>
                                                    <form method="POST" action="{{ route('merchandisers.admin.merchandisers.route-settings', $m) }}" class="space-y-3">
                                                        @csrf
                                                        @php
                                                            $workingDays = collect($m->merchandiser_working_days ?? [1,2,3,4,5])->map(fn($day) => (int) $day)->all();
                                                            $routeTargetValue = old('merchandiser_daily_outlet_target', (int) ($m->merchandiser_daily_outlet_target ?? 0) === 8 ? '' : $m->merchandiser_daily_outlet_target);
                                                        @endphp
                                                        <div class="grid grid-cols-7 gap-1">
                                                            @foreach([1 => 'M', 2 => 'T', 3 => 'W', 4 => 'T', 5 => 'F', 6 => 'S', 7 => 'S'] as $dayValue => $dayLabel)
                                                                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-lg border border-brand-white/10 bg-brand-white/[0.03] px-1 py-2 text-[10px] text-brand-white/70">
                                                                    <input type="checkbox" name="merchandiser_working_days[]" value="{{ $dayValue }}" {{ in_array($dayValue, $workingDays, true) ? 'checked' : '' }} class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                                                    {{ $dayLabel }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <input type="number" name="merchandiser_daily_outlet_target" min="1" value="{{ $routeTargetValue }}" class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-2 focus:border-brand-red focus:ring-0" placeholder="Auto daily stops">
                                                            <select name="merchandiser_outlet_frequency" class="rounded-lg border border-brand-white/10 bg-brand-black text-brand-white text-xs px-2 py-2 focus:border-brand-red focus:ring-0">
                                                                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly'] as $frequency => $label)
                                                                    <option value="{{ $frequency }}" {{ ($m->merchandiser_outlet_frequency ?? 'weekly') === $frequency ? 'selected' : '' }}>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <p class="text-[10px] leading-relaxed text-brand-white/45">Leave daily stops blank to auto-spread all KD outlets across the selected working days and frequency.</p>
                                                        <button type="submit" class="w-full py-1.5 bg-brand-red text-white text-[10px] font-bold rounded-lg hover:bg-red-700 transition">Save Route Settings</button>
                                                    </form>
                                                </div>
                                                <!-- Personal Info -->
                                                <div class="bg-brand-black/50 rounded-xl p-4 border border-brand-white/10">
                                                    <p class="text-[10px] uppercase tracking-widest text-brand-ash mb-3 font-semibold">👤 Profile Info</p>
                                                    <div class="space-y-2 text-xs">
                                                        <div class="flex justify-between gap-2"><span class="text-brand-ash shrink-0">Joined</span><span class="text-brand-white text-right">{{ $m->created_at->format('d M Y') }}</span></div>
                                                        <div class="flex justify-between gap-2"><span class="text-brand-ash shrink-0">Phone</span><span class="text-brand-white text-right">{{ $m->phone ?? '—' }}</span></div>
                                                        <div class="flex justify-between gap-2"><span class="text-brand-ash shrink-0">Bank</span><span class="text-brand-white text-right truncate max-w-[120px]">{{ $m->bank_name ?? '—' }}</span></div>
                                                        <div class="flex justify-between gap-2"><span class="text-brand-ash shrink-0">A/C No.</span><span class="text-brand-white text-right font-mono">{{ $m->bank_account_number ?? '—' }}</span></div>
                                                        <div class="flex justify-between gap-2"><span class="text-brand-ash shrink-0">MoMo</span><span class="text-brand-white text-right font-mono">{{ $m->momo_number ?? '—' }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                @empty
                                <tbody>
                                    <tr><td colspan="7" class="px-5 py-10 text-center text-brand-ash text-sm">No merchandisers found.</td></tr>
                                </tbody>
                                @endforelse
                            </table>
                        </div>
                        @if(method_exists($allMerchandisers, 'hasPages') && $allMerchandisers->hasPages())
                            <div class="border-t border-brand-white/10 px-5 py-4">
                                {{ $allMerchandisers->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: ASSET MANAGEMENT
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'assets'" x-transition>
                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                        <div class="px-5 py-4 border-b border-brand-white/10 flex items-center justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash">All Field Gear & POSM Check-Outs</p>
                                <p class="text-xs text-brand-ash mt-0.5">Entered by merchandisers. {{ $allAssetsTotal }} total entries.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-brand-white/10">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Date</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Item</th>
                                        <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Qty Out</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Location</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Notes</th>
                                        <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Photo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allAssets as $asset)
                                    <tr class="border-b border-brand-white/5">
                                        <td class="px-5 py-3 text-brand-ash text-xs whitespace-nowrap">{{ $asset->created_at->format('d M Y') }}</td>
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-brand-white text-xs">{{ $asset->createdBy->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-brand-white text-sm font-medium">{{ $asset->item_name }}</td>
                                        <td class="px-5 py-3 text-center">
                                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400">{{ $asset->quantity_out }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-brand-ash text-xs">{{ $asset->location ?? '—' }}</td>
                                        <td class="px-5 py-3 text-brand-ash text-xs max-w-[200px]">
                                            <div class="line-clamp-2">{{ strip_tags($asset->notes ?? '—') }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            @if($asset->image_path)
                                                <a href="{{ Storage::url($asset->image_path) }}" target="_blank" class="inline-block">
                                                    <img src="{{ Storage::url($asset->image_path) }}" alt="Proof" class="w-10 h-10 rounded-lg object-cover border border-brand-white/20 hover:border-brand-red/50 transition cursor-pointer">
                                                </a>
                                            @else
                                                <span class="text-brand-ash/40 text-xs">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="px-5 py-10 text-center text-brand-ash text-sm">No field gear check-outs recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($allAssets->hasPages())
                            <div class="border-t border-brand-white/10 px-5 py-4">
                                {{ $allAssets->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     TAB: NOTIFICATIONS & APPROVALS
                ════════════════════════════════════════════════════════════ -->
                <div x-show="activeTab === 'supervisors'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                        <div class="xl:col-span-2 glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-5">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-brand-ash">Supervisor accountability</p>
                                    <h3 class="text-xl font-display text-brand-white mt-1">PJP, KDs, Merchandisers & Compliance</h3>
                                    <p class="text-xs text-brand-ash mt-1">Brands Team promotes/demotes merchandiser supervisors, assigns their coverage, and reviews PJP activity. Supervisors upload weekly PJPs from their own supervisor view.</p>
                                </div>
                                <span class="inline-flex w-fit rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                    {{ $supervisorCandidates->count() }} supervisors
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <form method="POST" action="{{ route('merchandisers.admin.supervisors.assign') }}" class="rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4 space-y-3">
                                    @csrf
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-brand-ash font-bold">Assign supervisor coverage</p>
                                        <p class="mt-1 text-[10px] leading-relaxed text-brand-white/45">Choose one supervisor, then tick every KD and merchandiser they cover. One supervisor can manage many KDs and many merchandisers.</p>
                                    </div>
                                    <select name="supervisor_id" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        <option value="">Select supervisor *</option>
                                        @foreach($supervisorCandidates as $supervisor)
                                            <option value="{{ $supervisor->id }}">{{ $supervisor->name }} — Merchandiser Supervisor</option>
                                        @endforeach
                                    </select>
                                    <fieldset class="block">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <span class="block text-[10px] uppercase tracking-wider text-brand-ash">Merchandisers under supervisor</span>
                                            <span class="shrink-0 rounded-full border border-brand-white/10 bg-brand-white/[0.04] px-2 py-0.5 text-[9px] text-brand-white/40">{{ $allMerchandisers->count() }} available</span>
                                        </div>
                                        <div class="max-h-48 space-y-2 overflow-y-auto rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 scrollbar-none">
                                            @foreach($allMerchandisers as $m)
                                                <label class="flex items-start gap-2 rounded-lg border border-brand-white/5 bg-brand-white/[0.02] p-2 text-xs text-brand-white transition hover:bg-brand-white/[0.05]">
                                                    <input type="checkbox" name="merchandiser_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, old('merchandiser_ids', []))) class="mt-0.5 rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                                    <span class="min-w-0">
                                                        <span class="block break-words font-semibold">{{ $m->name }}</span>
                                                        <span class="block break-words text-[10px] text-brand-ash">{{ $m->merchandiserKd->name ?? 'No KD' }}</span>
                                                        @if($m->isMerchandiserSupervisor())
                                                            <span class="mt-1 inline-flex rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider text-amber-300">Supervisor account</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <p class="mt-1 text-[9px] text-brand-white/35">Tick all merchandisers this supervisor should monitor.</p>
                                    </fieldset>
                                    <fieldset class="block">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <span class="block text-[10px] uppercase tracking-wider text-brand-ash">KDs supervised</span>
                                            <span class="shrink-0 rounded-full border border-brand-white/10 bg-brand-white/[0.04] px-2 py-0.5 text-[9px] text-brand-white/40">{{ $kds->count() }} available</span>
                                        </div>
                                        <div class="max-h-44 space-y-2 overflow-y-auto rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 scrollbar-none">
                                            @foreach($kds as $kd)
                                                <label class="flex items-start gap-2 rounded-lg border border-brand-white/5 bg-brand-white/[0.02] p-2 text-xs text-brand-white transition hover:bg-brand-white/[0.05]">
                                                    <input type="checkbox" name="kd_ids[]" value="{{ $kd->id }}" @checked(in_array($kd->id, old('kd_ids', []))) class="mt-0.5 rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                                    <span class="min-w-0">
                                                        <span class="block break-words font-semibold">{{ $kd->name }}</span>
                                                        <span class="block break-words text-[10px] text-brand-ash">{{ $kd->region->name ?? 'No region' }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <p class="mt-1 text-[9px] text-brand-white/35">Tick every KD this supervisor is responsible for.</p>
                                    </fieldset>
                                    <button type="submit" class="w-full rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">Save Supervisor Assignment</button>
                                </form>

                                @if($currentUserCanUploadPjp)
                                    <form method="POST" action="{{ route('merchandisers.admin.pjps.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4 space-y-3">
                                        @csrf
                                        <div>
                                            <p class="text-[10px] uppercase tracking-widest text-brand-ash font-bold">Upload weekly PJP</p>
                                            <p class="mt-1 text-[10px] text-green-300">Supervisor: {{ auth()->user()->name }}</p>
                                        </div>
                                        <input type="text" name="title" required placeholder="PJP title / market route" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="date" name="week_start" required class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                            <input type="date" name="week_end" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <input type="number" step="0.00000001" name="latitude" required placeholder="Latitude *" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                            <input type="number" step="0.00000001" name="longitude" required placeholder="Longitude *" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                            <input type="number" name="radius_meters" min="25" max="1000" value="150" required class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <select name="kd_ids[]" multiple class="min-h-24 rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                                @foreach($kds as $kd)
                                                    <option value="{{ $kd->id }}">{{ $kd->name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="merchandiser_ids[]" multiple class="min-h-24 rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                                @foreach($allMerchandisers as $m)
                                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="file" name="pjp_file" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white file:mr-3 file:rounded-lg file:border-0 file:bg-brand-red file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                                        <textarea name="notes" rows="2" placeholder="PJP notes / strict geofence instructions" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0"></textarea>
                                        <button type="submit" class="w-full rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">Upload PJP</button>
                                    </form>
                                @else
                                    <div class="rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4 space-y-4">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-widest text-brand-ash font-bold">Weekly PJP Upload</p>
                                            <p class="mt-2 text-xs leading-relaxed text-brand-white/60">This entry form is only visible to promoted merchandiser supervisors. Brands Team can review supervisor PJP activity, statuses, files, and clock-in logs below.</p>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3 text-center">
                                                <p class="text-xl font-black text-brand-white">{{ $pjps->count() }}</p>
                                                <p class="text-[9px] uppercase tracking-wider text-brand-ash">PJPs</p>
                                            </div>
                                            <div class="rounded-xl border border-green-500/20 bg-green-500/10 p-3 text-center">
                                                <p class="text-xl font-black text-green-300">{{ $pjps->where('status', 'active')->count() }}</p>
                                                <p class="text-[9px] uppercase tracking-wider text-green-200/70">Active</p>
                                            </div>
                                            <div class="rounded-xl border border-blue-500/20 bg-blue-500/10 p-3 text-center">
                                                <p class="text-xl font-black text-blue-300">{{ $clockPjpCount }}</p>
                                                <p class="text-[9px] uppercase tracking-wider text-blue-200/70">Filtered</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 space-y-4">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Supervisor role management</p>
                                <p class="mt-1 text-[10px] text-brand-white/45">{{ $supervisorManageMerchandisers->total() }} active merchandiser{{ $supervisorManageMerchandisers->total() === 1 ? '' : 's' }} {{ $supervisorRoleSearch !== '' ? 'matching your search' : 'available' }}. Supervisors remain merchandisers with extra privileges.</p>
                            </div>

                            <form method="GET" action="{{ route('merchandisers.admin.dashboard') }}" class="space-y-2">
                                <input type="hidden" name="tab" value="supervisors">
                                <label for="supervisor-role-search" class="sr-only">Search supervisor role management</label>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input id="supervisor-role-search" type="search" name="supervisor_role_search" value="{{ $supervisorRoleSearch }}" placeholder="Search name, email, phone, KD, region..."
                                        class="min-w-0 flex-1 rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 rounded-xl bg-brand-red px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-white hover:bg-red-700 sm:flex-none">Search</button>
                                        @if($supervisorRoleSearch !== '')
                                            <a href="{{ route('merchandisers.admin.dashboard', ['tab' => 'supervisors']) }}" class="flex-1 rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-center text-[10px] font-bold uppercase tracking-widest text-brand-ash hover:text-brand-white sm:flex-none">Clear</a>
                                        @endif
                                    </div>
                                </div>
                            </form>

                            <div class="space-y-3">
                            @forelse($supervisorManageMerchandisers as $m)
                                <div class="flex flex-col gap-3 rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-brand-white break-words">{{ $m->name }}</p>
                                        <p class="text-[10px] text-brand-ash break-words">{{ $m->merchandiserKd->name ?? 'No KD' }}</p>
                                        @if($m->isMerchandiserSupervisor())
                                            <span class="mt-2 inline-flex rounded-full border border-green-500/20 bg-green-500/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-green-300">Supervisor</span>
                                        @endif
                                    </div>
                                    @if($m->isMerchandiserSupervisor())
                                        <form method="POST" action="{{ route('merchandisers.admin.supervisors.demote', $m) }}" onsubmit="return confirm('Remove supervisor privileges from {{ addslashes($m->name) }}?')">
                                            @csrf
                                            <button type="submit" class="w-full rounded-lg bg-brand-red/20 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red hover:bg-brand-red/30 sm:w-auto">Remove Supervisor</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('merchandisers.admin.merchandisers.promote-supervisor', $m) }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-lg bg-amber-500/20 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-amber-300 hover:bg-amber-500/30 sm:w-auto">Make Supervisor</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-4 text-xs text-brand-ash">No active merchandisers match this search.</p>
                            @endforelse
                            </div>

                            @if($supervisorManageMerchandisers->total() > 0)
                                <div class="flex flex-col gap-3 rounded-2xl border border-brand-white/10 bg-brand-black/30 p-3 text-[10px] text-brand-ash sm:flex-row sm:items-center sm:justify-between">
                                    <p>
                                        Showing {{ $supervisorManageMerchandisers->firstItem() }}–{{ $supervisorManageMerchandisers->lastItem() }}
                                        of {{ $supervisorManageMerchandisers->total() }}
                                    </p>
                                    <div class="flex items-center justify-between gap-2 sm:justify-end">
                                        @if($supervisorManageMerchandisers->onFirstPage())
                                            <span class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-1.5 text-brand-white/25">Prev</span>
                                        @else
                                            <a href="{{ $supervisorManageMerchandisers->previousPageUrl() }}" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-1.5 text-brand-white hover:bg-brand-white/10">Prev</a>
                                        @endif

                                        <span class="rounded-lg border border-brand-white/10 bg-brand-white/[0.03] px-3 py-1.5 text-brand-white/60">
                                            Page {{ $supervisorManageMerchandisers->currentPage() }} of {{ $supervisorManageMerchandisers->lastPage() }}
                                        </span>

                                        @if($supervisorManageMerchandisers->hasMorePages())
                                            <a href="{{ $supervisorManageMerchandisers->nextPageUrl() }}" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-1.5 text-brand-white hover:bg-brand-white/10">Next</a>
                                        @else
                                            <span class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-1.5 text-brand-white/25">Next</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if($activePjpForCurrentUser)
                                <div class="rounded-2xl border border-green-500/20 bg-green-500/10 p-4">
                                    <p class="text-[10px] uppercase tracking-widest text-green-300 font-bold">Your active PJP</p>
                                    <p class="mt-1 text-sm font-semibold text-brand-white">{{ $activePjpForCurrentUser->title }}</p>
                                    @if($currentUserPjpClockin)
                                        <p class="mt-2 rounded-xl border border-green-500/20 bg-green-500/10 px-3 py-2 text-xs font-bold text-green-300">Clocked today at {{ $currentUserPjpClockin->clocked_in_at->format('H:i') }}</p>
                                    @else
                                        <form method="POST" action="{{ route('merchandisers.admin.supervisors.pjp-clock-in') }}" class="mt-3 space-y-2" data-clock-in-form>
                                            @csrf
                                            <input type="hidden" name="pjp_id" value="{{ $activePjpForCurrentUser->id }}">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="number" step="0.00000001" name="latitude" required placeholder="Your latitude" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                                <input type="number" step="0.00000001" name="longitude" required placeholder="Your longitude" class="rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                            </div>
                                            <button type="submit" data-clock-in-submit class="w-full rounded-xl bg-green-500 px-4 py-2 text-xs font-black uppercase tracking-widest text-black">Clock into PJP</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Supervisor performance metrics</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10 bg-brand-white/3">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Supervisor</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Merchs</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">KDs</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Clockins</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Outlets Covered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($supervisorStats as $stat)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 font-semibold text-brand-white">{{ $stat['user']->name }}</td>
                                                <td class="px-5 py-3 text-center text-blue-300 font-bold">{{ $stat['assigned_merchandisers'] }}</td>
                                                <td class="px-5 py-3 text-center text-amber-300 font-bold">{{ $stat['assigned_kds'] }}</td>
                                                <td class="px-5 py-3 text-center text-green-300 font-bold">{{ $stat['clockins'] }}</td>
                                                <td class="px-5 py-3 text-center text-brand-white font-bold">{{ $stat['outlets_covered'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-5 py-8 text-center text-brand-ash text-sm">No supervisor metrics yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold mb-4">Send compliance query</p>
                            <form method="POST" action="{{ route('merchandisers.admin.compliance-queries.store') }}" class="space-y-3">
                                @csrf
                                <select name="user_id" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="">Select merchandiser / supervisor *</option>
                                    @foreach($allMerchandisers as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }} — Merchandiser</option>
                                    @endforeach
                                    @foreach($supervisorCandidates as $supervisor)
                                        <option value="{{ $supervisor->id }}">{{ $supervisor->name }} — Supervisor</option>
                                    @endforeach
                                </select>
                                <select name="channel" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="in_app">In-app notification</option>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="email_sms">Email + SMS</option>
                                </select>
                                <div class="grid grid-cols-2 gap-2 text-xs text-brand-white/70">
                                    <label class="flex items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2"><input type="checkbox" name="issues[]" value="Missed clock-in" class="rounded bg-brand-black text-brand-red"> Missed clock-in</label>
                                    <label class="flex items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2"><input type="checkbox" name="issues[]" value="Outlet coverage gap" class="rounded bg-brand-black text-brand-red"> Outlet coverage gap</label>
                                    <label class="flex items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2"><input type="checkbox" name="issues[]" value="Core KPI gap" class="rounded bg-brand-black text-brand-red"> Core KPI gap</label>
                                    <label class="flex items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2"><input type="checkbox" name="issues[]" value="GPS compliance" class="rounded bg-brand-black text-brand-red"> GPS compliance</label>
                                </div>
                                <input type="text" name="subject" required placeholder="Query subject" class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0">
                                <textarea name="message" rows="4" required placeholder="Explain what they need to correct..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white placeholder-brand-ash focus:border-brand-red focus:ring-0"></textarea>
                                <button type="submit" class="w-full rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-white hover:bg-red-700 transition">Send Query</button>
                            </form>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Weekly PJPs</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[900px] text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10 bg-brand-white/3">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">PJP</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Supervisor</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Week</th>
                                            <th class="px-5 py-3 text-center text-[10px] uppercase tracking-widest text-brand-ash">Status</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pjps as $pjp)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3">
                                                    <p class="font-semibold text-brand-white">{{ $pjp->title }}</p>
                                                    <p class="text-[10px] text-brand-ash">Radius: {{ $pjp->radius_meters }}m · {{ number_format((float) $pjp->latitude, 5) }}, {{ number_format((float) $pjp->longitude, 5) }}</p>
                                                </td>
                                                <td class="px-5 py-3 text-xs text-brand-white">{{ $pjp->supervisor?->name ?? '—' }}</td>
                                                <td class="px-5 py-3 text-xs text-brand-ash">{{ $pjp->week_start?->format('d M') }} — {{ $pjp->week_end?->format('d M Y') ?? 'open' }}</td>
                                                <td class="px-5 py-3 text-center"><span class="rounded-full border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] font-bold uppercase text-brand-white">{{ $pjp->status }}</span></td>
                                                <td class="px-5 py-3">
                                                    <div class="flex justify-end gap-2">
                                                        @if($pjp->status === 'draft')
                                                            <form method="POST" action="{{ route('merchandisers.admin.pjps.forward', $pjp) }}">@csrf<button type="submit" class="rounded-lg bg-blue-500/20 px-3 py-1.5 text-[10px] font-bold text-blue-300">Forward</button></form>
                                                        @endif
                                                        @if($pjp->status !== 'active')
                                                            <form method="POST" action="{{ route('merchandisers.admin.pjps.activate', $pjp) }}">@csrf<button type="submit" class="rounded-lg bg-green-500/20 px-3 py-1.5 text-[10px] font-bold text-green-300">Activate</button></form>
                                                        @endif
                                                        @if($pjp->file_path)
                                                            <a href="{{ Storage::url($pjp->file_path) }}" target="_blank" class="rounded-lg bg-brand-white/10 px-3 py-1.5 text-[10px] font-bold text-brand-white">File</a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-5 py-8 text-center text-brand-ash text-sm">No PJPs uploaded yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="px-5 py-4 border-b border-brand-white/10">
                                <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">PCM / PJP Clock-in logs - {{ $clockRangeLabel }}</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10 bg-brand-white/3">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">User</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Type</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Location</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($todayPcmClockins as $clock)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 text-brand-white text-xs font-semibold">{{ $clock->user?->name ?? '—' }}</td>
                                                <td class="px-5 py-3 text-amber-300 text-xs font-bold">PCM/KD</td>
                                                <td class="px-5 py-3 text-brand-ash text-xs">{{ $clock->keyDistributor?->name ?? '—' }} · {{ number_format((float) $clock->distance_from_kd, 1) }}m</td>
                                                <td class="px-5 py-3 text-right text-brand-white text-xs">{{ $clock->clocked_in_at->format('H:i') }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($todayPjpClockins as $clock)
                                            <tr class="border-b border-brand-white/5">
                                                <td class="px-5 py-3 text-brand-white text-xs font-semibold">{{ $clock->user?->name ?? '—' }}</td>
                                                <td class="px-5 py-3 text-green-300 text-xs font-bold">PJP</td>
                                                <td class="px-5 py-3 text-brand-ash text-xs">{{ $clock->pjp?->title ?? '—' }} · {{ number_format((float) $clock->distance_from_pjp, 1) }}m</td>
                                                <td class="px-5 py-3 text-right text-brand-white text-xs">{{ $clock->clocked_in_at->format('H:i') }}</td>
                                            </tr>
                                        @endforeach
                                        @if($todayPcmClockins->isEmpty() && $todayPjpClockins->isEmpty())
                                            <tr><td colspan="4" class="px-5 py-8 text-center text-brand-ash text-sm">No PCM or PJP clock-ins for the selected range.</td></tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                        <div class="px-5 py-4 border-b border-brand-white/10">
                            <p class="text-xs uppercase tracking-widest text-brand-ash font-bold">Recent compliance queries</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px] text-sm">
                                <thead>
                                    <tr class="border-b border-brand-white/10 bg-brand-white/3">
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">To</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Subject</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Channel</th>
                                        <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Delivery</th>
                                        <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($complianceQueries as $query)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3 text-xs font-semibold text-brand-white">{{ $query->user?->name ?? '—' }}</td>
                                            <td class="px-5 py-3 text-xs text-brand-white">{{ $query->subject }}<p class="mt-1 text-[10px] text-brand-ash line-clamp-1">{{ $query->message }}</p></td>
                                            <td class="px-5 py-3 text-xs uppercase text-amber-300">{{ $query->channel }}</td>
                                            <td class="px-5 py-3 text-xs text-brand-ash">Email: {{ $query->email_sent ? 'sent' : '—' }} · SMS: {{ $query->sms_attempted ? ($query->sms_sent ? 'sent' : 'failed/not configured') : '—' }}</td>
                                            <td class="px-5 py-3 text-right text-xs text-brand-ash">{{ $query->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-5 py-8 text-center text-brand-ash text-sm">No compliance queries sent yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'notifications'" x-transition>

                    <!-- Sub-tabs -->
                    <div class="flex gap-2 mb-5 flex-wrap">
                        <button @click="notifTab = 'leaves'"
                            :class="notifTab === 'leaves' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'"
                            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">
                            📅 Leaves <span class="ml-1 bg-white/20 text-white text-[9px] px-1.5 py-0.5 rounded-full">{{ $pendingLeaves }}</span>
                        </button>
                        <button @click="notifTab = 'claims'"
                            :class="notifTab === 'claims' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'"
                            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">
                            💰 Claims <span class="ml-1 bg-white/20 text-white text-[9px] px-1.5 py-0.5 rounded-full">{{ $pendingClaims }}</span>
                        </button>
                        <button @click="notifTab = 'loans'"
                            :class="notifTab === 'loans' ? 'bg-brand-red text-white' : 'bg-brand-white/5 text-brand-ash hover:text-brand-white'"
                            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">
                            💵 Loans <span class="ml-1 bg-white/20 text-white text-[9px] px-1.5 py-0.5 rounded-full">{{ $pendingLoans }}</span>
                        </button>
                    </div>

                    <!-- Leaves -->
                    <div x-show="notifTab === 'leaves'" x-transition>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Type</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Dates</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Reason</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pendingLeavesList as $leave)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3">
                                                <p class="font-medium text-brand-white">{{ $leave->user->name ?? '—' }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $leave->created_at->diffForHumans() }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-brand-white text-xs capitalize">{{ $leave->leave_type ?? 'Annual' }}</td>
                                            <td class="px-5 py-3 text-brand-ash text-xs">{{ \Carbon\Carbon::parse($leave->start_date)->format('d M') }} – {{ \Carbon\Carbon::parse($leave->end_date)->format('d M Y') }}</td>
                                            <td class="px-5 py-3 text-brand-ash text-xs max-w-[200px]"><div class="line-clamp-2">{{ strip_tags($leave->reason ?? '—') }}</div></td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <form method="POST" action="{{ route('merchandisers.admin.leaves.approve', $leave) }}">
                                                        @csrf
                                                        <button type="submit" class="text-[10px] px-3 py-1.5 bg-green-500/20 text-green-400 font-bold rounded-lg hover:bg-green-500/40 transition">Approve</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('merchandisers.admin.leaves.reject', $leave) }}">
                                                        @csrf
                                                        <button type="submit" class="text-[10px] px-3 py-1.5 bg-brand-red/20 text-brand-red font-bold rounded-lg hover:bg-brand-red/40 transition">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="5" class="px-5 py-8 text-center text-brand-ash text-sm">✅ No pending leave applications.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingLeavesList->hasPages())
                                <div class="border-t border-brand-white/10 px-5 py-4">
                                    {{ $pendingLeavesList->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Claims -->
                    <div x-show="notifTab === 'claims'" x-transition>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Description</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Amount</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pendingClaimsList as $claim)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3">
                                                <p class="font-medium text-brand-white">{{ $claim->user->name ?? '—' }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $claim->created_at->diffForHumans() }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-brand-ash text-xs max-w-[250px]"><div class="line-clamp-2">{{ strip_tags($claim->description ?? '—') }}</div></td>
                                            <td class="px-5 py-3 text-right text-brand-white font-bold">GHS {{ number_format($claim->amount ?? 0, 2) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <form method="POST" action="{{ route('merchandisers.admin.claims.approve', $claim) }}">@csrf<button type="submit" class="text-[10px] px-3 py-1.5 bg-green-500/20 text-green-400 font-bold rounded-lg hover:bg-green-500/40 transition">Approve</button></form>
                                                    <form method="POST" action="{{ route('merchandisers.admin.claims.reject', $claim) }}">@csrf<button type="submit" class="text-[10px] px-3 py-1.5 bg-brand-red/20 text-brand-red font-bold rounded-lg hover:bg-brand-red/40 transition">Reject</button></form>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-brand-ash text-sm">✅ No pending claims.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingClaimsList->hasPages())
                                <div class="border-t border-brand-white/10 px-5 py-4">
                                    {{ $pendingClaimsList->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Loans -->
                    <div x-show="notifTab === 'loans'" x-transition>
                        <div class="glass-panel rounded-2xl border border-brand-white/10 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-brand-white/10">
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Merchandiser</th>
                                            <th class="px-5 py-3 text-left text-[10px] uppercase tracking-widest text-brand-ash">Reason</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Amount</th>
                                            <th class="px-5 py-3 text-right text-[10px] uppercase tracking-widest text-brand-ash">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pendingLoansList as $loan)
                                        <tr class="border-b border-brand-white/5">
                                            <td class="px-5 py-3">
                                                <p class="font-medium text-brand-white">{{ $loan->user->name ?? '—' }}</p>
                                                <p class="text-[10px] text-brand-ash">{{ $loan->created_at->diffForHumans() }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-brand-ash text-xs max-w-[250px]"><div class="line-clamp-2">{{ strip_tags($loan->reason ?? '—') }}</div></td>
                                            <td class="px-5 py-3 text-right text-brand-white font-bold">GHS {{ number_format($loan->amount ?? 0, 2) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <form method="POST" action="{{ route('merchandisers.admin.loans.approve', $loan) }}">@csrf<button type="submit" class="text-[10px] px-3 py-1.5 bg-green-500/20 text-green-400 font-bold rounded-lg hover:bg-green-500/40 transition">Approve</button></form>
                                                    <form method="POST" action="{{ route('merchandisers.admin.loans.reject', $loan) }}">@csrf<button type="submit" class="text-[10px] px-3 py-1.5 bg-brand-red/20 text-brand-red font-bold rounded-lg hover:bg-brand-red/40 transition">Reject</button></form>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-brand-ash text-sm">✅ No pending loan requests.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingLoansList->hasPages())
                                <div class="border-t border-brand-white/10 px-5 py-4">
                                    {{ $pendingLoansList->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </main>
        </div><!-- /main -->
    </div><!-- /layout -->
</div><!-- /app -->

<script>
const adminChartsAvailable = typeof Chart !== 'undefined';
if (adminChartsAvailable) {
    Chart.defaults.color = 'rgba(255,255,255,0.72)';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.1)';
}

const merchKpiChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }
        }
    },
    scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 } } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 } }, beginAtZero: true, max: 100 }
    }
};

const perfectMetricRadarCtx = document.getElementById('perfectStoreMetricRadarChart');
if (perfectMetricRadarCtx && adminChartsAvailable) {
    new Chart(perfectMetricRadarCtx, {
        type: 'radar',
        data: {
            labels: @json($perfectMetricLabels ?? []),
            datasets: [
                {
                    label: 'Actual',
                    data: @json($perfectMetricValues ?? []),
                    backgroundColor: 'rgba(239,68,68,0.16)',
                    borderColor: 'rgba(239,68,68,0.9)',
                    borderWidth: 2,
                    pointBackgroundColor: '#ef4444',
                },
                {
                    label: 'Target',
                    data: @json($perfectTargetValues ?? []),
                    backgroundColor: 'rgba(34,197,94,0.08)',
                    borderColor: 'rgba(34,197,94,0.7)',
                    borderDash: [4, 4],
                    borderWidth: 1.5,
                    pointBackgroundColor: '#22c55e',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } } }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(255,255,255,0.08)' },
                    angleLines: { color: 'rgba(255,255,255,0.08)' },
                    pointLabels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } },
                    ticks: { display: false }
                }
            }
        }
    });
}

const perfectMerchCtx = document.getElementById('perfectStoreMerchChart');
if (perfectMerchCtx && adminChartsAvailable) {
    const labels = @json($perfectMerchChartLabels ?? []);
    const scores = @json($perfectMerchChartScores ?? []);
    new Chart(perfectMerchCtx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['No data'],
            datasets: [{
                label: 'Score',
                data: scores.length ? scores : [0],
                backgroundColor: 'rgba(14,165,233,0.55)',
                borderColor: 'rgba(14,165,233,0.95)',
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: { ...merchKpiChartOptions, indexAxis: 'y' }
    });
}

const perfectKdCtx = document.getElementById('perfectStoreKdChart');
if (perfectKdCtx && adminChartsAvailable) {
    const labels = @json($perfectKdChartLabels ?? []);
    const scores = @json($perfectKdChartScores ?? []);
    new Chart(perfectKdCtx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['No data'],
            datasets: [{
                label: 'Score',
                data: scores.length ? scores : [0],
                backgroundColor: 'rgba(167,139,250,0.55)',
                borderColor: 'rgba(167,139,250,0.95)',
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: { ...merchKpiChartOptions, indexAxis: 'y' }
    });
}

const routeDailyCtx = document.getElementById('routeDailyChart');
if (routeDailyCtx && adminChartsAvailable) {
    new Chart(routeDailyCtx, {
        type: 'bar',
        data: {
            labels: @json($routeDailyChart['labels']),
            datasets: [
                {
                    label: 'Total',
                    data: @json($routeDailyChart['total']),
                    backgroundColor: 'rgba(59,130,246,0.42)',
                    borderColor: 'rgba(59,130,246,0.9)',
                    borderWidth: 1.2,
                    borderRadius: 5,
                },
                {
                    label: 'Completed',
                    data: @json($routeDailyChart['completed']),
                    backgroundColor: 'rgba(34,197,94,0.5)',
                    borderColor: 'rgba(34,197,94,0.9)',
                    borderWidth: 1.2,
                    borderRadius: 5,
                },
                {
                    label: 'Planned',
                    data: @json($routeDailyChart['planned']),
                    backgroundColor: 'rgba(245,158,11,0.45)',
                    borderColor: 'rgba(245,158,11,0.9)',
                    borderWidth: 1.2,
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 } } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 }, stepSize: 1 }, beginAtZero: true }
            }
        }
    });
}

const routeStatusCtx = document.getElementById('routeStatusChart');
if (routeStatusCtx && adminChartsAvailable) {
    new Chart(routeStatusCtx, {
        type: 'doughnut',
        data: {
            labels: @json($routeStatusChart['labels']),
            datasets: [{
                data: @json($routeStatusChart['data']),
                backgroundColor: [
                    'rgba(34,197,94,0.68)',
                    'rgba(14,165,233,0.62)',
                    'rgba(245,158,11,0.62)',
                    'rgba(239,68,68,0.62)'
                ],
                borderColor: ['#22c55e', '#0ea5e9', '#f59e0b', '#ef4444'],
                borderWidth: 1.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }
                }
            }
        }
    });
}

// ── Attendance Chart ───────────────────────────────────────────────────────
window.initMerchandiserAttendanceChart = function(root = document) {
    const attCtx = root.querySelector ? root.querySelector('#attendanceChart') : document.getElementById('attendanceChart');
    if (!attCtx || typeof Chart === 'undefined') {
        return;
    }

    const labels = JSON.parse(attCtx.dataset.chartLabels || '[]');
    const values = JSON.parse(attCtx.dataset.chartValues || '[]');
    Chart.getChart(attCtx)?.destroy();

    new Chart(attCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Clock-Ins',
                data: values,
                backgroundColor: 'rgba(220,38,38,0.55)',
                borderColor: 'rgba(220,38,38,0.9)',
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.45)', font: { size: 11 } } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.45)', font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
            }
        }
    });
};

window.initMerchandiserAttendanceChart(document);
document.addEventListener('cmih:silent-content-updated', (event) => {
    window.initMerchandiserAttendanceChart(event.detail?.region || document);
});

// ── Merchandiser Status Breakdown Chart ────────────────────────────────────
const statusCtx = document.getElementById('statusChart');
if (statusCtx && adminChartsAvailable) {
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Pending', 'Suspended'],
            datasets: [{
                data: [{{ $activeMerchandisers }}, {{ $pendingMerchandisers }}, {{ $suspendedMerchandisers }}],
                backgroundColor: [
                    'rgba(34,197,94,0.65)',
                    'rgba(245,158,11,0.65)',
                    'rgba(239,68,68,0.65)'
                ],
                borderColor: [
                    '#22c55e',
                    '#f59e0b',
                    '#ef4444'
                ],
                borderWidth: 1.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }
                }
            }
        }
    });
}

// ── Visits by KD Chart ─────────────────────────────────────────────────────
const kdCtx = document.getElementById('kdVisitsChart');
if (kdCtx && adminChartsAvailable) {
    new Chart(kdCtx, {
        type: 'bar',
        data: {
            labels: @json(array_keys($visitsByKd)),
            datasets: [{
                label: 'Visits',
                data: @json(array_values($visitsByKd)),
                backgroundColor: 'rgba(59,130,246,0.65)',
                borderColor: '#3b82f6',
                borderWidth: 1.5,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 } } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 9 }, stepSize: 1 }, beginAtZero: true }
            }
        }
    });
}

// ── POSM / Assets Distribution Chart ───────────────────────────────────────
const assetsCtx = document.getElementById('assetsChart');
if (assetsCtx && adminChartsAvailable) {
    new Chart(assetsCtx, {
        type: 'pie',
        data: {
            labels: @json(array_keys($assetsByItem)),
            datasets: [{
                data: @json(array_values($assetsByItem)),
                backgroundColor: [
                    'rgba(168,85,247,0.65)',
                    'rgba(236,72,153,0.65)',
                    'rgba(6,182,212,0.65)',
                    'rgba(20,184,166,0.65)',
                    'rgba(249,115,22,0.65)'
                ],
                borderWidth: 1.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }
                }
            }
        }
    });
}

// ── Live Tracking Map (Google Maps) ───────────────────────────────────────
let googleMap = null;
let mapInitialized = false;
let merchandiserMapMarkers = {};
let merchandiserInfoWindow = null;

function readMerchandiserMapLocations() {
    const source = document.querySelector('[data-merchandiser-map-locations]');

    if (!source) return [];

    try {
        return JSON.parse(source.textContent || '[]');
    } catch (error) {
        console.warn('Unable to read merchandiser map locations.', error);
        return [];
    }
}

function merchandiserInfoHtml(m, color) {
    return `
        <div style="font-family:'Sora',sans-serif; padding:8px 4px; min-width:180px; background:#0f0f0f; color:#fff; border-radius:10px;">
            <p style="font-weight:700; font-size:14px; margin:0 0 6px;">${m.name}</p>
            <p style="font-size:11px; color:rgba(255,255,255,0.6); margin:2px 0;">📞 ${m.phone}</p>
            <p style="font-size:11px; color:rgba(255,255,255,0.6); margin:2px 0;">⏱️ ${m.last_seen}</p>
            <p style="font-size:10px; color:rgba(255,255,255,0.45); margin:6px 0 0;">${Number(m.latitude).toFixed(6)}, ${Number(m.longitude).toFixed(6)}</p>
            <p style="margin-top:8px;">
                <span style="background:${m.clocked_in ? '#16a34a33' : '#92400e33'};color:${color};border:1px solid ${color}55;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:700;">
                    ${m.clocked_in ? '✅ Clocked In' : '⏳ Not Clocked In'}
                </span>
            </p>
        </div>
    `;
}

function focusMerchandiserOnMap(merchandiserId) {
    if (!googleMap) {
        tryInitMap();
        setTimeout(() => focusMerchandiserOnMap(merchandiserId), 200);
        return;
    }

    const markerRecord = merchandiserMapMarkers[String(merchandiserId)];
    if (!googleMap || !markerRecord) return;

    const { marker, data, color } = markerRecord;
    googleMap.panTo(marker.getPosition());
    googleMap.setZoom(Math.max(googleMap.getZoom() || 0, 19));

    if (typeof googleMap.setTilt === 'function') {
        googleMap.setTilt(45);
    }

    if (!merchandiserInfoWindow) {
        merchandiserInfoWindow = new google.maps.InfoWindow();
    }

    merchandiserInfoWindow.setContent(merchandiserInfoHtml(data, color));
    merchandiserInfoWindow.open(googleMap, marker);

    const mapEl = document.getElementById('admin-map');
    if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function initAdminMap() {
    const mapEl = document.getElementById('admin-map');
    if (!mapEl || mapInitialized) return;
    if (typeof google === 'undefined' || !google.maps) return;
    mapInitialized = true;
    const locations = readMerchandiserMapLocations();

    googleMap = new google.maps.Map(mapEl, {
        center: { lat: 5.6037, lng: -0.1870 }, // Accra default
        zoom: 11,
        mapTypeId: 'roadmap',
        styles: [
            { elementType: 'geometry', stylers: [{ color: '#1a1a2e' }] },
            { elementType: 'labels.text.fill', stylers: [{ color: '#8ec3b9' }] },
            { elementType: 'labels.text.stroke', stylers: [{ color: '#1a3646' }] },
            { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#334155' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#293859' }] },
            { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#1f2a40' }] },
            { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9ca5b3' }] },
            { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#374264' }] },
            { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2f3948' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0e1626' }] },
            { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#4e6d70' }] },
            { featureType: 'poi', stylers: [{ visibility: 'off' }] },
        ],
        disableDefaultUI: false,
        zoomControl: true,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true,
    });

    const bounds = new google.maps.LatLngBounds();
    let hasPoints = false;
    merchandiserMapMarkers = {};
    merchandiserInfoWindow = new google.maps.InfoWindow();

    locations.forEach(m => {
        if (!m.latitude || !m.longitude) return;
        hasPoints = true;

        const color = m.clocked_in ? '#4ade80' : '#fbbf24';
        const bgColor = m.clocked_in ? '#16a34a' : '#b45309';

        // Custom SVG marker
        const svgMarker = {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: bgColor,
            fillOpacity: 0.85,
            strokeColor: color,
            strokeWeight: 2.5,
            scale: 14,
        };

        const marker = new google.maps.Marker({
            position: { lat: m.latitude, lng: m.longitude },
            map: googleMap,
            icon: svgMarker,
            title: m.name,
            label: {
                text: m.name.charAt(0).toUpperCase(),
                color: '#ffffff',
                fontSize: '11px',
                fontWeight: 'bold',
            },
        });

        merchandiserMapMarkers[String(m.id)] = { marker, data: m, color };

        marker.addListener('click', () => {
            focusMerchandiserOnMap(m.id);
        });

        bounds.extend({ lat: m.latitude, lng: m.longitude });
    });

    if (hasPoints) {
        googleMap.fitBounds(bounds);
        const listener = google.maps.event.addListener(googleMap, 'idle', () => {
            if (googleMap.getZoom() > 14) googleMap.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

// Init map when Google Maps API is ready and tracking tab is shown
function tryInitMap() {
    if (window._googleMapsReady) {
        initAdminMap();
    } else {
        window.addEventListener('google-maps-ready', initAdminMap, { once: true });
    }
}

function refreshAdminMap() {
    googleMap = null;
    mapInitialized = false;
    merchandiserMapMarkers = {};
    merchandiserInfoWindow = null;

    const mapEl = document.getElementById('admin-map');
    if (mapEl) {
        mapEl.innerHTML = '';
    }

    tryInitMap();
}

document.addEventListener('cmih:silent-content-updated', (event) => {
    const region = event.detail?.region;
    if (!region) return;

    if (region.matches?.('[data-silent-region="merch-live-tracking"]') || region.querySelector?.('#admin-map')) {
        refreshAdminMap();
    }
});

// Alpine.js tab watcher
document.addEventListener('alpine:initialized', () => {
    Alpine.effect(() => {
        const comp = Alpine.$data(document.querySelector('[x-data]'));
        if (comp && comp.activeTab === 'tracking') {
            setTimeout(tryInitMap, 80);
        }
    });
});
window.addEventListener('load', () => {
    const comp = document.querySelector('[x-data]');
    if (comp && Alpine.$data(comp).activeTab === 'tracking') tryInitMap();
});

document.addEventListener('DOMContentLoaded', () => {
    function setCoordinateStatus(scope, message, tone = 'muted') {
        const status = scope.querySelector('[data-gps-status]');
        if (!status) return;

        status.textContent = message;
        status.classList.remove('text-green-300', 'text-red-300', 'text-amber-200');
        if (tone === 'success') status.classList.add('text-green-300');
        if (tone === 'error') status.classList.add('text-red-300');
        if (tone === 'warning') status.classList.add('text-amber-200');
    }

    document.querySelectorAll('[data-gps-capture]').forEach((button) => {
        button.addEventListener('click', () => {
            const scope = button.closest('[data-gps-coordinate-scope]');
            if (!scope) return;

            const latitudeInput = scope.querySelector('[data-gps-latitude]');
            const longitudeInput = scope.querySelector('[data-gps-longitude]');
            if (!latitudeInput || !longitudeInput) return;

            if (!navigator.geolocation) {
                setCoordinateStatus(scope, 'This browser does not support GPS capture. Enter verified coordinates manually.', 'error');
                return;
            }

            const originalText = button.textContent;
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.textContent = 'Capturing...';
            setCoordinateStatus(scope, 'Requesting location permission...', 'warning');

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitudeInput.value = position.coords.latitude.toFixed(8);
                    longitudeInput.value = position.coords.longitude.toFixed(8);
                    setCoordinateStatus(scope, 'GPS captured. Save this record to apply the geofence coordinates.', 'success');
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                    button.textContent = originalText;
                },
                (error) => {
                    let message = 'GPS capture failed. Allow location access or enter verified coordinates manually.';
                    if (error.code === error.PERMISSION_DENIED) message = 'Location permission was denied. Enable location access, then try again.';
                    if (error.code === error.POSITION_UNAVAILABLE) message = 'Location is unavailable from this device right now.';
                    if (error.code === error.TIMEOUT) message = 'Location request timed out. Move outdoors or try again.';
                    setCoordinateStatus(scope, message, 'error');
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                    button.textContent = originalText;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    });

    document.querySelectorAll('[data-clock-in-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-clock-in-submit]');
            if (!button) return;

            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.innerHTML = 'Clocking In...';
        });
    });
});
</script>

</body>
</html>
