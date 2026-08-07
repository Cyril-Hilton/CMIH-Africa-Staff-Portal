<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
                <h2 class="text-3xl font-display text-brand-white">💰 Salary Advances (Loans)</h2>
            </div>
            <a href="{{ route('portal.finance') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">
                Reimbursements & Claims
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 p-3 text-xs text-brand-red">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $isFinanceStaff = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';
    @endphp

    <div x-data="{ 
        openResubmitModal: false,
        resubmitAdvanceData: {},
        resubmitActionUrl: '',
        triggerResubmit(advance) {
            this.resubmitAdvanceData = Object.assign({}, advance);
            this.resubmitActionUrl = '{{ url('/portal/finance/advances') }}/' + advance.id + '/resubmit';
            this.openResubmitModal = true;
        }
    }" class="space-y-6">

        @php
            $cvoPendingCount = $pendingCvoAdvances->count();
            $financePendingCount = $advances->where('status', 'pending_finance')->count();
        @endphp

        {{-- Verification Queues --}}
        @if ($isFinanceStaff && $financePendingCount > 0)
            <div class="glass-panel rounded-2xl p-6 border border-amber-500/20 bg-amber-500/5">
                <h3 class="text-sm font-bold uppercase tracking-widest text-amber-400 mb-4">⏳ Finance Department Verification Queue</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($advances->where('status', 'pending_finance') as $item)
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-black/40 space-y-3">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $item->user->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $item->user->department)) }}</p>
                                </div>
                                <span class="text-xs font-bold text-amber-400">GH₵ {{ number_format($item->amount, 2) }}</span>
                            </div>
                            <p class="text-xs text-brand-white/80 leading-relaxed italic">"{{ $item->reason }}"</p>
                            <div class="text-[11px] text-brand-white/60 space-y-1">
                                <p>Repayment: <strong class="capitalize">{{ str_replace('_', ' ', $item->repayment_style) }}</strong></p>
                                @if ($item->repayment_style === 'monthly_deduction')
                                    <p>Deduction: <strong>GH₵ {{ number_format($item->monthly_deduction_amount, 2) }}</strong></p>
                                @endif
                            </div>

                            <div class="pt-2 border-t border-brand-white/5 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="verify">
                                    <button type="submit" class="rounded bg-emerald-500/20 hover:bg-emerald-500/35 px-2.5 py-1 text-[10px] uppercase font-bold text-emerald-300">
                                        Verify & Route CVO
                                    </button>
                                </form>

                                <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('ret-form-{{ $item->id }}'); f.notes.value = note; f.submit(); }" 
                                        class="rounded bg-cyan-500/20 hover:bg-cyan-500/35 px-2.5 py-1 text-[10px] uppercase font-bold text-cyan-300">
                                    Return for Correction
                                </button>
                                <form id="ret-form-{{ $item->id }}" method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}" class="hidden">
                                    @csrf
                                    <input type="hidden" name="action" value="return_for_correction">
                                    <input type="hidden" name="notes" value="">
                                </form>

                                <form method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="rounded bg-brand-red/20 hover:bg-brand-red/35 px-2.5 py-1 text-[10px] uppercase font-bold text-brand-red">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($isCVO && $cvoPendingCount > 0)
            <div class="glass-panel rounded-2xl p-6 border border-purple-500/20 bg-purple-500/5">
                <h3 class="text-sm font-bold uppercase tracking-widest text-purple-400 mb-4">👑 CVO / Executive Approval Queue</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pendingCvoAdvances as $item)
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-black/40 space-y-3">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $item->user->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $item->user->department)) }}</p>
                                </div>
                                <span class="text-xs font-bold text-purple-400">GH₵ {{ number_format($item->amount, 2) }}</span>
                            </div>
                            <p class="text-xs text-brand-white/80 leading-relaxed italic">"{{ $item->reason }}"</p>
                            <p class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1">✓ Verified by Finance Department</p>

                            <div class="pt-2 border-t border-brand-white/5 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="rounded bg-purple-500/20 hover:bg-purple-500/35 px-2.5 py-1 text-[10px] uppercase font-bold text-purple-300">
                                        Approve Loan
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="return_to_finance">
                                    <button type="submit" class="rounded bg-cyan-500/20 hover:bg-cyan-500/35 px-2.5 py-1 text-[10px] uppercase font-bold text-cyan-300">
                                        Return to Finance
                                    </button>
                                </form>
                                <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('cvo-ret-adv-{{ $item->id }}'); f.feedback.value = note; f.submit(); }"
                                        class="rounded bg-amber-500/20 hover:bg-amber-500/35 px-2.5 py-1 text-[10px] uppercase font-bold text-amber-300">
                                    Return to Creator
                                </button>
                                <form id="cvo-ret-adv-{{ $item->id }}" method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}" class="hidden">
                                    @csrf
                                    <input type="hidden" name="action" value="return_for_correction">
                                    <input type="hidden" name="feedback" value="">
                                </form>
                                <form method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="rounded bg-brand-red/20 hover:bg-brand-red/35 px-2.5 py-1 text-[10px] uppercase font-bold text-brand-red">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Core Advances request and log --}}
        <div class="space-y-6">
            <!-- Apply for Loan -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit"
                 x-data="{
                    repaymentStyle: 'monthly_deduction',
                    amount: '',
                    salary: {{ auth()->user()->monthlySalary() }},
                    get maxAdvance() { return this.salary * 2; }
                 }">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">💰 Request Salary Advance</h3>
                    <p class="text-[11px] text-brand-white/50 mt-1">Get immediate short-term financing up to 2x your registered monthly salary.</p>
                </div>

                <div class="mb-4 p-3.5 rounded-xl bg-brand-black/40 border border-brand-white/5 space-y-1">
                    <div class="flex justify-between text-xs text-brand-white/60">
                        <span>Your Monthly Salary:</span>
                        <span class="font-bold text-brand-white">GH₵ {{ number_format(auth()->user()->monthlySalary(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-brand-white/60">
                        <span>Max Advance Allowed:</span>
                        <span class="font-bold text-amber-400">GH₵ {{ number_format(auth()->user()->monthlySalary() * 2, 2) }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('portal.finance.advances.store') }}" class="space-y-4">
                    @csrf
                    
                    <!-- Amount -->
                    <div>
                        <x-input-label for="amount" :value="__('Loan Amount Requested (GH₵)')" />
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" :max="maxAdvance" x-model="amount" required 
                               class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-amber-500" placeholder="0.00" />
                        <p x-show="amount > maxAdvance" class="text-[10px] text-brand-red mt-1 font-semibold">
                            ⚠️ Requested amount exceeds your maximum limit of GH₵ <span x-text="maxAdvance.toLocaleString()"></span>
                        </p>
                    </div>

                    <!-- Repayment Style -->
                    <div>
                        <x-input-label for="repayment_style" :value="__('Repayment Style')" />
                        <select id="repayment_style" name="repayment_style" x-model="repaymentStyle" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500">
                            <option value="monthly_deduction">Monthly Deduction (salary auto-deducted)</option>
                            <option value="pay_all_at_once">Pay All at Once (2 months no salary)</option>
                        </select>
                    </div>

                    <!-- Monthly Deduction Amount -->
                    <div x-show="repaymentStyle === 'monthly_deduction'">
                        <x-input-label for="monthly_deduction_amount" :value="__('Monthly Deduction Amount (Min: GH₵ 1,000)')" />
                        <input id="monthly_deduction_amount" name="monthly_deduction_amount" type="number" step="0.01" min="1000" :required="repaymentStyle === 'monthly_deduction'"
                               class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-amber-500" placeholder="1000.00" />
                    </div>

                    <!-- Reason -->
                    <div>
                        <x-input-label for="reason" :value="__('Reason / Justification')" />
                        <textarea id="reason" name="reason" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500" required rows="3" placeholder="Describe the purpose of the loan..."></textarea>
                    </div>

                    <button type="submit" :disabled="amount > maxAdvance" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark disabled:opacity-50 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Submit Advance Request
                    </button>
                </form>
            </div>

            <!-- Ledger/Log of requests -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📄 Salary Advance Requests Ledger</h3>
                
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                    @forelse ($advances as $advance)
                        @php
                            $statusColors = [
                                'pending_finance' => 'text-amber-400 bg-amber-400/10 border-amber-400/20',
                                'pending_cvo'     => 'text-purple-400 bg-purple-400/10 border-purple-400/20',
                                'returned_for_correction' => 'text-cyan-400 bg-cyan-400/10 border-cyan-400/20',
                                'approved'        => 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
                                'rejected'        => 'text-brand-red bg-brand-red/10 border-brand-red/20',
                            ];
                            $color = $statusColors[$advance->status] ?? 'text-brand-white/60 bg-brand-white/5';
                        @endphp
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-white/5 hover:border-amber-500/20 transition">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">GH₵ {{ number_format($advance->amount, 2) }}</p>
                                    <p class="text-xs text-brand-ash font-mono mt-0.5">{{ $advance->created_at->format('d M Y H:i') }}</p>
                                    @if ($isFinanceStaff)
                                        <p class="text-[10px] text-brand-white/60">Requested by: {{ $advance->user->name }}</p>
                                    @endif
                                </div>
                                <span class="px-2 py-0.5 rounded border text-[9px] uppercase tracking-wider font-bold {{ $color }}">
                                    {{ str_replace('_', ' ', $advance->status) }}
                                </span>
                            </div>
                            <div class="text-xs text-brand-white/80 space-y-1">
                                <p>Repayment style: <strong class="text-brand-white capitalize">{{ str_replace('_', ' ', $advance->repayment_style) }}</strong></p>
                                @if ($advance->repayment_style === 'monthly_deduction')
                                    <p>Monthly deduction: <strong class="text-brand-white">GH₵ {{ number_format($advance->monthly_deduction_amount, 2) }}</strong></p>
                                @endif
                                <div class="italic font-normal">{{ strip_tags((string) $advance->reason) }}</div>
                            </div>

                            @if ($advance->status === 'returned_for_correction' && $advance->finance_feedback)
                                <div class="mt-3 p-3 rounded-xl border border-cyan-500/20 bg-cyan-500/5 text-xs">
                                    <p class="font-bold text-cyan-400">Finance Correction Notes:</p>
                                    <p class="text-brand-white/80 mt-1">"{{ $advance->finance_feedback }}"</p>
                                    
                                    @if ($advance->user_id === auth()->id() || auth()->user()->hasRole('super_admin'))
                                        <button @click="triggerResubmit({{ json_encode($advance) }})" class="mt-3 px-3 py-1 rounded bg-cyan-500 text-brand-black text-[10px] font-bold hover:bg-cyan-400 transition uppercase tracking-wider">
                                            ✏️ Correct & Resubmit
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/30 italic text-center py-8">No salary advance requests logged.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    <!-- Alpine.js Resubmit Salary Advance Modal -->
    <div x-show="openResubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-black/80 backdrop-blur-sm" x-cloak style="display: none;">
        <div @click.away="openResubmitModal = false" class="w-full max-w-md glass-panel rounded-2xl p-6 border border-brand-white/15 shadow-2xl relative"
             x-data="{
                resubmitRepaymentStyle: 'monthly_deduction',
                resubmitAmount: '',
                salary: {{ auth()->user()->monthlySalary() }},
                get maxAdvance() { return this.salary * 2; }
             }"
             x-init="$watch('openResubmitModal', value => {
                 if (value) {
                     resubmitRepaymentStyle = resubmitAdvanceData.repayment_style || 'monthly_deduction';
                     resubmitAmount = resubmitAdvanceData.amount || '';
                 }
             })">
            <button @click="openResubmitModal = false" class="absolute top-4 right-4 text-brand-white/60 hover:text-brand-white text-lg transition-colors focus:outline-none">
                ✕
            </button>
            
            <h3 class="text-lg font-semibold text-brand-white mb-2">Correct & Resubmit Salary Advance</h3>
            <p class="text-xs text-brand-ash mb-4">Modify and correct your requested advance below to submit back to Finance.</p>

            <div class="mb-4 p-3 rounded-xl bg-brand-black/40 border border-brand-white/5 space-y-1">
                <div class="flex justify-between text-xs text-brand-white/70">
                    <span>Max Advance Allowed:</span>
                    <span class="font-bold text-amber-400">GH₵ {{ number_format(auth()->user()->monthlySalary() * 2, 2) }}</span>
                </div>
            </div>
            
            <form :action="resubmitActionUrl" method="POST" class="space-y-4">
                @csrf
                
                <!-- Amount -->
                <div>
                    <x-input-label for="resubmit_amount" :value="__('Loan Amount Requested (GH₵)')" />
                    <input id="resubmit_amount" name="amount" type="number" step="0.01" min="0.01" :max="maxAdvance" x-model="resubmitAmount" required 
                           class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-amber-500" />
                    <p x-show="resubmitAmount > maxAdvance" class="text-[10px] text-brand-red mt-1 font-semibold">⚠️ Requested amount exceeds your maximum limit of GH₵ <span x-text="maxAdvance"></span></p>
                </div>

                <!-- Repayment Style -->
                <div>
                    <x-input-label for="resubmit_repayment_style" :value="__('Repayment Style')" />
                    <select id="resubmit_repayment_style" name="repayment_style" x-model="resubmitRepaymentStyle" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
                        <option value="monthly_deduction">Monthly Deduction</option>
                        <option value="pay_all_at_once">Pay All at Once</option>
                    </select>
                </div>

                <!-- Monthly Deduction Amount -->
                <div x-show="resubmitRepaymentStyle === 'monthly_deduction'">
                    <x-input-label for="resubmit_monthly_deduction_amount" :value="__('Monthly Deduction Amount (Min: GH₵ 1,000)')" />
                    <input id="resubmit_monthly_deduction_amount" name="monthly_deduction_amount" type="number" step="0.01" min="1000" :required="resubmitRepaymentStyle === 'monthly_deduction'"
                           :value="resubmitAdvanceData.monthly_deduction_amount"
                           class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-sm focus:outline-none focus:border-amber-500" />
                </div>

                <!-- Reason -->
                <div>
                    <x-input-label for="resubmit_reason" :value="__('Reason / Justification')" />
                    <textarea id="resubmit_reason" name="reason" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500" required rows="3" :value="resubmitAdvanceData.reason"></textarea>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                    <button type="button" @click="openResubmitModal = false" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="resubmitAmount > maxAdvance" class="px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20 disabled:opacity-50">
                        Resubmit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
