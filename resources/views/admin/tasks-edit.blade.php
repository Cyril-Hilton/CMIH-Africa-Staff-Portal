<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Assignments</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Task</h2>
            </div>
            <a href="{{ route('admin.tasks') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">Back to Tasks</a>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-6 max-w-6xl">
        <form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="title" :value="__('Task Title')" />
                <x-text-input id="title" name="title" type="text" required :value="old('title', $task->title)" />
            </div>

            <div>
                <x-input-label for="details" :value="__('Details')" />
                <textarea id="details" name="details" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">{{ old('details', $task->details) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="assigned_to" :value="__('Assign To')" />
                    <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="">Select staff</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected(old('assigned_to', $task->assigned_to) == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="department" :value="__('Department')" />
                    <select id="department" name="department" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach (['operations' => 'Operations', 'creatives' => 'Creatives', 'client_service' => 'Client Service', 'finance' => 'Finance', 'brands' => 'Brands', 'admin' => 'Admin', 'transport' => 'Transport'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('department', $task->department) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Supporting Staff (Multi-select checkbox list) --}}
            <div>
                <x-input-label :value="__('Supporting Staff / Contributors')" />
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30 mt-1">
                    @foreach ($staff as $staffMember)
                        <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-xs text-brand-white/80">
                            <input type="checkbox" name="supporting_staff_ids[]" value="{{ $staffMember->id }}" 
                                @checked(in_array($staffMember->id, (array) old('supporting_staff_ids', $task->supporting_staff_ids ?? [])))
                                class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0" />
                            <span>{{ $staffMember->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Supporting Roles --}}
            <div>
                <x-input-label for="supporting_roles" :value="__('Supporting Role / Description')" />
                <x-text-input id="supporting_roles" name="supporting_roles" type="text" :value="old('supporting_roles', $task->supporting_roles)" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Graphic Designer, Copywriter..." />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach ([
                            'Open' => 'Open — 10%',
                            'In Progress' => 'In Progress — 50%',
                            'Awaiting Approval' => 'Awaiting Approval — 95%',
                            'Completed' => 'Completed — 100%',
                            'Cancelled' => 'Cancelled — 0%',
                            'Awaiting Feedback' => 'Awaiting Feedback — 70%',
                            'Sent' => 'Sent — 90%',
                            'Approved' => 'Approved — 100%',
                            'Rejected' => 'Rejected — 30%',
                            'Paid' => 'Paid — 100%',
                            'Overdue' => 'Overdue — 40%'
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $task->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-brand-white/45">Progress is calculated automatically from the status.</p>
                </div>
                <div>
                    <x-input-label for="priority" :value="__('Priority')" />
                    <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $task->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="due_on" :value="__('Due Date')" />
                    <x-text-input id="due_on" name="due_on" type="date" :value="old('due_on', optional($task->due_on)->format('Y-m-d'))" />
                </div>
            </div>

            <div class="flex gap-4 pt-2">
                <button type="button" onclick="if(confirm('Are you sure you want to delete this task?')) { document.getElementById('delete-task-form').submit(); }" class="flex-1 rounded-md border border-brand-white/10 bg-zinc-700 hover:bg-zinc-600 py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all text-center">
                    Delete Task
                </button>
                <x-primary-button class="flex-1 justify-center">Update Task</x-primary-button>
            </div>
        </form>
        <form id="delete-task-form" method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</x-app-layout>
