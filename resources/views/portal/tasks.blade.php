<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Tasks</p>
            <h2 class="text-3xl font-display text-brand-white">My Assignments</h2>
        </div>
    </x-slot>

    @php
        $departmentStyles = [
            'operations' => 'border-sky-400/40 bg-sky-500/10 text-brand-white',
            'creatives' => 'border-fuchsia-400/40 bg-fuchsia-500/10 text-brand-white',
            'client_service' => 'border-amber-400/40 bg-amber-500/10 text-brand-white',
            'finance' => 'border-emerald-400/40 bg-emerald-500/10 text-brand-white',
            'brands' => 'border-indigo-400/40 bg-indigo-500/10 text-brand-white',
            'admin' => 'border-brand-red/40 bg-brand-red/10 text-brand-white',
            'transport' => 'border-cyan-400/40 bg-cyan-500/10 text-brand-white',
        ];

        $departmentBarStyles = [
            'operations' => 'bg-sky-500',
            'creatives' => 'bg-fuchsia-500',
            'client_service' => 'bg-amber-500',
            'finance' => 'bg-emerald-500',
            'brands' => 'bg-indigo-500',
            'admin' => 'bg-brand-red',
            'transport' => 'bg-cyan-500',
        ];

        $priorityStyles = [
            'high' => 'border-brand-red/40 bg-brand-red/10 text-brand-red',
            'medium' => 'border-brand-white/20 bg-brand-white/10 text-brand-white',
            'low' => 'border-brand-white/10 bg-brand-white/5 text-brand-white/70',
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

        $toggleDirection = $direction === 'asc' ? 'desc' : 'asc';
    @endphp

    <div class="space-y-6">
        <!-- Create Task Form (To unlock clock-in) -->
        <div id="create-task-form" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 mb-6">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">➕ Add Daily Task / Project Activity</h3>
            
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.tasks.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="title" :value="__('Task Title')" />
                        <x-text-input id="title" name="title" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Creative Brief Review or client pitch" />
                    </div>
                    <div>
                        <x-input-label for="due_on" :value="__('Due Date')" />
                        <x-text-input id="due_on" name="due_on" type="date" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white" :value="now()->toDateString()" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="priority" :value="__('Priority')" />
                        <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="details" :value="__('Details / Objective')" />
                        <x-text-input id="details" name="details" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Short description of deliverables" />
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Create Task
                    </button>
                </div>
            </form>
        </div>
        <div class="space-y-4">
            @forelse ($myTasks as $task)
                <div class="glass-panel rounded-2xl p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Assigned by {{ $task->assigner?->name ?? 'Admin' }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-brand-white">{{ $task->title }}</h3>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs uppercase tracking-[0.3em] text-brand-ash">
                                <span class="rounded-full border px-3 py-1 {{ $departmentStyles[$task->department] ?? 'border-brand-white/20' }}">
                                    {{ str_replace('_', ' ', $task->department ?? 'operations') }}
                                </span>
                                <span class="rounded-full border px-3 py-1 {{ $priorityStyles[$task->priority] ?? 'border-brand-white/20' }}">
                                    {{ ucfirst($task->priority ?? 'medium') }} Priority
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Status</p>
                            <div class="mt-2">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$task->status] ?? 'border-brand-white/10 text-brand-white/60' }}">
                                    {{ $task->status }}
                                </span>
                            </div>
                            @if ($task->due_on)
                                <p class="text-xs text-brand-white/60">Due {{ $task->due_on->format('M d, Y') }}</p>
                            @else
                                <p class="text-xs text-brand-white/40">No timeline set</p>
                            @endif
                        </div>
                    </div>
                    @if ($task->details)
                        <p class="mt-3 text-sm text-brand-white/70">{!! $task->details !!}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-brand-white/10 pt-4">
                        <div class="text-xs text-brand-white/50">
                            Progress: {{ $task->progress }}%
                        </div>
                        @php
                            $isSupporting = is_array($task->supporting_staff_ids) && in_array(auth()->id(), $task->supporting_staff_ids);
                            $canEdit = $task->assigned_to === auth()->id() || $task->assigned_by === auth()->id() || $isSupporting;
                        @endphp
                        @if ($canEdit)
                            <div class="flex items-center gap-3">
                                <a href="{{ route('portal.tasks.edit', $task) }}" class="rounded-full border border-brand-white/20 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all whitespace-nowrap">
                                    Edit Task
                                </a>
                                <form method="POST" action="{{ route('portal.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-brand-red/30 bg-brand-red/10 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-red hover:bg-brand-red/20 transition-all whitespace-nowrap">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="glass-panel rounded-2xl p-6 text-sm text-brand-white/60">
                    No tasks assigned yet.
                </div>
            @endforelse

            @if ($myTasks instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div>
                    {{ $myTasks->links() }}
                </div>
            @endif
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">All Staff Tasks</p>
                    <h3 class="text-lg font-semibold text-brand-white">Company Task Board</h3>
                </div>
                <div class="text-xs uppercase tracking-[0.3em] text-brand-ash">
                    Sort: {{ $sort ? str_replace('_', ' ', $sort) : 'latest' }}
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.3em] text-brand-ash">
                        <tr class="">
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'task', 'direction' => $sort === 'task' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Task
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'task' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'task' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'staff', 'direction' => $sort === 'staff' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Staff
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'staff' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'staff' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'department', 'direction' => $sort === 'department' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Department
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'department' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'department' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'priority', 'direction' => $sort === 'priority' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Priority
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'priority' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'priority' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'timeline', 'direction' => $sort === 'timeline' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Timeline
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'timeline' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'timeline' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 font-normal text-left cursor-pointer group hover:text-brand-white transition-colors">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'direction' => $sort === 'status' ? $toggleDirection : 'asc']) }}" class="flex items-center gap-2">
                                    Status
                                    <span class="opacity-30 group-hover:opacity-100 transition-opacity {{ $sort === 'status' ? 'opacity-100 text-brand-red' : '' }}">
                                        @if($sort === 'status' && $direction === 'desc')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                        @endif
                                    </span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($allTasks as $task)
                            <tr class="border-t border-brand-white/10">
                                <td class="py-4 text-brand-white">
                                    <p class="text-sm">{{ $task->title }}</p>
                                    <p class="text-xs text-brand-white/50">Assigned by {{ $task->assigner?->name ?? 'Admin' }}</p>
                                </td>
                                <td class="py-4">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="py-4">
                                    <span class="rounded-full border px-3 py-1 text-xs uppercase tracking-[0.3em] {{ $departmentStyles[$task->department] ?? 'border-brand-white/20' }}">
                                        {{ str_replace('_', ' ', $task->department ?? 'operations') }}
                                    </span>
                                </td>
                                <td class="py-4">
                                    <span class="rounded-full border px-3 py-1 text-xs uppercase tracking-[0.3em] {{ $priorityStyles[$task->priority] ?? 'border-brand-white/20' }}">
                                        {{ ucfirst($task->priority ?? 'medium') }}
                                    </span>
                                </td>
                                <td class="py-4">
                                    {{ $task->due_on?->format('M d, Y') ?? 'No date' }}
                                </td>
                                <td class="py-4">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusBadge[$task->status] ?? 'border-brand-white/10 text-brand-white/60' }}">
                                        {{ $task->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-brand-white/60">No tasks logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($allTasks instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-4">
                    {{ $allTasks->links() }}
                </div>
            @endif
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Department Performance</p>
                    <h3 class="text-lg font-semibold text-brand-white">Completion by Department</h3>
                </div>
                <div class="text-xs uppercase tracking-[0.3em] text-brand-ash">
                    Powered by completed tasks
                </div>
            </div>

            @if ($showDepartmentChart)
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach ($departmentStats as $department)
                        <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4">
                            <div class="flex items-center justify-between text-xs uppercase tracking-[0.3em] text-brand-ash">
                                <span>{{ $department['label'] }}</span>
                                <span>{{ $department['completed'] }} / {{ $department['total'] }} done</span>
                            </div>
                            <div class="mt-3 h-2 w-full rounded-full bg-brand-white/10 overflow-hidden">
                                <div class="h-full rounded-full {{ $departmentBarStyles[$department['key']] ?? 'bg-brand-white' }}" style="width: {{ $department['performance'] }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-brand-white/60">{{ $department['performance'] }}% completion</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-brand-white/60">Complete at least one task to unlock department performance charts.</p>
            @endif
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Completed Updates</p>
                    <h3 class="text-lg font-semibold text-brand-white">Progress Milestones</h3>
                </div>
                <div class="text-xs uppercase tracking-[0.3em] text-brand-ash">100% updates</div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @forelse ($completedUpdates as $update)
                    <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $update->user?->name ?? 'Staff' }}</p>
                                <p class="mt-2 text-sm text-brand-white">{{ $update->title }}</p>
                            </div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Completed</p>
                        </div>
                        <p class="mt-2 text-xs text-brand-white/60">{!! $update->summary !!}</p>
                        <p class="mt-2 text-xs text-brand-white/50">Target: {{ $update->due_on?->format('M d, Y') ?? 'TBD' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-brand-white/60">No completed updates yet.</p>
                @endforelse
            </div>

            @if ($completedUpdates instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-4">
                    {{ $completedUpdates->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


