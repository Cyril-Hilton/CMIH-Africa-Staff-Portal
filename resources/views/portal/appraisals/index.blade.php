<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Performance</p>
                <h2 class="text-3xl font-display text-brand-white">Appraisal Centre</h2>
            </div>
            <div class="text-xs text-brand-ash uppercase tracking-widest rounded-xl border border-brand-white/10 px-4 py-2">
                {{ now()->format('l, d M Y') }}
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/40 bg-brand-red/10 p-4 text-sm text-brand-white/80">
            <ul class="list-disc pl-5 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ── Personal Performance Stats ─────────────────────────────────────── --}}
    <div class="mb-8 grid gap-5 md:grid-cols-3 xl:grid-cols-5">
        @foreach([
            ['label' => 'Completion Rate', 'value' => $stats['completion_rate'] . '%', 'color' => 'text-emerald-400'],
            ['label' => 'Punctuality',     'value' => $stats['punctuality'] . '%',     'color' => 'text-sky-400'],
            ['label' => 'Overtime Hrs',    'value' => $stats['overtime_hours'],         'color' => 'text-amber-400'],
            ['label' => 'Total Tasks',     'value' => $stats['total_tasks'],            'color' => 'text-brand-white'],
            ['label' => 'Completed Tasks', 'value' => $stats['completed_tasks'],        'color' => 'text-green-400'],
        ] as $kpi)
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">{{ $kpi['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold {{ $kpi['color'] }}">{{ $kpi['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div x-data="{ activeTab: 'appraisals' }">
        {{-- Tabs Navigation --}}
        <div class="mb-8 flex flex-wrap gap-2 border-b border-brand-white/10 pb-4">
            <button @click="activeTab = 'appraisals'" :class="activeTab === 'appraisals' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-brand-white/5 text-brand-ash hover:text-white border-transparent'" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider rounded-xl border transition-all">
                📋 Appraisal Cycles
            </button>
            <button @click="activeTab = 'ledger'" :class="activeTab === 'ledger' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-brand-white/5 text-brand-ash hover:text-white border-transparent'" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider rounded-xl border transition-all">
                📊 Profile Tracking Ledger
            </button>
            @if(count($transparencyMatrix) > 0)
                <button @click="activeTab = 'matrix'" :class="activeTab === 'matrix' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-brand-white/5 text-brand-ash hover:text-white border-transparent'" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider rounded-xl border transition-all">
                    🔭 Manager Transparency Matrix
                </button>
            @endif
        </div>

        {{-- Tab 1: Appraisal History --}}
        <div x-show="activeTab === 'appraisals'" class="grid gap-8 lg:grid-cols-2">
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📋 My Appraisal History</h3>
                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($appraisals as $ap)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $ap->quarter }} {{ $ap->year }}</p>
                                    <p class="text-xs text-brand-ash mt-0.5">{{ $ap->status_label }}</p>
                                </div>
                                <div class="text-right text-xs space-y-0.5">
                                    @if($ap->avg_self_score > 0)
                                        <p class="text-emerald-400">Self: <span class="font-bold">{{ $ap->avg_self_score }}/10</span></p>
                                    @endif
                                    @if($ap->avg_manager_score > 0)
                                        <p class="text-sky-400">Manager: <span class="font-bold">{{ $ap->avg_manager_score }}/10</span></p>
                                    @endif
                                    @if($ap->final_score > 0)
                                        <p class="text-amber-400 font-bold">Final: {{ $ap->final_score }}/10</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-2">
                                @if(in_array($ap->status, ['draft', 'self_assessment']) && $ap->user_id === $user->id)
                                    <a href="{{ route('portal.appraisals.self.form', $ap) }}"
                                       class="rounded-lg bg-brand-red px-3 py-1 text-[10px] uppercase tracking-wider font-semibold text-white hover:bg-brand-red-dark transition-all">
                                        ✏️ Submit Self-Assessment
                                    </a>
                                @endif
                                @if($ap->status === 'submitted' && in_array($user->access_role, ['admin', 'manager', 'super_admin']))
                                    <a href="{{ route('portal.appraisals.manager.form', $ap) }}"
                                       class="rounded-lg bg-sky-600 px-3 py-1 text-[10px] uppercase tracking-wider font-semibold text-white hover:bg-sky-700 transition-all">
                                        🔍 Manager Review
                                    </a>
                                @endif
                                @if($ap->status === 'manager_reviewed')
                                    @php $isHR = in_array(strtolower($user->department ?? ''), ['admin', 'hr_admin']) || $user->access_role === 'super_admin'; @endphp
                                    @if($isHR)
                                        <a href="{{ route('portal.appraisals.audit.form', $ap) }}"
                                           class="rounded-lg bg-purple-600 px-3 py-1 text-[10px] uppercase tracking-wider font-semibold text-white hover:bg-purple-700 transition-all">
                                            🏛️ HR Audit
                                        </a>
                                    @endif
                                @endif
                                @if($user->access_role === 'super_admin' || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah']))
                                    <form method="POST" action="{{ route('portal.appraisals.unlock', $ap) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-amber-600/20 border border-amber-500/30 px-3 py-1 text-[10px] uppercase tracking-wider font-semibold text-amber-400 hover:bg-amber-600/30 transition-all">
                                            🔓 Unlock
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-8">No appraisal cycles have been opened for you yet.</p>
                    @endforelse
                </div>
                @if($appraisals instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $appraisals->links() }}
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                @php
                    $isHR = in_array(strtolower($user->department ?? ''), ['admin', 'hr_admin'])
                        || $user->access_role === 'super_admin'
                        || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah']);
                @endphp
                @if($isHR)
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🗂️ Open New Appraisal Cycle</h3>
                    <form method="POST" action="{{ route('portal.appraisals.create') }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="ap_user" :value="__('Staff Member')" />
                                <select id="ap_user" name="user_id" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                    <option value="">Select Staff</option>
                                    @foreach(\App\Models\User::internalStaff()->where('status','active')->orderBy('name')->get() as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="ap_quarter" :value="__('Quarter')" />
                                <select id="ap_quarter" name="quarter" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                    <option value="Q1">Q1 (Jan–Mar)</option>
                                    <option value="Q2">Q2 (Apr–Jun)</option>
                                    <option value="Q3">Q3 (Jul–Sep)</option>
                                    <option value="Q4">Q4 (Oct–Dec)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="ap_year" :value="__('Year')" />
                                <x-text-input id="ap_year" name="year" type="number" required value="{{ now()->year }}"
                                    class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white" />
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-white transition-all">
                            Open Appraisal Cycle
                        </button>
                    </form>
                </div>
                @endif

                @if($staffAppraisals->isNotEmpty())
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🔭 Staff Performance Matrix</h3>
                    <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                        @foreach($staffAppraisals as $ap)
                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex items-center justify-between text-xs">
                                <div>
                                    <p class="text-brand-white font-semibold">{{ $ap->employee->name ?? 'N/A' }}</p>
                                    <p class="text-brand-ash text-[10px]">{{ $ap->quarter }} {{ $ap->year }} · {{ $ap->status_label }}</p>
                                </div>
                                <div class="text-right">
                                    @if($ap->final_score > 0)
                                        <span class="text-amber-400 font-bold text-sm">{{ $ap->final_score }}/10</span>
                                    @else
                                        <span class="text-brand-white/30">Pending</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($staffAppraisals instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4 pt-4 border-t border-brand-white/10">
                            {{ $staffAppraisals->links() }}
                        </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Tab 2: Profile Tracking Ledger --}}
        <div x-show="activeTab === 'ledger'" class="grid gap-8 lg:grid-cols-2" style="display: none;">
            {{-- My Recent Tasks --}}
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📋 My Recent Tasks Ledger</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 text-brand-ash pb-2">
                                <th class="py-2">Task Title</th>
                                <th class="py-2">Due Date</th>
                                <th class="py-2">Priority</th>
                                <th class="py-2 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($myRecentTasks as $task)
                                <tr>
                                    <td class="py-3 pr-2 font-medium text-brand-white min-w-[200px]">{{ $task->title }}</td>
                                    <td class="py-3 text-brand-ash">{{ $task->due_on ? $task->due_on->format('d M Y') : 'N/A' }}</td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($task->priority === 'high') bg-brand-red/10 text-brand-red border border-brand-red/20
                                            @elseif($task->priority === 'medium') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                            @else bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 @endif">
                                            {{ ucfirst($task->priority ?? 'normal') }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($task->isApprovedForPerformance()) bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                            @else bg-amber-500/10 text-amber-400 border border-amber-500/20 @endif">
                                            {{ $task->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-brand-white/40 italic">No assigned tasks found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- My Recent Clock-ins --}}
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">⏰ My Attendance & Overtime Ledger</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 text-brand-ash pb-2">
                                <th class="py-2">Date</th>
                                <th class="py-2">Clock In / Out</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Overtime</th>
                                <th class="py-2 text-right">Objective</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($myRecentAttendances as $att)
                                <tr>
                                    <td class="py-3 font-medium text-brand-white">{{ $att->clock_in_at ? $att->clock_in_at->format('d M') : 'N/A' }}</td>
                                    <td class="py-3 text-brand-ash">
                                        {{ $att->clock_in_at ? $att->clock_in_at->format('h:i A') : 'N/A' }} /
                                        {{ $att->clock_out_at ? $att->clock_out_at->format('h:i A') : 'Pending' }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($att->status === 'Late') bg-brand-red/10 text-brand-red border border-brand-red/20
                                            @else bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 @endif">
                                            {{ $att->status }}
                                        </span>
                                    </td>
                                    <td class="py-3 font-semibold text-amber-400">
                                        {{ $att->overtime_minutes > 0 ? round($att->overtime_minutes / 60, 1) . 'h' : '-' }}
                                    </td>
                                    <td class="py-3 text-right text-brand-ash min-w-[150px]">
                                        {{ $att->daily_objective }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-brand-white/40 italic">No attendance records clocked.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tab 3: Manager Transparency Matrix --}}
        @if(count($transparencyMatrix) > 0)
        <div x-show="activeTab === 'matrix'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5" style="display: none;">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🔭 Departmental Transparency & KPI Matrix</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-brand-white/10 text-brand-ash pb-2">
                            <th class="py-2">Staff Member</th>
                            <th class="py-2">Total Tasks</th>
                            <th class="py-2">Task Completion Rate</th>
                            <th class="py-2">Punctuality Score</th>
                            <th class="py-2">Latenesses</th>
                            <th class="py-2">Avg Check-in Delay</th>
                            <th class="py-2">Total Overtime</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @foreach($transparencyMatrix as $item)
                            <tr>
                                <td class="py-3.5">
                                    <p class="font-bold text-brand-white">{{ $item['user']->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ $item['user']->email }}</p>
                                </td>
                                <td class="py-3.5 text-brand-white font-medium">{{ $item['total_tasks'] }}</td>
                                <td class="py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-emerald-400">{{ $item['completion_rate'] }}%</span>
                                        <div class="w-16 bg-brand-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-emerald-500 h-1.5" style="width: {{ $item['completion_rate'] }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sky-400">{{ $item['punctuality'] }}%</span>
                                        <div class="w-16 bg-brand-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-sky-400 h-1.5" style="width: {{ $item['punctuality'] }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 text-brand-white font-medium">
                                    <span class="{{ $item['latenesses'] > 3 ? 'text-brand-red font-bold' : '' }}">
                                        {{ $item['latenesses'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 font-medium text-brand-white">
                                    @if($item['avg_delay_minutes'] > 0)
                                        <span class="text-amber-400">{{ $item['avg_delay_minutes'] }} mins</span>
                                    @else
                                        <span class="text-brand-white/40">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 font-semibold text-amber-400">{{ $item['overtime_hours'] }} hrs</td>
                                <td class="py-3.5 text-right">
                                    <a href="{{ route('portal.appraisals.report', $item['user']) }}" target="_blank"
                                       class="inline-block rounded-xl bg-amber-500/20 border border-amber-500/30 px-3 py-1.5 text-[10px] uppercase tracking-wider font-semibold text-amber-400 hover:bg-amber-500/30 transition-all">
                                        📄 Get Accountability Report
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
