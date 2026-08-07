<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Task Management</p>
            <h2 class="text-3xl font-display text-brand-white">Create New Task</h2>
        </div>
    </x-slot>

    {{-- Session flash --}}
    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 flex items-start gap-4">
            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300" aria-hidden="true">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.42 0L3.29 9.274a1 1 0 1 1 1.42-1.414l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/></svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-emerald-400">Task Created Successfully</p>
                <p class="text-xs text-emerald-400/70 mt-1">{{ session('status') }}</p>
                @if (!$todayAttendance)
                    <a href="{{ route('dashboard') }}" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 px-5 py-2.5 text-xs uppercase tracking-[0.2em] font-bold text-white transition-all">
                        Clock In
                    </a>
                @endif
            </div>
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

    <div class="max-w-6xl mx-auto">
        {{-- Clock-in tip banner (only if user hasn't created a task today) --}}
        @if (!$hasTodayTask)
            <div class="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 flex items-start gap-4">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-300" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 4a1 1 0 1 0-2 0v4a1 1 0 0 0 .553.894l3 1.5a1 1 0 0 0 .894-1.788L11 9.382V6Z" clip-rule="evenodd"/></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-amber-400">Add Your First Task Today to Clock In</p>
                    <p class="text-xs text-amber-400/70 mt-1">You must register a task or project for today before you can clock in via the dashboard. Fill in the form below to unlock your clock-in.</p>
                </div>
            </div>
        @elseif (!$todayAttendance)
            <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300" aria-hidden="true">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.42 0L3.29 9.274a1 1 0 1 1 1.42-1.414l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/></svg>
            </span>
                    <div>
                        <p class="text-sm font-semibold text-emerald-400">Today's task registered</p>
                        <p class="text-xs text-emerald-400/70">You're good to clock in. Head to the dashboard to proceed.</p>
                    </div>
                </div>
                <a href="{{ route('dashboard') }}" class="shrink-0 rounded-xl bg-emerald-500 hover:bg-emerald-600 px-5 py-2.5 text-xs uppercase tracking-[0.2em] font-bold text-white transition-all whitespace-nowrap">
                    Clock In Now
                </a>
            </div>
        @else
            <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 flex items-center gap-4">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300" aria-hidden="true">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.42 0L3.29 9.274a1 1 0 1 1 1.42-1.414l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/></svg>
            </span>
                <div>
                    <p class="text-sm font-semibold text-emerald-400">Today's clock-in is complete</p>
                    <p class="text-xs text-emerald-400/70">You clocked in at {{ $todayAttendance->clock_in_at?->format('h:i A') }}. Add as many task updates as needed today without clocking in again.</p>
                </div>
            </div>
        @endif

        {{-- Create Task Form --}}
        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-8">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-xl bg-brand-red/20 border border-brand-red/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-widest text-brand-white">New Task / Project Activity</h3>
                    <p class="text-xs text-brand-white/40 mt-0.5">Fill in the details below to register a task</p>
                </div>
            </div>

            <form method="POST" action="{{ route('portal.tasks.store') }}" class="space-y-6">
                @csrf

                {{-- Row 1: Title + Due Date --}}
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="title" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Task Title <span class="text-brand-red">*</span></label>
                        <input id="title" name="title" type="text" required value="{{ old('title') }}"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0 transition"
                            placeholder="e.g. Creative Brief Review, Client Pitch Deck...">
                    </div>
                    <div>
                        <label for="due_on" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Due Date</label>
                        <input id="due_on" name="due_on" type="date"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition"
                            value="{{ old('due_on', now()->toDateString()) }}">
                    </div>
                </div>

                {{-- Client / Campaign Selection --}}
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="campaign_id" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Associate with Campaign</label>
                        <select id="campaign_id" name="campaign_id"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
                            <option value="">— Select Campaign (Optional) —</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" {{ old('campaign_id') == $campaign->id ? 'selected' : '' }}>
                                    {{ $campaign->name }} @if($campaign->client_name) ({{ $campaign->client_name }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="client_name" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Client / Brand Name</label>
                        <input id="client_name" name="client_name" type="text" value="{{ old('client_name') }}"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0 transition"
                            placeholder="e.g. Coca-Cola, MTN (Optional)">
                    </div>
                </div>

                {{-- Row 2: Priority + Details --}}
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="priority" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Priority</label>
                        <select id="priority" name="priority"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>🟢 Low</option>
                            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>🟡 Medium</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>🔴 High</option>
                        </select>
                    </div>

                    @if ($canAssignOthers)
                    <div>
                        <label for="assign_to" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Assign To</label>
                        <select id="assign_to" name="assign_to"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
                            <option value="">— Assign to yourself —</option>
                            @foreach ($teamMembers as $member)
                                <option value="{{ $member->id }}" {{ old('assign_to') == $member->id ? 'selected' : '' }}>
                                    {{ $member->name }} ({{ ucwords(str_replace('_', ' ', $member->department ?? 'N/A')) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                {{-- Supporting Staff (Multi-select checkbox list) --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Supporting Staff / Contributors</label>
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30">
                        @foreach ($allStaff as $staffMember)
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-xs text-brand-white/80">
                                <input type="checkbox" name="supporting_staff_ids[]" value="{{ $staffMember->id }}" 
                                    @checked(in_array($staffMember->id, (array) old('supporting_staff_ids', [])))
                                    class="rounded border-brand-white/25 bg-brand-black text-brand-red focus:ring-0" />
                                <span>{{ $staffMember->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @php
                    $oldCopiedManagers = (array) old('copied_manager_ids', []);
                    $selectedCompletionManagerId = old('completion_manager_id');
                @endphp

                @if($requiresCompletionManager)
                    <div>
                        <label for="completion_manager_id" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">
                            Line Manager for Completion Approval <span class="text-brand-red">*</span>
                        </label>
                        <select id="completion_manager_id" name="completion_manager_id" required
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
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
                    <label class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Additional Copied Managers <span class="text-brand-white/30">(Optional)</span></label>
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30">
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
                    <label for="supporting_roles" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Supporting Role / Description</label>
                    <input id="supporting_roles" name="supporting_roles" type="text" value="{{ old('supporting_roles') }}"
                        class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0 transition"
                        placeholder="e.g. Graphic Designer, Copywriter, QA Assistance...">
                </div>

                {{-- Details / Objective --}}
                <div>
                    <label for="details" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash mb-2">Details / Objective</label>
                    <textarea id="details" name="details" rows="3"
                        class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0 transition resize-none"
                        placeholder="Brief description of deliverables, goals, or acceptance criteria...">{{ old('details') }}</textarea>
                </div>

                {{-- Submit --}}
                <div class="pt-2 flex items-center justify-between gap-4">
                    <a href="{{ route('portal.tasks') }}" class="text-xs uppercase tracking-[0.2em] text-brand-white/40 hover:text-brand-white transition">
                        ← Back to My Tasks
                    </a>
                    <button type="submit"
                        class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-8 py-3 text-xs uppercase tracking-[0.25em] font-bold text-white transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        Create Task
                    </button>
                </div>
            </form>
        </div>

        {{-- Tips --}}
        <div class="mt-6 grid sm:grid-cols-3 gap-4 text-center">
            <div class="rounded-2xl border border-brand-white/5 bg-brand-white/[0.03] p-4">
                <div class="mb-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-red/15 text-brand-red" aria-hidden="true"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" clip-rule="evenodd"/></svg></div>
                <p class="text-xs text-brand-white/50 leading-relaxed">Be specific with your task title for better tracking</p>
            </div>
            <div class="rounded-2xl border border-brand-white/5 bg-brand-white/[0.03] p-4">
                <div class="mb-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-white/10 text-brand-white/70" aria-hidden="true"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H2V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1Z"/><path fill-rule="evenodd" d="M2 10h16v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6Zm4 2a1 1 0 0 0 0 2h4a1 1 0 1 0 0-2H6Z" clip-rule="evenodd"/></svg></div>
                <p class="text-xs text-brand-white/50 leading-relaxed">Set realistic deadlines to avoid overdue flags</p>
            </div>
            <div class="rounded-2xl border border-brand-white/5 bg-brand-white/[0.03] p-4">
                <div class="mb-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/15 text-amber-300" aria-hidden="true"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 4a1 1 0 1 0-2 0v4a1 1 0 0 0 .553.894l3 1.5a1 1 0 0 0 .894-1.788L11 9.382V6Z" clip-rule="evenodd"/></svg></div>
                <p class="text-xs text-brand-white/50 leading-relaxed">Creating a task unlocks your daily clock-in on the dashboard</p>
            </div>
        </div>
    </div>
</x-app-layout>
