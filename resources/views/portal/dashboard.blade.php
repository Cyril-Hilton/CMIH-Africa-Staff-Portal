<x-app-layout>
    <x-slot name="header">
        @php
            $headerDepartment = strtolower(trim((string) $user->department));
            $canExportAttendancePerformance = $user->isCvoOrSuperAdmin()
                || (in_array($headerDepartment, ['hr_admin', 'admin'], true) && $user->hrLevel() <= 2);
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Dashboard</p>
                <h2 class="text-3xl font-display text-brand-white">Welcome back, {{ $user->name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($canExportAttendancePerformance)
                    <a href="{{ route('portal.attendance-performance.export') }}" class="inline-flex items-center justify-center rounded-xl border border-brand-red/40 bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition hover:bg-brand-red-dark">
                        Export Clock-In CSV
                    </a>
                @endif
                <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                    <p>Role: <span class="text-brand-red font-semibold">{{ strtoupper($user->access_role) }}</span></p>
                    <p>Department: {{ ucwords(str_replace('_', ' ', $user->department)) }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @if ($user->must_reset_password)
        <div class="mb-6 rounded-2xl border border-brand-red/40 bg-brand-red/10 p-5 text-sm text-brand-white/80">
            Your account is using a temporary password. Please update it in your profile settings.
        </div>
    @endif

    <!-- Session Status and Errors -->
    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-brand-red/40 bg-brand-red/10 p-4 text-sm text-brand-white/80">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $isDeveloperUser = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
        $canManageMegaColumns = $user->isCvoOrSuperAdmin()
            || in_array($user->access_role, ['admin', 'manager', 'staff'], true)
            || $isDeveloperUser;
        $clockOutAvailableAt = \Illuminate\Support\Carbon::today()->setTime(18, 0, 0);
        $canClockOutNow = \Illuminate\Support\Carbon::now()->gte($clockOutAvailableAt);
    @endphp

    @if (!$isDeveloperUser)
    <!-- Attendance Clock In/Out Widget -->
    <div class="mb-8 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-1">
                <h3 class="text-lg font-display text-brand-white uppercase tracking-wider flex items-center gap-2">
                    Daily Work Gate
                </h3>
                <p class="text-sm text-brand-white/70">
                    @if(!$todayAttendance)
                        @if(!$hasTodayTask)
                            <span class="text-amber-400 font-semibold">You must add a task or project for today before you can clock in.</span>
                        @else
                            You are not clocked in yet. Please set your daily objective to clock in.
                        @endif
                    @elseif(!$todayAttendance->clock_out_at)
                        You clocked in today at <span class="text-brand-white font-semibold">{{ $todayAttendance->clock_in_at->format('h:i A') }}</span> (Status: <span class="font-bold text-sky-400">{{ $todayAttendance->status }}</span>).
                        @if($canClockOutNow)
                            You can clock out now.
                        @else
                            Clock-out opens at <span class="text-amber-300 font-semibold">6:00 PM</span>.
                        @endif
                    @else
                        You completed your shift today! Clock-out time: <span class="text-brand-white font-semibold">{{ $todayAttendance->clock_out_at->format('h:i A') }}</span>.
                    @endif
                </p>
            </div>
            
            <div class="flex-1 max-w-md w-full">
                @if(!$todayAttendance)
                    @if(!$hasTodayTask)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-brand-white/10 bg-brand-black/20 p-4">
                            <p class="text-xs text-brand-white/60">No task registered for today yet.</p>
                            <a href="{{ route('portal.tasks') }}" class="inline-flex justify-center items-center rounded-xl bg-brand-red hover:bg-brand-red-dark px-4 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all whitespace-nowrap">
                                Add Task First
                            </a>
                        </div>
                    @else
                        <form id="clock-in-form" method="POST" action="{{ route('portal.attendance.clock-in') }}" class="space-y-3" data-clock-in-form>
                            @csrf
                            <input type="hidden" name="latitude" id="lat-input">
                            <input type="hidden" name="longitude" id="lon-input">
                            
                            <div>
                                <textarea id="daily_objective" name="daily_objective" rows="2"
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/40 focus:border-brand-red focus:ring-0" 
                                    placeholder="Optional: add a focus note, or leave blank to use your first task for today."></textarea>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="text" name="remote_notes" id="remote_notes" 
                                    class="flex-1 min-w-[200px] rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white placeholder-brand-white/40 focus:border-brand-red focus:ring-0" 
                                    placeholder="Remote notes (e.g. WFH, Field Site - optional)">
                                
                                <button type="submit" id="clock-in-btn" data-clock-in-submit
                                    class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all flex items-center gap-2">
                                    Clock In
                                </button>
                            </div>
                        </form>
                    @endif
                @elseif(!$todayAttendance->clock_out_at)
                    <form method="POST" action="{{ route('portal.attendance.clock-out') }}" class="flex justify-end">
                        @csrf
                        <button type="submit" @disabled(!$canClockOutNow)
                            class="w-full md:w-auto rounded-xl px-6 py-3 text-xs uppercase tracking-[0.2em] font-semibold transition-all flex items-center justify-center gap-2 {{ $canClockOutNow ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-brand-white/10 text-brand-white/35 cursor-not-allowed' }}">
                            @if($canClockOutNow)
                                Clock Out
                            @else
                                Clock Out After 6 PM
                            @endif
                        </button>
                    </form>
                @else
                    <div class="flex justify-end">
                        <span class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-emerald-400">
                            Day Completed
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- 1. Individual KPIs Block -->
    <div class="mb-8">
        <h3 class="text-lg font-display text-brand-white uppercase tracking-wider mb-4">My Individual KPIs</h3>
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">My Completion Rate</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $individualStats['completion_rate'] }}%</p>
                <div class="mt-2 h-1.5 w-full bg-brand-white/10 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: {{ $individualStats['completion_rate'] }}%"></div>
                </div>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Active Deliverables</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $individualStats['open_deliverables'] }}</p>
                <p class="text-xs text-brand-ash mt-2">Pending final sign-off</p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Punctuality Score</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $individualStats['punctuality_score'] }}%</p>
                <div class="mt-2 h-1.5 w-full bg-brand-white/10 rounded-full overflow-hidden">
                    <div class="h-full bg-sky-500" style="width: {{ $individualStats['punctuality_score'] }}%"></div>
                </div>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Overtime Accumulated</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $individualStats['overtime_hours'] }} hrs</p>
                <p class="text-xs text-brand-ash mt-2">Post 6:00 PM hours</p>
            </div>
        </div>
    </div>

    <!-- 2. Collective KPIs & Department Badges -->
    <div class="mb-8">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-lg font-display text-brand-white uppercase tracking-wider">Collective Agency Dashboard</h3>
            @if($winningDept)
                <div class="flex items-center gap-2 rounded-xl bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 text-xs text-amber-400 uppercase tracking-widest font-semibold animate-pulse">
                    <span>Top Performer:</span>
                    <span>{{ $departments[$winningDept] ?? $winningDept }}</span>
                </div>
            @endif
        </div>
        <div class="grid gap-6 md:grid-cols-3">
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Agency Work Completed</p>
                <div class="mt-3 flex items-baseline justify-between">
                    <p class="text-3xl font-semibold text-brand-white">{{ $collectiveStats['reached_activations'] }}</p>
                    <p class="text-xs text-brand-ash">Total cycle tasks: {{ $collectiveStats['target_activations'] }}</p>
                </div>
                <div class="mt-2 h-1.5 w-full bg-brand-white/10 rounded-full overflow-hidden">
                    @php
                        $targetPercent = $collectiveStats['target_activations'] > 0 ? min(round(($collectiveStats['reached_activations'] / $collectiveStats['target_activations']) * 100), 100) : 0;
                    @endphp
                    <div class="h-full bg-brand-red" style="width: {{ $targetPercent }}%"></div>
                </div>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Project Win-Rate</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $collectiveStats['win_rate'] }}%</p>
                <p class="text-xs text-brand-ash mt-2">Approved completed / total cycle tasks</p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Critical Bottlenecks</p>
                <p class="mt-3 text-3xl font-semibold text-brand-red">{{ $collectiveStats['bottlenecks'] }}</p>
                <p class="text-xs text-brand-ash mt-2">High priority overdue / risk items</p>
            </div>
        </div>
    </div>

    <!-- 3. Analytical Charts Block -->
    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h4 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">Task Performance Velocity</h4>
            <div class="h-64">
                <canvas id="velocityChart"></canvas>
            </div>
        </div>
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h4 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">Weekly Completion Trends</h4>
            <div class="h-64">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
    </div>

    <!--  Performance Awards Leaderboard Section -->
    <div id="mega-table-live-region"
         data-refresh-url="{{ route('portal.dashboard.live', request()->query()) }}"
         class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Recognitions & Standing</p>
                <h3 class="text-xl font-display text-brand-white uppercase"> CMIH Performance Leaderboard</h3>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if(in_array($user->access_role, ['super_admin','admin']) || in_array(strtolower(trim($user->department ?? '')), ['hr_admin','admin']))
                    <button onclick="openAwardLockModal()"
                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 px-4 py-2 text-xs uppercase tracking-[0.15em] text-amber-400 font-semibold transition-all">
                         Lock & Issue Awards
                    </button>
                @endif
                <select id="leaderboard-period" onchange="fetchLeaderboard(this.value)"
                        class="rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white">
                    @php
                        $monthsToShow = [];
                        for ($i = 0; $i < 6; $i++) {
                            $m = now()->subMonths($i);
                            $monthsToShow[$m->format('Y-m')] = $m->format('F Y');
                        }
                        $yearToShow = now()->format('Y');
                    @endphp
                    <optgroup label="Monthly Awards">
                        @foreach($monthsToShow as $val => $lbl)
                            <option value="{{ $val }}" {{ $val === $currentMonth ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Yearly Awards">
                        <option value="{{ $yearToShow }}" {{ $yearToShow === $currentYear ? 'selected' : '' }}>Year {{ $yearToShow }}</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div id="award-period-notice" class="hidden mb-6 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-400">
            <!-- Dynamic notice e.g. estimated standings -->
        </div>

        <div class="grid gap-6">
            <!-- Cards columns -->
            <div class="grid min-w-0 gap-6" id="leaderboard-container">
                <!-- Employee Awards Card -->
                <div class="min-w-0 rounded-xl border border-brand-white/10 bg-brand-black/20 p-4 sm:p-5 space-y-4">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-bold border-b border-brand-white/5 pb-2">Employee Award Standings</h4>
                    <div id="employee-award-cards" class="grid items-start gap-4 lg:grid-cols-3">
                        <!-- Dynamic Employee Cards -->
                    </div>
                </div>

                <!-- Department Awards Card -->
                <div class="min-w-0 rounded-xl border border-brand-white/10 bg-brand-black/20 p-4 sm:p-5 space-y-4">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-bold border-b border-brand-white/5 pb-2">Department Award Standings</h4>
                    <div id="department-award-cards" class="grid items-start gap-4 lg:grid-cols-3">
                        <!-- Dynamic Department Cards -->
                    </div>
                </div>
            </div>

            <!-- Charts Column -->
            <div class="min-w-0 rounded-xl border border-brand-white/10 bg-brand-black/20 p-4 sm:p-5 flex flex-col justify-between">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-bold border-b border-brand-white/5 pb-2 mb-4">Performance Scores</h4>
                <div class="h-72 md:h-80 relative flex-1 min-w-0">
                    <canvas id="leaderboardChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. The Collective Dashboard (The Mega Table Section) -->
    <div id="mega-operational-table-live-region"
         data-silent-region="mega-operational-table"
         data-refresh-url="{{ route('portal.dashboard.live', request()->query()) }}"
         class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Operational Control</p>
                <h3 class="text-xl font-display text-brand-white uppercase">The Mega Table</h3>
            </div>
            <div class="flex items-center gap-2">
                @if($canManageMegaColumns)
                    <button onclick="document.getElementById('col-manager-modal').classList.remove('hidden')"
                            class="rounded-xl border border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 px-4 py-2 text-xs uppercase tracking-[0.15em] text-brand-white/70 hover:text-brand-white transition-all">
                         Manage Columns
                    </button>
                @endif
                <a href="{{ route('portal.tasks') }}" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-4 py-2 text-xs uppercase tracking-[0.2em] font-semibold transition-all text-white">
                    + Add Task / Project
                </a>
            </div>
        </div>

        {{-- Department Tab Toggles --}}
        <div class="flex overflow-x-auto flex-nowrap lg:flex-wrap gap-2 border-b border-brand-white/10 pb-4 mb-6 scrollbar-none snap-x snap-mandatory" id="dept-tabs">
            @foreach($departments as $key => $label)
                <button onclick="switchTab('{{ $key }}')" id="tab-btn-{{ $key }}"
                        data-award-winner="{{ $winningDept === $key ? 'true' : 'false' }}"
                        class="dept-tab-btn rounded-xl border border-brand-white/10 px-4 py-2.5 text-xs uppercase tracking-[0.15em] text-brand-ash hover:text-brand-white transition-all flex items-center gap-2 whitespace-nowrap snap-start">
                    @if($winningDept === $key)<span></span>@endif
                    <span>{{ $label }}</span>
                </button>
            @endforeach
        </div>

        {{-- Department Table Panes --}}
        @foreach($departments as $key => $label)
            @php
                $customCols = $departmentColumns[$key] ?? collect();
                $mappedDeptsForTab = $deptMapping[$key] ?? [$key];
                $mappedDeptKeysForTab = collect($mappedDeptsForTab)
                    ->map(fn ($department) => \App\Models\User::normalizeDepartmentKey($department))
                    ->all();
                $statusColors = [
                    'Open'               => 'border-gray-500/30 bg-gray-500/10 text-gray-400',
                    'In Progress'        => 'border-blue-500/30 bg-blue-500/10 text-blue-400',
                    'Awaiting Approval'  => 'border-purple-500/30 bg-purple-500/10 text-purple-400',
                    'Awaiting Feedback'  => 'border-amber-500/30 bg-amber-500/10 text-amber-400',
                    'Sent'               => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-400',
                    'Approved'           => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
                    'Completed'          => 'border-green-600/30 bg-green-600/10 text-green-500',
                    'Rejected'           => 'border-red-500/30 bg-red-500/10 text-red-400',
                    'Paid'               => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-400',
                    'Overdue'            => 'border-red-600/40 bg-red-600/15 text-red-500 font-bold',
                    'Cancelled'          => 'border-zinc-600/30 bg-zinc-600/10 text-zinc-500',
                ];
            @endphp
            <div id="tab-pane-{{ $key }}" class="dept-tab-pane hidden">
                <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm text-brand-white/70" data-table-key="mega-table-{{ $key }}">
                    <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash border-b border-brand-white/10">
                        <tr class="">
                            <th class="w-10 pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">S/N</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Client</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Campaign</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Lead Staff</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Supporting</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Role</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Deliverables</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Deadline</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Approved / Updated</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Status</th>
                            <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Priority</th>
                            {{-- Dynamic custom columns --}}
                            @foreach($customCols as $col)
                                <th class="pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">{{ $col->label }}</th>
                            @endforeach
                                <th class="w-24 pb-3 pr-6 font-semibold text-left" style="padding-bottom: 12px; padding-right: 24px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departmentTables[$key] as $idx => $task)
                            @php
                                $leadDepartment = $task->assignee?->department;
                                $leadDepartmentKey = \App\Models\User::normalizeDepartmentKey($leadDepartment);
                                $leadIsCrossDepartment = $leadDepartmentKey && ! in_array($leadDepartmentKey, $mappedDeptKeysForTab, true);
                                $leadDepartmentLabel = \App\Models\PerformanceAward::getDepartmentLabel($leadDepartment);
                                if ($leadDepartmentLabel === $leadDepartment) {
                                    $leadDepartmentLabel = ucwords(str_replace('_', ' ', (string) $leadDepartment));
                                }
                                $activityAt = $task->completion_reviewed_at ?? $task->updated_at ?? $task->created_at;
                                $activityLabel = $task->completion_review_status === 'approved' && $task->completion_reviewed_at ? 'Approved' : 'Updated';
                                $canManageTask = $task->canBeEditedBy($user);
                            @endphp
                            <tr class="border-b border-brand-white/5 hover:bg-brand-white/5 transition-colors group">
                                <td class="py-3 pr-6 font-mono text-xs">{{ ($departmentTables[$key]->firstItem() ?? 1) + $idx }}</td>
                                <td class="py-3 pr-6 text-brand-white font-semibold">{{ $task->client_name ?? ($task->campaign?->client_name ?? 'N/A') }}</td>
                                <td class="py-3 pr-6 text-brand-ash text-xs">{{ $task->campaign?->name ?? '-' }}</td>
                                <td class="py-3 pr-6 {{ $leadIsCrossDepartment ? 'border-l-2 border-sky-400/70 bg-sky-500/[0.08] pl-3' : '' }}"
                                    data-cross-department-lead="{{ $leadIsCrossDepartment ? 'true' : 'false' }}">
                                    <div class="flex min-w-[150px] flex-col gap-1.5">
                                        <span class="{{ $leadIsCrossDepartment ? 'font-semibold text-sky-100' : '' }}">{{ $task->assignee?->name ?? 'Unassigned' }}</span>
                                        @if($leadIsCrossDepartment)
                                            <span class="flex flex-wrap items-center gap-1.5 text-[10px] leading-tight">
                                                <span class="rounded-full border border-sky-300/25 bg-sky-300/10 px-2 py-0.5 font-semibold uppercase tracking-[0.12em] text-sky-300">
                                                    External lead
                                                </span>
                                                <span class="max-w-[11rem] text-sky-100/70 normal-case tracking-normal">
                                                    {{ $leadDepartmentLabel }}
                                                </span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 pr-6">
                                    @if(!empty($task->supporting_staff_ids))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($task->supporting_staff as $s)
                                                <span class="rounded bg-brand-white/10 px-2 py-0.5 text-xs text-brand-white/80">{{ $s->name }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-brand-white/30 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-6 text-xs text-brand-white/60 min-w-[150px]">{{ $task->supporting_roles ?? '-' }}</td>
                                <td class="py-3 pr-6 text-xs min-w-[250px]">
                                    <div class="font-semibold text-brand-white mb-0.5">{{ $task->title }}</div>
                                    @if($task->details)
                                        <div class="text-[11px] leading-relaxed text-brand-white/50">{!! $task->details !!}</div>
                                    @endif
                                </td>
                                <td class="py-3 pr-6 text-xs whitespace-nowrap">{{ $task->due_on?->format('d M Y') ?? 'TBD' }}</td>
                                <td class="py-3 pr-6 text-xs whitespace-nowrap">
                                    <span class="block font-semibold text-brand-white/80">{{ $activityAt?->format('d M Y') ?? 'TBD' }}</span>
                                    <span class="text-[10px] uppercase tracking-widest text-brand-white/35">{{ $activityLabel }}</span>
                                </td>
                                <td class="py-3 pr-6">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusColors[$task->status] ?? 'border-brand-white/10' }}">
                                        {{ $task->status }}
                                    </span>
                                </td>
                                <td class="py-3 pr-6 text-xs font-semibold {{ ['High' => 'text-brand-red', 'Medium' => 'text-amber-500', 'Low' => 'text-green-500'][$task->priority] ?? 'text-brand-white' }}">
                                    {{ $task->priority }}
                                </td>
                                {{-- Dynamic custom column values --}}
                                @foreach($customCols as $col)
                                    <td class="py-3 pr-6 text-xs text-brand-white/60">
                                        {{ $task->custom_fields[$col->column_key] ?? '-' }}
                                    </td>
                                @endforeach
                                {{-- Actions (Reassign + Edit) --}}
                                <td class="py-3 pr-6">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($canManageTask)
                                            <button type="button" onclick="openReassignModal({{ $task->id }}, '{{ addslashes($task->title) }}', {{ $task->assigned_to ?? 'null' }})"
                                                    title="Reassign task"
                                                    class="rounded-lg bg-sky-600/20 border border-sky-500/30 px-2 py-1 text-[10px] text-sky-400 hover:bg-sky-600/40 transition-all">
                                                Reassign
                                            </button>
                                        @endif
                                        @if($canManageTask)
                                            <a href="{{ route('portal.tasks.edit', $task) }}"
                                               title="Edit task"
                                               class="rounded-lg bg-brand-white/5 border border-brand-white/10 px-2 py-1 text-[10px] text-brand-white/60 hover:text-brand-white hover:bg-brand-white/10 transition-all">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('portal.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete task" class="rounded-lg bg-brand-red/10 border border-brand-red/30 px-2 py-1 text-[10px] text-brand-red hover:bg-brand-red/20 transition-all">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 12 + $customCols->count() }}"
                                    class="py-8 text-center text-sm text-brand-white/30 italic">
                                    No campaign tasks registered in this department yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @php
                    $megaPaginator = $departmentTables[$key]->appends(array_merge(request()->except($departmentTables[$key]->getPageName()), ['tab' => $key]));
                @endphp
                <div class="mt-4" data-mega-pagination>
                    <x-dashboard-pagination :paginator="$megaPaginator" />
                </div>
            </div>
        @endforeach
    </div>

    {{--  WEEKLY CONSOLIDATED TABLE  --}}
    <style>
        .weekly-rich-content {
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            color: rgba(255,255,255,.78);
            line-height: 1.65;
            overflow-wrap: anywhere;
        }
        .weekly-rich-content :is(p, ul, ol, table, blockquote) { margin-bottom: .65rem; }
        .weekly-rich-content :is(p, ul, ol, blockquote):last-child { margin-bottom: 0; }
        .weekly-rich-content ul { list-style: disc; padding-left: 1rem; }
        .weekly-rich-content ol { list-style: decimal; padding-left: 1rem; }
        .weekly-rich-content :is(figure, .table) {
            width: 100% !important;
            max-width: 100%;
            margin: .15rem 0 .65rem;
            overflow-x: auto;
        }
        .weekly-rich-content table {
            width: 100% !important;
            min-width: 0;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: .35rem;
            margin-bottom: .35rem;
        }
        .weekly-rich-content th,
        .weekly-rich-content td {
            width: auto !important;
            height: auto !important;
            border: 1px solid rgba(255,255,255,.16) !important;
            padding: .55rem .7rem;
            vertical-align: top;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .weekly-rich-content th { background: rgba(255,255,255,.08); color: #fff; }
        .weekly-consolidated-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .weekly-consolidated-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }
        .weekly-consolidated-table {
            width: 100%;
            min-width: max(100%, 56rem);
            table-layout: auto;
            border-collapse: separate;
            border-spacing: clamp(.3rem, .55vw, .75rem) .65rem;
        }
        .weekly-consolidated-table th,
        .weekly-consolidated-table td {
            min-width: clamp(6.75rem, 9vw, 11rem);
            max-width: min(24rem, 34vw);
            padding: clamp(.7rem, .85vw, 1rem);
            vertical-align: top;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .weekly-consolidated-table th {
            letter-spacing: .18em;
            line-height: 1.35;
        }
        .weekly-consolidated-table th:nth-child(1),
        .weekly-consolidated-table td:nth-child(1) { min-width: 6.5rem; max-width: 8rem; }
        .weekly-consolidated-table th:nth-child(2),
        .weekly-consolidated-table td:nth-child(2) { min-width: 9rem; max-width: 13rem; }
        .weekly-consolidated-table th:nth-child(3),
        .weekly-consolidated-table td:nth-child(3) { min-width: 10rem; max-width: 16rem; }
        .weekly-consolidated-table th:nth-child(4),
        .weekly-consolidated-table td:nth-child(4) { min-width: 12rem; max-width: 24rem; }
        .weekly-consolidated-table th:nth-child(5),
        .weekly-consolidated-table td:nth-child(5),
        .weekly-consolidated-table th:nth-child(6),
        .weekly-consolidated-table td:nth-child(6),
        .weekly-consolidated-table th:nth-child(7),
        .weekly-consolidated-table td:nth-child(7) { min-width: 12rem; max-width: 24rem; }
        .weekly-consolidated-table th:nth-last-child(2),
        .weekly-consolidated-table td:nth-last-child(2) { min-width: 7rem; max-width: 9rem; }
        .weekly-consolidated-table th:last-child,
        .weekly-consolidated-table td:last-child { min-width: 11rem; max-width: 13rem; }
        .weekly-consolidated-table--brands {
            min-width: 145rem;
            table-layout: fixed;
            border-spacing: .65rem .75rem;
        }
        .weekly-consolidated-table--brands th,
        .weekly-consolidated-table--brands td {
            max-width: none;
        }
        .weekly-consolidated-table--brands th:nth-child(1),
        .weekly-consolidated-table--brands td:nth-child(1) { width: 8rem; min-width: 8rem; max-width: 8rem; }
        .weekly-consolidated-table--brands th:nth-child(2),
        .weekly-consolidated-table--brands td:nth-child(2) { width: 14rem; min-width: 14rem; max-width: 14rem; }
        .weekly-consolidated-table--brands th:nth-child(3),
        .weekly-consolidated-table--brands td:nth-child(3) { width: 30rem; min-width: 30rem; max-width: 30rem; }
        .weekly-consolidated-table--brands th:nth-child(4),
        .weekly-consolidated-table--brands td:nth-child(4) { width: 18rem; min-width: 18rem; max-width: 18rem; }
        .weekly-consolidated-table--brands th:nth-child(5),
        .weekly-consolidated-table--brands td:nth-child(5) { width: 20rem; min-width: 20rem; max-width: 20rem; }
        .weekly-consolidated-table--brands th:nth-child(6),
        .weekly-consolidated-table--brands td:nth-child(6) { width: 24rem; min-width: 24rem; max-width: 24rem; }
        .weekly-consolidated-table--brands th:nth-child(7),
        .weekly-consolidated-table--brands td:nth-child(7) { width: 10rem; min-width: 10rem; max-width: 10rem; }
        .weekly-consolidated-table--brands th:nth-child(8),
        .weekly-consolidated-table--brands td:nth-child(8) { width: 10rem; min-width: 10rem; max-width: 10rem; }
        .weekly-consolidated-table--brands th:nth-child(9),
        .weekly-consolidated-table--brands td:nth-child(9) { width: 22rem; min-width: 22rem; max-width: 22rem; }
        .weekly-consolidated-table--brands .weekly-rich-content {
            font-size: .9rem;
            line-height: 1.55;
        }
        .weekly-consolidated-table--brands .weekly-rich-content table {
            font-size: .82rem;
            line-height: 1.45;
        }
        .weekly-consolidated-table--brands .weekly-rich-content td {
            padding: .65rem .75rem;
        }
        .weekly-consolidated-actions {
            display: flex;
            flex-direction: column;
            gap: .65rem;
            min-width: 11rem;
        }
        .weekly-consolidated-action-button {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            border-radius: .85rem;
            padding: .7rem .85rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            transition: border-color .18s ease, background .18s ease, color .18s ease;
        }
        .weekly-consolidated-action-button--danger {
            color: #f87171;
        }
        .weekly-consolidated-edit-panel {
            z-index: 30;
            width: min(92vw, 68rem);
        }
        .weekly-consolidated-table details {
            position: relative;
        }
        .weekly-consolidated-table details > summary {
            list-style: none;
        }
        .weekly-consolidated-table details > summary::-webkit-details-marker {
            display: none;
        }
        @media (max-width: 640px) {
            .weekly-consolidated-table {
                min-width: 48rem;
                border-spacing: .4rem .55rem;
            }
            .weekly-consolidated-table--brands {
                min-width: 122rem;
                border-spacing: .5rem .65rem;
            }
            .weekly-consolidated-table th,
            .weekly-consolidated-table td {
                min-width: 7.5rem;
                max-width: 18rem;
                padding: .7rem;
            }
        }
    </style>
    @php
        $isAllWeeklyDepartments = $isAllWeeklyDepartments ?? false;
        $isBrandsWeeklyDepartment = ! $isAllWeeklyDepartments && $weeklyDepartmentFilter === 'brands_marketing';
        $weeklyVisibleCustomColumns = $isBrandsWeeklyDepartment ? collect() : $weeklyConsolidatedDisplayColumns;
        $weeklyBaseColumnCount = $isAllWeeklyDepartments
            ? 8 + ($weeklyDepartmentHasBreakdown ? 3 : 0)
            : ($isBrandsWeeklyDepartment ? 9 : (5 + ($weeklyDepartmentHasBreakdown ? 3 : 0)));
        $weeklyTableColumnCount = $weeklyBaseColumnCount + $weeklyVisibleCustomColumns->count() + ($canManageActiveWeeklyDepartment ? 1 : 0);
        $weeklyStatusClasses = [
            'Planned' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
            'In Progress' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
            'Done' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            'Blocked' => 'border-brand-red/30 bg-brand-red/10 text-brand-red',
            'Deferred' => 'border-brand-white/20 bg-brand-white/10 text-brand-white/70',
        ];
    @endphp
    <div id="weekly-consolidated-live-region"
         data-silent-region="weekly-consolidated-table"
         data-refresh-url="{{ route('portal.dashboard.live', array_merge(request()->query(), ['weekly_department' => $weeklyDepartmentFilter]), false) }}"
         data-current-weekly-department="{{ $weeklyDepartmentFilter }}"
         class="mt-10 glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/[0.03] p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-brand-ash">Line Manager Summary</p>
                <h3 class="mt-1 text-xl font-display uppercase text-brand-white">Weekly Consolidated Table</h3>
                <p class="mt-2 max-w-4xl text-sm text-brand-white/60">
                    A manager-owned weekly review table. Everyone can view it; only line managers, CVO, and Super Admin can update rows. Custom columns are personal to the manager who created them.
                </p>
            </div>
            @if($canManageActiveWeeklyDepartment)
                <div class="flex flex-wrap gap-2">
                    @unless($isBrandsWeeklyDepartment)
                        <button type="button" onclick="document.getElementById('weekly-column-manager').classList.toggle('hidden')"
                                class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-white/75 hover:bg-brand-white/10 hover:text-brand-white">
                             Manage Columns
                        </button>
                    @endunless
                    <button type="button" onclick="document.getElementById('weekly-consolidated-form').classList.toggle('hidden')"
                            class="rounded-xl bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-white hover:bg-brand-red-dark">
                        + Add Weekly Row
                    </button>
                </div>
            @endif
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.22em] text-emerald-200/70">Completed</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-200">{{ $weeklyConsolidatedMetrics['completed'] }}</p>
            </div>
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.22em] text-amber-200/70">Pending</p>
                <p class="mt-2 text-2xl font-semibold text-amber-200">{{ $weeklyConsolidatedMetrics['pending'] }}</p>
            </div>
            <div class="rounded-2xl border border-brand-red/20 bg-brand-red/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.22em] text-brand-red/80">Blocked / Deferred</p>
                <p class="mt-2 text-2xl font-semibold text-brand-red">{{ $weeklyConsolidatedMetrics['blocked'] }}</p>
            </div>
            <div class="rounded-2xl border border-orange-500/20 bg-orange-500/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.22em] text-orange-200/70">Overdue</p>
                <p class="mt-2 text-2xl font-semibold text-orange-200">{{ $weeklyConsolidatedMetrics['overdue'] }}</p>
            </div>
        </div>

        <div class="mt-5 flex overflow-x-auto flex-nowrap gap-2 border-b border-brand-white/10 pb-4 scrollbar-none snap-x snap-mandatory">
            @foreach($weeklyConsolidatedDepartments as $key => $label)
                @php
                    $weeklyTabUrl = route('dashboard', array_merge(request()->query(), ['weekly_department' => $key]), false);
                    $isActiveWeeklyTab = $weeklyDepartmentFilter === $key;
                @endphp
                <a href="{{ $weeklyTabUrl }}"
                   data-weekly-department-tab
                   class="rounded-xl border px-4 py-2.5 text-xs uppercase tracking-[0.15em] transition-all flex items-center gap-2 whitespace-nowrap snap-start {{ $isActiveWeeklyTab ? 'border-brand-red bg-brand-red/10 text-brand-white' : 'border-brand-white/10 text-brand-ash hover:text-brand-white' }}">
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>

        @if($canManageActiveWeeklyDepartment && ! $isBrandsWeeklyDepartment)
            <div id="weekly-column-manager" class="hidden mt-5 rounded-2xl border border-brand-white/10 bg-brand-black/45 p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                    <form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.columns.store') }}" class="grid flex-1 gap-3 md:grid-cols-[1fr_180px_auto]">
                        @csrf
                        <input type="hidden" name="department" value="{{ $weeklyDepartmentFilter }}">
                        <input type="text" name="label" required maxlength="100" placeholder="New column label, e.g. Risks / Client Feedback"
                               class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                        <select name="type" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                            <option value="rich_text">Rich Text / Table</option>
                            <option value="text">Short Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="status">Status</option>
                        </select>
                        <button type="submit" class="rounded-xl bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-white">Add Column</button>
                    </form>
                </div>
                <div class="mt-4 grid gap-2 lg:grid-cols-2">
                    @forelse($myWeeklyConsolidatedColumns as $column)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                            <form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.columns.update', $column) }}" class="grid gap-2 md:grid-cols-[1fr_130px_90px_auto]">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="label" value="{{ $column->label }}" required class="rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                <select name="type" class="rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                    @foreach(['rich_text' => 'Rich Text', 'text' => 'Text', 'number' => 'Number', 'date' => 'Date', 'status' => 'Status'] as $type => $label)
                                        <option value="{{ $type }}" @selected($column->type === $type)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="order" value="{{ $column->order }}" min="0" max="999" class="rounded-lg border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                <button type="submit" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-bold uppercase tracking-widest text-emerald-300">Save</button>
                            </form>
                            <form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.columns.destroy', $column) }}" onsubmit="return confirm('Remove this weekly column from your entries?')" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-brand-red/30 bg-brand-red/10 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-red">Remove Column</button>
                            </form>
                        </div>
                    @empty
                        <p class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4 text-xs text-brand-white/45">No personal weekly columns yet.</p>
                    @endforelse
                </div>
            </div>
        @endif

        @if($canManageActiveWeeklyDepartment)
            <form id="weekly-consolidated-form" method="POST" action="{{ route('portal.dashboard.weekly-consolidated.store') }}"
                  class="hidden mt-5 rounded-2xl border border-brand-white/10 bg-brand-black/40 p-4">
                @csrf
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Department</label>
                        <input type="hidden" name="department" value="{{ $weeklyDepartmentFilter }}">
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] px-3 py-2 text-xs font-semibold text-brand-white">
                            {{ $departments[$weeklyDepartmentFilter] ?? \App\Models\User::departmentLabel($weeklyDepartmentFilter) }}
                        </div>
                    </div>
                    @if($isBrandsWeeklyDepartment)
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Task ID</label>
                            <input type="text" name="brands_task_id" value="{{ old('brands_task_id') }}" required maxlength="80" placeholder="e.g. BTL-REX-001"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                        </div>
                    @endif
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Start Date' : 'Week Start' }}</label>
                        <input type="date" name="week_start" value="{{ old('week_start', now()->startOfWeek()->toDateString()) }}" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Due Date' : 'Week End' }}</label>
                        <input type="date" name="week_end" value="{{ old('week_end', now()->endOfWeek()->toDateString()) }}" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Task Name' : 'Client' }}</label>
                        <input type="text" name="client_name" value="{{ old('client_name') }}" placeholder="Optional" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Project' : 'Campaign / Workstream' }}</label>
                        <input type="text" name="campaign_name" value="{{ old('campaign_name') }}" placeholder="Optional" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Assigned To' : 'Lead Staff' }}</label>
                        <select name="lead_staff_id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                            <option value="">Select lead...</option>
                            @foreach($allStaff as $staff)
                                <option value="{{ $staff->id }}" @selected((int) old('lead_staff_id') === (int) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[10px] uppercase tracking-widest text-brand-ash">Supporting Staff + Role</p>
                        <button type="button" onclick="cloneWeeklySupportRow('weekly-support-rows')" class="rounded-lg border border-brand-white/10 px-3 py-1.5 text-[10px] uppercase tracking-widest text-brand-white/60 hover:bg-brand-white/10">+ Add Staff</button>
                    </div>
                    <div id="weekly-support-rows" class="mt-3 space-y-2">
                        <div class="grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row">
                            <select name="supporting_staff_ids[]" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                <option value="">Supporting staff...</option>
                                @foreach($allStaff as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="supporting_roles[]" placeholder="Role on this weekly action" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                            <button type="button" onclick="removeWeeklySupportRow(this)" class="rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red">Remove</button>
                        </div>
                    </div>
                </div>

                @if($isBrandsWeeklyDepartment)
                    <div class="mt-4">
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">KPIS</label>
                        <textarea name="target_breakdown" rows="4" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('target_breakdown') }}</textarea>
                    </div>
                @elseif($weeklyDepartmentHasBreakdown)
                    <div class="mt-4 grid gap-3 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Target</label>
                            <textarea name="target_breakdown" rows="5" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('target_breakdown') }}</textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Achieved</label>
                            <textarea name="achieved_breakdown" rows="5" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('achieved_breakdown') }}</textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Gap To Go</label>
                            <textarea name="gap_breakdown" rows="5" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('gap_breakdown') }}</textarea>
                        </div>
                    </div>
                @endif

                @if(! $isBrandsWeeklyDepartment && $myWeeklyConsolidatedColumns->isNotEmpty())
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @foreach($myWeeklyConsolidatedColumns as $column)
                            <div>
                                <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $column->label }}</label>
                                <textarea name="custom_fields[{{ $column->column_key }}]" rows="4" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">{{ old('custom_fields.' . $column->column_key) }}</textarea>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_220px]">
                    <div>
                        <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $isBrandsWeeklyDepartment ? 'Project Brief' : 'Deliverables / Weekly Summary' }}</label>
                        <textarea name="deliverables" rows="6" required class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('deliverables') }}</textarea>
                    </div>
                        @if($isBrandsWeeklyDepartment)
                            <div>
                                <input type="hidden" name="status" value="{{ old('status', 'In Progress') }}">
                                <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Update</label>
                                <textarea name="notes" rows="6" class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">{{ old('notes') }}</textarea>
                            </div>
                        @else
                            <div>
                                <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">Status</label>
                                <select name="status" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                    @foreach(['Planned', 'In Progress', 'Done', 'Blocked', 'Deferred'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Planned') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <button type="submit" class="mt-3 w-full rounded-xl bg-brand-red px-4 py-2.5 text-xs font-bold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark">
                            Save Weekly Row
                        </button>
                </div>
            </form>
        @endif

        <div class="weekly-consolidated-scroll mt-6 rounded-2xl border border-brand-white/10">
            <table @class([
                    'weekly-consolidated-table text-left text-sm text-brand-white/75',
                    'weekly-consolidated-table--brands' => $isBrandsWeeklyDepartment,
                ])
                   data-table-key="weekly-consolidated-{{ $weeklyDepartmentFilter }}">
                <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash">
                    <tr>
                        @if($isBrandsWeeklyDepartment)
                            <th class="min-w-[140px] px-5 py-4">Task ID</th>
                            <th class="min-w-[220px] px-5 py-4">Project</th>
                            <th class="min-w-[360px] px-5 py-4">Project Brief</th>
                            <th class="min-w-[240px] px-5 py-4">Task Name</th>
                            <th class="min-w-[280px] px-5 py-4">Assigned To</th>
                            <th class="min-w-[280px] px-5 py-4">KPIS</th>
                            <th class="min-w-[160px] px-5 py-4">Start Date</th>
                            <th class="min-w-[160px] px-5 py-4">Due Date</th>
                            <th class="min-w-[320px] px-5 py-4">Update</th>
                        @else
                            @if($isAllWeeklyDepartments)
                                <th class="min-w-[180px] px-5 py-4">Department</th>
                            @endif
                            <th class="min-w-[150px] px-5 py-4">Week</th>
                            <th class="min-w-[240px] px-5 py-4">Client / Campaign</th>
                            <th class="min-w-[280px] px-5 py-4">Lead + Supporting Staff</th>
                            <th class="min-w-[420px] px-5 py-4">Deliverables</th>
                            @if($weeklyDepartmentHasBreakdown)
                                <th class="min-w-[340px] px-5 py-4">Target</th>
                                <th class="min-w-[340px] px-5 py-4">Achieved</th>
                                <th class="min-w-[340px] px-5 py-4">Gap</th>
                            @endif
                        @endif
                        @foreach($weeklyVisibleCustomColumns as $column)
                            <th class="min-w-[300px] px-5 py-4">
                                <span>{{ $column->label }}</span>
                                <span class="mt-1 block text-[9px] normal-case tracking-normal text-brand-white/35">{{ $column->user?->name }}</span>
                            </th>
                        @endforeach
                        @unless($isBrandsWeeklyDepartment)
                            <th class="min-w-[160px] px-5 py-4">Status</th>
                            @if($isAllWeeklyDepartments)
                                <th class="min-w-[150px] px-5 py-4">Priority</th>
                                <th class="min-w-[150px] px-5 py-4">Progress %</th>
                            @endif
                        @endunless
                        @if($canManageActiveWeeklyDepartment)
                            <th class="min-w-[170px] px-5 py-4">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($weeklyConsolidatedItems as $item)
                        @php
                            $itemColumns = $weeklyVisibleCustomColumns->where('user_id', $item->created_by);
                            $itemIsBrandsWeeklyDepartment = \App\Models\User::normalizeDepartmentKey((string) $item->department) === 'brands_marketing';
                            $editSupportRows = $item->supporting_staff_with_roles;
                            $itemProgress = $item->progress_percent ?? match ($item->status) {
                                'Done' => 100,
                                'In Progress' => 50,
                                default => 0,
                            };
                        @endphp
                        <tr class="align-top">
                            @if($isBrandsWeeklyDepartment)
                                <td class="rounded-l-2xl border-y border-l border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs font-mono text-brand-white whitespace-nowrap">
                                    {{ $item->brandsTaskId() ?: '' }}
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="font-semibold text-brand-white">{{ $item->campaign_name ?: '' }}</div>
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="weekly-rich-content">{!! $item->deliverables !!}</div>
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs font-semibold text-brand-white">
                                    {{ $item->client_name ?: '' }}
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="font-semibold text-brand-white">{{ $item->leadStaff?->name ?? 'Unassigned' }}</div>
                                    <div class="mt-2 space-y-1">
                                        @forelse($item->supporting_staff_with_roles as $staff)
                                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] px-2 py-1.5">
                                                <span class="font-medium text-brand-white/80">{{ $staff->name }}</span>
                                                @if($staff->weekly_role)
                                                    <span class="block text-[10px] text-amber-300">{{ $staff->weekly_role }}</span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-brand-white/30">No supporting staff</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="weekly-rich-content">{!! $item->target_breakdown ?: '<span class="text-brand-white/30"></span>' !!}</div>
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs whitespace-nowrap">
                                    {{ $item->week_start?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs whitespace-nowrap">
                                    {{ $item->week_end?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="weekly-rich-content">{!! $item->notes ?: '<span class="text-brand-white/30"></span>' !!}</div>
                                </td>
                            @else
                            @if($isAllWeeklyDepartments)
                            <td class="rounded-l-2xl border-y border-l border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                <div class="font-semibold text-brand-white">{{ \App\Models\User::departmentLabel($item->department) }}</div>
                                <div class="mt-1 text-[10px] text-brand-white/35">{{ \App\Models\User::normalizeDepartmentKey($item->department) }}</div>
                            </td>
                            @endif
                            <td class="{{ $isAllWeeklyDepartments ? '' : 'rounded-l-2xl border-l' }} border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs whitespace-nowrap">
                                <div class="font-semibold text-brand-white">{{ $item->week_start?->format('d M') }}</div>
                                <div class="text-brand-white/40">to {{ $item->week_end?->format('d M Y') ?? 'week end' }}</div>
                            </td>
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                <div class="font-semibold text-brand-white">{{ $item->client_name ?: '' }}</div>
                                <div class="mt-1 text-brand-white/50">{{ $item->campaign_name ?: '' }}</div>
                                <div class="mt-2 text-[10px] text-brand-white/30">Owner: {{ $item->creator?->name ?? 'Unknown' }}</div>
                            </td>
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                <div class="font-semibold text-brand-white">{{ $item->leadStaff?->name ?? 'Unassigned' }}</div>
                                <div class="mt-2 space-y-1">
                                    @forelse($item->supporting_staff_with_roles as $staff)
                                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] px-2 py-1.5">
                                            <span class="font-medium text-brand-white/80">{{ $staff->name }}</span>
                                            @if($staff->weekly_role)
                                                <span class="block text-[10px] text-amber-300">{{ $staff->weekly_role }}</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-brand-white/30">No supporting staff</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                <div class="weekly-rich-content">{!! $item->deliverables !!}</div>
                            </td>
                            @if($weeklyDepartmentHasBreakdown)
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs"><div class="weekly-rich-content">{!! $item->target_breakdown ?: '<span class="text-brand-white/30"></span>' !!}</div></td>
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs"><div class="weekly-rich-content">{!! $item->achieved_breakdown ?: '<span class="text-brand-white/30"></span>' !!}</div></td>
                            <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs"><div class="weekly-rich-content">{!! $item->gap_breakdown ?: '<span class="text-brand-white/30"></span>' !!}</div></td>
                            @endif
                            @endif
                            @foreach($weeklyVisibleCustomColumns as $column)
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    @if((int) $column->user_id === (int) $item->created_by && $item->customFieldValue($column))
                                        <div class="weekly-rich-content">{!! $item->customFieldValue($column) !!}</div>
                                    @else
                                        <span class="text-brand-white/25"></span>
                                    @endif
                                </td>
                            @endforeach
                            @unless($isBrandsWeeklyDepartment)
                                <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <span class="rounded-full border px-3 py-1 font-semibold {{ $weeklyStatusClasses[$item->status] ?? 'border-brand-white/10 bg-brand-white/5 text-brand-white' }}">{{ $item->status }}</span>
                                </td>
                                @if($isAllWeeklyDepartments)
                                    <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                        <span class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 font-semibold text-amber-200">{{ $item->priority ?: 'Medium' }}</span>
                                    </td>
                                    <td class="border-y border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-brand-white">{{ $itemProgress }}%</span>
                                            <span class="h-2 w-24 overflow-hidden rounded-full bg-brand-white/10">
                                                <span class="block h-full rounded-full bg-emerald-400" style="width: {{ max(0, min(100, (int) $itemProgress)) }}%"></span>
                                            </span>
                                        </div>
                                    </td>
                                @endif
                            @endunless
                            @if($canManageActiveWeeklyDepartment || ($user->isEffectiveLineManager() && ((int) $item->created_by === (int) $user->id || (int) $item->lead_staff_id === (int) $user->id || $user->isActingLineManagerFor((int) $item->created_by) || $user->isActingLineManagerFor((int) $item->lead_staff_id))))
                                <td class="rounded-r-2xl border-y border-r border-brand-white/10 bg-brand-black/35 px-5 py-5 text-xs">
                                    <div class="weekly-consolidated-actions">
                                        <details>
                                            <summary class="weekly-consolidated-action-button cursor-pointer border border-brand-white/10 bg-brand-white/10 hover:bg-brand-white/15">Edit Row</summary>
                                            <div class="weekly-consolidated-edit-panel mt-3 rounded-xl border border-brand-white/10 bg-brand-black p-4 shadow-2xl">
                                            <form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.update', $item) }}" class="space-y-4">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid gap-2 md:grid-cols-3">
                                                    <select name="department" required class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                        @foreach($departments as $key => $label)
                                                            <option value="{{ $key }}" @selected(\App\Models\User::normalizeDepartmentKey($item->department) === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if($itemIsBrandsWeeklyDepartment)
                                                        <input type="text" name="brands_task_id" value="{{ $item->brandsTaskId() }}" required maxlength="80" placeholder="Task ID" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                    @endif
                                                    <input type="date" name="week_start" value="{{ $item->week_start?->toDateString() }}" required title="{{ $itemIsBrandsWeeklyDepartment ? 'Start Date' : 'Week Start' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                    <input type="date" name="week_end" value="{{ $item->week_end?->toDateString() }}" title="{{ $itemIsBrandsWeeklyDepartment ? 'Due Date' : 'Week End' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                    <input type="text" name="client_name" value="{{ $item->client_name }}" placeholder="{{ $itemIsBrandsWeeklyDepartment ? 'Task Name' : 'Client' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                    <input type="text" name="campaign_name" value="{{ $item->campaign_name }}" placeholder="{{ $itemIsBrandsWeeklyDepartment ? 'Project' : 'Campaign' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                    <select name="lead_staff_id" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                        <option value="">Lead staff...</option>
                                                        @foreach($allStaff as $staff)
                                                            <option value="{{ $staff->id }}" @selected((int) $item->lead_staff_id === (int) $staff->id)>{{ $staff->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <p class="text-[10px] uppercase tracking-widest text-brand-ash">Supporting Staff + Role</p>
                                                        <button type="button" onclick="cloneWeeklySupportRow('weekly-support-rows-{{ $item->id }}')" class="rounded-lg border border-brand-white/10 px-3 py-1.5 text-[10px] uppercase tracking-widest text-brand-white/60 hover:bg-brand-white/10">+ Add Staff</button>
                                                    </div>
                                                    <div id="weekly-support-rows-{{ $item->id }}" class="mt-3 space-y-2">
                                                        @forelse($editSupportRows as $supportStaff)
                                                            <div class="grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row">
                                                                <select name="supporting_staff_ids[]" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                                                    <option value="">Supporting staff...</option>
                                                                    @foreach($allStaff as $staff)
                                                                        <option value="{{ $staff->id }}" @selected((int) $supportStaff->id === (int) $staff->id)>{{ $staff->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <input type="text" name="supporting_roles[]" value="{{ $supportStaff->weekly_role }}" placeholder="Role on this weekly action" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                                                                <button type="button" onclick="removeWeeklySupportRow(this)" class="rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red">Remove</button>
                                                            </div>
                                                        @empty
                                                            <div class="grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row">
                                                                <select name="supporting_staff_ids[]" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                                                                    <option value="">Supporting staff...</option>
                                                                    @foreach($allStaff as $staff)
                                                                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <input type="text" name="supporting_roles[]" placeholder="Role on this weekly action" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                                                                <button type="button" onclick="removeWeeklySupportRow(this)" class="rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red">Remove</button>
                                                            </div>
                                                        @endforelse
                                                    </div>
                                                </div>

                                                <textarea name="deliverables" rows="4" required class="wysiwyg-editor w-full rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white" placeholder="{{ $itemIsBrandsWeeklyDepartment ? 'Project Brief' : 'Deliverables / Weekly Summary' }}">{{ $item->deliverables }}</textarea>
                                                @if($itemIsBrandsWeeklyDepartment)
                                                    <div class="grid gap-2 md:grid-cols-2">
                                                        <textarea name="target_breakdown" rows="4" class="wysiwyg-editor rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white" placeholder="KPIS">{{ $item->target_breakdown }}</textarea>
                                                        <textarea name="notes" rows="4" class="wysiwyg-editor rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white" placeholder="Update">{{ $item->notes }}</textarea>
                                                    </div>
                                                @elseif($weeklyDepartmentHasBreakdown)
                                                    <div class="grid gap-2 md:grid-cols-3">
                                                        <textarea name="target_breakdown" rows="4" class="wysiwyg-editor rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->target_breakdown }}</textarea>
                                                        <textarea name="achieved_breakdown" rows="4" class="wysiwyg-editor rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->achieved_breakdown }}</textarea>
                                                        <textarea name="gap_breakdown" rows="4" class="wysiwyg-editor rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->gap_breakdown }}</textarea>
                                                    </div>
                                                @endif
                                                @if($itemColumns->isNotEmpty())
                                                    <div class="grid gap-2 md:grid-cols-2">
                                                        @foreach($itemColumns as $column)
                                                            <div>
                                                                <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $column->label }}</label>
                                                                <textarea name="custom_fields[{{ $column->column_key }}]" rows="3" class="wysiwyg-editor w-full rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->customFieldValue($column) }}</textarea>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @if($itemIsBrandsWeeklyDepartment)
                                                        <input type="hidden" name="status" value="{{ $item->status ?: 'In Progress' }}">
                                                    @else
                                                        <select name="status" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
                                                            @foreach(['Planned', 'In Progress', 'Done', 'Blocked', 'Deferred'] as $status)
                                                                <option value="{{ $status }}" @selected($item->status === $status)>{{ $status }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                    <button type="submit" class="rounded-lg bg-brand-red px-3 py-2 text-xs font-bold uppercase tracking-widest text-white">Update</button>
                                                </div>
                                            </form>
                                            </div>
                                        </details>
                                        <form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.destroy', $item) }}" onsubmit="return confirm('Remove this weekly consolidated row?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="weekly-consolidated-action-button weekly-consolidated-action-button--danger border border-brand-red/30 bg-brand-red/10 hover:border-brand-red/50 hover:bg-brand-red/20">
                                                Delete Row
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $weeklyTableColumnCount }}" class="py-10 text-center text-sm italic text-brand-white/30">
                                No weekly consolidated rows have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @php
            $weeklyPaginator = $weeklyConsolidatedItems->appends(request()->except($weeklyConsolidatedItems->getPageName()));
        @endphp
        <div class="mt-4" data-weekly-pagination>
            <x-dashboard-pagination :paginator="$weeklyPaginator" />
        </div>

        {{-- MEETING ACTION POINTS CARD --}}
        <div class="glass-panel mt-8 rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6 shadow-2xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📋</span>
                        <h3 class="text-lg font-bold text-brand-white">Meeting Action Points</h3>
                    </div>
                    <p class="mt-1 text-xs text-brand-ash">
                        Track and update action items assigned during previous meetings. Editable by all team members.
                    </p>
                </div>
                
                {{-- Add Action Point Modal Trigger --}}
                <details class="relative">
                    <summary class="cursor-pointer inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark transition-all shadow-lg">
                        + Add Action Point
                    </summary>
                    <div class="absolute right-0 top-full mt-2 z-30 w-96 rounded-2xl border border-brand-white/10 bg-brand-black/95 p-5 shadow-2xl backdrop-blur-xl">
                        <form method="POST" action="{{ route('portal.action-points.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Action Point *</label>
                                <textarea name="action_point" rows="3" required placeholder="Type action required from meeting..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-brand-red"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Assignee</label>
                                    <select name="assignee_id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red">
                                        <option value="">Select staff...</option>
                                        @foreach($allStaff as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Status *</label>
                                    <select name="status" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="done">Done</option>
                                        <option value="not_done">Not Done</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Comments / Notes</label>
                                <input type="text" name="comments" placeholder="Progress notes or comments..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red">
                            </div>

                            <button type="submit" class="w-full rounded-xl bg-brand-red py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark transition-all">
                                Save Action Point
                            </button>
                        </form>
                    </div>
                </details>
            </div>

            {{-- Action Points Table --}}
            <div class="overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full text-left text-sm text-brand-white/80">
                    <thead class="bg-brand-black/60 text-xs uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="px-4 py-3.5 min-w-[280px]">ACTION POINT</th>
                            <th class="px-4 py-3.5 min-w-[180px]">ASSIGNEE</th>
                            <th class="px-4 py-3.5 min-w-[140px]">STATUS</th>
                            <th class="px-4 py-3.5 min-w-[260px]">COMMENTS</th>
                            <th class="px-4 py-3.5 min-w-[120px] text-right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($actionPoints as $point)
                            <tr class="hover:bg-brand-white/[0.02] transition-colors">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-brand-white text-xs whitespace-pre-wrap">{{ $point->action_point }}</p>
                                    <span class="mt-1 block text-[10px] text-brand-white/30">Added by {{ $point->creator?->name ?? 'Staff' }} on {{ $point->created_at->format('M d, Y') }}</span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="font-semibold text-xs text-brand-white">{{ $point->display_assignee }}</span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $point->status_badge_class }}">
                                        {{ $point->status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="text-xs text-brand-white/70 whitespace-pre-wrap">{{ $point->comments ?: 'No comments' }}</p>
                                </td>
                                <td class="px-4 py-4 align-top text-right">
                                    <details class="relative inline-block text-left">
                                        <summary class="cursor-pointer rounded-lg border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[11px] font-semibold text-brand-white hover:bg-brand-white/10 transition-all">
                                            Edit
                                        </summary>
                                        <div class="absolute right-0 top-full mt-2 z-30 w-80 rounded-xl border border-brand-white/10 bg-brand-black/95 p-4 shadow-2xl backdrop-blur-xl text-left">
                                            <form method="POST" action="{{ route('portal.action-points.update', $point) }}" class="space-y-3">
                                                @csrf
                                                @method('PATCH')
                                                <div>
                                                    <label class="block text-[10px] uppercase text-brand-ash mb-1">Action Point</label>
                                                    <textarea name="action_point" rows="2" required class="w-full rounded-lg border border-brand-black/70 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">{{ $point->action_point }}</textarea>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-brand-ash mb-1">Assignee</label>
                                                        <select name="assignee_id" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                            <option value="">Unassigned</option>
                                                            @foreach($allStaff as $staff)
                                                                <option value="{{ $staff->id }}" @selected((int)$point->assignee_id === (int)$staff->id)>{{ $staff->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-brand-ash mb-1">Status</label>
                                                        <select name="status" required class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                            <option value="pending" @selected($point->status === 'pending')>Pending</option>
                                                            <option value="in_progress" @selected($point->status === 'in_progress')>In Progress</option>
                                                            <option value="done" @selected($point->status === 'done')>Done</option>
                                                            <option value="not_done" @selected($point->status === 'not_done')>Not Done</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase text-brand-ash mb-1">Comments</label>
                                                    <input type="text" name="comments" value="{{ $point->comments }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                </div>
                                                <div class="flex items-center justify-between pt-1">
                                                    <button type="submit" class="rounded-lg bg-brand-red px-3 py-1.5 text-[10px] font-bold uppercase text-white hover:bg-brand-red-dark">Update</button>
                                                </div>
                                            </form>
                                            <form method="POST" action="{{ route('portal.action-points.destroy', $point) }}" onsubmit="return confirm('Delete this action point?')" class="mt-2 text-right">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[10px] text-red-400 hover:underline">Delete Action Point</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-xs text-brand-white/40 italic">
                                    No meeting action points recorded yet. Click "+ Add Action Point" above to log action items from your meeting.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{--  TASK REASSIGN MODAL  --}}
    @if($user->access_role !== 'merchandiser')
    <div id="reassign-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-brand-black/80 backdrop-blur-sm p-4">
        <div class="glass-panel w-full max-w-md rounded-2xl border border-brand-white/10 bg-brand-black/90 p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold uppercase tracking-widest text-brand-white">Reassign Task</h3>
                <button onclick="closeReassignModal()" class="text-brand-white/40 hover:text-brand-white text-xl transition-colors">x</button>
            </div>
            <p id="reassign-task-title" class="text-xs text-brand-ash mb-4 italic"></p>
            <form id="reassign-form" method="POST" action="" class="space-y-4">
                @csrf
                @method('POST')
                <div>
                    <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Assign To</label>
                    <select name="assigned_to" id="reassign-staff-select" required
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-sm text-brand-white">
                        <option value="">Select staff member...</option>
                        @foreach($allStaff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} - {{ ucwords(str_replace('_',' ',$s->department ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Reason <span class="text-brand-white/30">(Optional)</span></label>
                    <input type="text" name="reason" maxlength="500" placeholder="e.g. Workload balancing, staff on leave..."
                           class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-sm text-brand-white placeholder-brand-white/30">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeReassignModal()"
                            class="flex-1 rounded-xl border border-brand-white/10 py-2.5 text-xs uppercase tracking-widest text-brand-white/60 hover:text-brand-white hover:bg-brand-white/5 transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                        Confirm Reassign
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{--  COLUMN MANAGER MODAL  --}}
    <div id="col-manager-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-brand-black/80 backdrop-blur-sm p-4">
        <div class="glass-panel w-full max-w-lg rounded-2xl border border-brand-white/10 bg-brand-black/90 p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            @php
                $userDept = strtolower(trim($user->department ?? ''));
                $deptNormMap = [
                    'admin'              => 'hr_admin',
                    'transport'          => 'hr_admin',
                    'client_service'     => 'client_relations',
                    'operations'         => 'operations_projects',
                    'brands'             => 'brands_marketing',
                    'hr_admin'           => 'hr_admin',
                    'finance'            => 'finance',
                    'client_relations'   => 'client_relations',
                    'operations_projects'=> 'operations_projects',
                    'brands_marketing'   => 'brands_marketing',
                    'creatives'          => 'creatives',
                ];
                $userNormDept = $deptNormMap[$userDept] ?? $userDept;
            @endphp
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-sm font-bold uppercase tracking-widest text-brand-white">Manage Dashboard Columns</h3>
                <button onclick="document.getElementById('col-manager-modal').classList.add('hidden')"
                        class="text-brand-white/40 hover:text-brand-white text-xl transition-colors">x</button>
            </div>
            {{-- Add Column Form --}}
            <form method="POST" action="{{ route('portal.dashboard.columns.store') }}" class="space-y-3 mb-6 border-b border-brand-white/10 pb-6">
                @csrf
                <p class="text-xs text-brand-ash uppercase tracking-widest font-semibold">Add New Column</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] text-brand-ash uppercase mb-1">Department</label>
                        <select name="department" required class="w-full rounded-lg border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white">
                            @foreach($departments as $k => $l)
                                @php
                                    $isAllowed = $user->isCvoOrSuperAdmin()
                                              || $user->access_role === 'admin'
                                              || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'])
                                              || ($userNormDept === $k);
                                @endphp
                                @if($isAllowed)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-brand-ash uppercase mb-1">Column Label</label>
                        <input type="text" name="label" required maxlength="100" placeholder="e.g. Budget Code"
                               class="w-full rounded-lg border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                    </div>
                    <div>
                        <label class="block text-[10px] text-brand-ash uppercase mb-1">Type</label>
                        <select name="type" required class="w-full rounded-lg border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="status">Status</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                    + Add Column
                </button>
            </form>
            {{-- Existing Columns List --}}
            <p class="text-xs text-brand-ash uppercase tracking-widest font-semibold mb-3">Existing Custom Columns</p>
            <div class="space-y-2">
                @php $allCustomCols = collect($departmentColumns)->flatten(); @endphp
                @forelse($allCustomCols as $col)
                    @php
                        $canRemove = $user->isCvoOrSuperAdmin()
                                  || $user->access_role === 'admin'
                                  || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'])
                                  || ($userNormDept === $col->department);
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5">
                        <div>
                            <p class="text-xs font-semibold text-brand-white">{{ $col->label }}</p>
                            <p class="text-[10px] text-brand-ash">{{ $col->department }}  {{ $col->type }}</p>
                        </div>
                        @if($canRemove)
                            <form method="POST" action="{{ route('portal.dashboard.columns.destroy', $col) }}" onsubmit="return confirm('Remove this column?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-brand-red/70 hover:text-brand-red transition-colors"> Remove</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-brand-white/30 italic text-center py-4">No custom columns added yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{--  LOCK & ISSUE AWARDS MODAL  --}}
    <div id="award-lock-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-brand-black/80 backdrop-blur-sm p-4">
        <div class="glass-panel w-full max-w-lg rounded-2xl border border-brand-white/10 bg-brand-black/90 p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold uppercase tracking-widest text-brand-white">Lock & Issue Performance Awards</h3>
                <button type="button" onclick="closeAwardLockModal()" class="text-brand-white/40 hover:text-brand-white text-xl transition-colors">x</button>
            </div>
            <form id="award-lock-form" method="POST" action="{{ route('portal.awards.lock') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Award Type</label>
                        <select name="award_type" id="lock-award-type" required onchange="toggleLockInputs(this.value)"
                                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                            <option value="employee_of_the_month">Employee of the Month</option>
                            <option value="department_of_the_month">Department of the Month</option>
                            <option value="employee_of_the_year">Employee of the Year</option>
                            <option value="department_of_the_year">Department of the Year</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Period</label>
                        <input type="text" name="period" id="lock-period" required readonly
                               class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                    </div>
                </div>

                <!-- Employee Winners Fields -->
                <div id="employee-winners-inputs" class="space-y-3">
                    <div>
                        <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Winner (1st Place)</label>
                        <select name="winner_id" id="lock-winner-id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                            <option value="">Select winner...</option>
                            @foreach($allStaff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="winner_score" id="lock-winner-score">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">1st Runner Up (2nd)</label>
                            <select name="first_runner_up_id" id="lock-first-runner-up-id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                                <option value="">Select 1st runner up...</option>
                                @foreach($allStaff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="first_runner_up_score" id="lock-first-runner-up-score">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">2nd Runner Up (3rd)</label>
                            <select name="second_runner_up_id" id="lock-second-runner-up-id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                                <option value="">Select 2nd runner up...</option>
                                @foreach($allStaff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="second_runner_up_score" id="lock-second-runner-up-score">
                        </div>
                    </div>
                </div>

                <!-- Department Winners Fields -->
                <div id="department-winners-inputs" class="space-y-3 hidden">
                    <div>
                        <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">Winner Department</label>
                        <select name="winner_val" id="lock-winner-val" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                            <option value="">Select winner department...</option>
                            @foreach($departments as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="winner_score" id="lock-winner-dept-score">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">1st Runner Up Dept</label>
                            <select name="first_runner_up_val" id="lock-first-runner-up-val" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                                <option value="">Select 1st runner up dept...</option>
                                @foreach($departments as $k => $l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="first_runner_up_score" id="lock-first-runner-up-dept-score">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-brand-ash mb-1">2nd Runner Up Dept</label>
                            <select name="second_runner_up_val" id="lock-second-runner-up-val" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2.5 text-xs text-brand-white">
                                <option value="">Select 2nd runner up dept...</option>
                                @foreach($departments as $k => $l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="second_runner_up_score" id="lock-second-runner-up-dept-score">
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-brand-white/10 bg-brand-black/40 p-4 text-[11px] text-brand-white/60 leading-relaxed">
                     <strong>IMPORTANT:</strong> Issuing these awards will lock them officially in the portal history and immediately send CSS-styled award certificate emails to the winners. If this period already has locked awards, this action will update the winners list.
                </div>

                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" onclick="closeAwardLockModal()"
                            class="flex-1 rounded-xl border border-brand-white/10 py-2.5 text-xs uppercase tracking-widest text-brand-white/60 hover:text-brand-white hover:bg-brand-white/5 transition-all">
                        Cancel
                    </button>
                    <button type="button" onclick="submitResendCertificates()"
                            class="flex-1 rounded-xl border border-sky-500/40 bg-sky-500/10 hover:bg-sky-500/20 py-2.5 text-xs uppercase tracking-widest font-semibold text-sky-300 transition-all">
                        📨 Resend Emails
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-xl bg-amber-600 hover:bg-amber-700 py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                         Lock & Issue Awards
                    </button>
                </div>
            </form>

            <form id="resend-certificates-form" method="POST" action="{{ route('portal.awards.resend-certificates') }}" class="hidden">
                @csrf
                <input type="hidden" name="award_type" id="resend-form-award-type">
                <input type="hidden" name="period" id="resend-form-period">
            </form>

            <script>
                function submitResendCertificates() {
                    const awardType = document.getElementById('lock-award-type').value;
                    const period = document.getElementById('lock-period').value;
                    if (!awardType || !period) {
                        alert('Please select an award type and period first.');
                        return;
                    }
                    document.getElementById('resend-form-award-type').value = awardType;
                    document.getElementById('resend-form-period').value = period;
                    if (confirm(`Resend certificate emails to winners for ${awardType.replace(/_/g, ' ')} (${period})?`)) {
                        document.getElementById('resend-certificates-form').submit();
                    }
                }
            </script>
        </div>
    </div>
    @endif



    <!-- Charts setup scripts -->
    <script>
        //  Reassign Modal 
        function openReassignModal(taskId, taskTitle, currentAssigneeId) {
            document.getElementById('reassign-task-title').textContent = ' ' + taskTitle;
            const form = document.getElementById('reassign-form');
            form.action = '/portal/tasks/' + taskId + '/reassign';
            const sel = document.getElementById('reassign-staff-select');
            if (sel && currentAssigneeId) {
                sel.value = currentAssigneeId;
            }
            document.getElementById('reassign-modal').classList.remove('hidden');
        }
        function closeReassignModal() {
            document.getElementById('reassign-modal').classList.add('hidden');
        }
        // Close on backdrop click
        document.getElementById('reassign-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeReassignModal();
        });

        function cloneWeeklySupportRow(containerId) {
            const container = document.getElementById(containerId);
            const firstRow = container?.querySelector('.weekly-support-row');
            if (!container || !firstRow) return;

            const clone = firstRow.cloneNode(true);
            clone.querySelectorAll('select, input').forEach((field) => {
                field.value = '';
            });
            container.appendChild(clone);
        }

        function removeWeeklySupportRow(button) {
            const row = button.closest('.weekly-support-row');
            const container = row?.parentElement;
            if (!row || !container) return;

            if (container.querySelectorAll('.weekly-support-row').length === 1) {
                row.querySelectorAll('select, input').forEach((field) => {
                    field.value = '';
                });
                return;
            }

            row.remove();
        }

        //  Setup department tab switching 
        function switchTab(deptKey) {
            window.currentMegaTableTab = deptKey;
            try {
                window.sessionStorage.setItem('cmih.dashboard.megaDepartment', deptKey);
            } catch (error) {
                // Browser storage can be disabled; tab switching should still work.
            }
            document.querySelectorAll('.dept-tab-pane').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.dept-tab-btn').forEach(el => {
                el.classList.remove('border-brand-red', 'text-brand-white', 'bg-brand-red/10');
                el.classList.add('border-brand-white/10', 'text-brand-ash');
            });

            const targetPane = document.getElementById('tab-pane-' + deptKey);
            if (targetPane) targetPane.classList.remove('hidden');

            const targetBtn = document.getElementById('tab-btn-' + deptKey);
            if (targetBtn) {
                targetBtn.classList.remove('border-brand-white/10', 'text-brand-ash');
                targetBtn.classList.add('border-brand-red', 'text-brand-white', 'bg-brand-red/10');
            }
        }

        // Initialize first active tab
        document.addEventListener('DOMContentLoaded', () => {
            const userDept = "{{ strtolower(trim($user->department ?? '')) }}";
            const deptNormMap = {
                'admin': 'hr_admin',
                'transport': 'hr_admin',
                'client_service': 'client_relations',
                'operations': 'operations_projects',
                'brands': 'brands_marketing',
                'hr_admin': 'hr_admin',
                'finance': 'finance',
                'client_relations': 'client_relations',
                'operations_projects': 'operations_projects',
                'brands_marketing': 'brands_marketing',
                'creatives': 'creatives'
            };
            const activeDept = deptNormMap[userDept] || userDept || 'operations_projects';
            const departments = @json(array_keys($departments));
            const weeklyDepartments = @json($weeklyConsolidatedDepartments->keys()->values());
            const dashboardUserId = @json((int) $user->id);
            const weeklyStorageKey = `cmih.dashboard.weeklyDepartment.${dashboardUserId}`;
            window.CMIHDashboardWeeklyStorageKey = weeklyStorageKey;
            const urlParams = new URLSearchParams(window.location.search);
            const urlTab = urlParams.get('tab');
            let storedMegaTab = null;
            let storedWeeklyDepartment = null;
            try {
                storedMegaTab = window.sessionStorage.getItem('cmih.dashboard.megaDepartment');
                storedWeeklyDepartment = window.sessionStorage.getItem(weeklyStorageKey);
            } catch (error) {
                storedMegaTab = null;
                storedWeeklyDepartment = null;
            }
            if (urlTab && departments.includes(urlTab)) {
                switchTab(urlTab);
            } else if (storedMegaTab && departments.includes(storedMegaTab)) {
                switchTab(storedMegaTab);
            } else if (departments.includes(activeDept)) {
                switchTab(activeDept);
            } else if (departments.length > 0) {
                switchTab(departments[0]);
            }

            let megaTableRegion = document.getElementById('mega-operational-table-live-region');
            let megaTableRefreshing = false;

            async function loadMegaTableUrl(refreshUrl, options = {}) {
                if (!megaTableRegion || !refreshUrl) return;

                const { pushState = false, preserveScroll = true, activeTab = null, isUserNavigation = false } = options;
                const previousScrollY = window.scrollY;
                const requestedUrl = new URL(refreshUrl, window.location.origin);
                const requestedTab = activeTab || requestedUrl.searchParams.get('tab') || window.currentMegaTableTab;
                megaTableRefreshing = true;
                megaTableRegion.classList.add('opacity-70', 'pointer-events-none');

                try {
                    const response = await fetch(refreshUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) return;

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const freshRegion = doc.getElementById('mega-operational-table-live-region');
                    if (!freshRegion) return;

                    megaTableRegion.replaceWith(freshRegion);
                    megaTableRegion = freshRegion;

                    if (pushState) {
                        window.history.pushState({ megaTable: true }, '', refreshUrl);
                    }

                    if (requestedTab && document.getElementById('tab-pane-' + requestedTab)) {
                        switchTab(requestedTab);
                    } else if (departments.length > 0) {
                        switchTab(departments[0]);
                    }

                    if (typeof window.enhanceTables === 'function') {
                        window.enhanceTables(megaTableRegion);
                    }

                    if (preserveScroll) {
                        window.scrollTo(window.scrollX, previousScrollY);
                    }
                } catch (error) {
                    console.debug(isUserNavigation ? 'Mega Table pagination load skipped:' : 'Mega Table live refresh skipped:', error);
                } finally {
                    megaTableRegion?.classList.remove('opacity-70', 'pointer-events-none');
                    megaTableRefreshing = false;
                }
            }

            window.loadMegaTableUrl = loadMegaTableUrl;
            async function refreshMegaTableSilently() {
                if (!megaTableRegion || megaTableRefreshing || document.hidden) return;
                if (megaTableRegion.contains(document.activeElement)) return;
                const refreshUrl = megaTableRegion.dataset.refreshUrl;
                if (!refreshUrl) return;

                await loadMegaTableUrl(refreshUrl, {
                    preserveScroll: true,
                    activeTab: window.currentMegaTableTab,
                });
            }

            window.refreshMegaTableSilently = refreshMegaTableSilently;
            let weeklyConsolidatedRegion = document.getElementById('weekly-consolidated-live-region');
            let weeklyConsolidatedRefreshing = false;
            const isValidWeeklyDepartment = (department) => Boolean(department && weeklyDepartments.includes(department));

            function getUrlWeeklyDepartment() {
                const department = new URLSearchParams(window.location.search).get('weekly_department');

                return isValidWeeklyDepartment(department) ? department : '';
            }

            function getStoredWeeklyDepartment() {
                try {
                    return window.sessionStorage.getItem(weeklyStorageKey) || '';
                } catch (error) {
                    return '';
                }
            }

            function storeWeeklyDepartment(department) {
                if (!isValidWeeklyDepartment(department)) return;

                try {
                    window.sessionStorage.setItem(weeklyStorageKey, department);
                } catch (error) {
                    // Browser storage can be disabled; URL state still preserves the tab.
                }
            }

            function getPreferredWeeklyDepartment(fallback = '') {
                const candidates = [
                    getUrlWeeklyDepartment(),
                    window.currentWeeklyConsolidatedDepartment,
                    getStoredWeeklyDepartment(),
                    weeklyConsolidatedRegion?.dataset.currentWeeklyDepartment,
                    fallback,
                    activeDept,
                ];

                return candidates.find(isValidWeeklyDepartment) || '';
            }

            function ensureWeeklyDepartmentInUrl(department, state = {}) {
                if (!isValidWeeklyDepartment(department) || getUrlWeeklyDepartment() === department) return;

                const url = new URL(window.location.href);
                url.searchParams.set('weekly_department', department);
                window.history.replaceState({ weeklyDepartment: true, ...state }, '', url.toString());
            }

            window.currentWeeklyConsolidatedDepartment = getPreferredWeeklyDepartment(storedWeeklyDepartment || '');
            storeWeeklyDepartment(window.currentWeeklyConsolidatedDepartment);

            function normalizeWeeklyUrl(targetUrl, requestedDepartment = null) {
                const url = new URL(targetUrl, window.location.origin);
                const department = requestedDepartment
                    || (isValidWeeklyDepartment(url.searchParams.get('weekly_department')) ? url.searchParams.get('weekly_department') : '')
                    || getPreferredWeeklyDepartment();

                if (isValidWeeklyDepartment(department)) {
                    url.searchParams.set('weekly_department', department);
                }

                return url;
            }

            function rememberWeeklyDepartment(department) {
                if (!isValidWeeklyDepartment(department)) return;

                window.currentWeeklyConsolidatedDepartment = department;
                storeWeeklyDepartment(department);
            }

            async function loadWeeklyConsolidatedUrl(refreshUrl, options = {}) {
                if (!weeklyConsolidatedRegion || !refreshUrl) return;

                const { pushState = false, preserveScroll = true, isUserNavigation = false, weeklyDepartment = null } = options;
                const previousScrollY = window.scrollY;
                const normalizedUrl = normalizeWeeklyUrl(refreshUrl, weeklyDepartment);
                const requestedDepartment = normalizedUrl.searchParams.get('weekly_department');
                if (requestedDepartment) {
                    rememberWeeklyDepartment(requestedDepartment);
                }
                weeklyConsolidatedRefreshing = true;
                weeklyConsolidatedRegion?.classList.add('opacity-70', 'pointer-events-none');

                try {
                    const response = await fetch(normalizedUrl.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) return;

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const freshRegion = doc.getElementById('weekly-consolidated-live-region');
                    if (!freshRegion) return;
                    const freshDepartment = freshRegion.dataset.currentWeeklyDepartment || '';
                    if (requestedDepartment && freshDepartment && freshDepartment !== requestedDepartment) {
                        console.debug('Weekly consolidated refresh ignored because it returned a different department:', {
                            requestedDepartment,
                            freshDepartment,
                        });
                        return;
                    }

                    weeklyConsolidatedRegion.replaceWith(freshRegion);
                    weeklyConsolidatedRegion = freshRegion;
                    rememberWeeklyDepartment(requestedDepartment || freshDepartment);

                    if (pushState) {
                        window.history.pushState({ weeklyDepartment: true }, '', normalizedUrl.toString());
                    }

                    if (typeof window.enhanceTables === 'function') {
                        window.enhanceTables(weeklyConsolidatedRegion);
                    }
                    if (typeof window.initWysiwygEditors === 'function') {
                        window.initWysiwygEditors(weeklyConsolidatedRegion);
                    }

                    if (preserveScroll) {
                        window.scrollTo(window.scrollX, previousScrollY);
                    }
                } catch (error) {
                    console.debug(isUserNavigation ? 'Weekly consolidated tab load skipped:' : 'Weekly consolidated live refresh skipped:', error);
                } finally {
                    weeklyConsolidatedRegion?.classList.remove('opacity-70', 'pointer-events-none');
                    weeklyConsolidatedRefreshing = false;
                }
            }

            window.loadWeeklyConsolidatedUrl = loadWeeklyConsolidatedUrl;
            async function refreshWeeklyConsolidatedSilently() {
                if (!weeklyConsolidatedRegion || weeklyConsolidatedRefreshing || document.hidden) return;
                if (weeklyConsolidatedRegion.contains(document.activeElement)) return;
                if (weeklyConsolidatedRegion.querySelector('#weekly-consolidated-form:not(.hidden), #weekly-column-manager:not(.hidden), details[open]')) return;

                const weeklyDepartment = getPreferredWeeklyDepartment();
                await loadWeeklyConsolidatedUrl(weeklyConsolidatedRegion.dataset.refreshUrl || window.location.href, {
                    preserveScroll: true,
                    weeklyDepartment,
                });
            }

            document.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : event.target?.parentElement;
                const link = target?.closest('[data-mega-pagination] a');
                if (!link) return;

                event.preventDefault();
                event.stopPropagation();

                if (megaTableRefreshing) return;

                const linkUrl = new URL(link.href, window.location.origin);
                loadMegaTableUrl(link.href, {
                    pushState: true,
                    preserveScroll: true,
                    activeTab: linkUrl.searchParams.get('tab') || window.currentMegaTableTab,
                    isUserNavigation: true,
                });
            }, true);

            document.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : event.target?.parentElement;
                const link = target?.closest('[data-weekly-department-tab], [data-weekly-pagination] a');
                if (!link) return;

                event.preventDefault();
                event.stopPropagation();

                if (weeklyConsolidatedRefreshing) return;

                const linkUrl = new URL(link.href, window.location.origin);
                const weeklyDepartment = linkUrl.searchParams.get('weekly_department') || window.currentWeeklyConsolidatedDepartment;
                loadWeeklyConsolidatedUrl(link.href, {
                    pushState: true,
                    preserveScroll: true,
                    isUserNavigation: true,
                    weeklyDepartment,
                });
            }, true);

            window.addEventListener('popstate', (event) => {
                if (event.state?.cmihSilent) return;

                const historyUrl = new URL(window.location.href);
                const weeklyDepartment = historyUrl.searchParams.get('weekly_department') || window.currentWeeklyConsolidatedDepartment;
                loadMegaTableUrl(window.location.href, {
                    preserveScroll: true,
                    isUserNavigation: true,
                });
                loadWeeklyConsolidatedUrl(window.location.href, {
                    preserveScroll: true,
                    isUserNavigation: true,
                    weeklyDepartment,
                });
            });

            const initialWeeklyDepartment = getPreferredWeeklyDepartment();
            if (initialWeeklyDepartment && !getUrlWeeklyDepartment()) {
                ensureWeeklyDepartmentInUrl(initialWeeklyDepartment, { initialWeeklyDepartment: true });
            }
            if (initialWeeklyDepartment
                && weeklyConsolidatedRegion?.dataset.currentWeeklyDepartment
                && weeklyConsolidatedRegion.dataset.currentWeeklyDepartment !== initialWeeklyDepartment) {
                const storedWeeklyUrl = normalizeWeeklyUrl(window.location.href, initialWeeklyDepartment);
                loadWeeklyConsolidatedUrl(storedWeeklyUrl.toString(), {
                    preserveScroll: true,
                    weeklyDepartment: initialWeeklyDepartment,
                });
            }

            window.refreshWeeklyConsolidatedSilently = refreshWeeklyConsolidatedSilently;
            setInterval(() => {
                refreshMegaTableSilently();
                refreshWeeklyConsolidatedSilently();
            }, 45000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    refreshMegaTableSilently();
                    refreshWeeklyConsolidatedSilently();
                }
            });

            // Task Performance Velocity Chart
            const ctxVelocity = document.getElementById('velocityChart').getContext('2d');
            const deptChartData = @json($departmentChartData);
            
            new Chart(ctxVelocity, {
                type: 'bar',
                data: {
                    labels: deptChartData.map(d => d.department),
                    datasets: [
                        {
                            label: 'Completed',
                            data: deptChartData.map(d => d.completed),
                            backgroundColor: 'rgba(16, 185, 129, 0.6)',
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1
                        },
                        {
                            label: 'In Progress',
                            data: deptChartData.map(d => d.in_progress),
                            backgroundColor: 'rgba(59, 130, 246, 0.6)',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1
                        },
                        {
                            label: 'Delayed/At Risk',
                            data: deptChartData.map(d => d.delayed),
                            backgroundColor: 'rgba(239, 68, 68, 0.6)',
                            borderColor: 'rgb(239, 68, 68)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#888' }
                        },
                        y: {
                            stacked: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#888' }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: '#fff' } }
                    }
                }
            });

            // Weekly Completion Trends Chart
            const ctxTrends = document.getElementById('trendsChart').getContext('2d');
            const weeklyTrends = @json($weeklyTrends);

            new Chart(ctxTrends, {
                type: 'line',
                data: {
                    labels: weeklyTrends.map(t => t.week),
                    datasets: [{
                        label: 'Tasks Completed',
                        data: weeklyTrends.map(t => t.completed),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#888' }
                        },
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#888', stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: '#fff' } }
                    }
                }
            });

            // Attendance Geolocation and Input validation
            const dailyObjective = document.getElementById('daily_objective');
            const clockInBtn = document.getElementById('clock-in-btn');
            const latInput = document.getElementById('lat-input');
            const lonInput = document.getElementById('lon-input');

            if (dailyObjective && clockInBtn) {
                // Request geolocation immediately if browser supports it
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            if (latInput) latInput.value = position.coords.latitude;
                            if (lonInput) lonInput.value = position.coords.longitude;
                        },
                        (error) => {
                            console.warn('Geolocation failed or denied:', error.message);
                        }
                    );
                }

                // A task for today already exists here, so the note is optional.
                clockInBtn.disabled = false;
                clockInBtn.innerHTML = 'Clock In';
                dailyObjective.addEventListener('input', () => {
                    const text = dailyObjective.value.trim();
                    clockInBtn.innerHTML = text.length > 0 ? 'Clock In With Note' : 'Clock In';
                });
            }

            //  Performance Leaderboard JS Implementation 
            let leaderboardChartInstance = null;
            let currentStandingsData = null;

            function renderLeaderboardChart(labels, scores) {
                const ctx = document.getElementById('leaderboardChart').getContext('2d');
                if (leaderboardChartInstance) {
                    leaderboardChartInstance.destroy();
                }
                leaderboardChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Performance Score (%)',
                            data: scores,
                            backgroundColor: 'rgba(212, 175, 55, 0.6)',
                            borderColor: '#d4af37',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: { left: 4, right: 8 }
                        },
                        scales: {
                            x: {
                                min: 0,
                                max: 100,
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: { color: '#888', font: { size: 11 } }
                            },
                            y: {
                                grid: { display: false },
                                ticks: {
                                    color: '#fff',
                                    autoSkip: false,
                                    font: { size: 11, weight: '600' },
                                    callback: function(value) {
                                        const label = this.getLabelForValue(value) || '';
                                        return label.length > 20 ? `${label.slice(0, 20)}...` : label;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            function escapeAwardHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function awardPct(value) {
                const number = Number(value ?? 0);
                if (!Number.isFinite(number)) return '0';
                return Number.isInteger(number) ? `${number}` : number.toFixed(1);
            }

            function employeeScoreBreakdown(metrics = {}, totalScore = null) {
                const score = totalScore ?? metrics.score ?? 0;

                return `
                    <div class="mt-3 space-y-1.5 rounded-lg border border-brand-white/10 bg-brand-black/35 p-2.5 text-[10px] leading-tight text-brand-white/75">
                        <div class="flex items-center justify-between gap-3">
                            <span>Punctuality Score</span>
                            <span class="font-bold text-sky-300">${awardPct(metrics.punctuality)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Task Completion Score</span>
                            <span class="font-bold text-emerald-300">${awardPct(metrics.task_rate)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Clock-in Attendance Score</span>
                            <span class="font-bold text-violet-300">${awardPct(metrics.attendance_rate)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-brand-white/10 pt-1.5 text-brand-white">
                            <span class="font-semibold">Total Score</span>
                            <span class="font-extrabold">${awardPct(score)}%</span>
                        </div>
                    </div>
                `;
            }

            function departmentMemberBreakdown(members = []) {
                if (!Array.isArray(members) || members.length === 0) {
                    return '<div class="mt-3 rounded-lg border border-brand-white/10 bg-brand-black/25 p-2 text-[10px] text-brand-white/45">No active team members in this period.</div>';
                }

                const rows = members.map(member => `
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.03] p-2">
                        <div class="flex min-w-0 items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-[10px] font-semibold text-brand-white" title="${escapeAwardHtml(member.name)}">${escapeAwardHtml(member.name)}</div>
                                <div class="text-[9px] text-brand-white/40">Score share +${awardPct(member.score_contribution)} pts</div>
                            </div>
                            <div class="shrink-0 text-right text-[10px] font-bold text-brand-white">${awardPct(member.score)}%</div>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-1 text-[9px] text-brand-white/65">
                            <span class="rounded-md bg-brand-white/[0.04] px-1.5 py-1">Task ${awardPct(member.task_rate)}%</span>
                            <span class="rounded-md bg-brand-white/[0.04] px-1.5 py-1">Punct ${awardPct(member.punctuality)}%</span>
                            <span class="rounded-md bg-brand-white/[0.04] px-1.5 py-1">Clock ${awardPct(member.attendance_rate)}%</span>
                        </div>
                        <div class="mt-1 grid grid-cols-3 gap-1 text-[9px] text-brand-white/45">
                            <span class="rounded-md bg-brand-black/25 px-1.5 py-1">Task +${awardPct(member.task_contribution)}</span>
                            <span class="rounded-md bg-brand-black/25 px-1.5 py-1">Punct +${awardPct(member.punctuality_contribution)}</span>
                            <span class="rounded-md bg-brand-black/25 px-1.5 py-1">Clock +${awardPct(member.attendance_contribution)}</span>
                        </div>
                    </div>
                `).join('');

                return `
                    <div class="mt-3">
                        <div class="mb-1.5 text-[9px] font-bold uppercase tracking-[0.2em] text-brand-white/45">Member Contributions</div>
                        <div class="max-h-72 space-y-1.5 overflow-y-auto pr-1 scrollbar-none">
                            ${rows}
                        </div>
                    </div>
                `;
            }

            function departmentScoreBreakdown(metrics = {}, includeMembers = true) {
                return `
                    <div class="mt-3 space-y-1.5 rounded-lg border border-brand-white/10 bg-brand-black/35 p-2.5 text-[10px] leading-tight text-brand-white/75">
                        <div class="flex items-center justify-between gap-3">
                            <span>Average Team Punctuality</span>
                            <span class="font-bold text-sky-300">${awardPct(metrics.punctuality)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Average Task Completion</span>
                            <span class="font-bold text-emerald-300">${awardPct(metrics.task_rate)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Average Clock-in Attendance</span>
                            <span class="font-bold text-violet-300">${awardPct(metrics.attendance_rate)}%</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-brand-white/10 pt-1.5 text-brand-white">
                            <span class="font-semibold">Average Team Score</span>
                            <span class="font-extrabold">${awardPct(metrics.score)}%</span>
                        </div>
                    </div>
                    ${includeMembers ? departmentMemberBreakdown(metrics.members || []) : ''}
                `;
            }


            function renderEmployeeWinnerCard(name, avatar, dept, score, metrics = {}) {
                return `
                    <div class="relative h-full min-w-0 overflow-hidden rounded-xl border border-amber-500/30 bg-gradient-to-b from-amber-500/10 to-transparent p-4 sm:p-5 flex flex-col gap-4">
                        <div class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full border border-amber-400/40 text-xs font-black text-amber-300">1</div>
                        <div class="w-14 h-14 rounded-full border-2 border-amber-500 overflow-hidden shrink-0">
                            <img src="${escapeAwardHtml(avatar || '/images/default-avatar.png')}" class="w-full h-full object-cover">
                        </div>
                        <div class="min-w-0 flex-1 pr-8">
                            <div class="text-[9px] uppercase tracking-widest text-amber-400 font-bold">Winner (1st Place)</div>
                            <div class="text-base font-bold text-brand-white leading-tight break-words">${escapeAwardHtml(name)}</div>
                            <div class="text-[10px] text-brand-ash break-words">${escapeAwardHtml(dept)}</div>
                            ${employeeScoreBreakdown({...metrics, score}, score)}
                        </div>
                    </div>
                `;
            }

            function renderEmployeeRunnerCard(place, name, avatar, dept, score, metrics = {}) {
                const textClass = place === 2 ? 'text-slate-400' : 'text-amber-700';
                const borderClass = place === 2 ? 'border-slate-400/30' : 'border-amber-800/30';
                const numLabel = place === 2 ? '2nd' : '3rd';

                return `
                    <div class="relative rounded-xl border ${borderClass} bg-brand-black/40 p-4">
                        <div class="absolute right-3 top-3 text-sm font-bold ${textClass}">${numLabel}</div>
                        <div class="flex min-w-0 items-center gap-3 pr-12">
                            <div class="h-10 w-10 rounded-full border border-brand-white/10 overflow-hidden shrink-0">
                                <img src="${escapeAwardHtml(avatar || '/images/default-avatar.png')}" class="w-full h-full object-cover">
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-brand-white leading-tight break-words">${escapeAwardHtml(name)}</div>
                                <div class="text-[9px] text-brand-ash break-words">${escapeAwardHtml(dept)}</div>
                            </div>
                        </div>
                        ${employeeScoreBreakdown({...metrics, score}, score)}
                    </div>
                `;
            }

            function renderDeptWinnerCard(label, score, memberCount = null, metrics = {}) {
                const details = {...metrics, score, member_count: memberCount ?? metrics.member_count};
                const memberText = memberCount ? `<div class="text-[10px] text-brand-ash mt-1">Team average - ${memberCount} staff</div>` : '';

                return `
                    <div class="relative h-full min-w-0 overflow-hidden rounded-xl border border-amber-500/30 bg-gradient-to-b from-amber-500/10 to-transparent p-4 sm:p-5 flex flex-col gap-4">
                        <div class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full border border-amber-400/40 text-xs font-black text-amber-300">1</div>
                        <div class="w-14 h-14 rounded-xl bg-amber-500/10 border border-amber-500 flex items-center justify-center text-[10px] font-black uppercase tracking-normal text-amber-500 shrink-0">
                            Dept
                        </div>
                        <div class="min-w-0 flex-1 pr-8">
                            <div class="text-[9px] uppercase tracking-widest text-amber-400 font-bold">Winner Department</div>
                            <div class="text-base font-bold text-brand-white leading-tight break-words">${escapeAwardHtml(label)}</div>
                            ${memberText}
                            ${departmentScoreBreakdown(details, Array.isArray(details.members))}
                        </div>
                    </div>
                `;
            }

            function renderDeptRunnerCard(place, label, score, memberCount = null, metrics = {}) {
                const textClass = place === 2 ? 'text-slate-400' : 'text-amber-700';
                const borderClass = place === 2 ? 'border-slate-400/30' : 'border-amber-800/30';
                const numLabel = place === 2 ? '2nd' : '3rd';
                const details = {...metrics, score, member_count: memberCount ?? metrics.member_count};
                const memberText = memberCount ? `<div class="text-[9px] text-brand-ash">Team avg - ${memberCount} staff</div>` : '';

                return `
                    <div class="relative rounded-xl border ${borderClass} bg-brand-black/40 p-4">
                        <div class="absolute right-3 top-3 text-sm font-bold ${textClass}">${numLabel}</div>
                        <div class="flex min-w-0 items-center gap-3 pr-12">
                            <div class="h-10 w-10 rounded-xl bg-brand-white/5 border border-brand-white/10 flex items-center justify-center shrink-0">
                                <span class="text-[8px] font-black uppercase tracking-normal text-brand-white/70">Dept</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-brand-white leading-tight break-words">${escapeAwardHtml(label)}</div>
                                ${memberText}
                            </div>
                        </div>
                        ${departmentScoreBreakdown(details, Array.isArray(details.members))}
                    </div>
                `;
            }

            window.fetchLeaderboard = function(period) {
                const notice = document.getElementById('award-period-notice');
                const employeeCards = document.getElementById('employee-award-cards');
                const departmentCards = document.getElementById('department-award-cards');

                employeeCards.innerHTML = '<div class="lg:col-span-3 text-center py-6 text-brand-white/30 italic">Loading standings...</div>';
                departmentCards.innerHTML = '<div class="lg:col-span-3 text-center py-6 text-brand-white/30 italic">Loading standings...</div>';

                fetch(`/portal/awards/standings?period=${encodeURIComponent(period)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(res => {
                        const contentType = res.headers.get('content-type') || '';
                        if (!res.ok || !contentType.includes('application/json')) {
                            throw new Error('Leaderboard data is temporarily unavailable.');
                        }

                        return res.json();
                    })
                    .then(data => {
                        currentStandingsData = data;
                        
                        const isYear = period.length === 4;
                        const empType = isYear ? 'employee_of_the_year' : 'employee_of_the_month';
                        const deptType = isYear ? 'department_of_the_year' : 'department_of_the_month';
                        const topEmps = data.calculated.employees || [];
                        const topDepts = data.calculated.departments || [];
                        const employeeByName = Object.fromEntries(topEmps.map(emp => [emp.name, emp]));
                        const departmentByKey = Object.fromEntries(topDepts.map(dept => [dept.key, dept]));

                        const lockedEmp = data.locked[empType];
                        const lockedDept = data.locked[deptType];

                        // Render Employee cards
                        let empHtml = '';
                        if (lockedEmp) {
                            const winnerMetrics = employeeByName[lockedEmp.winner] || { score: lockedEmp.winner_score };
                            const firstRunnerMetrics = employeeByName[lockedEmp.first_runner_up] || { score: lockedEmp.first_runner_up_score };
                            const secondRunnerMetrics = employeeByName[lockedEmp.second_runner_up] || { score: lockedEmp.second_runner_up_score };

                            empHtml += renderEmployeeWinnerCard(
                                lockedEmp.winner || 'N/A',
                                winnerMetrics.avatar || lockedEmp.winner_avatar || '/images/default-avatar.png',
                                winnerMetrics.department || PerformanceAwardDeptLabel(lockedEmp.winner_val),
                                lockedEmp.winner_score,
                                winnerMetrics
                            );
                            empHtml += renderEmployeeRunnerCard(
                                2,
                                lockedEmp.first_runner_up || 'N/A',
                                firstRunnerMetrics.avatar || '/images/default-avatar.png',
                                firstRunnerMetrics.department || '',
                                lockedEmp.first_runner_up_score,
                                firstRunnerMetrics
                            );
                            empHtml += renderEmployeeRunnerCard(
                                3,
                                lockedEmp.second_runner_up || 'N/A',
                                secondRunnerMetrics.avatar || '/images/default-avatar.png',
                                secondRunnerMetrics.department || '',
                                lockedEmp.second_runner_up_score,
                                secondRunnerMetrics
                            );
                        } else {
                            if (topEmps.length > 0) {
                                empHtml += renderEmployeeWinnerCard(topEmps[0].name, topEmps[0].avatar, topEmps[0].department, topEmps[0].score, topEmps[0]);
                                if (topEmps.length > 1) {
                                    empHtml += renderEmployeeRunnerCard(2, topEmps[1].name, topEmps[1].avatar, topEmps[1].department, topEmps[1].score, topEmps[1]);
                                }
                                if (topEmps.length > 2) {
                                    empHtml += renderEmployeeRunnerCard(3, topEmps[2].name, topEmps[2].avatar, topEmps[2].department, topEmps[2].score, topEmps[2]);
                                }
                            } else {
                                empHtml = '<div class="lg:col-span-3 text-center py-6 text-brand-white/30 italic">No active employee standings in this period.</div>';
                            }
                        }
                        employeeCards.innerHTML = empHtml;

                        // Render Department cards
                        let deptHtml = '';
                        if (lockedDept) {
                            const winnerDeptMetrics = departmentByKey[lockedDept.winner_val] || { score: lockedDept.winner_score };
                            const firstDeptMetrics = departmentByKey[lockedDept.first_runner_up_val] || { score: lockedDept.first_runner_up_score };
                            const secondDeptMetrics = departmentByKey[lockedDept.second_runner_up_val] || { score: lockedDept.second_runner_up_score };

                            deptHtml += renderDeptWinnerCard(lockedDept.winner_val_label, lockedDept.winner_score, winnerDeptMetrics.member_count, winnerDeptMetrics);
                            deptHtml += renderDeptRunnerCard(2, lockedDept.first_runner_up_val_label, lockedDept.first_runner_up_score, firstDeptMetrics.member_count, firstDeptMetrics);
                            deptHtml += renderDeptRunnerCard(3, lockedDept.second_runner_up_val_label, lockedDept.second_runner_up_score, secondDeptMetrics.member_count, secondDeptMetrics);
                        } else {
                            if (topDepts.length > 0) {
                                deptHtml += renderDeptWinnerCard(topDepts[0].label, topDepts[0].score, topDepts[0].member_count, topDepts[0]);
                                if (topDepts.length > 1) {
                                    deptHtml += renderDeptRunnerCard(2, topDepts[1].label, topDepts[1].score, topDepts[1].member_count, topDepts[1]);
                                }
                                if (topDepts.length > 2) {
                                    deptHtml += renderDeptRunnerCard(3, topDepts[2].label, topDepts[2].score, topDepts[2].member_count, topDepts[2]);
                                }
                            } else {
                                deptHtml = '<div class="lg:col-span-3 text-center py-6 text-brand-white/30 italic">No department standings in this period.</div>';
                            }
                        }
                        departmentCards.innerHTML = deptHtml;

                        // Render Notice
                        notice.classList.remove('hidden');
                        if (lockedEmp || lockedDept) {
                            notice.className = "mb-6 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-xs text-emerald-400";
                            notice.innerHTML = `Official awards locked for period: <strong>${escapeAwardHtml(period)}</strong>`;
                        } else {
                            notice.className = "mb-6 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-400";
                            notice.innerHTML = 'Official awards have not been locked for this period yet. Showing real-time estimated standings.';
                        }

                        // Render Chart
                        const chartLabels = [];
                        const chartScores = [];
                        topEmps.slice(0, 5).forEach(e => {
                            chartLabels.push(e.name);
                            chartScores.push(e.score);
                        });
                        renderLeaderboardChart(chartLabels, chartScores);
                    })
                    .catch(() => {
                        employeeCards.innerHTML = '<div class="lg:col-span-3 text-center py-6 text-brand-white/40">Employee standings are temporarily unavailable.</div>';
                        departmentCards.innerHTML = '<div class="lg:col-span-3 text-center py-6 text-brand-white/40">Department standings are temporarily unavailable.</div>';
                        notice.className = 'mb-6 rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 text-xs text-brand-white/60';
                        notice.textContent = 'Performance standings could not be refreshed. Please try again shortly.';
                        notice.classList.remove('hidden');
                    });
            }

            function PerformanceAwardDeptLabel(val) {
                const departments = {
                    'hr_admin': 'HR & Admin',
                    'finance': 'Finance',
                    'client_relations': 'Client Relations',
                    'operations_projects': 'Operations / Projects',
                    'brands_marketing': 'Brands & Marketing',
                    'creatives': 'Creatives'
                };
                return departments[val] || val || '';
            }

            window.openAwardLockModal = function() {
                const period = document.getElementById('leaderboard-period').value;
                document.getElementById('lock-period').value = period;
                
                const isYear = period.length === 4;
                const select = document.getElementById('lock-award-type');
                select.value = isYear ? 'employee_of_the_year' : 'employee_of_the_month';
                
                toggleLockInputs(select.value);
                
                document.getElementById('award-lock-modal').classList.remove('hidden');
            }

            window.closeAwardLockModal = function() {
                document.getElementById('award-lock-modal').classList.add('hidden');
            }

            window.toggleLockInputs = function(awardType) {
                const isDept = awardType.includes('department');
                const empInputs = document.getElementById('employee-winners-inputs');
                const deptInputs = document.getElementById('department-winners-inputs');
                
                if (isDept) {
                    empInputs.classList.add('hidden');
                    deptInputs.classList.remove('hidden');
                    
                    if (currentStandingsData && currentStandingsData.calculated.departments) {
                        const depts = currentStandingsData.calculated.departments;
                        if (depts.length > 0) {
                            document.getElementById('lock-winner-val').value = depts[0].key;
                            document.getElementById('lock-winner-dept-score').value = depts[0].score;
                        }
                        if (depts.length > 1) {
                            document.getElementById('lock-first-runner-up-val').value = depts[1].key;
                            document.getElementById('lock-first-runner-up-dept-score').value = depts[1].score;
                        }
                        if (depts.length > 2) {
                            document.getElementById('lock-second-runner-up-val').value = depts[2].key;
                            document.getElementById('lock-second-runner-up-dept-score').value = depts[2].score;
                        }
                    }
                } else {
                    empInputs.classList.remove('hidden');
                    deptInputs.classList.add('hidden');
                    
                    if (currentStandingsData && currentStandingsData.calculated.employees) {
                        const emps = currentStandingsData.calculated.employees;
                        if (emps.length > 0) {
                            document.getElementById('lock-winner-id').value = emps[0].user_id;
                            document.getElementById('lock-winner-score').value = emps[0].score;
                        }
                        if (emps.length > 1) {
                            document.getElementById('lock-first-runner-up-id').value = emps[1].user_id;
                            document.getElementById('lock-first-runner-up-score').value = emps[1].score;
                        }
                        if (emps.length > 2) {
                            document.getElementById('lock-second-runner-up-id').value = emps[2].user_id;
                            document.getElementById('lock-second-runner-up-score').value = emps[2].score;
                        }
                    }
                }
            }

            // Backdrop click close modal
            document.getElementById('award-lock-modal')?.addEventListener('click', function(e) {
                if (e.target === this) closeAwardLockModal();
            });

            // Initial fetch
            const initialPeriod = document.getElementById('leaderboard-period').value;
            fetchLeaderboard(initialPeriod);
        });
    </script>
</x-app-layout>
