<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Action Required</p>
                <h2 class="text-3xl font-display text-brand-white">Pending Tasks</h2>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-xs uppercase tracking-[0.2em] font-semibold text-amber-400">
                    {{ $totalPending }} Unresolved
                </span>
            </div>
        </div>
    </x-slot>

    @php
        $departmentColors = [
            'operations'     => 'bg-sky-500',
            'creatives'      => 'bg-fuchsia-500',
            'client_service' => 'bg-amber-500',
            'finance'        => 'bg-emerald-500',
            'brands'         => 'bg-indigo-500',
            'admin'          => 'bg-brand-red',
            'transport'      => 'bg-cyan-500',
        ];
        $priorityStyles = [
            'High'   => ['bg' => 'border-red-500/30 bg-red-500/10 text-red-400', 'label' => '🔴 High'],
            'Medium' => ['bg' => 'border-amber-500/30 bg-amber-500/10 text-amber-400', 'label' => '🟡 Medium'],
            'Low'    => ['bg' => 'border-green-500/30 bg-green-500/10 text-green-400', 'label' => '🟢 Low'],
        ];
        $toggleDir = $direction === 'asc' ? 'desc' : 'asc';

        $statusBadge = [
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

        $sortUrl = fn($col) => request()->fullUrlWithQuery(['view' => 'pending', 'sort' => $col, 'direction' => $sort === $col ? $toggleDir : 'asc']);
        $pendingFilterOptions = [
            'all' => 'All pending',
            'overdue' => 'Overdue',
            'high_priority' => 'High priority',
            'awaiting_approval' => 'Awaiting approval',
            'sent_back' => 'Sent back',
        ];
        $filterUrl = fn($value) => request()->fullUrlWithQuery(['view' => 'pending', 'filter' => $value, 'p_page' => null]);
    @endphp

    {{-- Urgency Banner --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="glass-panel rounded-2xl p-5 border border-red-500/30 bg-red-500/5 flex items-center gap-4">
            <div class="text-3xl">🔥</div>
            <div>
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Overdue</p>
                <p class="text-3xl font-bold text-red-400">{{ $overdueCount }}</p>
            </div>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-amber-500/30 bg-amber-500/5 flex items-center gap-4">
            <div class="text-3xl">⚡</div>
            <div>
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">High Priority</p>
                <p class="text-3xl font-bold text-amber-400">{{ $highPrioCount }}</p>
            </div>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 flex items-center gap-4">
            <div class="text-3xl">📋</div>
            <div>
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Total Pending</p>
                <p class="text-3xl font-bold text-brand-white">{{ $totalPending }}</p>
            </div>
        </div>
    </div>

    {{-- Awaiting My Approval --}}
    <div class="glass-panel rounded-2xl p-6 border border-purple-500/20 bg-purple-500/[0.04] mb-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-brand-ash">Line Manager Queue</p>
                <h3 class="text-2xl font-display text-brand-white">Awaiting My Approval</h3>
            </div>
            <span class="rounded-xl border border-purple-400/30 bg-purple-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-purple-200">
                {{ $approvalQueueTotal ?? 0 }} Waiting
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-[0.25em] text-brand-ash">
                    <tr>
                        <th class="pb-3 font-semibold">Task</th>
                        <th class="pb-3 font-semibold">Assignee</th>
                        <th class="pb-3 font-semibold">Requested By</th>
                        <th class="pb-3 font-semibold">Requested</th>
                        <th class="pb-3 font-semibold">Due Date</th>
                        <th class="pb-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-white/5">
                    @forelse(($approvalQueue ?? collect()) as $task)
                        <tr class="hover:bg-brand-white/[0.04] transition-colors">
                            <td class="py-4 pr-4">
                                <p class="font-semibold text-brand-white">{{ $task->title }}</p>
                                @if($task->completion_review_note)
                                    <p class="mt-1 text-xs text-brand-white/45">{{ $task->completion_review_note }}</p>
                                @endif
                            </td>
                            <td class="py-4 pr-4 text-brand-white/75">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="py-4 pr-4 text-brand-white/60">{{ $task->assigner?->name ?? 'System' }}</td>
                            <td class="py-4 pr-4 text-xs text-brand-white/50">{{ $task->completion_review_requested_at?->format('d M Y H:i') ?? $task->updated_at?->format('d M Y H:i') }}</td>
                            <td class="py-4 pr-4 text-xs {{ $task->due_on && $task->due_on->isPast() ? 'font-bold text-red-400' : 'text-brand-white/50' }}">{{ $task->due_on?->format('d M Y') ?? '-' }}</td>
                            <td class="py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('portal.tasks.edit', $task) }}" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-brand-white/70 hover:text-brand-white">Review</a>
                                    <form method="POST" action="{{ route('portal.tasks.completion-review', $task) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-emerald-500">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('portal.tasks.completion-review', $task) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="revert">
                                        <input type="hidden" name="review_comment" value="Please correct and resubmit this task for approval.">
                                        <button type="submit" class="rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-200 hover:bg-amber-500/20">Send Back</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-brand-white/45">No tasks are currently waiting for your approval.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Filter / Sort Bar --}}
    <div class="glass-panel rounded-2xl p-4 border border-brand-white/10 bg-brand-white/5 mb-6">
        <div class="mb-3 flex flex-wrap items-center gap-3 text-[10px] uppercase tracking-[0.2em]">
            <span class="text-brand-ash">Filter:</span>
            @foreach($pendingFilterOptions as $value => $label)
                <a href="{{ $filterUrl($value) }}" class="rounded-lg border px-3 py-1.5 transition {{ $pendingFilter === $value ? 'border-brand-red/40 bg-brand-red/10 text-brand-red font-semibold' : 'border-brand-white/10 text-brand-white/50 hover:text-brand-white hover:border-brand-white/20' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-3 text-[10px] uppercase tracking-[0.2em]">
            <span class="text-brand-ash">Sort:</span>
            @foreach(['task' => 'Title', 'staff' => 'Staff', 'department' => 'Dept', 'priority' => 'Priority', 'timeline' => 'Due Date', 'status' => 'Status'] as $col => $colLabel)
                <a href="{{ $sortUrl($col) }}" class="rounded-lg border px-3 py-1.5 transition {{ $sort === $col ? 'border-brand-red/40 bg-brand-red/10 text-brand-red font-semibold' : 'border-brand-white/10 text-brand-white/50 hover:text-brand-white hover:border-brand-white/20' }}">
                    {{ $colLabel }}
                    @if($sort === $col)
                        {{ $direction === 'desc' ? '↓' : '↑' }}
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- Tasks Table --}}
    <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="text-[10px] uppercase tracking-[0.25em] text-brand-ash border-b border-brand-white/10">
                    <tr>
                        <th class="pb-3 font-semibold w-8">#</th>
                        <th class="pb-3 font-semibold">Task</th>
                        <th class="pb-3 font-semibold">Assignee</th>
                        <th class="pb-3 font-semibold">Department</th>
                        <th class="pb-3 font-semibold">Priority</th>
                        <th class="pb-3 font-semibold">Due Date</th>
                        <th class="pb-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-white/5">
                    @forelse ($pendingTasks as $i => $task)
                        @php
                            $isOverdue = $task->due_on && $task->due_on->isPast();
                            $pStyle = $priorityStyles[$task->priority] ?? ['bg' => 'border-brand-white/10 text-brand-white/60', 'label' => ucfirst($task->priority ?? 'medium')];
                            $deptColor = $departmentColors[$task->department] ?? 'bg-gray-500';
                        @endphp
                        <tr class="{{ $isOverdue ? 'bg-red-500/5' : '' }} hover:bg-brand-white/[0.04] transition-colors">
                            <td class="py-4 font-mono text-xs text-brand-white/30">{{ $pendingTasks->firstItem() + $i }}</td>
                            <td class="py-4">
                                <p class="text-sm font-semibold text-brand-white">{{ $task->title }}</p>
                                @if($task->details)
                                    <div class="text-xs text-brand-white/40 mt-0.5">{!! $task->details !!}</div>
                                @endif
                                @if($isOverdue)
                                    <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider">⚠ Overdue</span>
                                @endif
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full {{ $deptColor }} flex items-center justify-center text-[10px] font-bold text-white shrink-0">
                                        {{ substr($task->assignee?->name ?? 'U', 0, 1) }}
                                    </div>
                                    <span class="text-sm text-brand-white/80">{{ $task->assignee?->name ?? 'Unassigned' }}</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full {{ $deptColor }}"></span>
                                    <span class="text-xs text-brand-white/60 uppercase tracking-wider">{{ str_replace('_', ' ', $task->department ?? '—') }}</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="rounded-full border px-2.5 py-1 text-[10px] uppercase tracking-wider font-semibold {{ $pStyle['bg'] }}">
                                    {{ $pStyle['label'] }}
                                </span>
                            </td>
                            <td class="py-4 text-xs {{ $isOverdue ? 'text-red-400 font-bold' : 'text-brand-white/50' }}">
                                {{ $task->due_on?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-4">
                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider {{ $statusBadge[$task->status] ?? 'border-brand-white/10 text-brand-white/60' }}">
                                    {{ $task->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="text-4xl mb-2">🎉</div>
                                <p class="text-brand-white/50 text-sm">No pending tasks! Everything is up to date.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pendingTasks instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-5 border-t border-brand-white/10 mt-4">{{ $pendingTasks->links() }}</div>
        @endif
    </div>
</x-app-layout>
