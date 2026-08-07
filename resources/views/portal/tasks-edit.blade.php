<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">My Assignments</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Task</h2>
            </div>
            <a href="{{ route('portal.tasks') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">Back to Tasks</a>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-6 max-w-6xl border border-brand-white/10 bg-brand-white/5">
        @if(session('status'))
            <div class="mb-5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-brand-red/40 bg-brand-red/10 p-4 text-sm text-brand-white/80">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($task->completion_review_status)
            @php
                $reviewStyles = [
                    'pending' => 'border-purple-500/30 bg-purple-500/10 text-purple-200',
                    'approved' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
                    'reverted' => 'border-amber-500/30 bg-amber-500/10 text-amber-200',
                    'audit_task' => 'border-sky-500/30 bg-sky-500/10 text-sky-200',
                ];
            @endphp
            <div class="mb-5 rounded-2xl border {{ $reviewStyles[$task->completion_review_status] ?? 'border-brand-white/10 bg-brand-white/5 text-brand-white/70' }} p-4">
                <p class="text-[10px] uppercase tracking-[0.25em] opacity-70">Completion Review</p>
                @if($task->completion_review_status === 'pending')
                    <p class="mt-2 text-sm font-semibold">Waiting for manager audit. This task is hidden from the Mega Table until it is approved.</p>
                    <p class="mt-1 text-xs opacity-70">Requested {{ $task->completion_review_requested_at?->diffForHumans() ?? 'recently' }}.</p>
                @elseif($task->completion_review_status === 'approved')
                    <p class="mt-2 text-sm font-semibold">Completion approved{{ $task->completionReviewer ? ' by '.$task->completionReviewer->name : '' }}. This task can appear on the Mega Table.</p>
                    @if($task->completion_review_note)
                        <p class="mt-2 text-xs opacity-80">{{ $task->completion_review_note }}</p>
                    @endif
                @elseif($task->completion_review_status === 'reverted')
                    <p class="mt-2 text-sm font-semibold">Sent back for rework. Resubmit as completed when the corrections are done.</p>
                    @if($task->completion_review_note)
                        <p class="mt-2 rounded-xl bg-brand-black/20 px-3 py-2 text-xs opacity-90">{{ $task->completion_review_note }}</p>
                    @endif
                @endif
            </div>
        @endif

        @if(($canReviewCompletion ?? false) && $task->completion_review_status === 'pending')
            <form method="POST" action="{{ route('portal.tasks.completion-review', $task) }}" class="mb-6 rounded-2xl border border-purple-500/25 bg-purple-500/10 p-5">
                @csrf
                <p class="text-xs uppercase tracking-[0.25em] text-purple-200/80">Manager Audit Action</p>
                <p class="mt-1 text-sm text-brand-white/70">Review the work, add a comment if needed, then approve it for the Mega Table or send it back for rework.</p>
                <textarea name="review_comment" rows="3" class="mt-4 w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0" placeholder="Optional approval/revert comments...">{{ old('review_comment') }}</textarea>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" name="action" value="approve" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-white transition-all">
                        Approve Completion
                    </button>
                    <button type="submit" name="action" value="revert" class="rounded-xl border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 px-5 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-amber-300 transition-all">
                        Send Back / Revert
                    </button>
                </div>
            </form>
        @endif

        <form method="POST" action="{{ route('portal.tasks.update', $task) }}" class="space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="title" :value="__('Task Title')" />
                <x-text-input id="title" name="title" type="text" required :value="old('title', $task->title)" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="campaign_id" :value="__('Associate with Campaign')" />
                    <select id="campaign_id" name="campaign_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="">— Select Campaign (Optional) —</option>
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign->id }}" @selected(old('campaign_id', $task->campaign_id) == $campaign->id)>
                                {{ $campaign->name }} @if($campaign->client_name) ({{ $campaign->client_name }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="client_name" :value="__('Client / Brand Name')" />
                    <x-text-input id="client_name" name="client_name" type="text" :value="old('client_name', $task->client_name)" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Coca-Cola, MTN" />
                </div>
            </div>

            <div>
                <x-input-label for="details" :value="__('Details / Objective')" />
                <textarea id="details" name="details" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Describe deliverables">{{ old('details', $task->details) }}</textarea>
            </div>

            {{-- Supporting Staff (Multi-select checkbox list) --}}
            <div>
                <x-input-label :value="__('Supporting Staff / Contributors')" />
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30 mt-1">
                    @foreach ($allStaff as $staffMember)
                        <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-xs text-brand-white/80">
                            <input type="checkbox" name="supporting_staff_ids[]" value="{{ $staffMember->id }}" 
                                @checked(in_array($staffMember->id, (array) old('supporting_staff_ids', $task->supporting_staff_ids ?? [])))
                                class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0" />
                            <span>{{ $staffMember->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @php
                $oldCopiedManagers = (array) old('copied_manager_ids', $task->copied_manager_ids ?? []);
            @endphp

            @if($requiresCompletionManager)
                <div>
                    <x-input-label for="completion_manager_id" :value="__('Line Manager for Completion Approval')" />
                    <select id="completion_manager_id" name="completion_manager_id" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="">— Select your line manager —</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) $selectedCompletionManagerId === (string) $manager->id)>
                                {{ $manager->name }} ({{ ucwords(str_replace('_', ' ', $manager->department ?? 'N/A')) }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-brand-white/45">
                        This person will approve the task before it appears on the Mega Table when you mark it complete.
                    </p>
                </div>
            @endif

            {{-- Additional Copied Managers (Optional multi-select checkbox list) --}}
            <div>
                <x-input-label :value="__('Additional Copied Managers (Optional)')" />
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30 mt-1">
                    @foreach ($managers as $manager)
                        <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-xs text-brand-white/80">
                            <input type="checkbox" name="copied_manager_ids[]" value="{{ $manager->id }}" 
                                @checked(in_array($manager->id, $oldCopiedManagers))
                                class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0" />
                            <span>{{ $manager->name }} ({{ ucwords(str_replace('_', ' ', $manager->department ?? 'N/A')) }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Supporting Roles --}}
            <div>
                <x-input-label for="supporting_roles" :value="__('Supporting Role / Description')" />
                <x-text-input id="supporting_roles" name="supporting_roles" type="text" :value="old('supporting_roles', $task->supporting_roles)" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Graphic Designer, Copywriter..." />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
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
                    <p class="mt-2 text-xs text-brand-white/45">Progress is now calculated automatically from the selected status.</p>
                </div>
                <div>
                    <x-input-label for="priority" :value="__('Priority')" />
                    <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach (['High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $task->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="due_on" :value="__('Due Date')" />
                <x-text-input id="due_on" name="due_on" type="datetime-local" :value="old('due_on', optional($task->due_on)->format('Y-m-d\TH:i'))" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white" />
            </div>

            <div class="flex justify-between items-center pt-4">
                <button type="button" onclick="if(confirm('Are you sure you want to delete this task?')) { document.getElementById('delete-task-form').submit(); }" class="rounded-xl bg-zinc-800 hover:bg-zinc-700 border border-brand-white/10 px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white/90 hover:text-brand-white transition-all">
                    Delete Task
                </button>
                <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                    Update Task
                </button>
            </div>
        </form>
        <form id="delete-task-form" method="POST" action="{{ route('portal.tasks.destroy', $task) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</x-app-layout>
