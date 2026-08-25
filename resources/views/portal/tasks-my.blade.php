<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Personal Workspace</p>
                <h2 class="text-3xl font-display text-brand-white">My Tasks</h2>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('portal.tasks', ['view' => 'create']) }}" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-5 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    New Task
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $departmentStyles = [
            'operations'     => 'border-sky-400/40 bg-sky-500/10 text-sky-300',
            'creatives'      => 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-300',
            'client_service' => 'border-amber-400/40 bg-amber-500/10 text-amber-300',
            'finance'        => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300',
            'brands'         => 'border-indigo-400/40 bg-indigo-500/10 text-indigo-300',
            'admin'          => 'border-brand-red/40 bg-brand-red/10 text-brand-red',
            'transport'      => 'border-cyan-400/40 bg-cyan-500/10 text-cyan-300',
        ];
        $priorityDot = [
            'High'   => 'bg-red-500',
            'Medium' => 'bg-amber-400',
            'Low'    => 'bg-emerald-500',
        ];
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
        $filterOptions = [
            'all' => 'All tasks',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'overdue' => 'Overdue',
            'awaiting_approval' => 'Awaiting approval',
            'sent_back' => 'Sent back',
            'high_priority' => 'High priority',
        ];
        $sortOptions = [
            'updated' => 'Recently updated',
            'due' => 'Due date',
            'created' => 'Created date',
            'priority' => 'Priority',
            'status' => 'Status',
            'title' => 'Task title',
        ];
    @endphp

    {{-- Session flash --}}
    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400 flex items-center gap-3">
            <span class="text-lg">✅</span> {{ session('status') }}
        </div>
    @endif

    {{-- Stats Strip --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
        <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Total Assigned</p>
            <p class="mt-2 text-4xl font-bold text-brand-white">{{ $myTotal }}</p>
            <p class="mt-1 text-xs text-brand-white/40">Assigned + created + supporting</p>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-cyan-500/20 bg-cyan-500/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Total Tasks</p>
            <p class="mt-2 text-4xl font-bold text-cyan-300">{{ $myCreatedTotal }}</p>
            <p class="mt-1 text-xs text-cyan-300/50">Created by you</p>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-emerald-500/20 bg-emerald-500/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Total Completed</p>
            <p class="mt-2 text-4xl font-bold text-emerald-400">{{ $myCompleted }}</p>
            <p class="mt-1 text-xs text-emerald-400/50">By you</p>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-violet-500/20 bg-violet-500/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Total Approved</p>
            <p class="mt-2 text-4xl font-bold text-violet-300">{{ $myApproved }}</p>
            <p class="mt-1 text-xs text-violet-300/50">{{ $myApprovalLabel }}</p>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-blue-500/20 bg-blue-500/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">In Progress</p>
            <p class="mt-2 text-4xl font-bold text-blue-400">{{ $myInProgress }}</p>
            <p class="mt-1 text-xs text-blue-400/50">Pending tasks</p>
        </div>
        <div class="glass-panel rounded-2xl p-5 border border-red-500/20 bg-red-500/5">
            <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Overdue</p>
            <p class="mt-2 text-4xl font-bold text-red-400">{{ $myOverdue }}</p>
            <p class="mt-1 text-xs text-red-400/50">Past due date</p>
        </div>
    </div>

    <form method="GET" action="{{ route('portal.tasks') }}" class="mb-8 rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4">
        <input type="hidden" name="view" value="my-tasks">
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_0.75fr_auto_auto] md:items-end">
            <label class="grid gap-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-brand-ash">Filter</span>
                <select name="filter" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    @foreach($filterOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-brand-ash">Sort</span>
                <select name="sort" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-brand-ash">Direction</span>
                <select name="direction" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                    <option value="desc" @selected($direction === 'desc')>Descending</option>
                    <option value="asc" @selected($direction === 'asc')>Ascending</option>
                </select>
            </label>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-brand-red-dark">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
                Apply
            </button>
            <a href="{{ route('portal.tasks') }}" class="inline-flex items-center justify-center rounded-xl border border-brand-white/10 px-4 py-2.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-white/60 transition hover:bg-brand-white/10 hover:text-brand-white">
                Reset
            </a>
        </div>
    </form>

    {{-- Task List --}}
    <section class="space-y-3" data-silent-region="my-task-list">
        <h3 class="text-xs uppercase tracking-[0.3em] text-brand-ash mb-4">Your Assignments</h3>

        @forelse ($myTasks as $task)
            @php
                $isOverdue = $task->due_on && $task->due_on->isPast() && ! $task->isApprovedForPerformance();
            @endphp
            <div class="glass-panel rounded-2xl border {{ $isOverdue ? 'border-red-500/30' : 'border-brand-white/10' }} bg-brand-white/5 p-5 hover:bg-brand-white/[0.07] transition-all group">
                <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="flex w-full items-start gap-3 min-w-0 sm:flex-1">
                        {{-- Priority indicator dot --}}
                        <div class="mt-1.5 w-2.5 h-2.5 rounded-full shrink-0 {{ $priorityDot[$task->priority] ?? 'bg-gray-500' }}"></div>
                        <div class="min-w-0">
                            <h4 class="text-base font-semibold text-brand-white break-words sm:truncate">{{ $task->title }}</h4>
                            <p class="mt-0.5 text-xs text-brand-white/50">Assigned by {{ $task->assigner?->name ?? 'Admin' }}</p>
                            @if ($task->details)
                                <div class="mt-2 text-sm text-brand-white/60">{!! $task->details !!}</div>
                            @endif
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full border px-3 py-1 text-[10px] uppercase tracking-[0.2em] {{ $departmentStyles[$task->department] ?? 'border-brand-white/20' }}">
                                    {{ str_replace('_', ' ', $task->department ?? 'general') }}
                                </span>
                                <span class="rounded-full border px-3 py-1 text-[10px] uppercase tracking-[0.2em] {{ $statusBadge[$task->status] ?? 'border-brand-white/20 text-brand-white/60' }}">
                                    {{ $task->status }}
                                </span>
                                @if ($task->completion_review_status === 'pending')
                                    <span class="rounded-full border border-purple-500/30 bg-purple-500/10 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-purple-300">
                                        Manager audit pending
                                    </span>
                                @elseif ($task->completion_review_status === 'reverted')
                                    <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-amber-300">
                                        Sent back
                                    </span>
                                @elseif ($task->completion_review_status === 'approved')
                                    <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-emerald-300">
                                        Approved for Mega Table
                                    </span>
                                @endif
                                @if ($isOverdue)
                                    <span class="rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-red-400 font-bold animate-pulse">
                                        ⚠ Overdue
                                    </span>
                                @endif
                            </div>
                            @if ($task->completion_review_status === 'reverted' && $task->completion_review_note)
                                <div class="mt-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
                                    <span class="font-semibold">Manager note:</span> {{ $task->completion_review_note }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="w-full shrink-0 border-t border-brand-white/10 pt-4 text-left sm:w-auto sm:border-t-0 sm:pt-0 sm:text-right">
                        <div class="text-xs text-brand-white/40 uppercase tracking-widest mb-1">Progress</div>
                        <div class="text-2xl font-bold text-brand-white">{{ $task->progress }}%</div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-brand-white/10 sm:w-24">
                            <div class="h-full rounded-full {{ $task->progress >= 100 ? 'bg-emerald-500' : ($task->progress >= 50 ? 'bg-sky-500' : 'bg-brand-red') }}" style="width: {{ $task->progress }}%"></div>
                        </div>
                        @if ($task->due_on)
                            <p class="mt-2 text-xs {{ $isOverdue ? 'text-red-400 font-semibold' : 'text-brand-white/40' }}">
                                Due {{ $task->due_on->format('d M Y') }}
                            </p>
                        @else
                            <p class="mt-2 text-xs text-brand-white/30">No deadline</p>
                        @endif
                        @php $canEdit = $task->canBeEditedBy(auth()->user()); @endphp
                        @if ($canEdit)
                            <div class="mt-3 flex flex-wrap items-center justify-start gap-2 transition-all sm:justify-end">
                                <a href="{{ route('portal.tasks.edit', $task) }}" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-[10px] uppercase tracking-[0.15em] text-brand-white/70 transition-all hover:bg-brand-white/10 hover:text-brand-white sm:flex-none sm:py-1.5">
                                    ✏ Edit
                                </a>
                                <form method="POST" action="{{ route('portal.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')" class="flex-1 sm:flex-none">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-brand-red/30 bg-brand-red/10 px-3 py-2 text-[10px] uppercase tracking-[0.15em] text-brand-red transition-all hover:bg-brand-red/20 sm:w-auto sm:py-1.5">
                                        🗑 Delete
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-12 text-center">
                <div class="text-4xl mb-3">📋</div>
                <p class="text-brand-white/50 text-sm">No tasks assigned to you yet.</p>
                <a href="{{ route('portal.tasks', ['view' => 'create']) }}" class="mt-4 inline-block rounded-xl bg-brand-red/20 border border-brand-red/30 hover:bg-brand-red/30 px-5 py-2.5 text-xs uppercase tracking-[0.2em] text-brand-red transition-all">
                    Create Your First Task
                </a>
            </div>
        @endforelse

        @if ($myTasks instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-4">
                <x-dashboard-pagination
                    :paginator="$myTasks->appends(request()->except('my_page'))"
                    item-label="tasks"
                />
            </div>
        @endif
    </section>
</x-app-layout>
