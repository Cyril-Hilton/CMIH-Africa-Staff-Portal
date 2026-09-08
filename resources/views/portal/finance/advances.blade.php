<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
                <h2 class="text-3xl font-display text-brand-white">Salary Advances (Loans)</h2>
            </div>
            <a href="{{ route('portal.finance') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 transition-all hover:bg-brand-white/10">
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
        $isFinanceStaff = (bool) ($isFinance ?? false);
        $isHrManager = (bool) ($isHR ?? false) || (auth()->user() && auth()->user()->hasFullHrAccess());
        $effectiveMinimum = (float) ($salaryAdvanceMinimum ?? 500);
        $effectiveMaximum = (float) ($salaryAdvanceMaximum ?? 0);
        $defaultMinimum = (float) ($salaryAdvanceDefaultMinimum ?? 500);
        $termsNote = trim((string) ($salaryAdvanceTermsNote ?? ''));
        $financePendingCount = $advances->where('status', 'pending_finance')->count();
        $hrPendingCount = isset($pendingHrAdvances) ? $pendingHrAdvances->count() : $advances->where('status', 'pending_hr')->count();
        $cvoPendingCount = $pendingCvoAdvances->count();
        $repaymentActiveCount = $advances->whereIn('status', ['repayment_active', 'approved'])->count();
        $statusColors = [
            'pending_hr' => 'text-sky-400 bg-sky-400/10 border-sky-400/20',
            'pending_finance' => 'text-amber-400 bg-amber-400/10 border-amber-400/20',
            'pending_cvo' => 'text-purple-400 bg-purple-400/10 border-purple-400/20',
            'returned_for_correction' => 'text-cyan-400 bg-cyan-400/10 border-cyan-400/20',
            'approved' => 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
            'repayment_active' => 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
            'fully_paid' => 'text-green-400 bg-green-400/10 border-green-400/20',
            'rejected' => 'text-brand-red bg-brand-red/10 border-brand-red/20',
        ];
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

        @if ($isHrManager && $hrPendingCount > 0)
            <div class="glass-panel rounded-2xl border border-sky-500/20 bg-sky-500/5 p-6">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-widest text-sky-400">HR Terms Approval Queue</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach (($pendingHrAdvances ?? $advances->where('status', 'pending_hr')) as $loan)
                        @php
                            $staffMinimum = \App\Support\SalaryAdvancePolicy::effectiveMonthlyDeductionMinimum($loan->user);
                            $staffMaximum = \App\Support\SalaryAdvancePolicy::effectiveMaximumAmount($loan->user);
                        @endphp
                        <div class="space-y-3 rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $loan->user?->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $loan->user?->department ?? '')) }}</p>
                                </div>
                                <span class="text-xs font-bold text-sky-400">GHC {{ number_format($loan->amount, 2) }}</span>
                            </div>
                            <p class="text-xs italic leading-relaxed text-brand-white/80">"{{ $loan->reason }}"</p>
                            <div class="space-y-1 text-[11px] text-brand-white/60">
                                <p>Repayment style: <strong class="capitalize">{{ str_replace('_', ' ', $loan->repayment_style) }}</strong></p>
                                @if ($loan->repayment_style === 'monthly_deduction')
                                    <p>Requested monthly deduction: <strong>GHC {{ number_format($loan->monthly_deduction_amount, 2) }}</strong></p>
                                    <p>Staff effective minimum: <strong>GHC {{ number_format($staffMinimum, 2) }}</strong></p>
                                @endif
                                <p>Staff max payout cap: <strong>GHC {{ number_format($staffMaximum, 2) }}</strong></p>
                            </div>

                            <div class="space-y-3 border-t border-brand-white/5 pt-2">
                                <form method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}" class="space-y-2 rounded-lg border border-sky-500/10 bg-sky-500/5 p-3">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    @if($loan->repayment_style === 'monthly_deduction')
                                        <div>
                                            <label class="block text-[9px] uppercase tracking-wider text-brand-white/50 mb-0.5">Approved Monthly Deduction</label>
                                            <input name="approved_monthly_deduction_amount" type="number" step="0.01" min="{{ number_format($staffMinimum, 2, '.', '') }}" value="{{ old('approved_monthly_deduction_amount', number_format((float) $loan->monthly_deduction_amount, 2, '.', '')) }}" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-1.5 text-[10px] text-brand-white focus:border-sky-400 focus:ring-0">
                                        </div>
                                    @endif
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[9px] uppercase tracking-wider text-brand-white/50 mb-0.5">Start Date</label>
                                            <input name="repayment_start_date" type="date" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2 py-1.5 text-[10px] text-brand-white focus:border-sky-400 focus:ring-0">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] uppercase tracking-wider text-brand-white/50 mb-0.5">Duration</label>
                                            <input name="repayment_months" type="number" min="1" max="120" placeholder="Months" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2 py-1.5 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-sky-400 focus:ring-0">
                                        </div>
                                    </div>
                                    <input name="feedback" type="text" placeholder="HR approval note (optional)" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-1.5 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-sky-400 focus:ring-0">
                                    <button type="submit" class="w-full rounded bg-emerald-500/20 px-2.5 py-2 text-[10px] font-bold uppercase text-emerald-300 hover:bg-emerald-500/35">
                                        Approve Terms & Send to Finance
                                    </button>
                                </form>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="const note = prompt('Enter correction reason for staff:'); if (note) { const f = document.getElementById('hr-corr-form-{{ $loan->id }}'); f.feedback.value = note; f.submit(); }" class="rounded bg-cyan-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-cyan-300 hover:bg-cyan-500/35">
                                        Send Back
                                    </button>
                                    <form id="hr-corr-form-{{ $loan->id }}" method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}" class="hidden">
                                        @csrf
                                        <input type="hidden" name="action" value="correction">
                                        <input type="hidden" name="feedback" value="">
                                    </form>
                                    <form method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="feedback" value="Rejected by HR Manager">
                                        <button type="submit" class="rounded bg-brand-red/20 px-2.5 py-1 text-[10px] font-bold uppercase text-brand-red hover:bg-brand-red/35">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($isFinanceStaff && $financePendingCount > 0)
            <div class="glass-panel rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-widest text-amber-400">Finance Payment Processing Queue</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($advances->where('status', 'pending_finance') as $item)
                        <div class="space-y-3 rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $item->user->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $item->user->department)) }}</p>
                                </div>
                                <span class="text-xs font-bold text-amber-400">GHC {{ number_format($item->amount, 2) }}</span>
                            </div>
                            <p class="text-xs italic leading-relaxed text-brand-white/80">"{{ $item->reason }}"</p>
                            <div class="space-y-1 text-[11px] text-brand-white/60">
                                <p>Repayment: <strong class="capitalize">{{ str_replace('_', ' ', $item->repayment_style) }}</strong></p>
                                @if ($item->repayment_style === 'monthly_deduction')
                                    <p>Requested deduction: <strong>GHC {{ number_format($item->monthly_deduction_amount, 2) }}</strong></p>
                                    <p>HR approved: <strong>GHC {{ number_format($item->approvedMonthlyDeduction() ?? $item->monthly_deduction_amount, 2) }}</strong></p>
                                @else
                                    <p>Payback: <strong>All at once</strong></p>
                                @endif
                                @if ($item->hrReviewer)
                                    <p>HR reviewed by: <strong>{{ $item->hrReviewer->name }}</strong></p>
                                @endif
                            </div>

                            <div class="space-y-3 border-t border-brand-white/5 pt-2">
                                <form method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}" class="space-y-2 rounded-lg border border-emerald-500/10 bg-emerald-500/5 p-3">
                                    @csrf
                                    <input type="hidden" name="action" value="approve_and_disburse">
                                    @if ($item->repayment_style === 'monthly_deduction')
                                        <input name="approved_monthly_deduction_amount" type="number" step="0.01" min="0.01" value="{{ number_format($item->approvedMonthlyDeduction() ?? $item->monthly_deduction_amount, 2, '.', '') }}" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white focus:border-emerald-400 focus:ring-0">
                                    @endif
                                    <div class="grid grid-cols-2 gap-2">
                                        <input name="disbursed_amount" type="number" step="0.01" min="0.01" max="{{ number_format($item->amount, 2, '.', '') }}" value="{{ number_format($item->amount, 2, '.', '') }}" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white focus:border-emerald-400 focus:ring-0">
                                        <input name="repayment_start_date" type="date" value="{{ optional($item->repayment_start_date)->format('Y-m-d') }}" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white focus:border-emerald-400 focus:ring-0">
                                    </div>
                                    <button type="submit" class="w-full rounded bg-emerald-500/20 px-2.5 py-2 text-[10px] font-bold uppercase text-emerald-300 hover:bg-emerald-500/35">
                                        Approve & Mark Paid Out
                                    </button>
                                </form>

                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="rounded bg-purple-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-purple-300 hover:bg-purple-500/35">
                                            Escalate to CVO
                                        </button>
                                    </form>
                                    <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('ret-form-{{ $item->id }}'); f.feedback.value = note; f.submit(); }" class="rounded bg-cyan-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-cyan-300 hover:bg-cyan-500/35">
                                        Return for Correction
                                    </button>
                                    <form id="ret-form-{{ $item->id }}" method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}" class="hidden">
                                        @csrf
                                        <input type="hidden" name="action" value="correction">
                                        <input type="hidden" name="feedback" value="">
                                    </form>
                                    <form method="POST" action="{{ route('portal.finance.advances.finance-action', $item) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="rounded bg-brand-red/20 px-2.5 py-1 text-[10px] font-bold uppercase text-brand-red hover:bg-brand-red/35">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($isCVO && $cvoPendingCount > 0)
            <div class="glass-panel rounded-2xl border border-purple-500/20 bg-purple-500/5 p-6">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-widest text-purple-400">CVO / Executive Approval Queue</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pendingCvoAdvances as $item)
                        <div class="space-y-3 rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $item->user->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $item->user->department)) }}</p>
                                </div>
                                <span class="text-xs font-bold text-purple-400">GHC {{ number_format($item->amount, 2) }}</span>
                            </div>
                            <p class="text-xs italic leading-relaxed text-brand-white/80">"{{ $item->reason }}"</p>
                            <p class="text-[10px] font-semibold text-emerald-400">Verified by Finance Department</p>

                            <div class="flex flex-wrap gap-2 border-t border-brand-white/5 pt-2">
                                <form method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="rounded bg-purple-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-purple-300 hover:bg-purple-500/35">
                                        Approve Loan
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('portal.finance.advances.cvo-action', $item) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="return_to_finance">
                                    <button type="submit" class="rounded bg-cyan-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-cyan-300 hover:bg-cyan-500/35">
                                        Return to Finance
                                    </button>
                                </form>
                                <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('cvo-ret-adv-{{ $item->id }}'); f.feedback.value = note; f.submit(); }" class="rounded bg-amber-500/20 px-2.5 py-1 text-[10px] font-bold uppercase text-amber-300 hover:bg-amber-500/35">
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
                                    <button type="submit" class="rounded bg-brand-red/20 px-2.5 py-1 text-[10px] font-bold uppercase text-brand-red hover:bg-brand-red/35">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($isFinanceStaff && $repaymentActiveCount > 0)
            <div class="glass-panel rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-6">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-widest text-emerald-400">Repayment Collection Tracker</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($advances->whereIn('status', ['repayment_active', 'approved']) as $item)
                        @php
                            $paid = $item->totalRepaid();
                            $balance = $item->balance();
                        @endphp
                        <div class="space-y-3 rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">{{ $item->user->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ ucwords(str_replace('_', ' ', $item->user->department)) }}</p>
                                </div>
                                <span class="text-xs font-bold text-emerald-400">GHC {{ number_format($balance, 2) }} left</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.03] p-2">
                                    <p class="text-brand-white/45">Paid</p>
                                    <p class="font-mono text-emerald-300">GHC {{ number_format($paid, 2) }}</p>
                                </div>
                                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.03] p-2">
                                    <p class="text-brand-white/45">Loan</p>
                                    <p class="font-mono text-brand-white">GHC {{ number_format($item->amount, 2) }}</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('portal.finance.advances.repayments.store', $item) }}" class="space-y-2 border-t border-brand-white/5 pt-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ number_format($balance, 2, '.', '') }}" required placeholder="Amount" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-emerald-400 focus:ring-0">
                                    <input name="payment_date" type="date" required value="{{ now()->toDateString() }}" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white focus:border-emerald-400 focus:ring-0">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="payment_method" type="text" placeholder="Method" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-emerald-400 focus:ring-0">
                                    <input name="reference" type="text" placeholder="Reference" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-emerald-400 focus:ring-0">
                                </div>
                                <input name="notes" type="text" placeholder="Notes, optional" class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-emerald-400 focus:ring-0">
                                <button type="submit" class="w-full rounded bg-emerald-500/20 px-2.5 py-2 text-[10px] font-bold uppercase text-emerald-300 hover:bg-emerald-500/35">
                                    Record Repayment
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-6">
            <div class="glass-panel h-fit rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6"
                 x-data="{
                    repaymentStyle: 'monthly_deduction',
                    amount: '',
                    maxAdvance: {{ number_format($effectiveMaximum, 2, '.', '') }}
                 }">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Request Salary Advance</h3>
                    @if($termsNote)
                        <p class="mt-1 text-[11px] text-brand-white/50">{{ $termsNote }}</p>
                    @endif
                </div>

                <div class="mb-4 space-y-1 rounded-xl border border-brand-white/5 bg-brand-black/40 p-3.5">
                    <div class="flex justify-between text-xs text-brand-white/60">
                        <span>Your Monthly Salary:</span>
                        <span class="font-bold text-brand-white">GHC {{ number_format(auth()->user()->monthlySalary(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-brand-white/60">
                        <span>Max Advance Allowed:</span>
                        <span class="font-bold text-amber-400">GHC {{ number_format($effectiveMaximum, 2) }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('portal.finance.advances.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('Loan Amount Requested (GHC)')" />
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" :max="maxAdvance" x-model="amount" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none" placeholder="0.00">
                        <p x-show="amount > maxAdvance" class="mt-1 text-[10px] font-semibold text-brand-red">
                            Requested amount exceeds your maximum limit of GHC <span x-text="maxAdvance.toLocaleString()"></span>
                        </p>
                    </div>

                    <div>
                        <x-input-label for="repayment_style" :value="__('Repayment Style')" />
                        <select id="repayment_style" name="repayment_style" x-model="repaymentStyle" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                            <option value="monthly_deduction">Monthly Deduction (salary auto-deducted)</option>
                            <option value="pay_all_at_once">Pay All at Once</option>
                        </select>
                    </div>

                    <div x-show="repaymentStyle === 'monthly_deduction'">
                        <x-input-label for="monthly_deduction_amount" :value="__('Monthly Deduction Amount')" />
                        <input id="monthly_deduction_amount" name="monthly_deduction_amount" type="number" step="0.01" min="{{ number_format($effectiveMinimum, 2, '.', '') }}" :required="repaymentStyle === 'monthly_deduction'" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none" placeholder="{{ number_format($effectiveMinimum, 2, '.', '') }}">
                        <p class="mt-1 text-[10px] text-brand-white/45">Minimum for your current HR terms: GHC {{ number_format($effectiveMinimum, 2) }}{{ $effectiveMinimum !== $defaultMinimum ? ' (staff-specific agreement)' : '' }}.</p>
                    </div>

                    <div>
                        <x-input-label for="reason" :value="__('Reason / Justification')" />
                        <textarea id="reason" name="reason" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none" required rows="3" placeholder="Describe the purpose of the loan..."></textarea>
                    </div>

                    <button type="submit" :disabled="amount > maxAdvance" class="w-full rounded-xl bg-brand-red py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition-all hover:bg-brand-red-dark disabled:opacity-50">
                        Submit Advance Request
                    </button>
                </form>
            </div>

            <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Salary Advance Requests Ledger</h3>
                <div class="max-h-[560px] space-y-4 overflow-y-auto pr-1">
                    @forelse ($advances as $advance)
                        @php
                            $color = $statusColors[$advance->status] ?? 'text-brand-white/60 bg-brand-white/5';
                            $paid = $advance->totalRepaid();
                            $balance = $advance->balance();
                        @endphp
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 transition hover:border-amber-500/20">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-brand-white">GHC {{ number_format($advance->amount, 2) }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-brand-ash">{{ $advance->created_at->format('d M Y H:i') }}</p>
                                    @if ($isFinanceStaff)
                                        <p class="text-[10px] text-brand-white/60">Requested by: {{ $advance->user->name }}</p>
                                    @endif
                                </div>
                                <span class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $color }}">
                                    {{ str_replace('_', ' ', $advance->status) }}
                                </span>
                            </div>
                            <div class="space-y-1 text-xs text-brand-white/80">
                                <p>Repayment style: <strong class="capitalize text-brand-white">{{ str_replace('_', ' ', $advance->repayment_style) }}</strong></p>
                                @if ($advance->repayment_style === 'monthly_deduction')
                                    <p>Requested monthly deduction: <strong class="text-brand-white">GHC {{ number_format($advance->monthly_deduction_amount, 2) }}</strong></p>
                                    @if($advance->approvedMonthlyDeduction())
                                        <p>Approved monthly deduction: <strong class="text-emerald-300">GHC {{ number_format($advance->approvedMonthlyDeduction(), 2) }}</strong></p>
                                    @endif
                                @endif
                                @if(in_array($advance->status, ['repayment_active', 'fully_paid', 'approved'], true))
                                    <p>Paid back: <strong class="text-emerald-300">GHC {{ number_format($paid, 2) }}</strong> / Balance: <strong class="text-brand-white">GHC {{ number_format($balance, 2) }}</strong></p>
                                @endif
                                <div class="italic font-normal">{{ strip_tags((string) $advance->reason) }}</div>
                            </div>

                            @if ($advance->hr_feedback || $advance->finance_feedback)
                                <div class="mt-3 rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-xs">
                                    @if ($advance->hr_feedback)
                                        <p class="font-bold text-sky-300">HR Notes:</p>
                                        <p class="mt-1 text-brand-white/80">"{{ $advance->hr_feedback }}"</p>
                                    @endif
                                    @if ($advance->finance_feedback)
                                        <p class="mt-2 font-bold text-amber-300">Finance/CVO Notes:</p>
                                        <p class="mt-1 text-brand-white/80">"{{ $advance->finance_feedback }}"</p>
                                    @endif
                                </div>
                            @endif

                            @if ($advance->status === 'returned_for_correction' && ($advance->user_id === auth()->id() || auth()->user()->hasRole('super_admin')))
                                <button @click="triggerResubmit({{ json_encode($advance) }})" class="mt-3 rounded bg-cyan-500 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-black transition hover:bg-cyan-400">
                                    Correct & Resubmit
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-xs italic text-brand-white/30">No salary advance requests logged.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div x-show="openResubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-brand-black/80 p-4 backdrop-blur-sm" x-cloak style="display: none;">
        <div @click.away="openResubmitModal = false" class="glass-panel relative w-full max-w-md rounded-2xl border border-brand-white/15 p-6 shadow-2xl"
             x-data="{
                resubmitRepaymentStyle: 'monthly_deduction',
                resubmitAmount: '',
                maxAdvance: {{ number_format($effectiveMaximum, 2, '.', '') }}
             }"
             x-init="$watch('openResubmitModal', value => {
                 if (value) {
                     resubmitRepaymentStyle = resubmitAdvanceData.repayment_style || 'monthly_deduction';
                     resubmitAmount = resubmitAdvanceData.amount || '';
                 }
             })">
            <button @click="openResubmitModal = false" class="absolute right-4 top-4 text-lg text-brand-white/60 transition-colors hover:text-brand-white focus:outline-none">
                x
            </button>

            <h3 class="mb-2 text-lg font-semibold text-brand-white">Correct & Resubmit Salary Advance</h3>
            <p class="mb-4 text-xs text-brand-ash">Modify your requested advance and send it back to HR for review.</p>

            <div class="mb-4 space-y-1 rounded-xl border border-brand-white/5 bg-brand-black/40 p-3">
                <div class="flex justify-between text-xs text-brand-white/70">
                    <span>Max Advance Allowed:</span>
                    <span class="font-bold text-amber-400">GHC {{ number_format($effectiveMaximum, 2) }}</span>
                </div>
            </div>

            <form :action="resubmitActionUrl" method="POST" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="resubmit_amount" :value="__('Loan Amount Requested (GHC)')" />
                    <input id="resubmit_amount" name="amount" type="number" step="0.01" min="0.01" :max="maxAdvance" x-model="resubmitAmount" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                    <p x-show="resubmitAmount > maxAdvance" class="mt-1 text-[10px] font-semibold text-brand-red">Requested amount exceeds your maximum limit of GHC <span x-text="maxAdvance"></span></p>
                </div>

                <div>
                    <x-input-label for="resubmit_repayment_style" :value="__('Repayment Style')" />
                    <select id="resubmit_repayment_style" name="repayment_style" x-model="resubmitRepaymentStyle" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                        <option value="monthly_deduction">Monthly Deduction</option>
                        <option value="pay_all_at_once">Pay All at Once</option>
                    </select>
                </div>

                <div x-show="resubmitRepaymentStyle === 'monthly_deduction'">
                    <x-input-label for="resubmit_monthly_deduction_amount" :value="__('Monthly Deduction Amount')" />
                    <input id="resubmit_monthly_deduction_amount" name="monthly_deduction_amount" type="number" step="0.01" min="{{ number_format($effectiveMinimum, 2, '.', '') }}" :required="resubmitRepaymentStyle === 'monthly_deduction'" :value="resubmitAdvanceData.monthly_deduction_amount" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                    <p class="mt-1 text-[10px] text-brand-white/45">Minimum for your current HR terms: GHC {{ number_format($effectiveMinimum, 2) }}.</p>
                </div>

                <div>
                    <x-input-label for="resubmit_reason" :value="__('Reason / Justification')" />
                    <textarea id="resubmit_reason" name="reason" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none" required rows="3" x-text="resubmitAdvanceData.reason"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-brand-white/10 pt-4">
                    <button type="button" @click="openResubmitModal = false" class="rounded-xl bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-wider text-brand-white/60 transition hover:bg-brand-white/10 hover:text-brand-white">
                        Cancel
                    </button>
                    <button type="submit" :disabled="resubmitAmount > maxAdvance" class="rounded-xl bg-amber-500 px-5 py-2 text-xs font-bold uppercase tracking-wider text-brand-black shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 disabled:opacity-50">
                        Resubmit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
