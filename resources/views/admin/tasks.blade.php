<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Assignments</p>
            <h2 class="text-3xl font-display text-brand-white">Task Manager</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    @php
        $departmentStyles = [
            'operations' => 'border-sky-400/40 bg-sky-500/10 text-sky-200',
            'creatives' => 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200',
            'client_service' => 'border-amber-400/40 bg-amber-500/10 text-amber-200',
            'finance' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200',
            'brands' => 'border-indigo-400/40 bg-indigo-500/10 text-indigo-200',
            'admin' => 'border-brand-red/40 bg-brand-red/10 text-brand-red',
            'transport' => 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200',
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
    @endphp

    <div class="space-y-6">
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Assign a Task</h3>
            <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <x-input-label for="title" :value="__('Task Title')" />
                    <x-text-input id="title" name="title" type="text" required placeholder="Launch checklist" />
                </div>
                <div>
                    <x-input-label for="details" :value="__('Details')" />
                    <textarea id="details" name="details" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Describe the task"></textarea>
                </div>
                <div>
                    <x-input-label for="assigned_to" :value="__('Assign To')" />
                    <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="">Select staff</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="department" :value="__('Department')" />
                    <select id="department" name="department" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        <option value="">Select department</option>
                        @foreach (['operations' => 'Operations', 'creatives' => 'Creatives', 'client_service' => 'Client Service', 'finance' => 'Finance', 'brands' => 'Brands', 'admin' => 'Admin', 'transport' => 'Transport'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Supporting Staff (Multi-select checkbox list) --}}
                <div>
                    <x-input-label :value="__('Supporting Staff / Contributors')" />
                    <div class="grid gap-3 sm:grid-cols-2 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30 mt-1">
                        @foreach ($staff as $staffMember)
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-xs text-brand-white/80">
                                <input type="checkbox" name="supporting_staff_ids[]" value="{{ $staffMember->id }}" 
                                    @checked(in_array($staffMember->id, (array) old('supporting_staff_ids', [])))
                                    class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0" />
                                <span>{{ $staffMember->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Supporting Roles --}}
                <div>
                    <x-input-label for="supporting_roles" :value="__('Supporting Role / Description')" />
                    <x-text-input id="supporting_roles" name="supporting_roles" type="text" value="{{ old('supporting_roles') }}"
                        class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30"
                        placeholder="e.g. Graphic Designer, Copywriter..." />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="Open">Open — 10%</option>
                            <option value="In Progress">In Progress — 50%</option>
                            <option value="Awaiting Approval">Awaiting Approval — 95%</option>
                            <option value="Completed">Completed — 100%</option>
                            <option value="Cancelled">Cancelled — 0%</option>
                            <option value="Awaiting Feedback">Awaiting Feedback — 70%</option>
                            <option value="Sent">Sent — 90%</option>
                            <option value="Approved">Approved — 100%</option>
                            <option value="Rejected">Rejected — 30%</option>
                            <option value="Paid">Paid — 100%</option>
                            <option value="Overdue">Overdue — 40%</option>
                        </select>
                        <p class="mt-2 text-xs text-brand-white/45">Progress is calculated automatically from the status.</p>
                    </div>
                    <div>
                        <x-input-label for="priority" :value="__('Priority')" />
                        <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="due_on" :value="__('Due Date')" />
                        <x-text-input id="due_on" name="due_on" type="date" />
                    </div>
                </div>
                <x-primary-button class="w-full justify-center">Assign Task</x-primary-button>
            </form>
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">All Tasks</h3>
            <div class="mt-4 space-y-4">
                        @forelse ($tasks as $task)
                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $task->assignee?->name ?? 'Unassigned' }}</p>
                                        <p class="mt-2 text-sm text-brand-white">{{ $task->title }}</p>
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
                                        <span class="rounded-full border px-2.5 py-1 text-[10px] uppercase tracking-wider font-semibold {{ $statusBadge[$task->status] ?? 'border-brand-white/10 text-brand-white/60' }}">
                                            {{ $task->status }}
                                        </span>
                                        @if ($task->due_on)
                                            <p class="text-xs text-brand-white/60">Due {{ $task->due_on->format('M d, Y') }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($task->details)
                                    <div class="mt-2 text-xs text-brand-white/60">{!! $task->details !!}</div>
                                @endif
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.tasks.edit', $task) }}" class="rounded-full border border-brand-white/20 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-white/70">Edit</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-brand-white/60">No tasks created yet.</p>
                        @endforelse
            </div>

            @if ($tasks instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-4">
                    {{ $tasks->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


