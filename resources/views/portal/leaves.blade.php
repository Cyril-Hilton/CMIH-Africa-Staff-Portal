<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Leave Portal</p>
            <h2 class="text-3xl font-display text-brand-white">Leave & Absences</h2>
        </div>
    </x-slot>

    @php
        $statusStyles = [
            'approved' => 'border-green-500/30 bg-green-500/10 text-green-400',
            'rejected' => 'border-brand-red/30 bg-brand-red/10 text-brand-red',
            'pending_manager' => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-400',
            'pending_cvo' => 'border-purple-500/30 bg-purple-500/10 text-purple-400',
            'pending_hr' => 'border-sky-500/30 bg-sky-500/10 text-sky-400',
            'returned_for_correction' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-400',
        ];
    @endphp

    <div x-data="{
        openResubmitModal: false,
        resubmitData: {},
        resubmitActionUrl: '',
        resubmitForm: {
            leave_type: 'annual',
            start_date: '',
            end_date: '',
            line_manager_id: '',
            covering_staff_id: '',
            delegate_line_manager_id: '',
            comments: '',
        },
        triggerResubmit(leave) {
            this.resubmitData = Object.assign({}, leave);
            this.resubmitActionUrl = '{{ url('/portal/leaves') }}/' + leave.id + '/resubmit';
            this.resubmitForm = {
                leave_type: leave.leave_type || 'annual',
                start_date: leave.start_date || '',
                end_date: leave.end_date || '',
                line_manager_id: leave.line_manager_id || '',
                covering_staff_id: leave.covering_staff_id || '',
                delegate_line_manager_id: leave.delegate_line_manager_id || '',
                comments: leave.comments ? leave.comments.replace('Returned for Correction: ', '') : '',
            };
            this.openResubmitModal = true;
        }
    }" class="space-y-6">
        <!-- Leave Balance & Info header -->
        <div class="grid gap-6 md:grid-cols-3">
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10 text-2xl">
                    📅
                </div>
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Remaining Balance</p>
                <p class="mt-3 text-4xl font-semibold text-brand-white">{{ $user->leave_balance }} Days</p>
                <p class="text-xs text-brand-ash mt-2">Deducted automatically upon final approval</p>
            </div>

            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Reporting Hierarchy</p>
                <p class="mt-3 text-lg font-semibold text-brand-white">
                    @if($user->access_role === 'super_admin')
                        HR / CVO / Super Admin final sign-off
                    @elseif($user->lineManager)
                        {{ $user->lineManager->name }} &rarr; HR / CVO / Super Admin
                    @else
                        Line Manager Not Set (Select in Application)
                    @endif
                </p>
                <p class="text-xs text-brand-ash mt-2">Leave requests go to line manager first, then final approval.</p>
            </div>

            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Total Requests</p>
                <p class="mt-3 text-4xl font-semibold text-brand-white">{{ $myLeaves->count() }}</p>
                <p class="text-xs text-brand-ash mt-2">Historical application tracking</p>
            </div>
        </div>

        <x-auth-session-status class="mb-6" :status="session('status')" />

        @if ($errors->any())
            <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Manager Approvals Section -->
        @if($pendingApprovals->isNotEmpty())
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-lg font-semibold text-brand-white mb-4">✍️ Leave Applications Pending Your Review</h3>
                <div class="space-y-4">
                    @foreach($pendingApprovals as $approval)
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-white/5 flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-[16rem] flex-1 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-brand-white">{{ $approval->user->name }}</p>
                                    @if($approval->lineManager && $approval->line_manager_id !== $user->id && $user->isPeerLineManagerOf((int) $approval->line_manager_id))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-400/30 bg-amber-400/10 text-amber-300">
                                            ↗ On behalf of {{ $approval->lineManager->name }}
                                        </span>
                                    @endif
                                </div>
                                <div class="grid gap-2 text-xs text-brand-white/55 sm:grid-cols-2 lg:grid-cols-3">
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Leave Type</span><span class="capitalize">{{ $approval->leave_type }}</span></p>
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Dates</span>{{ $approval->start_date->format('M d, Y') }} to {{ $approval->end_date->format('M d, Y') }}</p>
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Days</span>{{ $approval->start_date->diffInDays($approval->end_date) + 1 }}</p>
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Covering Duty</span>{{ $approval->coveringStaff?->name ?? 'Not selected' }}</p>
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Line Manager</span>{{ $approval->lineManager?->name ?? 'Direct HR/CVO approval' }}</p>
                                    @if($approval->delegateLineManager)
                                        <p><span class="block text-[10px] uppercase tracking-[0.2em] text-amber-400 font-semibold">Acting Line Manager</span><span class="text-amber-300 font-medium">{{ $approval->delegateLineManager->name }}</span></p>
                                    @endif
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Submitted</span>{{ $approval->created_at?->format('M d, Y h:i A') }}</p>
                                    <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Current Stage</span>{{ ucwords(str_replace('_', ' ', $approval->status)) }}</p>
                                </div>
                                <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-xs text-brand-white/65">
                                    <span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Reason / Notes</span>
                                    <p class="mt-1 whitespace-pre-wrap">{{ trim(strip_tags($approval->comments ?? '')) ?: 'No reason provided.' }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Approve form -->
                                <form method="POST" action="{{ route('portal.leaves.approve', $approval) }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 text-xs font-semibold uppercase tracking-wider hover:bg-green-500/20">
                                        Approve
                                    </button>
                                </form>

                                <!-- Reject form block -->
                                <form method="POST" action="{{ route('portal.leaves.reject', $approval) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" id="rej-comm-{{ $approval->id }}" name="rejection_comments" placeholder="Correction/Rejection notes" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-xs text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/30 w-44">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-brand-red/10 text-brand-red border border-brand-red/20 text-xs font-semibold uppercase tracking-wider hover:bg-brand-red/20">
                                        Reject
                                    </button>
                                    <button type="button" onclick="const inputVal = document.getElementById('rej-comm-{{ $approval->id }}').value; if (!inputVal) { alert('Please enter notes in the notes field first.'); return; } const f = document.getElementById('ret-form-{{ $approval->id }}'); f.rejection_comments.value = inputVal; f.submit();"
                                            class="px-4 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-xs font-semibold uppercase tracking-wider hover:bg-cyan-500/20">
                                        Return
                                    </button>
                                </form>
                                <form id="ret-form-{{ $approval->id }}" method="POST" action="{{ route('portal.leaves.return', $approval) }}" class="hidden">
                                    @csrf
                                    <input type="hidden" name="rejection_comments" value="">
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($pendingApprovals instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $pendingApprovals->links() }}
                    </div>
                @endif
            </div>
        @endif

        <div class="space-y-6">
            <!-- Leave Application Form -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-lg font-semibold text-brand-white mb-4">➕ Request Leave Time-Off</h3>
                <form method="POST" action="{{ route('portal.leaves.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="leave_type" :value="__('Leave Category')" />
                        <select id="leave_type" name="leave_type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="maternity">Maternity Leave</option>
                            <option value="paternity">Paternity Leave</option>
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="start_date" :value="__('Start Date')" />
                            <x-text-input id="start_date" name="start_date" type="date" required class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label for="end_date" :value="__('End Date')" />
                            <x-text-input id="end_date" name="end_date" type="date" required class="mt-1 w-full" />
                        </div>
                    </div>

                    @php
                        $requiresLineManager = $user->access_role !== 'super_admin';
                        $isLineManagerApplicant = $user->isLineManager();
                    @endphp

                    @if($requiresLineManager)
                        <div>
                            <x-input-label for="line_manager_id" :value="__('Routing Line Manager')" />
                            <select id="line_manager_id" name="line_manager_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                <option value="">Select Designated Line Manager</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->id }}" {{ $user->line_manager_id == $manager->id ? 'selected' : '' }}>{{ $manager->name }} ({{ ucfirst($manager->department) }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($isLineManagerApplicant)
                        <div>
                            <x-input-label for="delegate_line_manager_id">
                                <span class="text-brand-white font-semibold">{{ __('Appoint Relief / Acting Line Manager') }}</span>
                                <span class="text-brand-red font-bold text-xs ml-1">* Required for Line Managers</span>
                            </x-input-label>
                            <p class="text-xs text-brand-ash mb-1">Mandatory for line managers going on leave: select a colleague to take up your role and approve department tasks while you are away.</p>
                            <select id="delegate_line_manager_id" name="delegate_line_manager_id" required class="mt-1 w-full rounded-md border border-brand-red/40 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                <option value="">Select Appointed Acting Line Manager</option>
                                @foreach($colleagues as $colleague)
                                    <option value="{{ $colleague->id }}" {{ old('delegate_line_manager_id') == $colleague->id ? 'selected' : '' }}>{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <x-input-label for="delegate_line_manager_id" :value="__('Appoint Relief / Acting Line Manager (Optional)')" />
                            <select id="delegate_line_manager_id" name="delegate_line_manager_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                <option value="">Optional Relief Line Manager</option>
                                @foreach($colleagues as $colleague)
                                    <option value="{{ $colleague->id }}" {{ old('delegate_line_manager_id') == $colleague->id ? 'selected' : '' }}>{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="covering_staff_id" :value="__('Colleague Covering Duties')" />
                        <select id="covering_staff_id" name="covering_staff_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                            <option value="">Select Cover Colleague</option>
                            @foreach($colleagues as $colleague)
                                <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="comments" :value="__('Comments / Handover Instructions')" />
                        <textarea id="comments" name="comments" rows="3" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red placeholder-brand-white/30" placeholder="Please list tasks to cover or general notes..."></textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-3 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>

            <!-- Historical Requests List -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-lg font-semibold text-brand-white mb-4">📜 Leave History & Ledgers</h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-left text-sm text-brand-white/70">
                        <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash border-b border-brand-white/10">
                            <tr class="">
                                <th class="font-normal pb-3 text-left">Category</th>
                                <th class="font-normal pb-3 text-left">Duration</th>
                                <th class="font-normal pb-3 text-left">Days</th>
                                <th class="font-normal pb-3 text-left">Cover / Acting LM</th>
                                <th class="font-normal pb-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myLeaves as $leave)
                                <tr class="border-b border-brand-white/5 hover:bg-brand-white/5 transition-colors">
                                    <td class="py-4 text-brand-white font-medium capitalize">
                                        {{ $leave->leave_type }}
                                        @if($leave->status === 'returned_for_correction' && $leave->comments)
                                            <div class="text-[10px] text-cyan-400 mt-1 font-semibold border-l border-cyan-500/30 pl-2">
                                                Correction reason: "{{ str_replace('Returned for Correction: ', '', $leave->comments) }}"
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-4 text-xs">
                                        {{ $leave->start_date->format('M d, Y') }} &rarr; <br>
                                        {{ $leave->end_date->format('M d, Y') }}
                                    </td>
                                    <td class="py-4 font-mono">{{ $leave->start_date->diffInDays($leave->end_date) + 1 }}</td>
                                    <td class="py-4 text-xs text-brand-white/60">
                                        <div>Cover: {{ $leave->coveringStaff?->name ?? 'None' }}</div>
                                        @if($leave->delegateLineManager)
                                            <div class="text-[11px] text-amber-300 font-medium mt-0.5">Acting LM: {{ $leave->delegateLineManager->name }}</div>
                                        @endif
                                    </td>
                                    <td class="py-4 text-right flex flex-col items-end gap-1.5 justify-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusStyles[$leave->status] ?? 'border-brand-white/20' }}">
                                            {{ ucwords(str_replace('_', ' ', $leave->status)) }}
                                        </span>
                                        @if($leave->status === 'returned_for_correction')
                                            @php
                                                $resubmitPayload = [
                                                    'id' => $leave->id,
                                                    'leave_type' => $leave->leave_type,
                                                    'start_date' => $leave->start_date?->toDateString(),
                                                    'end_date' => $leave->end_date?->toDateString(),
                                                    'line_manager_id' => $leave->line_manager_id,
                                                    'covering_staff_id' => $leave->covering_staff_id,
                                                    'delegate_line_manager_id' => $leave->delegate_line_manager_id,
                                                    'comments' => $leave->comments,
                                                ];
                                            @endphp
                                            <button type="button" x-on:click="triggerResubmit({{ \Illuminate\Support\Js::from($resubmitPayload) }})"
                                                    class="px-2 py-0.5 rounded bg-cyan-500 text-brand-black text-[10px] font-bold hover:bg-cyan-400 transition uppercase tracking-wider">
                                                ✏️ Correct
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-sm text-brand-white/50">No leave requests submitted yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($myLeaves instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $myLeaves->links() }}
                    </div>
                @endif
            </div>
        </div>

    <!-- Alpine.js Resubmit Leave Modal -->
    <div x-show="openResubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-black/80 backdrop-blur-sm" x-cloak style="display: none;">
        <div @click.away="openResubmitModal = false" class="w-full max-w-md glass-panel rounded-2xl p-6 border border-brand-white/15 shadow-2xl relative"
             x-init="$watch('openResubmitModal', value => {
                 if (value) {
                     document.getElementById('resubmit_leave_type').value = resubmitData.leave_type || 'annual';
                     document.getElementById('resubmit_start_date').value = resubmitData.start_date || '';
                     document.getElementById('resubmit_end_date').value = resubmitData.end_date || '';
                     document.getElementById('resubmit_covering_staff_id').value = resubmitData.covering_staff_id || '';
                     if (document.getElementById('resubmit_line_manager_id')) {
                         document.getElementById('resubmit_line_manager_id').value = resubmitData.line_manager_id || '';
                     }
                     if (document.getElementById('resubmit_delegate_line_manager_id')) {
                         document.getElementById('resubmit_delegate_line_manager_id').value = resubmitData.delegate_line_manager_id || '';
                     }
                     document.getElementById('resubmit_comments').value = resubmitData.comments ? resubmitData.comments.replace('Returned for Correction: ', '') : '';
                 }
             })">
            <button @click="openResubmitModal = false" class="absolute top-4 right-4 text-brand-white/60 hover:text-brand-white text-lg transition-colors focus:outline-none">
                ✕
            </button>
            
            <h3 class="text-lg font-semibold text-brand-white mb-2">Correct & Resubmit Leave Request</h3>
            <p class="text-xs text-brand-ash mb-4">Modify and correct your leave request details below.</p>
            
            <form :action="resubmitActionUrl || '#'" method="POST" class="space-y-4">
                @csrf
                
                <div>
                    <x-input-label for="resubmit_leave_type" :value="__('Leave Category')" />
                    <select id="resubmit_leave_type" name="leave_type" x-model="resubmitForm.leave_type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red">
                        <option value="annual">Annual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="casual">Casual Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="paternity">Paternity Leave</option>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="resubmit_start_date" :value="__('Start Date')" />
                        <input id="resubmit_start_date" name="start_date" type="date" x-model="resubmitForm.start_date" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-brand-red" />
                    </div>
                    <div>
                        <x-input-label for="resubmit_end_date" :value="__('End Date')" />
                        <input id="resubmit_end_date" name="end_date" type="date" x-model="resubmitForm.end_date" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-brand-red" />
                    </div>
                </div>

                @if($requiresLineManager)
                    <div>
                        <x-input-label for="resubmit_line_manager_id" :value="__('Routing Line Manager')" />
                        <select id="resubmit_line_manager_id" name="line_manager_id" x-model="resubmitForm.line_manager_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red">
                            <option value="">Select Designated Line Manager</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }} ({{ ucfirst($manager->department) }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($isLineManagerApplicant)
                    <div>
                        <x-input-label for="resubmit_delegate_line_manager_id">
                            <span class="text-brand-white font-semibold">{{ __('Appoint Relief / Acting Line Manager') }}</span>
                            <span class="text-brand-red font-bold text-xs ml-1">* Required</span>
                        </x-input-label>
                        <select id="resubmit_delegate_line_manager_id" name="delegate_line_manager_id" x-model="resubmitForm.delegate_line_manager_id" required class="mt-1 w-full rounded-md border border-brand-red/50 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red">
                            <option value="">Select Appointed Acting Line Manager</option>
                            @foreach($colleagues as $colleague)
                                <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <x-input-label for="resubmit_delegate_line_manager_id" :value="__('Appoint Relief / Acting Line Manager (Optional)')" />
                        <select id="resubmit_delegate_line_manager_id" name="delegate_line_manager_id" x-model="resubmitForm.delegate_line_manager_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red">
                            <option value="">Optional Relief Line Manager</option>
                            @foreach($colleagues as $colleague)
                                <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <x-input-label for="resubmit_covering_staff_id" :value="__('Colleague Covering Duties')" />
                    <select id="resubmit_covering_staff_id" name="covering_staff_id" x-model="resubmitForm.covering_staff_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red">
                        <option value="">Select Cover Colleague</option>
                        @foreach($colleagues as $colleague)
                            <option value="{{ $colleague->id }}">{{ $colleague->name }} ({{ ucfirst($colleague->department) }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="resubmit_comments" :value="__('Comments / Handover Instructions')" />
                    <textarea id="resubmit_comments" name="comments" rows="3" x-model="resubmitForm.comments" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-brand-red placeholder-brand-white/30" placeholder="Please list tasks to cover or general notes..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                    <button type="button" @click="openResubmitModal = false" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="!resubmitActionUrl" class="px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-white bg-brand-red hover:bg-brand-red-dark transition shadow-lg shadow-brand-red/20 disabled:cursor-not-allowed disabled:opacity-50">
                        Resubmit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
</x-app-layout>
