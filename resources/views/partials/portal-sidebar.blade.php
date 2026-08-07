@php
    $user       = auth()->user();
    $dept       = strtolower(trim($user->department ?? ''));
    $role       = $user->access_role ?? '';
    $isDeveloper  = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    $isSuperAdmin = $role === 'super_admin';
    $isManager    = in_array($role, ['admin', 'super_admin', 'manager']);

    // HR level 1 = HR Manager (full access), 2 = HR Assistant, 3 = Admin/Front Desk, 4 = Transport/Support, 0 = not HR
    $hrLevel = $user->hrLevel();
    // Full HR access = Level 1 OR super_admin
    $hasFullHr = $user->hasFullHrAccess();

    // Normalise legacy dept keys → current keys
    $normDept = \App\Models\User::normalizeDepartmentKey($user->department);

    // Full module map keyed by normalised dept
    $allDeptModules = [
        'hr_admin'            => ['label' => 'HR & Admin Module',      'route' => 'portal.hr'],
        'finance'             => ['label' => 'Finance Module',          'route' => 'portal.finance'],
        'operations_projects' => ['label' => 'Operations & Projects',   'route' => 'portal.operations'],
        'brands_marketing'    => ['label' => 'Brands & Marketing',      'route' => 'portal.brands'],
        'client_relations'    => ['label' => 'Client Relations',        'route' => 'portal.creative'],
        'creatives'           => ['label' => 'Creative Department',     'route' => 'portal.creative'],
    ];

    // ── Determine sidebar dept links ─────────────────────────────────────────
    // RULE: super_admin only sees ALL departments.
    //       Everyone else (including developers) sees ONLY their own department.
    //       Developer flag only bypasses 403 on controller level — NOT the sidebar.
    //       Transport/supporting staff (hrLevel 4) within HR/Admin dept do NOT
    //       get the HR module — they only see standard staff tools.
    $deptLinks  = [];
    $crossLinks = [];

    if ($isSuperAdmin) {
        // Super admin sees every department module
        $deptLinks = array_values($allDeptModules);
    } else {
        // Transport / Supporting staff in HR dept — NO HR module link
        $isTransportOrSupport = $hrLevel === 4;

        if (isset($allDeptModules[$normDept]) && ! $isTransportOrSupport) {
            $deptLinks[] = $allDeptModules[$normDept];
        }

        // For non-HR staff who are system-level 'admin', also show HR module
        // but only if they have at least HR Level 1-3 (not Transport/Support)
        if ($role === 'admin' && $normDept !== 'hr_admin' && ! $isTransportOrSupport) {
            $deptLinks[] = $allDeptModules['hr_admin'];
        }
    }


    // ── Core links shown to EVERYONE ────────────────────────────────────────
    $coreLinks = [
        ['label' => 'Dashboard',              'route' => 'dashboard'],
        ['label' => 'CMIH Messenger',         'route' => 'portal.messages'],
        ['label' => 'Notifications',          'route' => 'portal.announcements'],
        ['label' => 'Team Management',        'route' => 'portal.directory'],
        ['label' => 'Collaborative Workspace','route' => 'portal.workspace.index'],
        ['label' => 'Cloud Storage',          'route' => 'portal.dropbox'],
        ['label' => 'Profile',                'route' => 'portal.profile'],
    ];

    // ── Financial Tools Links ────────────────────────────────────────────────
    $financialLinks = [
        ['label' => 'Claims & Invoices',        'route' => 'portal.finance'],
        ['label' => 'Project Budgets',          'route' => 'portal.finance.budgets.index'],
        ['label' => 'Salary Advances (Loans)',  'route' => 'portal.finance.advances.index'],
        ['label' => 'Payroll & Banking',        'route' => 'portal.payroll'],
    ];

    // ── HRM Links shown to EVERYONE ──────────────────────────────────────────
    $hrmLinks = [
        ['label' => 'Leaves & Absences',   'route' => 'portal.leaves'],
        ['label' => 'Fleet Requests',      'route' => 'portal.fleet-requests'],
        ['label' => 'Appraisals',          'route' => 'portal.appraisals.index'],
        ['label' => 'DAM & Inventory',     'route' => 'portal.assets'],
        ['label' => 'Visitor Management',  'route' => 'portal.visitors'],
        ['label' => 'Surveys',             'route' => 'portal.surveys.index'],
    ];

    // ── Admin Panel ─────────────────────────────────────────────────────────
    // Full platform admins see the whole panel. HR/CVO-level approvers only see
    // staff management links that the backend actually permits.
    $fullAdminLinks = [
        ['label' => 'Admin Overview', 'route' => 'admin.dashboard'],
        ['label' => 'Manage Staff',   'route' => 'admin.users'],
        ['label' => 'Team Updates',   'route' => 'admin.updates'],
        ['label' => 'Site Content',   'route' => 'admin.content'],
        ['label' => 'Assignments',    'route' => 'admin.tasks'],
        ['label' => 'Announcements',  'route' => 'admin.announcements'],
        ['label' => 'Events',         'route' => 'admin.events'],
        ['label' => 'Brands',         'route' => 'admin.brands'],
        ['label' => 'Portfolio',      'route' => 'admin.portfolio'],
    ];

    if ($isSuperAdmin) {
        $fullAdminLinks[] = ['label' => 'Portfolio Payments', 'route' => 'admin.portfolio-payments'];
    }

    $fullAdminLinks[] = ['label' => 'Settings',       'route' => 'admin.settings'];
    $staffAdminLinks = [
        ['label' => 'Manage Staff', 'route' => 'admin.users'],
    ];
    $canSeeStaffAdminLinks = $hasFullHr && ! $isDeveloper;
    $adminLinks = $user->hasRole(['admin', 'super_admin'])
        ? $fullAdminLinks
        : ($canSeeStaffAdminLinks ? $staffAdminLinks : []);
    $showAdminPanel = count($adminLinks) > 0;
    
    // Merchandiser Admin Panel
    $merchAdminLinks = [
        ['label' => 'Open Admin Hub', 'route' => 'merchandisers.admin.dashboard'],
    ];
    $showMerchAdmin = $user->isMerchandiserPortalAdmin();
@endphp

<aside id="portal-sidebar"
       aria-label="Portal navigation"
       x-show="! sidebarCollapsed || sidebarOpen"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 flex h-full max-h-screen min-h-0 w-[min(18rem,calc(100vw-2rem))] shrink-0 flex-col overflow-y-auto overscroll-contain scrollbar-none border-r border-brand-white/10 bg-brand-black/95 px-4 py-6 shadow-2xl backdrop-blur-xl transition-transform duration-300 ease-out lg:static lg:h-screen lg:w-72 lg:translate-x-0 lg:bg-brand-black/80 lg:shadow-none lg:backdrop-blur-none sm:px-6 sm:py-8">
    <div class="flex items-center justify-between gap-3">
        <x-application-logo class="h-8" />
        <button type="button"
                @click="hideSidebar()"
                class="hidden h-9 w-9 items-center justify-center rounded-full border border-brand-white/10 text-brand-white/55 transition hover:border-brand-white/25 hover:bg-brand-white/5 hover:text-brand-white lg:inline-flex"
                aria-label="Hide navigation"
                title="Hide navigation">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 18l-6-6 6-6"></path>
            </svg>
        </button>
        <button type="button" @click="hideSidebar()" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-white/10 text-brand-white/55 transition hover:border-brand-white/25 hover:bg-brand-white/5 hover:text-brand-white lg:hidden" aria-label="Close menu" title="Close menu">
            <svg class="h-4 w-4 text-brand-white/55" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg>
        </button>
    </div>

    <nav class="mt-8 space-y-2 text-xs uppercase tracking-[0.3em]">
        @foreach ($coreLinks as $link)
            @php
                $isActive = false;
                if ($link['route'] === 'portal.messages') {
                    $isActive = request()->routeIs('portal.messages') || request()->routeIs('portal.messages.show');
                } elseif ($link['route'] === 'portal.profile') {
                    $isActive = request()->routeIs('portal.profile') || request()->routeIs('profile.edit');
                } elseif ($link['route'] === 'portal.workspace.index') {
                    $isActive = request()->routeIs('portal.workspace.*');
                } elseif ($link['route'] === 'portal.finance') {
                    $isActive = request()->routeIs('portal.finance*');
                } else {
                    $isActive = request()->routeIs($link['route']);
                }
            @endphp
            <a href="{{ route($link['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ $isActive ? 'bg-brand-white/10 text-brand-white font-semibold shadow-inner' : 'text-brand-white/60 hover:text-brand-white' }}">
                <span>{{ $link['label'] }}</span>
                @if ($link['route'] === 'portal.messages')
                    @php
                        $msgCount = \App\Models\Message::unreadFor($user)->count();
                    @endphp
                    <span data-sidebar-message-count="{{ $msgCount }}" class="{{ $msgCount > 0 ? '' : 'hidden' }} rounded-full bg-brand-red px-2 py-0.5 text-[9px] font-bold text-white shrink-0 tracking-normal leading-tight shadow-md">
                        {{ $msgCount }}
                    </span>
                @elseif ($link['route'] === 'portal.announcements')
                    @php
                        $notifCount = \App\Models\Notification::where('user_id', auth()->id())
                            ->whereNull('read_at')
                            ->count();
                    @endphp
                    <span data-sidebar-notification-count="{{ $notifCount }}" class="{{ $notifCount > 0 ? '' : 'hidden' }} rounded-full bg-brand-red px-2 py-0.5 text-[9px] font-bold text-white shrink-0 tracking-normal leading-tight shadow-md">
                        {{ $notifCount }}
                    </span>
                @endif
            </a>

            @if ($link['route'] === 'dashboard')
                {{-- Expandable parent-child Tasks Menu --}}
                <div x-data="{ open: {{ request()->routeIs('portal.tasks*') ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="open = !open" type="button" class="w-full flex items-center justify-between rounded-xl px-4 py-3 text-left transition {{ request()->routeIs('portal.tasks*') ? 'text-brand-white font-medium bg-brand-white/5' : 'text-brand-white/60 hover:text-brand-white' }} focus:outline-none uppercase tracking-[0.3em]">
                        <span>Tasks</span>
                        <svg :class="open ? 'rotate-180' : ''" class="w-3 h-3 transition-transform text-brand-white/60" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="pl-4 space-y-1" style="display: none;">
                        <a href="{{ route('portal.tasks') }}" class="flex items-center rounded-xl px-4 py-2 transition text-[10px] tracking-[0.2em] {{ (request()->routeIs('portal.tasks') && !request()->has('filter') && !request()->has('view')) ? 'bg-brand-white/10 text-brand-white font-semibold' : 'text-brand-white/50 hover:text-brand-white' }}">
                            My Tasks
                        </a>
                        <a href="{{ route('portal.tasks', ['view' => 'pending']) }}" class="flex items-center rounded-xl px-4 py-2 transition text-[10px] tracking-[0.2em] {{ request()->query('view') === 'pending' ? 'bg-brand-white/10 text-brand-white font-semibold' : 'text-brand-white/50 hover:text-brand-white' }}">
                            Pending Tasks
                        </a>
                        <a href="{{ route('portal.tasks', ['view' => 'create']) }}" class="flex items-center rounded-xl px-4 py-2 transition text-[10px] tracking-[0.2em] {{ request()->query('view') === 'create' ? 'bg-brand-red/20 text-brand-red font-semibold' : 'text-brand-white/50 hover:text-brand-white' }}">
                            + Create Task
                        </a>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Financial Tools Group Links --}}
    <div class="mt-6">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
        <nav class="mt-3 space-y-2 text-xs uppercase tracking-[0.3em]">
            @foreach ($financialLinks as $link)
                @php
                    $isActive = request()->routeIs($link['route'] . '*');
                @endphp
                <a href="{{ route($link['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ $isActive ? 'bg-brand-white/10 text-brand-white' : 'text-brand-white/60 hover:text-brand-white' }}">
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- HRM Group Links --}}
    <div class="mt-6">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">HRM</p>
        <nav class="mt-3 space-y-2 text-xs uppercase tracking-[0.3em]">
            @foreach ($hrmLinks as $link)
                @php
                    $isActive = false;
                    if ($link['route'] === 'portal.surveys.index') {
                        $isActive = request()->routeIs('portal.surveys.*');
                    } else {
                        $isActive = request()->routeIs($link['route']);
                    }
                @endphp
                <a href="{{ route($link['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ $isActive ? 'bg-brand-white/10 text-brand-white' : 'text-brand-white/60 hover:text-brand-white' }}">
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Department Modules --}}
    @if (count($deptLinks) > 0)
        <div class="mt-6">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">My Department</p>
            <nav class="mt-3 space-y-2 text-xs uppercase tracking-[0.3em]">
                @foreach ($deptLinks as $dLink)
                    <a href="{{ route($dLink['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ request()->routeIs($dLink['route']) ? 'bg-brand-white/10 text-brand-white' : 'text-brand-white/60 hover:text-brand-white' }}">
                        <span>{{ $dLink['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    {{-- Cross-department shared features (e.g. submit budget request) --}}
    @if (count($crossLinks) > 0)
        <div class="mt-4">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
            <nav class="mt-3 space-y-2 text-xs uppercase tracking-[0.3em]">
                @foreach ($crossLinks as $cLink)
                    <a href="{{ route($cLink['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ request()->routeIs($cLink['route']) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-brand-white/60 hover:text-brand-white' }}">
                        <span>{{ $cLink['label'] }}</span>
                        <span class="text-[9px] text-emerald-400/70 uppercase tracking-widest">→ Finance</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    {{-- ⚡ CVO Command Centre — only visible to CVO / super_admin --}}
    @if ($user->isCvoOrSuperAdmin())
        <div class="mt-8">
            <p class="text-xs uppercase tracking-[0.3em] text-amber-400/70">Executive</p>
            <nav class="mt-3 space-y-2 text-xs uppercase tracking-[0.3em]">
                <a href="{{ route('portal.cvo') }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition border {{ request()->routeIs('portal.cvo') ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-amber-400/70 hover:text-amber-300 border-transparent hover:border-amber-500/20' }}">
                    <span>⚡ CVO Command Centre</span>
                </a>
            </nav>
        </div>
    @endif

    {{-- Merchandiser sub-portal Admin Panel --}}
    @if ($showMerchAdmin)
        <div class="mt-8">
            <p class="text-xs uppercase tracking-[0.3em] text-amber-500/70">Merchandiser Portal</p>
            <nav class="mt-4 space-y-2 text-xs uppercase tracking-[0.3em]">
                @foreach ($merchAdminLinks as $link)
                    @php
                        $isActive = request()->routeIs($link['route'] . '*');
                    @endphp
                    <a href="{{ route($link['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ $isActive ? 'bg-brand-white/10 text-brand-white font-semibold' : 'text-brand-white/60 hover:text-brand-white' }}">
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    {{-- Admin Panel Links --}}
    @if ($showAdminPanel)
        <div class="mt-8">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Admin</p>
            <nav class="mt-4 space-y-2 text-xs uppercase tracking-[0.3em]">
                @foreach ($adminLinks as $link)
                    <a href="{{ route($link['route']) }}" class="flex items-center justify-between rounded-xl px-4 py-3 transition {{ request()->routeIs($link['route']) ? 'bg-brand-white/10 text-brand-white' : 'text-brand-white/60 hover:text-brand-white' }}">
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="mt-auto space-y-3 rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Status</p>
        <p class="text-sm text-brand-white">{{ auth()->user()->status === 'active' ? 'Active' : 'Pending' }}</p>
        <div class="text-xs text-brand-white/60 space-y-1">
            <p>Previous login: {{ auth()->user()->previous_login_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
            <p>
                Last login:
                {{ auth()->user()->last_login_at?->diffForHumans() ?? 'First time' }}
                @if (auth()->user()->last_login_at)
                    ({{ auth()->user()->last_login_at->format('M d, Y H:i') }})
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="pt-2 border-t border-brand-white/10 mt-2">
            @csrf
            <button type="submit" class="w-full text-left rounded-xl hover:bg-brand-white/10 px-3 py-1.5 text-xs uppercase tracking-[0.3em] text-brand-red font-semibold transition-all">
                👋 Log Out
            </button>
        </form>
    </div>
</aside>
