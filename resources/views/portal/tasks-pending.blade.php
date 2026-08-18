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

    {{-- Session flash --}}
    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400 flex items-center gap-3">
            <span class="text-lg">✅</span> {{ session('status') }}
        </div>
    @endif

    {{-- LINE MANAGER QUEUE (AWAITING MY APPROVAL) --}}
    <div class="glass-panel rounded-2xl p-6 border border-purple-500/30 bg-purple-500/5 mb-8 shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-brand-white/10 pb-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.3em] text-purple-300 font-bold">Line Manager Queue</p>
                <h3 class="text-xl font-display text-brand-white uppercase">Awaiting My Approval</h3>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-xl border border-purple-400/40 bg-purple-500/20 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-purple-300">
                    {{ isset($myPendingApprovals) ? ($myPendingApprovals instanceof \Illuminate\Pagination\LengthAwarePaginator ? $myPendingApprovals->total() : $myPendingApprovals->count()) : 0 }} Waiting
                </span>
            </div>
        </div>

        @if(isset($myPendingApprovals) && $myPendingApprovals->isNotEmpty())
            <div class="space-y-3">
                @foreach($myPendingApprovals as $approvalTask)
                    <div id="approval-task-row-{{ $approvalTask->id }}" class="rounded-xl border border-purple-500/20 bg-brand-black/60 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-300">
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="rounded-full border border-purple-400/30 bg-purple-500/10 px-2.5 py-0.5 text-[9px] font-bold uppercase text-purple-300">Awaiting Your Sign-Off</span>
                                <span class="text-xs text-brand-white/40">Dept: {{ str_replace('_', ' ', $approvalTask->department) }}</span>
                            </div>
                            <h4 class="text-base font-semibold text-brand-white break-words">{{ $approvalTask->title }}</h4>
                            <p class="text-xs text-brand-white/60">Assignee: <strong class="text-brand-white">{{ $approvalTask->assignee?->name ?? 'Staff' }}</strong> &bull; Assigned by: {{ $approvalTask->assigner?->name ?? 'Admin' }}</p>
                            @if($approvalTask->details)
                                <div class="text-xs text-brand-white/50 line-clamp-2 mt-1">{!! $approvalTask->details !!}</div>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('portal.tasks.completion-review', $approvalTask) }}" class="inline approval-action-form">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-500 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-lg transition-all">
                                    ✓ Approve Task
                                </button>
                            </form>

                            <details class="relative inline-block text-left">
                                <summary class="cursor-pointer rounded-xl border border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/20 px-4 py-2 text-xs font-bold uppercase tracking-wider text-amber-300 transition-all">
                                    ↩ Send Back
                                </summary>
                                <div class="absolute right-0 top-full mt-2 z-30 w-80 rounded-2xl border border-amber-500/30 bg-brand-black/95 p-4 shadow-2xl backdrop-blur-xl">
                                    <form method="POST" action="{{ route('portal.tasks.completion-review', $approvalTask) }}" class="space-y-3 approval-action-form">
                                        @csrf
                                        <input type="hidden" name="action" value="revert">
                                        <div>
                                            <label class="block text-[10px] uppercase tracking-wider text-amber-300 mb-1 font-bold">Feedback / Rework Notes</label>
                                            <textarea name="review_comment" rows="3" required placeholder="Explain what needs fixing..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 p-2.5 text-xs text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:outline-none"></textarea>
                                        </div>
                                        <button type="submit" class="w-full rounded-xl bg-amber-600 hover:bg-amber-500 py-2 text-xs font-bold uppercase tracking-wider text-white transition-all">
                                            Confirm Send Back
                                        </button>
                                    </form>
                                </div>
                            </details>

                            <a href="{{ route('portal.tasks.edit', $approvalTask) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 px-3 py-2 text-xs text-brand-white/70 hover:text-brand-white transition-all">
                                Details →
                            </a>
                        </div>
                    </div>
                @endforeach

                @if(isset($myPendingApprovals) && $myPendingApprovals instanceof \Illuminate\Pagination\LengthAwarePaginator && $myPendingApprovals->hasPages())
                    <div class="mt-4 flex items-center justify-between border-t border-purple-500/20 pt-3">
                        <span class="text-xs text-brand-white/40">Showing page {{ $myPendingApprovals->currentPage() }} of {{ $myPendingApprovals->lastPage() }}</span>
                        <div class="flex items-center gap-2">
                            @if($myPendingApprovals->onFirstPage())
                                <span class="rounded-lg border border-brand-white/10 px-3 py-1 text-xs text-brand-white/30 cursor-not-allowed">← Prev</span>
                            @else
                                <a href="{{ $myPendingApprovals->previousPageUrl() }}" class="rounded-lg border border-brand-white/20 bg-brand-white/5 hover:bg-brand-white/15 px-3 py-1 text-xs text-brand-white">← Prev</a>
                            @endif

                            @if($myPendingApprovals->hasMorePages())
                                <a href="{{ $myPendingApprovals->nextPageUrl() }}" class="rounded-lg border border-brand-white/20 bg-brand-white/5 hover:bg-brand-white/15 px-3 py-1 text-xs text-brand-white">Next →</a>
                            @else
                                <span class="rounded-lg border border-brand-white/10 px-3 py-1 text-xs text-brand-white/30 cursor-not-allowed">Next →</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="py-6 text-center">
                <p class="text-sm text-brand-white/50">No tasks are currently waiting for your approval.</p>
                <p class="mt-1 text-xs text-brand-white/30">Tasks in the table below with status <span class="text-purple-300 font-semibold">'Awaiting Approval'</span> are awaiting review by their respective designated line managers.</p>
            </div>
        @endif
    </div>

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
                                @if($task->status === 'Awaiting Approval' || $task->completion_review_status === 'pending')
                                    @if($task->canBeEditedBy(auth()->user()))
                                        <span class="rounded-full border border-purple-500/40 bg-purple-500/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-purple-300">
                                            Awaiting Your Approval
                                        </span>
                                    @else
                                        <span class="rounded-full border border-purple-500/30 bg-purple-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-purple-400">
                                            Awaiting Line Manager
                                        </span>
                                    @endif
                                @else
                                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider {{ $statusBadge[$task->status] ?? 'border-brand-white/10 text-brand-white/60' }}">
                                        {{ $task->status }}
                                    </span>
                                @endif
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

    <script>
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('.approval-action-form');
        if (!form) return;

        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        const row = form.closest('[id^="approval-task-row-"]');

        fetch(form.action, {
            method: form.method || 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value || ''
            },
            body: new FormData(form)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(-10px)';
                    setTimeout(() => row.remove(), 300);
                }
                showApprovalToast(data.message || 'Action completed successfully.', 'success');
            } else {
                showApprovalToast(data.message || 'Failed to complete action.', 'error');
                if (submitBtn) submitBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            showApprovalToast('An error occurred. Please try again.', 'error');
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    function showApprovalToast(message, type = 'success') {
        let toast = document.getElementById('approval-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'approval-toast';
            toast.className = 'fixed bottom-6 right-6 z-50 rounded-2xl border px-5 py-3 text-xs font-semibold shadow-2xl backdrop-blur-xl transition-all duration-300 transform translate-y-full opacity-0 flex items-center gap-2';
            document.body.appendChild(toast);
        }

        toast.className = `fixed bottom-6 right-6 z-50 rounded-2xl border px-5 py-3 text-xs font-semibold shadow-2xl backdrop-blur-xl transition-all duration-300 transform translate-y-0 opacity-100 flex items-center gap-2 ${
            type === 'error'
                ? 'border-red-500/30 bg-red-950/90 text-red-200 shadow-red-950/50'
                : 'border-emerald-500/30 bg-emerald-950/90 text-emerald-200 shadow-emerald-950/50'
        }`;
        toast.innerHTML = `<span>${type === 'error' ? '⚠️' : '✓'}</span> <span>${message}</span>`;

        setTimeout(() => {
            toast.className = toast.className.replace('translate-y-0 opacity-100', 'translate-y-full opacity-0');
        }, 4000);
    }
    </script>
</x-app-layout>
