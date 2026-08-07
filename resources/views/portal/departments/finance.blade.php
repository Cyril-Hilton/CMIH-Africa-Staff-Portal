<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
                <h2 class="text-3xl font-display text-brand-white">💸 Reimbursements & Claims</h2>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('portal.finance.budgets.index') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">
                    📊 Project Budgets
                </a>
                <a href="{{ route('portal.finance.advances.index') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">
                    💰 Salary Advances
                </a>
            </div>
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
        $user = $user ?? auth()->user();
        $isFinanceUser = strtolower(trim($user->department ?? '')) === 'finance'
            || ($user && $user->access_role === 'super_admin');
    @endphp

    <div x-data="{ 
        openResubmitClaimModal: false,
        resubmitClaimData: {},
        resubmitClaimActionUrl: '',
        triggerResubmitClaim(claim) {
            this.resubmitClaimData = Object.assign({}, claim);
            this.resubmitClaimActionUrl = '{{ url('/portal/finance/claims') }}/' + claim.id + '/resubmit';
            this.openResubmitClaimModal = true;
        },
        openResubmitInvoiceModal: false,
        resubmitInvoiceData: {},
        resubmitInvoiceActionUrl: '',
        triggerResubmitInvoice(inv) {
            this.resubmitInvoiceData = Object.assign({}, inv);
            this.resubmitInvoiceActionUrl = '{{ url('/portal/finance/invoices') }}/' + inv.id + '/resubmit';
            this.openResubmitInvoiceModal = true;
        }
    }" class="space-y-6">
        
        <!-- CLAIMS & INVOICES -->
        <div class="space-y-6">
            <!-- Submit Claim Widget -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">💸 New Reimbursement Claim</h3>
                <form method="POST" action="{{ route('portal.finance.claims.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="claim_amount" :value="__('Amount')" />
                            <x-text-input id="claim_amount" name="amount" type="number" step="0.01" min="0.01" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="150.00" />
                        </div>
                        <div>
                            <x-input-label for="claim_currency" :value="__('Currency')" />
                            <select id="claim_currency" name="currency" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white">
                                <option value="GH₵" selected>GH₵ – Ghana Cedi</option>
                                <option value="USD">USD – US Dollar</option>
                                <option value="EUR">EUR – Euro</option>
                                <option value="GBP">GBP – British Pound</option>
                                <option value="NGN">NGN – Nigerian Naira</option>
                                <option value="SLE">SLE – Sierra Leonean Leone</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="claim_desc" :value="__('Description / Justification')" />
                        <textarea id="claim_desc" name="description" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="State expense purpose..." required></textarea>
                    </div>
                    <div>
                        <x-input-label for="claim_receipt" :value="__('Receipt Upload (Image or PDF)')" />
                        <input id="claim_receipt" name="receipt" type="file" accept="image/*,application/pdf" class="mt-1 w-full text-xs text-brand-ash file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                    </div>
                    <div>
                        <x-input-label for="claim_submit_to" :value="__('Submit To')" />
                        <select id="claim_submit_to" name="submit_to" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white">
                            <option value="finance">Finance Department (for verification first)</option>
                            <option value="cvo" selected>Chief Visionary Officer (directly)</option>
                        </select>
                    </div>
                    <p class="text-[10px] text-amber-400/70 flex items-center gap-1">⚠️ All claims require CVO approval before Finance can pay them.</p>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Submit Expense Claim
                    </button>
                </form>
            </div>

            <!-- Claims Audit Board -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">📈 Expense Claim Ledger</h3>
                    <a href="{{ route('portal.export', ['table' => 'petty_cash_claims']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        📤 Export Ledger
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="py-3">Staff</th>
                                <th class="py-3">Amount</th>
                                <th class="py-3">Justification</th>
                                <th class="py-3">Receipt</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($claims as $claim)
                                <tr>
                                    <td class="py-4 font-semibold text-brand-white">{{ $claim->user?->name ?? 'Staff' }}</td>
                                    <td class="py-4 text-emerald-400 font-bold">{{ ($claim->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($claim->currency ?? 'GH₵') }} {{ number_format($claim->amount, 2) }}</td>
                                    <td class="py-4 min-w-[250px]">
                                        {{ strip_tags((string) $claim->description) }}
                                        @if($claim->status === 'Returned for Correction' && $claim->notes)
                                            <div class="text-[10px] text-cyan-400 mt-1 font-semibold border-l border-cyan-500/30 pl-2">
                                                Correction reason: "{{ $claim->notes }}"
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        @if($claim->receipt_path)
                                            <a href="{{ route('portal.finance.claims.receipt', $claim) }}" target="_blank" class="text-sky-400 underline font-semibold">View File</a>
                                        @else
                                            <span class="text-brand-white/30 italic">None</span>
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        @php
                                            $badges = [
                                                'Pending'      => 'bg-amber-500/10 border-amber-500/20 text-amber-400',
                                                'CVO Approved' => 'bg-purple-500/10 border-purple-500/20 text-purple-400',
                                                'Flagged'      => 'bg-brand-red/10 border-brand-red/20 text-brand-red',
                                                'Paid'         => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
                                                'Rejected'     => 'bg-slate-500/10 border-slate-500/20 text-slate-400',
                                                'Returned for Correction' => 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400',
                                                'Updated'      => 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400',
                                            ];
                                            $badgeClass = $badges[$claim->status] ?? 'bg-white/10 border-white/20 text-white';
                                        @endphp
                                        <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase font-bold {{ $badgeClass }}">
                                            {{ $claim->status }}
                                        </span>
                                    </td>
                                    <td class="py-4 text-right space-x-1">
                                        @if($claim->status === 'Pending' || $claim->status === 'Updated')
                                            <span class="text-[9px] text-amber-400/80 italic">⏳ Awaiting CVO</span>
                                        @elseif($claim->status === 'CVO Approved' && $isFinanceUser)
                                            <form method="POST" action="{{ route('portal.finance.claims.action', [$claim, 'pay']) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded bg-emerald-500/20 hover:bg-emerald-500/40 px-2.5 py-1 text-[9px] uppercase font-semibold text-emerald-300">Pay Claim</button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.finance.claims.action', [$claim, 'flag']) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded bg-brand-red/20 hover:bg-brand-red/40 px-2.5 py-1 text-[9px] uppercase font-semibold text-brand-red">Flag</button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.finance.claims.action', [$claim, 'reject']) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded bg-slate-500/20 hover:bg-slate-500/40 px-2.5 py-1 text-[9px] uppercase font-semibold text-slate-400">Reject</button>
                                            </form>
                                            <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('ret-claim-{{ $claim->id }}'); f.notes.value = note; f.submit(); }"
                                                    class="rounded bg-cyan-500/20 hover:bg-cyan-500/40 px-2.5 py-1 text-[9px] uppercase font-semibold text-cyan-300">Return</button>
                                            <form id="ret-claim-{{ $claim->id }}" method="POST" action="{{ route('portal.finance.claims.action', [$claim, 'return']) }}" class="hidden">
                                                @csrf
                                                <input type="hidden" name="notes" value="">
                                            </form>
                                        @elseif($claim->status === 'Returned for Correction' && ($claim->user_id === auth()->id() || auth()->user()->hasRole('super_admin')))
                                            <button type="button" @click="triggerResubmitClaim({{ json_encode($claim) }})"
                                                    class="rounded bg-cyan-500 text-brand-black px-2.5 py-1 text-[9px] uppercase font-bold hover:bg-cyan-400 transition">Correct</button>
                                        @else
                                            <span class="text-[10px] text-brand-white/40 italic">Closed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-xs text-brand-white/40 italic">No expense claims logged.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($claims instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $claims->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Supplier Invoicing -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="mb-5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">🧾 Supplier Invoice Ingestion</h3>
            </div>
            <div class="space-y-6">
                <!-- Submit Invoice -->
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 h-fit">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-3">Submit Invoice</h4>
                    <form method="POST" action="{{ route('portal.finance.invoices.store') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Supplier Name *</label>
                            <input type="text" name="supplier_name" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Invoice #</label>
                            <input type="text" name="invoice_number" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="INV-001">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Amount *</label>
                                <input type="number" name="amount" required min="0.01" step="0.01" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Currency</label>
                                <select name="currency" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white">
                                    <option value="GH₵" selected>GH₵</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                    <option value="NGN">NGN</option>
                                    <option value="SLE">SLE</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Description *</label>
                            <textarea name="description" required class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30" rows="3" placeholder="What is this invoice for?"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Invoice Attachment (PDF/Image) *</label>
                            <input type="file" name="attachment" required accept="image/*,application/pdf" class="w-full text-xs text-brand-ash file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Submit To *</label>
                            <select name="submit_to" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white">
                                <option value="finance">Finance Department (for verification first)</option>
                                <option value="cvo" selected>Chief Visionary Officer (directly)</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Submit Invoice
                        </button>
                    </form>
                </div>

                <!-- Invoice ledger -->
                <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 p-5">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-3">Invoice Ledger</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-2.5">Supplier</th>
                                    <th class="py-2.5">Invoice #</th>
                                    <th class="py-2.5">Amount</th>
                                    <th class="py-2.5">File</th>
                                    <th class="py-2.5">Status</th>
                                    <th class="py-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse ($invoices as $inv)
                                    <tr>
                                        <td class="py-3 font-semibold text-brand-white">
                                            {{ $inv->supplier_name }}
                                            @if($inv->status === 'Returned for Correction' && $inv->notes)
                                                <div class="text-[10px] text-cyan-400 mt-1 font-semibold border-l border-cyan-500/30 pl-2">
                                                    Correction reason: "{{ $inv->notes }}"
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3">{{ $inv->invoice_number ?? '—' }}</td>
                                        <td class="py-3 text-emerald-400 font-bold">{{ $inv->currency }} {{ number_format($inv->amount, 2) }}</td>
                                        <td class="py-3">
                                            @if($inv->attachment_path)
                                                <a href="{{ route('portal.finance.invoices.attachment', $inv) }}" target="_blank" class="text-sky-400 underline font-semibold">View File</a>
                                            @else
                                                <span class="text-brand-white/30 italic">None</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            @php
                                                $badges = [
                                                    'Pending'      => 'bg-amber-500/10 border-amber-500/20 text-amber-400',
                                                    'CVO Approved' => 'bg-purple-500/10 border-purple-500/20 text-purple-400',
                                                    'Paid'         => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
                                                    'Rejected'     => 'bg-slate-500/10 border-slate-500/20 text-slate-400',
                                                    'Returned for Correction' => 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400',
                                                    'Updated'      => 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400',
                                                ];
                                                $cls = $badges[$inv->status] ?? 'bg-white/10 border-white/20 text-white';
                                            @endphp
                                            <span class="inline-block rounded-full border px-2 py-0.5 text-[9px] uppercase font-bold {{ $cls }}">
                                                {{ $inv->status }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right space-x-1">
                                            @if ($inv->status === 'Pending' || $inv->status === 'Updated')
                                                <span class="text-[9px] text-amber-400/80 italic">⏳ Awaiting CVO</span>
                                            @elseif ($inv->status === 'CVO Approved' && $isFinanceUser)
                                                <form method="POST" action="{{ route('portal.finance.invoices.action', [$inv, 'pay']) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="rounded bg-emerald-500/20 hover:bg-emerald-500/40 px-2 py-1 text-[9px] uppercase font-bold text-emerald-400">Pay</button>
                                                </form>
                                                <form method="POST" action="{{ route('portal.finance.invoices.action', [$inv, 'reject']) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="rounded bg-brand-red/20 hover:bg-brand-red/40 px-2 py-1 text-[9px] uppercase font-bold text-brand-red">Reject</button>
                                                </form>
                                                <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('ret-inv-{{ $inv->id }}'); f.notes.value = note; f.submit(); }"
                                                        class="rounded bg-cyan-500/20 hover:bg-cyan-500/40 px-2 py-1 text-[9px] uppercase font-bold text-cyan-300">Return</button>
                                                <form id="ret-inv-{{ $inv->id }}" method="POST" action="{{ route('portal.finance.invoices.action', [$inv, 'return']) }}" class="hidden">
                                                    @csrf
                                                    <input type="hidden" name="notes" value="">
                                                </form>
                                            @elseif ($inv->status === 'Returned for Correction' && ($inv->submitted_by === auth()->id() || auth()->user()->hasRole('super_admin')))
                                                <button type="button" @click="triggerResubmitInvoice({{ json_encode($inv) }})"
                                                        class="rounded bg-cyan-500 text-brand-black px-2 py-1 text-[9px] uppercase font-bold hover:bg-cyan-400 transition">Correct</button>
                                            @else
                                                <span class="text-[10px] text-brand-white/40 italic">Closed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-xs text-brand-white/40 italic">No invoices submitted.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Alpine.js Resubmit Claim Modal -->
    <div x-show="openResubmitClaimModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-black/80 backdrop-blur-sm" x-cloak style="display: none;">
        <div @click.away="openResubmitClaimModal = false" class="w-full max-w-md glass-panel rounded-2xl p-6 border border-brand-white/15 shadow-2xl relative"
             x-init="$watch('openResubmitClaimModal', value => {
                 if (value) {
                     document.getElementById('resubmit_claim_amount').value = resubmitClaimData.amount || '';
                     document.getElementById('resubmit_claim_currency').value = resubmitClaimData.currency || 'GH₵';
                     document.getElementById('resubmit_claim_desc').value = resubmitClaimData.description || '';
                 }
             })">
            <button @click="openResubmitClaimModal = false" class="absolute top-4 right-4 text-brand-white/60 hover:text-brand-white text-lg transition-colors focus:outline-none">
                ✕
            </button>
            
            <h3 class="text-lg font-semibold text-brand-white mb-2">Correct & Resubmit Reimbursement Claim</h3>
            <p class="text-xs text-brand-ash mb-4">Modify and correct your reimbursement claim details below.</p>

            <form :action="resubmitClaimActionUrl" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="resubmit_claim_amount" :value="__('Amount')" />
                        <x-text-input id="resubmit_claim_amount" name="amount" type="number" step="0.01" min="0.01" required class="mt-1 w-full" />
                    </div>
                    <div>
                        <x-input-label for="resubmit_claim_currency" :value="__('Currency')" />
                        <select id="resubmit_claim_currency" name="currency" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white">
                            <option value="GH₵">GH₵ – Ghana Cedi</option>
                            <option value="USD">USD – US Dollar</option>
                            <option value="EUR">EUR – Euro</option>
                            <option value="GBP">GBP – British Pound</option>
                            <option value="NGN">NGN – Nigerian Naira</option>
                            <option value="SLE">SLE – Sierra Leonean Leone</option>
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="resubmit_claim_desc" :value="__('Description / Justification')" />
                    <textarea id="resubmit_claim_desc" name="description" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500 placeholder-brand-white/30" required rows="3"></textarea>
                </div>

                <div>
                    <x-input-label for="resubmit_claim_receipt" :value="__('New Receipt (optional)')" />
                    <input id="resubmit_claim_receipt" name="receipt" type="file" accept="image/*,application/pdf" class="mt-1 w-full text-xs text-brand-ash file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                </div>

                <div>
                    <x-input-label for="resubmit_claim_submit_to" :value="__('Submit To')" />
                    <select id="resubmit_claim_submit_to" name="submit_to" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500">
                        <option value="finance">Finance Department (for verification first)</option>
                        <option value="cvo" selected>Chief Visionary Officer (directly)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                    <button type="button" @click="openResubmitClaimModal = false" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                        Resubmit Claim
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alpine.js Resubmit Invoice Modal -->
    <div x-show="openResubmitInvoiceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-black/80 backdrop-blur-sm" x-cloak style="display: none;">
        <div @click.away="openResubmitInvoiceModal = false" class="w-full max-w-md glass-panel rounded-2xl p-6 border border-brand-white/15 shadow-2xl relative"
             x-init="$watch('openResubmitInvoiceModal', value => {
                 if (value) {
                     document.getElementById('resubmit_inv_supplier').value = resubmitInvoiceData.supplier_name || '';
                     document.getElementById('resubmit_inv_number').value = resubmitInvoiceData.invoice_number || '';
                     document.getElementById('resubmit_inv_amount').value = resubmitInvoiceData.amount || '';
                     document.getElementById('resubmit_inv_currency').value = resubmitInvoiceData.currency || 'GH₵';
                     document.getElementById('resubmit_inv_desc').value = resubmitInvoiceData.description || '';
                 }
             })">
            <button @click="openResubmitInvoiceModal = false" class="absolute top-4 right-4 text-brand-white/60 hover:text-brand-white text-lg transition-colors focus:outline-none">
                ✕
            </button>
            
            <h3 class="text-lg font-semibold text-brand-white mb-2">Correct & Resubmit Supplier Invoice</h3>
            <p class="text-xs text-brand-ash mb-4">Modify and correct your supplier invoice details below.</p>

            <form :action="resubmitInvoiceActionUrl" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Supplier Name *</label>
                    <input type="text" id="resubmit_inv_supplier" name="supplier_name" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Invoice #</label>
                    <input type="text" id="resubmit_inv_number" name="invoice_number" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500" placeholder="INV-001">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Amount *</label>
                        <input type="number" id="resubmit_inv_amount" name="amount" required min="0.01" step="0.01" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Currency</label>
                        <select id="resubmit_inv_currency" name="currency" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500">
                            <option value="GH₵">GH₵</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="NGN">NGN</option>
                            <option value="SLE">SLE</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Description *</label>
                    <textarea id="resubmit_inv_desc" name="description" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white focus:outline-none focus:border-amber-500 placeholder-brand-white/30" rows="3"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">New Attachment (optional)</label>
                    <input type="file" name="attachment" accept="image/*,application/pdf" class="w-full text-xs text-brand-ash file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20">
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Submit To *</label>
                    <select name="submit_to" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 px-3 py-2 text-sm text-brand-white focus:outline-none focus:border-amber-500">
                        <option value="finance">Finance Department (for verification first)</option>
                        <option value="cvo" selected>Chief Visionary Officer (directly)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                    <button type="button" @click="openResubmitInvoiceModal = false" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                        Resubmit Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
