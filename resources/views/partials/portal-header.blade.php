@php
    $headerUser = auth()->user();
    $todayAtt   = \App\Models\Attendance::where('user_id', $headerUser->id)
        ->whereDate('clock_in_at', \Illuminate\Support\Carbon::today())
        ->first();
    $isHeaderDev = in_array(strtolower(trim($headerUser->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    $isHeaderSuperAdmin = $headerUser->access_role === 'super_admin';
    $isHeaderAutoClocked = $isHeaderDev || $isHeaderSuperAdmin;
    $headerHasTodayTask = \App\Models\Task::where(function ($query) use ($headerUser) {
            $query->where('assigned_to', $headerUser->id)
                ->orWhere('assigned_by', $headerUser->id);
        })
        ->whereDate('created_at', \Illuminate\Support\Carbon::today())
        ->exists();
    $canHeaderClockOutNow = \Illuminate\Support\Carbon::now()->gte(\Illuminate\Support\Carbon::today()->setTime(18, 0, 0));
@endphp

<header class="border-b border-brand-white/10 bg-brand-black/60 px-4 py-3 sm:px-6 sm:py-4 lg:px-10">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button type="button"
                    @click.stop="toggleSidebar()"
                    :aria-expanded="(window.matchMedia('(min-width: 1024px)').matches ? ! sidebarCollapsed : sidebarOpen).toString()"
                    aria-controls="portal-sidebar"
                    aria-label="Toggle navigation menu"
                    class="inline-flex items-center gap-2 rounded-full border border-brand-white/20 px-3 py-2 text-[10px] uppercase tracking-[0.2em] text-brand-white/70 transition hover:border-brand-white/35 hover:bg-brand-white/5 hover:text-brand-white sm:px-4 sm:text-xs sm:tracking-[0.3em]">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path x-show="sidebarCollapsed" d="M9 18l6-6-6-6"></path>
                    <path x-show="! sidebarCollapsed" d="M15 18l-6-6 6-6"></path>
                </svg>
                <span class="hidden sm:inline" x-text="sidebarCollapsed ? 'Show Nav' : 'Hide Nav'"></span>
                <span class="sm:hidden">Nav</span>
            </button>
            <div>
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-ash sm:text-xs sm:tracking-[0.3em]">CMIH Africa Staff Portal</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-4">
            <img src="{{ auth()->user()->profilePhotoUrl() }}" alt="{{ auth()->user()->name }}" class="h-9 w-9 rounded-full object-cover border border-brand-white/20 sm:h-10 sm:w-10" />
            <div class="hidden sm:block text-right">
                <p class="text-sm text-brand-white">{{ auth()->user()->name }}</p>
                <p class="text-xs text-brand-white/60">{{ auth()->user()->email }}</p>
            </div>
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
            <button type="button"
                    data-native-notification-toggle
                    class="relative inline-flex h-9 items-center gap-2 rounded-full border border-brand-white/20 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-white/70 transition hover:border-brand-white/35 hover:bg-brand-white/5 hover:text-brand-white sm:px-3.5"
                    aria-label="Enable or test device alerts"
                    title="Enable or test device alerts">
                <span data-native-notification-dot class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-brand-white/30 ring-2 ring-brand-black"></span>
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="hidden xl:inline" data-native-notification-label>Device Alerts</span>
            </button>
            <a href="{{ route('profile.edit') }}" class="hidden sm:inline-flex items-center rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                Profile
            </a>
            
            @if (!$isHeaderAutoClocked)
                @if (!$todayAtt)
                    @if($headerHasTodayTask)
                        <form method="POST" action="{{ route('portal.attendance.clock-in') }}" data-clock-in-form>
                            @csrf
                            <button type="submit" data-clock-in-submit class="inline-flex items-center rounded-full bg-brand-red hover:bg-brand-red-dark px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-white transition-all sm:px-4 sm:text-xs sm:tracking-[0.2em]">
                                Clock In
                            </button>
                        </form>
                    @else
                        <a href="{{ route('portal.tasks') }}" class="inline-flex items-center rounded-full bg-brand-red hover:bg-brand-red-dark px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-white transition-all sm:px-4 sm:text-xs sm:tracking-[0.2em]">
                            Add Task First
                        </a>
                    @endif
                @elseif (!$todayAtt->clock_out_at)
                    <form method="POST" action="{{ route('portal.attendance.clock-out') }}">
                        @csrf
                        <button type="submit" @disabled(!$canHeaderClockOutNow)
                                class="inline-flex items-center rounded-full px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] transition-all sm:px-4 sm:text-xs sm:tracking-[0.2em] {{ $canHeaderClockOutNow ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-brand-white/10 text-brand-white/35 cursor-not-allowed' }}">
                            @if($canHeaderClockOutNow)
                                Clock Out
                            @else
                                Clock Out After 6 PM
                            @endif
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-400 sm:px-4 sm:text-xs sm:tracking-[0.2em]">
                        Shift Done
                    </span>
                @endif
            @endif
        </div>
    </div>
</header>
