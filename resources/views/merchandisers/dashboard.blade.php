<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Merchandiser Field Dashboard - CMIH Africa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .leaflet-container {
            background-color: #0b0a0a !important;
        }
        /* Dark mode theme overrides for CKEditor 5 */
        :root {
            --ck-color-rect-border: rgba(255, 255, 255, 0.1) !important;
            --ck-color-base-border: rgba(255, 255, 255, 0.1) !important;
            --ck-color-toolbar-background: rgba(20, 20, 20, 0.95) !important;
            --ck-color-base-background: rgba(0, 0, 0, 0.5) !important;
            --ck-color-button-default-hover-background: rgba(255, 255, 255, 0.1) !important;
            --ck-color-button-on-background: rgba(255, 255, 255, 0.15) !important;
            --ck-color-button-on-hover-background: rgba(255, 255, 255, 0.2) !important;
            --ck-color-list-background: rgba(20, 20, 20, 0.95) !important;
            --ck-color-panel-background: rgba(20, 20, 20, 0.95) !important;
            --ck-color-panel-border: rgba(255, 255, 255, 0.1) !important;
            --ck-color-dropdown-panel-background: rgba(20, 20, 20, 0.95) !important;
            --ck-color-dropdown-panel-border: rgba(255, 255, 255, 0.1) !important;
        }
        .ck-editor__editable_inline {
            background-color: rgba(0, 0, 0, 0.4) !important;
            color: #fff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            min-height: 120px !important;
            transition: min-height 0.2s ease;
            line-height: 1.7 !important;
            font-size: 0.9rem !important;
        }
        .ck-editor__editable_inline:focus {
            border-color: rgba(239, 68, 68, 0.5) !important;
            outline: none !important;
        }
        .ck.ck-editor__main>.ck-editor__editable {
            background: rgba(0, 0, 0, 0.4) !important;
        }
        .ck-toolbar {
            background-color: rgba(20, 20, 20, 0.8) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        .ck-toolbar * {
            color: #fff !important;
        }
        .ck.ck-button:not(.ck-disabled):hover, a.ck.ck-button:not(.ck-disabled):hover {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .ck.ck-button.ck-on, a.ck.ck-button.ck-on {
            background: rgba(255, 255, 255, 0.2) !important;
        }
        .ck.ck-dropdown .ck-button.ck-dropdown__button {
            background: transparent !important;
        }
        .ck.ck-list {
            background: rgba(20, 20, 20, 0.95) !important;
        }
        .ck.ck-list__item .ck-button:hover {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .ck.ck-list__item .ck-button.ck-on {
            background: rgba(255, 255, 255, 0.2) !important;
        }
        .ck.ck-placeholder::before {
            color: rgba(255, 255, 255, 0.3) !important;
        }
        .merch-shell main > [x-show],
        .merch-shell .glass-panel,
        .merch-shell section,
        .merch-shell article {
            max-width: 100%;
            min-width: 0;
        }
        .merch-shell .overflow-x-auto {
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 640px) {
            html,
            body {
                max-width: 100%;
                overflow-x: hidden;
            }
            .merch-shell main {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            .merch-shell .glass-panel {
                border-radius: 1rem;
                padding: 1rem !important;
            }
            .merch-shell h1,
            .merch-shell h2,
            .merch-shell h3 {
                overflow-wrap: anywhere;
            }
            .merch-shell .ck-editor__editable_inline {
                min-height: 180px !important;
            }
            .merch-shell .ck-toolbar {
                flex-wrap: wrap !important;
            }
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-brand-black font-sans antialiased text-brand-white">

    <div class="merch-shell h-screen overflow-hidden bg-inked"
         x-data="{ sidebarOpen: false, activeTab: 'outlets' }"
         @keydown.escape.window="sidebarOpen = false"
         x-effect="document.body.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024)">

        <!-- Global Location Error Banner -->
        <div id="gps-error-banner" class="hidden sticky top-0 z-[100] bg-red-600 border-b border-red-700 text-white px-4 py-3 shadow-xl transition-all">
            <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 animate-bounce shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="font-semibold">GPS Error:</span>
                    <span id="gps-error-text">Please enable GPS/location access. Geofenced clock-ins require location permission.</span>
                </div>
                <button onclick="pingLocation()" class="px-3 py-1 bg-white text-red-700 hover:bg-red-50 text-xs font-bold rounded-lg uppercase tracking-wider transition-all">
                    Retry Connection
                </button>
            </div>
        </div>

        <div x-show="sidebarOpen" x-cloak
             class="fixed inset-0 z-40 bg-brand-black/70 backdrop-blur-sm lg:hidden"
             @click="sidebarOpen = false"></div>

        <div class="flex h-full min-h-0 overflow-hidden">

            <!-- Collapsible Sidebar (Portal Style) -->
            <aside id="merchandiser-sidebar"
                   aria-label="Merchandiser navigation"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                   class="fixed inset-y-0 left-0 z-50 flex h-full max-h-screen min-h-0 w-[min(18rem,calc(100vw-2rem))] shrink-0 flex-col overflow-y-auto overscroll-contain scrollbar-none border-r border-brand-white/10 bg-brand-black/95 px-4 py-6 shadow-2xl backdrop-blur-xl transition-transform duration-300 ease-out lg:static lg:h-screen lg:w-72 lg:translate-x-0 lg:bg-brand-black/80 lg:shadow-none lg:backdrop-blur-none sm:px-6 sm:py-8">
                <div class="flex items-center justify-between gap-3">
                    <x-application-logo class="h-8 w-auto" />
                    <button type="button" @click="sidebarOpen = false" class="lg:hidden text-brand-white/60 hover:text-brand-white text-lg transition-colors p-1" aria-label="Close menu">
                        ✕
                    </button>
                </div>

                <nav class="mt-8 space-y-2 text-xs uppercase tracking-[0.3em]">
                    <p class="text-[10px] uppercase font-bold tracking-[0.2em] text-brand-ash px-4 mb-2">Navigation</p>
                    
                    <button @click="activeTab = 'outlets'; sidebarOpen = false" :class="activeTab === 'outlets' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        🏬 Visits & Clock-In
                    </button>
                    <button @click="activeTab = 'profile'; sidebarOpen = false" :class="activeTab === 'profile' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        👤 Profile & Banking
                    </button>
                    <button @click="activeTab = 'payroll'; sidebarOpen = false" :class="activeTab === 'payroll' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        📊 Payroll & Deductions
                    </button>
                    <button @click="activeTab = 'leaves'; sidebarOpen = false" :class="activeTab === 'leaves' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        📅 Leaves & Absences
                    </button>
                    <button @click="activeTab = 'claims'; sidebarOpen = false" :class="activeTab === 'claims' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        💰 Petty Cash Claims
                    </button>
                    <button @click="activeTab = 'loans'; sidebarOpen = false" :class="activeTab === 'loans' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        💵 Salary Advances
                    </button>
                    <button @click="activeTab = 'appraisals'; sidebarOpen = false" :class="activeTab === 'appraisals' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        📝 Self-Appraisals
                    </button>
                    <button @click="activeTab = 'inventory'; sidebarOpen = false" :class="activeTab === 'inventory' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        📁 Field Gear Check-out
                    </button>
                    <button @click="activeTab = 'surveys'; sidebarOpen = false" :class="activeTab === 'surveys' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        📋 Active Surveys
                    </button>
                    <button @click="activeTab = 'notifications'; sidebarOpen = false" :class="activeTab === 'notifications' ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white'" class="w-full text-left px-4 py-3 rounded-xl transition flex items-center gap-2 whitespace-nowrap">
                        🔔 Notifications
                    </button>
                </nav>
            </aside>

            <!-- Main Content Area -->
            <div class="flex min-h-0 flex-1 flex-col min-w-0">

                <!-- Header / Navigation -->
                <header class="border-b border-brand-white/10 bg-brand-black/60 px-4 py-3 sm:px-6 sm:py-4 lg:px-10 sticky top-0 z-40">
                    <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3 sm:gap-4">
                        <div class="flex items-center gap-3">
                            <!-- Mobile Menu Toggle Button -->
                            <button type="button"
                                    @click.stop="sidebarOpen = true"
                                    :aria-expanded="sidebarOpen.toString()"
                                    aria-controls="merchandiser-sidebar"
                                    aria-label="Open navigation menu"
                                    class="lg:hidden inline-flex items-center rounded-full border border-brand-white/20 px-3 py-2 text-[10px] uppercase tracking-[0.2em] text-brand-white/70 sm:px-4 sm:text-xs sm:tracking-[0.3em]">
                                Menu
                            </button>
                            <span class="text-xs uppercase tracking-[0.2em] font-semibold text-brand-ash hidden sm:inline-block">Field Portal</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
                            <!-- GPS Status Badge -->
                            <div id="gps-status-pill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-brand-white/5 text-brand-white/45 border border-brand-white/10">
                                <span class="w-2 h-2 rounded-full bg-brand-white/20"></span> Connecting GPS
                            </div>
                            <!-- Theme Toggle Button -->
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
                            <!-- Logout Form -->
                            <form method="POST" action="{{ route('merchandisers.logout') }}">
                                @csrf
                                <button type="submit" class="p-2 rounded-xl text-brand-white/50 hover:text-brand-red hover:bg-brand-red/10 transition-colors" title="Log Out">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main id="merchandiser-dashboard-main"
                      data-silent-root
                      class="main-scrollbar-none min-h-0 flex-1 max-w-6xl w-full mx-auto overflow-y-auto overflow-x-hidden overscroll-contain px-4 py-5 sm:px-6 sm:py-8 lg:px-10 min-w-0 space-y-6">

                    <!-- Welcome Banner -->
                    <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between hover:border-brand-red/20 transition-all duration-300">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Welcome back,</p>
                            <h1 class="text-3xl font-display text-brand-white mt-1">{{ auth()->user()->name }}</h1>
                            <p class="text-xs text-brand-white/60 mt-1">
                                📍 Region: <span class="text-brand-white font-medium">{{ auth()->user()->merchandiserRegion->name ?? 'N/A' }}</span>
                                | 🏬 KD: <span class="text-brand-white font-medium">{{ auth()->user()->merchandiserKd->name ?? 'N/A' }}</span>
                            </p>
                        </div>
                        @if(isset($error))
                            <div class="bg-brand-red/10 border border-brand-red/25 rounded-xl p-4 text-brand-red text-xs sm:max-w-xs">
                                {{ $error }}
                            </div>
                        @endif
                    </div>

                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    @if ($errors->any())
                        <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(auth()->user()->kd_id && auth()->user()->region_id)
                        <!-- Main Content Tab Panel -->
                        <div class="space-y-6">

                            <!-- TAB 1: OUTLETS & VISITS -->
                            <div x-show="activeTab === 'outlets'" x-data="{ outletSearch: '' }" style="display: none;">
                                <!-- Perfect Store Personal Scorecard -->
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                                    <div class="glass-panel rounded-2xl border border-lime-500/20 bg-lime-500/5 p-4 flex items-center justify-between shadow-lg">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-lime-300">My Facing Score</p>
                                            <p class="text-2xl font-display text-brand-white mt-1">{{ number_format($merchMetrics['facing_pct'] ?? 95, 1) }}%</p>
                                            <p class="text-[10px] text-brand-white/50 mt-0.5">Target: 95% Overall</p>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-lime-500/20 border border-lime-500/40 flex items-center justify-center text-lime-300 text-sm font-bold">
                                            📐
                                        </div>
                                    </div>

                                    <div class="glass-panel rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4 flex items-center justify-between shadow-lg">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-cyan-300">Planogram Alignment</p>
                                            <p class="text-2xl font-display text-brand-white mt-1">{{ number_format($merchMetrics['planogram_pct'] ?? 100, 1) }}%</p>
                                            <p class="text-[10px] text-brand-white/50 mt-0.5">Target: 100% Alignment</p>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-cyan-500/20 border border-cyan-500/40 flex items-center justify-center text-cyan-300 text-sm font-bold">
                                            🖼️
                                        </div>
                                    </div>

                                    <div class="glass-panel rounded-2xl border border-pink-500/20 bg-pink-500/5 p-4 flex items-center justify-between shadow-lg">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-pink-300">Share of Shelf (SOS)</p>
                                            <p class="text-2xl font-display text-brand-white mt-1">{{ number_format($merchMetrics['sos_pct'] ?? 0, 1) }}%</p>
                                            <p class="text-[10px] text-brand-white/50 mt-0.5">Category Unilever Share</p>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-pink-500/20 border border-pink-500/40 flex items-center justify-center text-pink-300 text-sm font-bold">
                                            🏷️
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <div class="lg:col-span-2 space-y-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                            <div>
                                                <h2 class="text-xl font-display text-brand-white tracking-wider">🏬 Assigned Outlets ({{ $scheduleLabel ?? ($dayLabels[$selectedDay] ?? 'Selected Day') }})</h2>
                                                <p class="mt-1 text-xs text-brand-white/45">{{ $merchMetrics['assigned_outlets_today'] }} planned for this view, {{ $merchMetrics['clockins_today'] }} clocked in, {{ $merchMetrics['outlets_scored_today'] }} scored, {{ $merchMetrics['not_covered_today'] }} not covered.</p>
                                            </div>
                                            <div class="w-full sm:w-80">
                                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Search outlets</label>
                                                <input x-model.debounce.150ms="outletSearch" type="search" placeholder="Search outlet name, code, address..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder:text-brand-white/30 focus:border-brand-red focus:ring-0">
                                            </div>
                                        </div>

                                        <!-- Day Schedule Filter Navigation -->
                                        <div class="flex flex-wrap items-center gap-1.5 p-1.5 rounded-2xl bg-brand-black/50 border border-brand-white/10 overflow-x-auto scrollbar-none">
                                            @foreach(['today' => $dayLabels['today'], '1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun', 'all' => 'All Outlets'] as $dayKey => $dayName)
                                                @php
                                                    $isCurrentDayTab = ($dayKey === 'today' && $selectedDay === 'today') || ($selectedDay === $dayKey);
                                                    $count = $dayOutletCounts[$dayKey === 'today' ? $currentIsoDay : $dayKey] ?? 0;
                                                    $isTodayPill = ($dayKey === 'today') || ($dayKey === $currentIsoDay);
                                                @endphp
                                                <a href="{{ route('merchandisers.dashboard', ['day' => $dayKey]) }}"
                                                   class="px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 whitespace-nowrap {{ $isCurrentDayTab ? 'bg-brand-red text-white shadow-lg shadow-brand-red/20 font-bold' : 'bg-brand-white/5 text-brand-white/70 hover:bg-brand-white/10 hover:text-white' }}">
                                                    <span>{{ $dayName }}</span>
                                                    @if($isTodayPill && $dayKey !== 'today')
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                    @endif
                                                    <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $isCurrentDayTab ? 'bg-black/30 text-white' : 'bg-brand-white/10 text-brand-white/60' }}">
                                                        {{ $count }}
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>

                                        <div class="glass-panel rounded-2xl p-5 border border-emerald-500/20 bg-emerald-500/5 space-y-4">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-300">Outlet Visit Window</p>
                                                    <h3 class="mt-1 text-lg font-bold text-brand-white">{{ $clockWindow['start_at']->format('g:i A') }} - {{ $clockWindow['end_at']->format('g:i A') }}</h3>
                                                    <p class="mt-1 text-xs text-brand-white/55">Clock in and clock out at every assigned outlet during this window. Perfect Store entry becomes available after the outlet clock-in.</p>
                                                </div>
                                                <div class="grid w-full grid-cols-3 gap-2 sm:w-auto sm:min-w-[22rem]">
                                                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 px-3 py-2 text-center">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Scheduled</p>
                                                        <p class="mt-1 text-lg font-black text-brand-white">{{ $merchMetrics['total_outlets'] }}</p>
                                                    </div>
                                                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 px-3 py-2 text-center">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Clocked In</p>
                                                        <p class="mt-1 text-lg font-black text-sky-300">{{ $merchMetrics['clockins_today'] }}</p>
                                                    </div>
                                                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 px-3 py-2 text-center">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Scored</p>
                                                        <p class="mt-1 text-lg font-black text-emerald-300">{{ $merchMetrics['outlets_scored_today'] }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-4">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Add outlet for {{ auth()->user()->merchandiserKd->name ?? 'your KD' }}</p>
                                                    <h3 class="text-lg font-bold text-brand-white mt-1">Register an Outlet</h3>
                                                    <p class="text-xs text-brand-white/50 mt-1">Stand at the outlet before saving. The system captures and locks your GPS automatically for future clock-ins.</p>
                                                </div>
                                                <span class="inline-flex w-fit rounded-full border border-green-500/20 bg-green-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-green-400">
                                                    GPS fills automatically
                                                </span>
                                            </div>

                                            <form method="POST" action="{{ route('merchandisers.outlets.store') }}" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3" data-requires-gps-form>
                                                @csrf
                                                <input type="hidden" name="latitude" class="user-lat-input">
                                                <input type="hidden" name="longitude" class="user-lng-input">

                                                <div class="xl:col-span-2">
                                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Outlet Name *</label>
                                                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Osu Main Shop" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder:text-brand-white/30 focus:border-brand-red focus:ring-0">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Outlet Code</label>
                                                    <input type="text" name="code" value="{{ old('code') }}" placeholder="Optional" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder:text-brand-white/30 focus:border-brand-red focus:ring-0">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Channel *</label>
                                                    <select name="channel_type" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                        <option value="GT" {{ old('channel_type', 'GT') === 'GT' ? 'selected' : '' }}>GT</option>
                                                        <option value="SSM" {{ old('channel_type') === 'SSM' ? 'selected' : '' }}>SSM</option>
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2 xl:col-span-2">
                                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Address</label>
                                                    <input type="text" name="address" value="{{ old('address') }}" placeholder="Outlet address / landmark" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder:text-brand-white/30 focus:border-brand-red focus:ring-0">
                                                </div>
                                                <div class="sm:col-span-2 xl:col-span-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <p class="text-[11px] text-brand-white/45">GPS is captured from your device. Coordinates are locked after saving and can only be corrected by admin.</p>
                                                    <button type="submit" class="rounded-xl bg-brand-red px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-600 transition-all">
                                                        Add Outlet
                                                    </button>
                                                </div>
                                            </form>
                                        </div>

                                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Outlet closure list</p>
                                                    <h3 class="mt-1 text-lg font-bold text-brand-white">{{ $merchMetrics['not_covered_today'] }} not covered of {{ $merchMetrics['total_outlets'] }}</h3>
                                                </div>
                                                <span class="inline-flex w-fit rounded-full border border-green-500/20 bg-green-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-green-400">
                                                    {{ $merchMetrics['coverage_today'] }}% scored coverage
                                                </span>
                                            </div>
                                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                                @forelse($pendingOutletsToday as $pendingOutlet)
                                                    <a href="#outlet-card-{{ $pendingOutlet->id }}" onclick="highlightOutletCard({{ $pendingOutlet->id }}); return false;" class="block rounded-xl border border-brand-white/10 bg-brand-white/[0.04] hover:bg-brand-white/[0.08] hover:border-brand-red/40 transition-all p-3 group">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <div>
                                                                <p class="text-xs font-semibold text-brand-white group-hover:text-brand-red transition-colors">{{ $pendingOutlet->name }}</p>
                                                                <p class="mt-0.5 text-[10px] text-brand-white/40">{{ $pendingOutlet->address ?: $pendingOutlet->code }}</p>
                                                            </div>
                                                            <span class="text-[10px] uppercase font-bold text-brand-red tracking-wider shrink-0 bg-brand-red/10 border border-brand-red/20 px-2 py-1 rounded-lg group-hover:bg-brand-red group-hover:text-white transition-all">
                                                                Clock-In &rarr;
                                                            </span>
                                                        </div>
                                                    </a>
                                                @empty
                                                    <div class="rounded-xl border border-green-500/20 bg-green-500/10 px-3 py-3 text-xs font-semibold text-green-400 sm:col-span-2">
                                                        Every listed outlet has been clocked in today.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>

                                        @php
                                            $assignmentsByOutlet = $todaysAssignments->keyBy('outlet_id');
                                        @endphp

                                        @forelse($outlets as $outlet)
                                            @php
                                                $timezone = auth()->user()->merchandiserRegion->timezone ?? 'Africa/Accra';
                                                $localNow = \Carbon\Carbon::now($timezone);
                                                $routeAssignment = $assignmentsByOutlet->get($outlet->id);
                                                $attendance = $outletAttendanceByOutlet->get($outlet->id);
                                                $hasClockedIn = (bool) $attendance;
                                                $hasClockedOut = (bool) ($attendance?->clock_out_time);
                                                $hasScored = $routeAssignment?->status === 'completed'
                                                    || (bool) ($routeAssignment?->visit_id)
                                                    || $scoredOutletIdsToday->contains($outlet->id);
                                                $visitOpen = $localNow->betweenIncluded($clockWindow['start_at'], $clockWindow['end_at']);
                                                $searchText = strtolower($outlet->name . ' ' . $outlet->code . ' ' . $outlet->address);
                                                $statusLabel = $hasScored ? 'Scored' : ($hasClockedOut ? 'Visited' : ($hasClockedIn ? 'Clocked In' : 'Not Covered'));
                                                $statusClass = $hasScored
                                                    ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20'
                                                    : ($hasClockedOut
                                                        ? 'bg-sky-500/10 text-sky-300 border-sky-500/20'
                                                        : ($hasClockedIn
                                                            ? 'bg-amber-500/10 text-amber-200 border-amber-500/20'
                                                            : 'bg-brand-red/10 text-brand-red border-brand-red/20'));
                                            @endphp
                                            
                                            <div id="outlet-card-{{ $outlet->id }}" x-show="outletSearch === '' || @js($searchText).includes(outletSearch.toLowerCase())" class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 hover:shadow-[0_0_15px_rgba(239,68,68,0.05)] transition-all duration-300 space-y-4">
                                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                                    <div>
                                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-brand-red/10 text-brand-red border border-brand-red/20 mb-2">
                                                            {{ $outlet->channel_type }}
                                                        </span>
                                                        @if($routeAssignment)
                                                            <span class="ml-2 inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $routeAssignment->status === 'completed' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-amber-500/10 text-amber-300 border border-amber-500/20' }}">
                                                                Stop {{ $routeAssignment->sequence }} / {{ $routeAssignment->status }}
                                                            </span>
                                                        @endif
                                                        <span class="ml-2 inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $statusClass }}">
                                                            {{ $statusLabel }}
                                                        </span>
                                                        <h3 class="text-lg font-bold text-brand-white">{{ $outlet->name }}</h3>
                                                        <p class="text-xs text-brand-white/50 mt-1">
                                                            Code: {{ $outlet->code }}
                                                            @if($outlet->address)
                                                                | {{ $outlet->address }}
                                                            @endif
                                                        </p>
                                                        <p class="mt-1 text-[10px] text-brand-white/35">
                                                            Registered by {{ $outlet->registeredBy?->name ?? 'Brands / System' }}
                                                            @if($outlet->created_at)
                                                                on {{ $outlet->created_at->format('d M Y') }}
                                                            @endif
                                                        </p>
                                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                                            @if($outlet->coordinates_locked_at)
                                                                <span class="inline-flex rounded-full border border-green-500/20 bg-green-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-green-300">GPS locked</span>
                                                            @else
                                                                <span class="inline-flex rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-200">GPS needs capture</span>
                                                                <form method="POST" action="{{ route('merchandisers.outlets.coordinates.update', $outlet) }}" class="inline-flex items-center gap-2" data-requires-gps-form>
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="latitude" class="user-lat-input">
                                                                    <input type="hidden" name="longitude" class="user-lng-input">
                                                                    <button type="submit" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-100 hover:bg-amber-500/20 transition">
                                                                        Capture GPS
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] px-4 py-2 text-xs text-brand-white/60">
                                                        PJP outlet visit
                                                    </div>
                                                </div>

                                                <!-- Outlet Visit Controls -->
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-brand-white/5">
                                                    <div class="rounded-xl border border-brand-white/5 bg-brand-black/20 p-3">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Clock-in</p>
                                                        <p class="mt-1 text-sm font-bold text-brand-white">
                                                            {{ $attendance?->clock_in_time ? $attendance->clock_in_time->timezone($timezone)->format('H:i') : 'Not started' }}
                                                        </p>
                                                    </div>
                                                    <div class="rounded-xl border border-brand-white/5 bg-brand-black/20 p-3">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Clock-out</p>
                                                        <p class="mt-1 text-sm font-bold text-brand-white">
                                                            {{ $attendance?->clock_out_time ? $attendance->clock_out_time->timezone($timezone)->format('H:i') : 'Pending' }}
                                                        </p>
                                                    </div>
                                                    <div class="rounded-xl border border-brand-white/5 bg-brand-black/20 p-3">
                                                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Visit Time</p>
                                                        <p class="mt-1 text-sm font-bold text-brand-white">
                                                            {{ $attendance?->visit_duration_minutes !== null ? $attendance->visit_duration_minutes.' min' : 'Calculates at clock-out' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col gap-2 border-t border-brand-white/5 pt-3 sm:flex-row sm:items-center">
                                                    @if(! $hasClockedIn)
                                                        @if($visitOpen)
                                                            <form method="POST" action="{{ route('merchandisers.clock-in') }}" class="sm:w-48" data-clock-form data-clock-verb="Clocking in">
                                                                @csrf
                                                                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                                                                <input type="hidden" name="clock_in_type" value="outlet">
                                                                <input type="hidden" name="latitude" class="user-lat-input">
                                                                <input type="hidden" name="longitude" class="user-lng-input">
                                                                <button type="submit" data-clock-submit class="w-full py-2.5 bg-brand-red hover:bg-red-600 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-md">
                                                                    Clock In
                                                                </button>
                                                            </form>
                                                        @else
                                                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-brand-white/35 sm:w-48">
                                                                Window Closed
                                                            </div>
                                                        @endif
                                                    @else
                                                        <a href="{{ route('merchandisers.visit', $outlet) }}" class="inline-flex justify-center rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-red-600 sm:w-56">
                                                            Perfect Store Entry
                                                        </a>
                                                    @endif

                                                    @if($hasClockedIn && ! $hasClockedOut)
                                                        @if($hasScored)
                                                            <form method="POST" action="{{ route('merchandisers.clock-out') }}" class="sm:w-48" data-clock-form data-clock-verb="Clocking out">
                                                                @csrf
                                                                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                                                                <input type="hidden" name="latitude" class="user-lat-input">
                                                                <input type="hidden" name="longitude" class="user-lng-input">
                                                                <button type="submit" data-clock-submit class="w-full py-2.5 border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-xs font-bold uppercase tracking-wider rounded-xl transition hover:bg-emerald-500/20">
                                                                    Clock Out
                                                                </button>
                                                            </form>
                                                        @else
                                                            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2.5 text-xs font-semibold text-amber-100">
                                                                Complete the Perfect Store entry before clock-out.
                                                            </div>
                                                        @endif
                                                    @elseif($hasClockedOut)
                                                        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-emerald-300 sm:w-48 text-center">
                                                            Clocked Out
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="glass-panel rounded-2xl p-8 border border-brand-white/10 bg-brand-black/40 text-center space-y-4">
                                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-white/5 text-brand-white/40 text-xl">
                                                    🏬
                                                </div>
                                                <div>
                                                    <h3 class="text-base font-bold text-brand-white">No outlets registered for your Key Distributor yet.</h3>
                                                    <p class="mt-1 text-xs text-brand-white/50 max-w-md mx-auto">
                                                        No route stops found for {{ $dayLabels[$selectedDay] ?? 'this day' }}. Use the registration form above to add your outlets under {{ auth()->user()->merchandiserKd->name ?? 'your KD' }}.
                                                    </p>
                                                </div>
                                                @if($selectedDay !== 'all')
                                                    <a href="{{ route('merchandisers.dashboard', ['day' => 'all']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-red text-white text-xs font-bold uppercase tracking-wider hover:bg-red-600 transition-all shadow-lg">
                                                        Show All Outlets ({{ $dayOutletCounts['all'] ?? 0 }})
                                                    </a>
                                                @endif
                                            </div>
                                        @endforelse
                                    </div>

                                    <!-- Right performance widgets -->
                                    <div class="space-y-4">
                                        <h2 class="text-xl font-display text-brand-white tracking-wider">📊 Stats Summary</h2>
                                        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-6">
                                            <div class="grid grid-cols-2 gap-3 text-center">
                                                <div class="p-3 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                                    <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Outlets Clocked In</p>
                                                    <p class="text-xl font-bold text-green-400 mt-1">{{ $merchMetrics['outlets_visited_today'] }}</p>
                                                    <p class="text-[10px] text-brand-white/35">of {{ $merchMetrics['total_outlets'] }}</p>
                                                </div>
                                                <div class="p-3 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                                    <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Not Covered</p>
                                                    <p class="text-xl font-bold text-amber-400 mt-1">{{ $merchMetrics['not_covered_today'] }}</p>
                                                    <p class="text-[10px] text-brand-white/35">by outlet clock-in</p>
                                                </div>
                                                <div class="p-3 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                                    <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Scored Today</p>
                                                    <p class="text-xl font-bold text-blue-400 mt-1">{{ $merchMetrics['outlets_scored_today'] }}</p>
                                                    <p class="text-[10px] text-brand-white/35">{{ $merchMetrics['coverage_today'] }}% coverage</p>
                                                </div>
                                                <div class="p-3 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                                    <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Visit Time Today</p>
                                                    <p class="text-xl font-bold text-emerald-400 mt-1">{{ $merchMetrics['total_visit_minutes_today'] }} min</p>
                                                    <p class="text-[10px] text-brand-white/35">tracked visit duration</p>
                                                </div>
                                            </div>
                                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="text-[10px] uppercase tracking-wider text-brand-white/45">Monthly outlets covered</p>
                                                    <p class="text-lg font-bold text-brand-white">{{ $merchMetrics['outlets_covered_month'] }}</p>
                                                </div>
                                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-brand-white/10">
                                                    @php
                                                        $coveragePercent = (float) ($merchMetrics['monthly_coverage_rate'] ?? 0);
                                                    @endphp
                                                    <div class="h-full rounded-full bg-brand-red" style="width: {{ $coveragePercent }}%"></div>
                                                </div>
                                                <p class="mt-2 text-[10px] text-brand-white/35">{{ $coveragePercent }}% of {{ $merchMetrics['registered_outlets'] }} registered outlets this month</p>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">Outlet coverage snapshot</h4>
                                                <div class="h-[160px]">
                                                    <canvas id="outletCoverageChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">7-day route execution</h4>
                                                <div class="h-[120px]">
                                                    <canvas id="punctualityChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">7-day coverage trend</h4>
                                                <div class="h-[120px]">
                                                    <canvas id="dailyCoverageTrendChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">Today's execution funnel</h4>
                                                <div class="h-[130px]">
                                                    <canvas id="visitFunnelChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">Live visit state</h4>
                                                <div class="h-[130px]">
                                                    <canvas id="visitStateChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-[10px] uppercase tracking-wider text-brand-white/60 font-semibold">7-day visit minutes</h4>
                                                <div class="h-[120px]">
                                                    <canvas id="visitMinutesChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 2: PROFILE & BANKING -->
                            <div x-show="activeTab === 'profile'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-6" style="display: none;">
                                <h2 class="text-xl font-display text-brand-white tracking-wider">👤 Profile & Banking settings</h2>
                                <form method="POST" action="{{ route('merchandisers.profile.update') }}" class="space-y-4" enctype="multipart/form-data">
                                    @csrf
                                    @method('PATCH')
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="name" value="Full Name" />
                                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="auth()->user()->name" required />
                                        </div>
                                        <div>
                                            <x-input-label for="email" value="Email Address" />
                                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="auth()->user()->email" required />
                                        </div>
                                        <div>
                                            <x-input-label for="phone" value="Phone Number" />
                                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="auth()->user()->phone" required />
                                        </div>
                                        <div>
                                            <x-input-label for="residential_address" value="Residential Address" />
                                            <x-text-input id="residential_address" name="residential_address" type="text" class="mt-1 block w-full" :value="auth()->user()->residential_address" required />
                                        </div>
                                    </div>

                                    <div class="border-t border-brand-white/10 pt-4 mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-3">Bank Details (Determined for Payroll payouts)</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="bank_name" value="Bank Name" />
                                                <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="auth()->user()->bank_name" />
                                            </div>
                                            <div>
                                                <x-input-label for="bank_branch" value="Bank Branch" />
                                                <x-text-input id="bank_branch" name="bank_branch" type="text" class="mt-1 block w-full" :value="auth()->user()->bank_branch" />
                                            </div>
                                            <div>
                                                <x-input-label for="bank_account_name" value="Account Holder Name" />
                                                <x-text-input id="bank_account_name" name="bank_account_name" type="text" class="mt-1 block w-full" :value="auth()->user()->bank_account_name" />
                                            </div>
                                            <div>
                                                <x-input-label for="bank_account_number" value="Account Number" />
                                                <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="mt-1 block w-full" :value="auth()->user()->bank_account_number" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-brand-white/10 pt-4 mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-3">MOMO Payout (Mobile Money Alternative)</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="momo_number" value="Momo Number" />
                                                <x-text-input id="momo_number" name="momo_number" type="text" class="mt-1 block w-full" :value="auth()->user()->momo_number" />
                                            </div>
                                            <div>
                                                <x-input-label for="momo_name" value="Registered Momo Name" />
                                                <x-text-input id="momo_name" name="momo_name" type="text" class="mt-1 block w-full" :value="auth()->user()->momo_name" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-brand-white/10 pt-4 mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-red mb-3">Change Password</h3>
                                        <p class="mb-3 text-[10px] text-brand-ash">Use more than 8 characters with at least one letter, one number, and one symbol.</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="password" value="New Password" />
                                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                            </div>
                                            <div>
                                                <x-input-label for="password_confirmation" value="Confirm New Password" />
                                                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end pt-4">
                                        <x-primary-button class="bg-brand-red hover:bg-red-600">
                                            Update Profile Credentials
                                        </x-primary-button>
                                    </div>
                                </form>
                            </div>

                            <!-- TAB 3: PAYROLL & LATENESS AUDIT -->
                            <div x-show="activeTab === 'payroll'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-6" style="display: none;">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">📊 Attendance-Based Payroll Audit</h2>
                                    <span class="text-xs px-2.5 py-1 bg-brand-red/10 text-brand-red border border-brand-red/20 rounded-lg font-bold">Month: {{ now()->format('F Y') }}</span>
                                </div>

                                <!-- Payroll Grid Cards -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="p-4 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                        <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Base Salary</p>
                                        <p class="text-2xl font-bold text-brand-white mt-1">{{ number_format($payroll['base_salary'], 2) }}</p>
                                        <p class="text-[9px] text-brand-white/30 mt-1">Determined by Brands Team Admin</p>
                                    </div>
                                    <div class="p-4 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                        <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Punctuality Work Rate</p>
                                        <p class="text-2xl font-bold text-brand-red mt-1">{{ $payroll['work_rate'] }}%</p>
                                        <p class="text-[9px] text-brand-white/30 mt-1">Goal target: 95% minimum</p>
                                    </div>
                                    <div class="p-4 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                        <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Calculated Deductions</p>
                                        <p class="text-2xl font-bold text-brand-red mt-1">-{{ number_format($payroll['deductions'], 2) }}</p>
                                        <p class="text-[9px] text-brand-white/30 mt-1">Based on late & missed slots</p>
                                    </div>
                                    <div class="p-4 bg-brand-white/5 rounded-xl border border-brand-white/5">
                                        <p class="text-[10px] text-brand-white/40 uppercase tracking-wider">Net Payment Payout</p>
                                        <p class="text-2xl font-bold text-green-400 mt-1">{{ number_format($payroll['net_pay'], 2) }}</p>
                                        <p class="text-[9px] text-brand-white/30 mt-1">Ready for Bank/Momo transfer</p>
                                    </div>
                                </div>

                                <!-- Deductions Breakdown Audit -->
                                <div class="border-t border-brand-white/10 pt-4 space-y-4">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash">Audit & Punctuality Breakdown</h3>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div class="bg-brand-black/20 p-3 rounded-lg border border-brand-white/5 flex items-center justify-between">
                                            <span class="text-xs text-brand-white/70">Excused Leave Days</span>
                                            <span class="px-2 py-0.5 bg-green-500/10 text-green-400 font-bold rounded text-xs">{{ $payroll['leave_days_count'] }} days</span>
                                        </div>
                                        <div class="bg-brand-black/20 p-3 rounded-lg border border-brand-white/5 flex items-center justify-between">
                                            <span class="text-xs text-brand-white/70">Missed Clock-In Slots</span>
                                            <span class="px-2 py-0.5 bg-brand-red/10 text-brand-red font-bold rounded text-xs">{{ $payroll['missed_slots'] }} slots</span>
                                        </div>
                                        <div class="bg-brand-black/20 p-3 rounded-lg border border-brand-white/5 flex items-center justify-between">
                                            <span class="text-xs text-brand-white/70">Late Clock-In Slots</span>
                                            <span class="px-2 py-0.5 bg-yellow-500/10 text-yellow-400 font-bold rounded text-xs">{{ $payroll['late_slots'] }} slots</span>
                                        </div>
                                    </div>

                                    <blockquote class="bg-brand-red/5 border-l-4 border-brand-red rounded p-4 text-xs text-brand-white/80 leading-relaxed">
                                        💡 <strong>Deduction Penalty Policy:</strong> Base salary is audited against geofenced clock-in checkpoints. 
                                        Each unexcused missed slot incurs a <strong>1% deduction</strong> penalty. 
                                        Each late slot (occurring past the operational window buffer) incurs a <strong>0.5% deduction</strong> penalty. 
                                        Days covered by an **Approved Leave Application** are excluded from penalty calculations.
                                    </blockquote>
                                </div>
                            </div>

                            <!-- TAB 4: LEAVES & ABSENCES -->
                            <div x-show="activeTab === 'leaves'" class="space-y-6" style="display: none;">
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h2 class="text-xl font-display text-brand-white tracking-wider">📅 Leaves & Absences</h2>
                                        <span class="text-xs px-2.5 py-1 bg-brand-white/10 text-brand-white border border-brand-white/20 rounded-lg font-bold">Leave Balance: {{ auth()->user()->leave_balance }} days</span>
                                    </div>

                                    <form method="POST" action="{{ route('merchandisers.leaves.store') }}" class="space-y-4">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="start_date" value="Start Date" />
                                                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <x-input-label for="end_date" value="End Date" />
                                                <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <x-input-label for="leave_type" value="Leave Type" />
                                                <select id="leave_type" name="leave_type" class="mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2 text-xs" required>
                                                    <option value="annual">Annual Leave</option>
                                                    <option value="sick">Sick Leave</option>
                                                    <option value="compassionate">Compassionate</option>
                                                    <option value="maternity">Maternity</option>
                                                    <option value="unpaid">Unpaid Leave</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="covering_staff_id" value="Duty Covering Colleague" />
                                                <select id="covering_staff_id" name="covering_staff_id" class="mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2 text-xs" required>
                                                    <option value="">Select colleague...</option>
                                                    @foreach($staffMembers as $member)
                                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label for="comments" value="Reason & Comments" />
                                            <textarea id="comments" name="comments" rows="3" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs" required></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Submit Leave Request
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Leaves History -->
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-4">Request Log</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="text-brand-ash uppercase border-b border-brand-white/10">
                                                    <th class="pb-2">Period</th>
                                                    <th class="pb-2">Type</th>
                                                    <th class="pb-2">Status</th>
                                                    <th class="pb-2">Covering Colleague</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-white/5">
                                                @forelse($leaves as $leave)
                                                    <tr>
                                                        <td class="py-3">
                                                            {{ $leave->start_date->format('Y-m-d') }} to {{ $leave->end_date->format('Y-m-d') }}
                                                        </td>
                                                        <td class="py-3 capitalize">{{ $leave->leave_type }}</td>
                                                        <td class="py-3">
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $leave->status === 'approved' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : ($leave->status === 'rejected' ? 'bg-brand-red/10 text-brand-red border border-brand-red/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20') }}">
                                                                {{ $leave->status }}
                                                            </span>
                                                        </td>
                                                        <td class="py-3">{{ $leave->coveringStaff->name ?? 'None' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-brand-white/30">No leaves requested.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 5: PETTY CASH CLAIMS -->
                            <div x-show="activeTab === 'claims'" class="space-y-6" style="display: none;">
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">💰 Petty Cash Claims (Out-of-pocket reimbursements)</h2>
                                    
                                    <form method="POST" action="{{ route('merchandisers.claims.store') }}" enctype="multipart/form-data" class="space-y-4">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <x-input-label for="claim_amount" value="Reimbursement Amount" />
                                                <x-text-input id="claim_amount" name="amount" type="number" step="0.01" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <x-input-label for="currency" value="Currency" />
                                                <select id="currency" name="currency" class="mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2 text-xs" required>
                                                    <option value="GHS">GHS (Ghanaian Cedi)</option>
                                                    <option value="NGN">NGN (Nigerian Naira)</option>
                                                    <option value="USD">USD (Dollar)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="receipt" value="Upload Receipt Image" />
                                                <input id="receipt" name="receipt" type="file" class="mt-1.5 block w-full text-xs text-brand-white/60" required />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label for="claim_desc" value="Reimbursement Description" />
                                            <textarea id="claim_desc" name="description" rows="2" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs" placeholder="e.g. Uber transit to Accra Mall shoprite for store audits" required></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Submit Claim
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Claims history -->
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-4">Claims History Log</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="text-brand-ash uppercase border-b border-brand-white/10">
                                                    <th class="pb-2">Date</th>
                                                    <th class="pb-2">Description</th>
                                                    <th class="pb-2">Amount</th>
                                                    <th class="pb-2">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-white/5">
                                                @forelse($claims as $claim)
                                                    <tr>
                                                        <td class="py-3">{{ $claim->created_at->format('Y-m-d') }}</td>
                                                        <td class="py-3">{{ $claim->description }}</td>
                                                        <td class="py-3 font-bold">{{ $claim->currency }} {{ number_format($claim->amount, 2) }}</td>
                                                        <td class="py-3 text-xs">
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $claim->status === 'approved' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : ($claim->status === 'rejected' ? 'bg-brand-red/10 text-brand-red border border-brand-red/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20') }}">
                                                                {{ $claim->status }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-brand-white/30">No reimbursement claims logged.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 6: SALARY ADVANCES -->
                            <div x-show="activeTab === 'loans'" class="space-y-6" style="display: none;">
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">💵 Salary Advances (Employee Loans)</h2>
                                    
                                    <form method="POST" action="{{ route('merchandisers.loans.store') }}" class="space-y-4">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <x-input-label for="loan_amount" value="Requested Loan Amount" />
                                                <x-text-input id="loan_amount" name="amount" type="number" step="0.01" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <x-input-label for="repayment_style" value="Repayment Style" />
                                                <select id="repayment_style" name="repayment_style" class="mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2 text-xs" required>
                                                    <option value="monthly_deduction">Monthly Deduction Payout</option>
                                                    <option value="flat">One-off Lump sum Deduction</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="monthly_deduction_amount" value="Monthly Deduction Amount" />
                                                <x-text-input id="monthly_deduction_amount" name="monthly_deduction_amount" type="number" step="0.01" class="mt-1 block w-full" required />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label for="loan_reason" value="Reason for Loan Request" />
                                            <textarea id="loan_reason" name="reason" rows="2" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs" placeholder="Describe the purpose of the advance..." required></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Request Salary Advance
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Loan history -->
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-4">Advance Request History</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="text-brand-ash uppercase border-b border-brand-white/10">
                                                    <th class="pb-2">Date</th>
                                                    <th class="pb-2">Amount Requested</th>
                                                    <th class="pb-2">Repayment Monthly Deduction</th>
                                                    <th class="pb-2">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-white/5">
                                                @forelse($loans as $loan)
                                                    <tr>
                                                        <td class="py-3">{{ $loan->created_at->format('Y-m-d') }}</td>
                                                        <td class="py-3 font-bold">{{ number_format($loan->amount, 2) }}</td>
                                                        <td class="py-3">{{ number_format($loan->monthly_deduction_amount, 2) }} / mo</td>
                                                        <td class="py-3">
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $loan->status === 'approved' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : ($loan->status === 'rejected' ? 'bg-brand-red/10 text-brand-red border border-brand-red/20' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20') }}">
                                                                {{ $loan->status }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-brand-white/30">No salary advances requested.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 7: APPRAISALS -->
                            <div x-show="activeTab === 'appraisals'" class="space-y-6" style="display: none;">
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">📝 Self-Appraisals ratings (Quarterly Submission)</h2>
                                    
                                    <form method="POST" action="{{ route('merchandisers.appraisals.store') }}" class="space-y-6">
                                        @csrf
                                        <div class="space-y-4">
                                            <h3 class="text-xs font-semibold uppercase tracking-wider text-brand-ash">Rate yourself from 1 (Low) to 10 (Excellent) in these categories</h3>
                                            
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="score_attendance" value="Punctuality & Attendance Check-ins" />
                                                    <x-text-input id="score_attendance" name="scores[attendance]" type="number" min="1" max="10" class="mt-1 block w-full" required />
                                                </div>
                                                <div>
                                                    <x-input-label for="score_execution" value="Store Visit Execution compliance" />
                                                    <x-text-input id="score_execution" name="scores[execution]" type="number" min="1" max="10" class="mt-1 block w-full" required />
                                                </div>
                                                <div>
                                                    <x-input-label for="score_order" value="Accuracy in KD Orders placement" />
                                                    <x-text-input id="score_order" name="scores[orders]" type="number" min="1" max="10" class="mt-1 block w-full" required />
                                                </div>
                                                <div>
                                                    <x-input-label for="score_comm" value="Communication & Feedback responsiveness" />
                                                    <x-text-input id="score_comm" name="scores[communication]" type="number" min="1" max="10" class="mt-1 block w-full" required />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label for="appraisal_feedback" value="Self-Assessment comments & feedback" />
                                            <textarea id="appraisal_feedback" name="feedback" rows="3" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs" required></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Submit Self-Appraisal
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Appraisal submissions history -->
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-4">Past Appraisals Log</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="text-brand-ash uppercase border-b border-brand-white/10">
                                                    <th class="pb-2">Period</th>
                                                    <th class="pb-2">Avg Self Score</th>
                                                    <th class="pb-2">Avg Manager Score</th>
                                                    <th class="pb-2">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-white/5">
                                                @forelse($appraisals as $app)
                                                    <tr>
                                                        <td class="py-3">{{ $app->quarter }} ({{ $app->year }})</td>
                                                        <td class="py-3 font-bold">{{ $app->avg_self_score }} / 10</td>
                                                        <td class="py-3 font-bold">{{ $app->avg_manager_score ?: 'Pending review' }}</td>
                                                        <td class="py-3 font-medium">{{ $app->status_label }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-brand-white/30">No quarterly appraisals submitted yet.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 8: POSM GEAR CHECKOUT -->
                            <div x-show="activeTab === 'inventory'" class="space-y-6" style="display: none;">
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">📁 Field POSM Materials & Gear Checkout</h2>
                                    
                                    <form method="POST" action="{{ route('merchandisers.inventory.store') }}" enctype="multipart/form-data" class="space-y-4">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div>
                                                <x-input-label for="item_name" value="Material/Gear Item Name" />
                                                <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" placeholder="e.g. Pull-up banner, Branded shirt" required />
                                            </div>
                                            <div>
                                                <x-input-label for="quantity_out" value="Quantity Checked Out" />
                                                <x-text-input id="quantity_out" name="quantity_out" type="number" min="1" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <x-input-label for="location" value="Delivery/Deployment Location" />
                                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" placeholder="e.g. Accra Mall Shoprite" required />
                                            </div>
                                            <div>
                                                <x-input-label for="gear_image" value="Proof / Handover Photo" />
                                                <input id="gear_image" name="image" type="file" class="mt-1.5 block w-full text-xs text-brand-white/60" />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label for="notes" value="Checkout Notes" />
                                            <textarea id="notes" name="notes" rows="2" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs" placeholder="e.g. POSM deployment for client activations..." required></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Log Gear Checkout
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Inventory history -->
                                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-4">My Checkout Logs</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="text-brand-ash uppercase border-b border-brand-white/10">
                                                    <th class="pb-2">Date</th>
                                                    <th class="pb-2">Material Item</th>
                                                    <th class="pb-2">Qty</th>
                                                    <th class="pb-2">Deployment Location</th>
                                                    <th class="pb-2">Photo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-brand-white/5">
                                                @forelse($inventory as $item)
                                                    <tr>
                                                        <td class="py-3">{{ $item->created_at->format('Y-m-d') }}</td>
                                                        <td class="py-3">{{ $item->item_name }}</td>
                                                        <td class="py-3 font-bold text-brand-red">{{ $item->quantity_out }} items</td>
                                                        <td class="py-3">{{ $item->location }}</td>
                                                        <td class="py-3">
                                                            @if($item->image_path)
                                                                <a href="{{ Storage::disk('public')->url($item->image_path) }}" target="_blank">
                                                                    <img src="{{ Storage::disk('public')->url($item->image_path) }}" class="w-8 h-8 rounded object-cover hover:scale-150 transition-all border border-brand-white/10" alt="Proof">
                                                                </a>
                                                            @else
                                                                <span class="text-brand-white/30 text-[10px]">No Photo</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="py-4 text-center text-brand-white/30">No POSM/field gear checkouts logged.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 9: ACTIVE SURVEYS -->
                            <div x-show="activeTab === 'surveys'" x-data="{ surveyView: 'list' }" class="space-y-6" style="display: none;">
                                <div class="flex items-center justify-between border-b border-brand-white/10 pb-3">
                                    <h2 class="text-xl font-display text-brand-white tracking-wider">📋 Broadcast Administrative Surveys</h2>
                                    <button type="button" @click="surveyView = (surveyView === 'list' ? 'create' : 'list')" class="px-4 py-2 bg-brand-red hover:bg-red-600 text-white text-xs uppercase tracking-wider font-bold rounded-xl transition-all shadow-lg flex items-center gap-1.5">
                                        <span x-text="surveyView === 'list' ? '+ Create New Survey' : '← Back to List'"></span>
                                    </button>
                                </div>
                                
                                @if($googleForms->isNotEmpty())
                                    <div class="glass-panel rounded-2xl p-6 border border-sky-400/20 bg-sky-500/10 space-y-4">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-[0.25em] text-sky-200/80">Assigned Forms</p>
                                            <h3 class="mt-1 text-lg font-display text-brand-white tracking-wider">Google Forms & Outlet Surveys</h3>
                                            <p class="mt-1 text-xs text-brand-white/55">Open each assigned form, submit it, then mark it completed so supervisors can track pending work.</p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                            @foreach($googleForms as $form)
                                                @php
                                                    $googleCompleted = in_array($form->id, $googleFormCompletionIds, true);
                                                    $nativeCompleted = in_array($form->id, $nativeFormCompletionIds, true);
                                                    $completed = $googleCompleted || $nativeCompleted;
                                                @endphp
                                                <div class="rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="text-sm font-semibold text-brand-white">{{ $form->title }}</p>
                                                            <p class="mt-1 text-[10px] uppercase tracking-wider text-brand-white/40">
                                                                {{ $form->brand?->name ?? 'Any brand' }} / {{ $form->campaign?->name ?? 'Any campaign' }} / {{ $form->category ?? 'Any category' }}
                                                            </p>
                                                        </div>
                                                        <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $completed ? 'border-green-500/20 bg-green-500/10 text-green-300' : 'border-amber-500/20 bg-amber-500/10 text-amber-200' }}">{{ $nativeCompleted ? 'Inbuilt Done' : ($googleCompleted ? 'Google Done' : 'Pending') }}</span>
                                                    </div>
                                                    @if($form->description)
                                                        <p class="mt-2 text-xs leading-relaxed text-brand-white/50">{{ $form->description }}</p>
                                                    @endif
                                                    <div class="mt-4 flex flex-wrap gap-2">
                                                        @if($form->google_enabled && $form->google_form_url)
                                                            <a href="{{ $form->google_form_url }}" target="_blank" rel="noopener" class="rounded-lg bg-sky-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-sky-400">Use Google Form</a>
                                                        @endif
                                                        @if($form->native_enabled)
                                                            <a href="{{ route('merchandisers.native-forms.show', $form) }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-emerald-400">{{ $nativeCompleted ? 'Edit Inbuilt Form' : 'Use Inbuilt Form' }}</a>
                                                        @endif
                                                        @if($form->google_enabled && ! $googleCompleted)
                                                            <form method="POST" action="{{ route('merchandisers.google-forms.complete', $form) }}">
                                                                @csrf
                                                                <button type="submit" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Mark Google Complete</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div x-show="surveyView === 'list'" class="space-y-6">
                                    @forelse($surveys as $survey)
                                        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-lg font-bold text-brand-white">{{ $survey->title }}</h3>
                                                <span class="text-xs px-2 py-0.5 bg-brand-red/10 text-brand-red border border-brand-red/20 rounded font-semibold uppercase">Brand: {{ $survey->client_brand_name ?: 'CMIH' }}</span>
                                            </div>
                                            <p class="text-xs text-brand-white/60 leading-relaxed">{{ $survey->description }}</p>

                                            <form method="POST" action="{{ route('merchandisers.surveys.respond', $survey) }}" class="space-y-4 border-t border-brand-white/5 pt-4">
                                                @csrf
                                                
                                                @foreach($survey->questions as $index => $question)
                                                    <div class="space-y-1.5">
                                                        <x-input-label for="ans_{{ $question->id }}" value="{{ ($index+1) }}. {{ $question->question_text }}" />
                                                        @if($question->question_type === 'text')
                                                            <x-text-input id="ans_{{ $question->id }}" name="answers[{{ $question->id }}]" type="text" class="block w-full" required />
                                                        @elseif($question->question_type === 'number')
                                                            <x-text-input id="ans_{{ $question->id }}" name="answers[{{ $question->id }}]" type="number" class="block w-full" required />
                                                        @elseif($question->question_type === 'select')
                                                            <select id="ans_{{ $question->id }}" name="answers[{{ $question->id }}]" class="block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2 text-xs" required>
                                                                @foreach(explode(',', $question->options) as $opt)
                                                                    <option value="{{ trim($opt) }}">{{ trim($opt) }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </div>
                                                @endforeach

                                                <div class="flex justify-end pt-2">
                                                    <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                        Submit Survey Response
                                                    </x-primary-button>
                                                </div>
                                            </form>
                                        </div>
                                    @empty
                                        <div class="glass-panel rounded-2xl p-8 border border-brand-white/10 bg-brand-white/5 text-center">
                                            <p class="text-sm text-brand-white/60">No active administrative surveys at this time.</p>
                                        </div>
                                    @endforelse
                                </div>

                                <div x-show="surveyView === 'create'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4" style="display: none;">
                                    <h3 class="text-lg font-bold text-brand-white">Build & Publish Survey</h3>
                                    
                                    <form method="POST" action="{{ route('merchandisers.surveys.store') }}" class="space-y-6" x-data="merchSurveyBuilder()" x-init="addQuestion()">
                                        @csrf
                                        
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <x-input-label for="survey_title" value="Survey Title *" />
                                                <x-text-input id="survey_title" name="title" type="text" required class="mt-1 w-full" placeholder="e.g. Weekly Field Feedback" />
                                            </div>
                                            <div>
                                                <x-input-label for="survey_status" value="Status" />
                                                <select id="survey_status" name="status" class="mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs focus:border-brand-red focus:ring-0" required>
                                                    <option value="published">Published (Open)</option>
                                                    <option value="draft">Draft</option>
                                                    <option value="closed">Closed</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <x-input-label for="survey_description" value="Description / Welcome Message" />
                                            <textarea id="survey_description" name="description" rows="3" class="wysiwyg-editor mt-1 block w-full rounded-xl border border-brand-white/10 bg-brand-black/40 text-brand-white/80 p-2.5 text-xs focus:border-brand-red focus:ring-0" placeholder="Welcome message shown to users..."></textarea>
                                        </div>

                                        <!-- Anonymous Toggle -->
                                        <div class="flex items-center justify-between bg-brand-black/20 p-3 rounded-xl border border-brand-white/5">
                                            <div>
                                                <span class="block text-xs font-semibold uppercase tracking-wider text-brand-white/70">Survey Mode</span>
                                                <p class="text-[9px] text-brand-white/40 mt-0.5">Anonymous hides respondent name and contact details.</p>
                                            </div>
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="is_anonymous" value="1" class="sr-only peer">
                                                <div class="relative w-11 h-6 bg-brand-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-brand-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-red"></div>
                                                <span class="ms-3 text-xs font-semibold uppercase tracking-wider text-brand-white/70">Anonymous</span>
                                            </label>
                                        </div>

                                        <!-- Question Builder Section -->
                                        <div class="border-t border-brand-white/10 pt-4 space-y-4">
                                            <div class="flex items-center justify-between border-b border-brand-white/5 pb-2">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-ash">Questions</h4>
                                                <button type="button" @click="addQuestion()" class="px-2.5 py-1 bg-brand-white/5 hover:bg-brand-white/10 text-brand-white text-[10px] font-bold uppercase tracking-wider rounded-lg border border-brand-white/10 transition-colors">
                                                    + Add Question
                                                </button>
                                            </div>
                                            
                                            <div class="space-y-4">
                                                <template x-for="(q, qIndex) in questions" :key="qIndex">
                                                    <div class="rounded-xl border border-brand-white/5 bg-brand-black/25 p-4 relative space-y-3">
                                                        <button type="button" @click="removeQuestion(qIndex)" class="absolute top-3 right-3 text-brand-red hover:text-red-400 text-xs font-bold transition-colors">
                                                            ✕ Remove
                                                        </button>
                                                        
                                                        <div class="grid gap-3 md:grid-cols-2">
                                                            <div>
                                                                <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-1">Question Prompt</label>
                                                                <input type="text" :name="'questions[' + qIndex + '][question_text]'" x-model="q.question_text" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 text-brand-white/80 p-2 text-xs focus:border-brand-red focus:ring-0" placeholder="e.g. Rate shelf presence">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-1">Response Type</label>
                                                                <select :name="'questions[' + qIndex + '][question_type]'" x-model="q.question_type" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 text-brand-white/80 p-2 text-xs focus:border-brand-red focus:ring-0">
                                                                    <option value="short_text">Short Answer</option>
                                                                    <option value="paragraph">Paragraph</option>
                                                                    <option value="radio">Multiple Choice — Pick ONE (Radio)</option>
                                                                    <option value="checkbox">Multiple Select — Pick MANY (Checkboxes)</option>
                                                                    <option value="dropdown">Dropdown Select</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <!-- Options builder for choices -->
                                                        <div x-show="['radio', 'checkbox', 'dropdown'].includes(q.question_type)" class="pl-4 border-l border-brand-white/10 space-y-2" style="display: none;">
                                                            <label class="block text-[10px] uppercase tracking-wider text-brand-white/60">Options</label>
                                                            <div class="space-y-1.5">
                                                                <template x-for="(opt, oIndex) in q.options" :key="oIndex">
                                                                    <div class="flex items-center gap-2">
                                                                        <input type="text" :name="'questions[' + qIndex + '][options][' + oIndex + ']'" x-model="q.options[oIndex]" required class="w-1/2 rounded-xl border border-brand-white/10 bg-brand-black/60 text-brand-white/80 px-2 py-1 text-xs focus:border-brand-red focus:ring-0">
                                                                        <button type="button" @click="removeOption(qIndex, oIndex)" class="text-brand-white/40 hover:text-brand-red text-xs transition-colors">✕</button>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <button type="button" @click="addOption(qIndex)" class="text-[10px] text-green-400 font-bold uppercase tracking-wider hover:text-green-300 transition-colors">
                                                                + Add Option
                                                            </button>
                                                        </div>

                                                        <!-- Required indicator -->
                                                        <div class="flex justify-end pt-2">
                                                            <label class="inline-flex items-center cursor-pointer">
                                                                <input type="checkbox" :name="'questions[' + qIndex + '][is_required]'" value="1" x-model="q.is_required" class="sr-only peer">
                                                                <div class="relative w-8 h-4 bg-brand-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-brand-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-brand-red"></div>
                                                                <span class="ms-2 text-[10px] uppercase tracking-wider text-brand-white/60">Required</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex justify-end border-t border-brand-white/5 pt-4">
                                            <x-primary-button class="bg-brand-red hover:bg-red-600">
                                                Create Administrative Survey
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- TAB 10: NOTIFICATIONS -->
                            <div x-show="activeTab === 'notifications'" class="space-y-6" style="display: none;">
                                <h2 class="text-xl font-display text-brand-white tracking-wider">🔔 Announcements & Notifications</h2>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Global Announcements -->
                                    <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-3">📢 Broadcast Announcements</h3>
                                        <div class="space-y-4">
                                            @forelse($announcements as $ann)
                                                <div class="p-4 bg-brand-white/5 rounded-xl border border-brand-white/5 space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <h4 class="font-bold text-brand-white text-sm">{{ $ann->title }}</h4>
                                                        <span class="text-[9px] text-brand-white/40">{{ $ann->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="text-xs text-brand-white/60 leading-relaxed">{!! nl2br(e($ann->plainBody())) !!}</p>
                                                    @if($ann->pinned)
                                                        <span class="inline-flex px-1.5 py-0.5 bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 rounded text-[9px] uppercase font-bold">📌 Pinned</span>
                                                    @endif
                                                </div>
                                            @empty
                                                <p class="text-xs text-brand-white/40 text-center py-6">No announcements published yet.</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <!-- Personal Notifications -->
                                    <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-brand-red/20 transition-all duration-300 space-y-4">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash mb-3">👤 Personal Alerts</h3>
                                        <div class="space-y-4">
                                            @forelse($notifications as $notif)
                                                <div class="p-4 rounded-xl border {{ is_null($notif->read_at) ? 'bg-brand-red/5 border-brand-red/20 shadow-md' : 'bg-brand-white/5 border-brand-white/5' }} space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <h4 class="font-bold text-brand-white text-sm">{{ $notif->title }}</h4>
                                                        <span class="text-[9px] text-brand-white/40">{{ $notif->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="text-xs text-brand-white/60 leading-relaxed">{{ $notif->message }}</p>
                                                    
                                                    @if(is_null($notif->read_at))
                                                        <div class="flex justify-end pt-1">
                                                            <form method="POST" action="{{ route('merchandisers.notifications.read', $notif) }}">
                                                                @csrf
                                                                <button type="submit" class="text-[9px] uppercase tracking-wider bg-brand-red/10 text-brand-red hover:bg-brand-red/20 border border-brand-red/35 px-2 py-0.5 rounded transition-all font-bold">
                                                                    Mark as Read
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <span class="inline-flex px-1.5 py-0.5 bg-green-500/10 text-green-400 border border-green-500/20 rounded text-[9px] uppercase font-bold">✓ Read</span>
                                                    @endif
                                                </div>
                                            @empty
                                                <p class="text-xs text-brand-white/40 text-center py-6">No personal alerts recorded.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>

                    </div>
                @endif

            </main>
        </div>
    </div>

    <!-- Geolocation & Chart scripts -->
    <script>
        // ── Geolocation Logic ──────────────────────────────────────
        let gpsBanner = document.getElementById('gps-error-banner');
        let gpsStatus = document.getElementById('gps-status-pill');

        function updateGPSStatus(success, errorMsg = '') {
            if (success) {
                gpsBanner.classList.add('hidden');
                gpsStatus.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span> GPS Active';
                gpsStatus.className = "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-400 border border-green-500/20";
            } else {
                gpsBanner.classList.remove('hidden');
                document.getElementById('gps-error-text').innerText = errorMsg || 'Please enable Location services to use this portal.';
                gpsStatus.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400"></span> GPS Disabled';
                gpsStatus.className = "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20";
            }
        }

        function pingLocation() {
            if (!navigator.geolocation) {
                updateGPSStatus(false, 'Geolocation is not supported by your browser.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    updateGPSStatus(true);
                    let lat = position.coords.latitude;
                    let lng = position.coords.longitude;

                    document.querySelectorAll('.user-lat-input').forEach(el => el.value = lat);
                    document.querySelectorAll('.user-lng-input').forEach(el => el.value = lng);

                    fetch('{{ route("merchandisers.location-ping") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ latitude: lat, longitude: lng })
                    });
                },
                function (error) {
                    let msg = 'GPS Permission Denied. Geofence verification will fail.';
                    if (error.code === error.POSITION_UNAVAILABLE) msg = 'Location information is unavailable.';
                    if (error.code === error.TIMEOUT) msg = 'Location request timed out.';
                    updateGPSStatus(false, msg);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        document.querySelectorAll('[data-requires-gps-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const latitude = form.querySelector('.user-lat-input')?.value;
                const longitude = form.querySelector('.user-lng-input')?.value;

                if (latitude && longitude) {
                    return;
                }

                event.preventDefault();
                updateGPSStatus(false, 'Allow location access while standing at the outlet, then submit again.');
                pingLocation();
            });
        });

        pingLocation();
        setInterval(pingLocation, 300000);

        function loadMerchExternalScript(src, globalName) {
            if (globalName && window[globalName]) {
                return Promise.resolve(window[globalName]);
            }

            const promiseKey = `cmihScript_${globalName || src}`;
            if (window[promiseKey]) {
                return window[promiseKey];
            }

            window[promiseKey] = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.onload = () => resolve(globalName ? window[globalName] : true);
                script.onerror = () => reject(new Error(`Failed to load ${src}`));
                document.head.appendChild(script);
            });

            return window[promiseKey];
        }

        // Chart.js Configurations
        document.addEventListener("DOMContentLoaded", function () {
            var ctxPunctual = document.getElementById('punctualityChart');
            var ctxOutletCoverage = document.getElementById('outletCoverageChart');
            var ctxDailyCoverageTrend = document.getElementById('dailyCoverageTrendChart');
            var ctxVisitFunnel = document.getElementById('visitFunnelChart');
            var ctxVisitState = document.getElementById('visitStateChart');
            var ctxVisitMinutes = document.getElementById('visitMinutesChart');
            var dailyPerformance = @json($dailyPerformanceChart);

            if (!ctxPunctual && !ctxOutletCoverage && !ctxDailyCoverageTrend && !ctxVisitFunnel && !ctxVisitState && !ctxVisitMinutes) {
                return;
            }

            loadMerchExternalScript('https://cdn.jsdelivr.net/npm/chart.js', 'Chart')
                .then(() => {
            // Apply universal gray color to grid lines and text to adapt to both themes cleanly
            Chart.defaults.color = 'rgba(128, 128, 128, 0.8)';
            Chart.defaults.borderColor = 'rgba(128, 128, 128, 0.15)';

            if (ctxPunctual) {
                new Chart(ctxPunctual, {
                    type: 'bar',
                    data: {
                        labels: dailyPerformance.labels || [],
                        datasets: [
                            {
                                label: 'Scheduled',
                                data: dailyPerformance.scheduled || [],
                                backgroundColor: 'rgba(148,163,184,.42)',
                                borderColor: 'rgba(148,163,184,.8)',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Clocked',
                                data: dailyPerformance.clocked || [],
                                backgroundColor: 'rgba(16,185,129,.55)',
                                borderColor: '#10b981',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Scored',
                                data: dailyPerformance.scored || [],
                                backgroundColor: 'rgba(56,189,248,.55)',
                                borderColor: '#38bdf8',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                type: 'line',
                                label: 'Coverage %',
                                data: dailyPerformance.coverage || [],
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,.14)',
                                borderWidth: 2,
                                tension: .35,
                                yAxisID: 'percentage'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, font: { size: 9 } }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(128, 128, 128, 0.1)' },
                                precision: 0,
                                ticks: { font: { size: 9 } }
                            },
                            percentage: {
                                beginAtZero: true,
                                max: 100,
                                position: 'right',
                                grid: { drawOnChartArea: false },
                                ticks: { font: { size: 9 }, callback: value => value + '%' }
                            },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }

            if (ctxOutletCoverage) {
                new Chart(ctxOutletCoverage, {
                    type: 'doughnut',
                    data: {
                        labels: ['Scored', 'Clocked not scored', 'Not covered'],
                        datasets: [{
                            data: [
                                {{ (int) ($merchMetrics['outlets_scored_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['clocked_not_scored_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['not_covered_today'] ?? 0) }}
                            ],
                            backgroundColor: ['#38bdf8', '#10b981', '#f59e0b'],
                            borderColor: 'rgba(255,255,255,0.08)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, font: { size: 10 } }
                            }
                        },
                        cutout: '62%'
                    }
                });
            }

            if (ctxDailyCoverageTrend) {
                new Chart(ctxDailyCoverageTrend, {
                    type: 'line',
                    data: {
                        labels: dailyPerformance.labels || [],
                        datasets: [{
                            label: 'Coverage %',
                            data: dailyPerformance.coverage || [],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,.16)',
                            fill: true,
                            tension: .35,
                            pointRadius: 3,
                            pointBackgroundColor: '#ef4444'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, max: 100, ticks: { font: { size: 9 }, callback: value => value + '%' } },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }

            if (ctxVisitFunnel) {
                new Chart(ctxVisitFunnel, {
                    type: 'bar',
                    data: {
                        labels: ['Assigned', 'Clocked', 'Scored', 'Not covered'],
                        datasets: [{
                            label: 'Outlets',
                            data: [
                                {{ (int) ($merchMetrics['assigned_outlets_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['outlets_visited_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['outlets_scored_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['not_covered_today'] ?? 0) }}
                            ],
                            backgroundColor: ['rgba(148,163,184,.62)', 'rgba(16,185,129,.62)', 'rgba(56,189,248,.62)', 'rgba(245,158,11,.62)'],
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, precision: 0, ticks: { font: { size: 9 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }

            if (ctxVisitState) {
                new Chart(ctxVisitState, {
                    type: 'doughnut',
                    data: {
                        labels: ['Active visits', 'Clocked out', 'Not covered'],
                        datasets: [{
                            data: [
                                {{ (int) ($merchMetrics['active_outlet_clockins_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['closed_outlet_clockins_today'] ?? 0) }},
                                {{ (int) ($merchMetrics['not_covered_today'] ?? 0) }}
                            ],
                            backgroundColor: ['#f59e0b', '#10b981', 'rgba(148,163,184,.5)'],
                            borderColor: 'rgba(255,255,255,0.08)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, font: { size: 10 } }
                            }
                        },
                        cutout: '62%'
                    }
                });
            }

            if (ctxVisitMinutes) {
                new Chart(ctxVisitMinutes, {
                    type: 'bar',
                    data: {
                        labels: dailyPerformance.labels || [],
                        datasets: [{
                            label: 'Visit minutes',
                            data: dailyPerformance.visit_minutes || [],
                            backgroundColor: 'rgba(239,68,68,.58)',
                            borderColor: '#ef4444',
                            borderWidth: 1,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 9 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }
                })
                .catch((error) => console.error(error));
        });
        // ── Alpine Survey Builder helper ────────────────────────────
        function merchSurveyBuilder() {
            return {
                questions: [],
                addQuestion() {
                    this.questions.push({ question_text: '', question_type: 'short_text', options: ['Option 1'], is_required: false });
                },
                removeQuestion(index) {
                    this.questions.splice(index, 1);
                },
                addOption(qIndex) {
                    this.questions[qIndex].options.push('Option ' + (this.questions[qIndex].options.length + 1));
                },
                removeOption(qIndex, oIndex) {
                    this.questions[qIndex].options.splice(oIndex, 1);
                    if (this.questions[qIndex].options.length === 0) this.questions[qIndex].options.push('Option 1');
                }
            };
        }

        // CKEditor 5 Initialization
        document.addEventListener('DOMContentLoaded', () => {
            const editors = Array.from(document.querySelectorAll('.wysiwyg-editor'));

            if (editors.length === 0) {
                return;
            }

            loadMerchExternalScript('https://cdn.ckeditor.com/ckeditor5/36.0.1/super-build/ckeditor.js', 'CKEDITOR')
                .then(() => {
                    editors.forEach((textarea) => {
                if (textarea.dataset.ckeditorReady === 'true') {
                    return;
                }

                CKEDITOR.ClassicEditor
                    .create(textarea, {
                        toolbar: {
                            items: [
                                'undo', 'redo', '|',
                                'bold', 'italic', 'underline', 'strikethrough', '|',
                                'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', 'alignment', '|',
                                'insertTable', 'link', 'blockQuote', 'horizontalLine', '|',
                                'sourceEditing'
                            ],
                            shouldNotGroupWhenFull: true
                        },
                        removePlugins: [
                            'CKBox', 'CKFinder', 'EasyImage', 'RealTimeCollaborativeComments',
                            'RealTimeCollaborativeTrackChanges', 'RealTimeCollaborativeRevisionHistory',
                            'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData',
                            'RevisionHistory', 'Pagination', 'WProofreader', 'MathType',
                            'WebSocketGateway', 'CloudServices', 'RealTimeCollaborativeEditing',
                            'ExportPdf', 'ExportWord'
                        ]
                    })
                    .then((editor) => {
                        textarea.dataset.ckeditorReady = 'true';
                        textarea._ckeditorInstance = editor;
                    })
                    .catch(error => {
                        console.error(error);
                    });
            });
                })
                .catch((error) => console.error(error));
        });

        document.addEventListener('DOMContentLoaded', () => {
            const queueKey = 'cmih_merchandiser_clock_queue';

            const ensureHidden = (form, name, value) => {
                let input = form.querySelector(`[name="${name}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    form.appendChild(input);
                }
                input.value = value;
            };

            const queuedItems = () => {
                try {
                    return JSON.parse(localStorage.getItem(queueKey) || '[]');
                } catch (error) {
                    return [];
                }
            };

            const saveQueue = (items) => localStorage.setItem(queueKey, JSON.stringify(items));

            const queueClockIn = (form) => {
                const data = Object.fromEntries(new FormData(form).entries());
                const items = queuedItems();
                items.push({
                    action: form.action,
                    method: form.method || 'POST',
                    data,
                    queuedAt: new Date().toISOString(),
                });
                saveQueue(items);
            };

            const syncQueuedClockIns = async () => {
                if (!navigator.onLine) return;

                const items = queuedItems();
                if (items.length === 0) return;

                const remaining = [];
                for (const item of items) {
                    try {
                        const body = new FormData();
                        Object.entries(item.data).forEach(([key, value]) => body.append(key, value));
                        body.set('sync_source', 'offline_retry');

                        const response = await fetch(item.action, {
                            method: item.method.toUpperCase(),
                            body,
                            headers: { 'Accept': 'text/html,application/xhtml+xml' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            remaining.push(item);
                        }
                    } catch (error) {
                        remaining.push(item);
                    }
                }

                saveQueue(remaining);
                if (remaining.length !== items.length) {
                    window.location.reload();
                }
            };

            document.querySelectorAll('[data-clock-form], [data-clock-in-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const token = `clock-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                    ensureHidden(form, 'client_recorded_at', new Date().toISOString());
                    ensureHidden(form, 'sync_token', token);
                    ensureHidden(form, 'sync_source', navigator.onLine ? 'live' : 'queued');

                    const button = form.querySelector('[data-clock-submit], [data-clock-in-submit]');
                    if (button) {
                        button.disabled = true;
                        button.classList.add('opacity-60', 'cursor-not-allowed');
                        const verb = form.dataset.clockVerb || 'Clocking in';
                        button.innerHTML = navigator.onLine ? `${verb}...` : 'Saved Offline';
                    }

                    if (!navigator.onLine) {
                        event.preventDefault();
                        queueClockIn(form);
                        alert('Outlet clock action saved on this device. It will sync automatically when your internet connection returns.');
                    }
                });
            });

            window.addEventListener('online', syncQueuedClockIns);
            syncQueuedClockIns();
        });

        function highlightOutletCard(id) {
            const el = document.getElementById('outlet-card-' + id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('ring-2', 'ring-brand-red', 'border-brand-red');
                setTimeout(() => {
                    el.classList.remove('ring-2', 'ring-brand-red', 'border-brand-red');
                }, 3000);
            }
        }
    </script>
</body>
</html>
