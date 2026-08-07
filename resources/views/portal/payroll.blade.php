<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Portal</p>
            <h2 class="text-3xl font-display text-brand-white">Payroll & Banking</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300 flex items-center justify-between">
                <div>{{ session('status') }}</div>
            </div>
        @endif

        <!-- Main Stats Summary -->
        <div class="grid gap-6 md:grid-cols-3">
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">💵</div>
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Basic Monthly Salary</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">
                    {{ $salary ? 'GHS '.number_format($salary, 2) : 'Not set' }}
                </p>
                <p class="text-xs text-brand-ash mt-2">Entered by HR only</p>
            </div>
            
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Deductions / Rewards</p>
                <div class="mt-3 space-y-1 text-sm text-brand-white">
                    <p>Deductions: <span class="font-mono text-red-400">{{ $user->payroll_deductions !== null ? 'GHS '.number_format((float) $user->payroll_deductions, 2) : 'GHS 0.00' }}</span></p>
                    <p>Rewards/Bonus: <span class="font-mono text-emerald-400">{{ $user->payroll_rewards_bonus !== null ? 'GHS '.number_format((float) $user->payroll_rewards_bonus, 2) : 'GHS 0.00' }}</span></p>
                </div>
                <p class="text-xs text-brand-ash mt-2">Entered by HR only</p>
            </div>

            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 relative overflow-hidden">
                <p class="text-xs uppercase tracking-[0.2em] text-brand-ash">Primary Payment Mode</p>
                <p class="mt-3 text-3xl font-semibold text-brand-white">
                    @if($user->bank_account_number)
                        Bank Transfer
                    @elseif($user->momo_number)
                        Mobile Money
                    @else
                        Bank Transfer
                    @endif
                </p>
                <p class="text-xs text-brand-ash mt-2">Bank details are primary when available</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <!-- Historical Payslips Repository -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-brand-white">My Payslips Archive</h3>
                        <p class="text-xs text-brand-ash mt-0.5">Permanent copies of your monthly payslips emailed to you</p>
                    </div>

                    {{-- Period Filter Dropdown --}}
                    <form method="GET" action="{{ route('portal.payroll') }}" class="flex items-center gap-2">
                        <input type="month" name="period" value="{{ $periodFilter }}" onchange="this.form.submit()" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-1.5 text-xs text-brand-white focus:border-brand-red focus:outline-none">
                        @if($periodFilter)
                            <a href="{{ route('portal.payroll') }}" class="text-xs text-brand-white/40 hover:text-brand-white">Clear</a>
                        @endif
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[550px] text-left text-sm text-brand-white/70">
                        <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="font-normal pb-3 text-left">Period</th>
                                <th class="font-normal pb-3 text-left">Gross</th>
                                <th class="font-normal pb-3 text-left">SSNIT / PAYE</th>
                                <th class="font-normal pb-3 text-left">Net Pay</th>
                                <th class="font-normal pb-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($myPayslips as $slip)
                                <tr class="hover:bg-brand-white/[0.02] transition-colors">
                                    <td class="py-4 text-brand-white font-medium">
                                        {{ $slip->period_label }}
                                        <span class="block text-[10px] text-brand-white/30">Issued {{ $slip->issued_at?->format('M d, Y') ?? $slip->created_at->format('M d, Y') }}</span>
                                    </td>
                                    <td class="py-4 font-mono text-xs">GHS {{ number_format($slip->gross_salary, 2) }}</td>
                                    <td class="py-4 font-mono text-xs text-brand-red">
                                        -GHS {{ number_format($slip->ssnit_employee + $slip->paye_tax, 2) }}
                                    </td>
                                    <td class="py-4 font-mono text-xs text-emerald-400 font-semibold">GHS {{ number_format($slip->net_salary, 2) }}</td>
                                    <td class="py-4 text-right">
                                        <a href="{{ route('portal.payroll.payslip', $slip) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-[11px] font-semibold text-amber-300 hover:bg-amber-500/20 transition-all">
                                            📥 View / Download
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-sm text-brand-white/40 italic">
                                        No payslips recorded for this criteria. Payslips issued by HR will automatically appear here.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                <div class="mt-4">
                    {{ $myPayslips->links() }}
                </div>
            </div>

            <!-- Payment & Bank Accounts Configuration -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-lg font-semibold text-brand-white mb-2">Payment & Banking Details</h3>
                <p class="text-xs text-brand-white/50 mb-6">Bank transfer is the default payment method. Mobile Money can be saved as a backup, but bank details remain primary whenever both are available.</p>

                <form method="POST" action="{{ route('portal.payroll.banking') }}" class="space-y-4">
                    @csrf
                    
                    <div class="border-b border-brand-white/10 pb-4">
                        <p class="text-xs font-semibold text-brand-ash uppercase tracking-wider mb-3">Primary Method: Bank Transfer</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="bank_name" :value="__('Bank Name')" />
                                <x-text-input id="bank_name" name="bank_name" type="text" :value="old('bank_name', $user->bank_name)" placeholder="e.g. GCB, Ecobank" class="mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="bank_branch" :value="__('Bank Branch')" />
                                <x-text-input id="bank_branch" name="bank_branch" type="text" :value="old('bank_branch', $user->bank_branch)" placeholder="e.g. Accra Mall" class="mt-1 w-full" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 mt-3">
                            <div>
                                <x-input-label for="bank_account_name" :value="__('Account Name')" />
                                <x-text-input id="bank_account_name" name="bank_account_name" type="text" :value="old('bank_account_name', $user->bank_account_name)" placeholder="e.g. John Doe" class="mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="bank_account_number" :value="__('Account Number')" />
                                <x-text-input id="bank_account_number" name="bank_account_number" type="text" :value="old('bank_account_number', $user->bank_account_number)" placeholder="e.g. 10400030029" class="mt-1 w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-brand-white/10 pb-4">
                        <p class="text-xs font-semibold text-brand-ash uppercase tracking-wider mb-3">Backup Method: Mobile Money</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="momo_number" :value="__('Momo Phone Number')" />
                                <x-text-input id="momo_number" name="momo_number" type="text" :value="old('momo_number', $user->momo_number)" placeholder="e.g. 0542204282" class="mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="momo_name" :value="__('Registered Momo Name')" />
                                <x-text-input id="momo_name" name="momo_name" type="text" :value="old('momo_name', $user->momo_name)" placeholder="e.g. John Doe" class="mt-1 w-full" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-brand-ash uppercase tracking-wider mb-3">🆔 Statutory Identification numbers</p>
                        <div class="max-w-xl">
                            <x-input-label for="ssnit_number" :value="__('SSNIT ID number')" />
                            <x-text-input id="ssnit_number" name="ssnit_number" type="text" :value="old('ssnit_number', $user->ssnit_number)" placeholder="e.g. C02910300" class="mt-1 w-full" />
                        </div>
                        <p class="mt-2 text-xs text-brand-white/40">Nationality and identity documents are managed securely from your Profile page.</p>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Save Payout Info
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employment Documents Section -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 mt-6 lg:col-span-2">
                <h3 class="text-lg font-semibold text-brand-white mb-2">Employment Documents</h3>
                <p class="text-xs text-brand-white/50 mb-4">View or download your employment contract and official job description uploaded by HR.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-center justify-between p-4 rounded-xl bg-brand-white/5 border border-brand-white/5">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📄</span>
                            <div>
                                <p class="text-sm font-medium text-brand-white">Employment Contract</p>
                                <p class="text-xs text-brand-white/40 font-mono">Official signed agreement</p>
                            </div>
                        </div>
                        @if($user->contract_path)
                            <a href="{{ route('portal.payroll.document', [$user, 'contract']) }}" target="_blank" class="text-xs uppercase tracking-[0.15em] text-amber-400 hover:text-amber-300 font-semibold transition-colors">
                                Download 📥
                            </a>
                        @else
                            <span class="text-xs text-brand-white/30 italic">Not Uploaded yet</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-xl bg-brand-white/5 border border-brand-white/5">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📋</span>
                            <div>
                                <p class="text-sm font-medium text-brand-white">Job Description</p>
                                <p class="text-xs text-brand-white/40 font-mono">Roles & responsibilities outline</p>
                            </div>
                        </div>
                        @if($user->job_description_path)
                            <a href="{{ route('portal.payroll.document', [$user, 'job-description']) }}" target="_blank" class="text-xs uppercase tracking-[0.15em] text-amber-400 hover:text-amber-300 font-semibold transition-colors">
                                Download 📥
                            </a>
                        @else
                            <span class="text-xs text-brand-white/30 italic">Not Uploaded yet</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @php
            $canViewAllPayroll = auth()->user()->canViewAllPayroll();
        @endphp

        @if ($canViewAllPayroll && isset($staffPayroll))
            <!-- HR Ghana Statutory Payroll Ledger & Bulk Email Actions -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 mt-6 shadow-2xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6 border-b border-brand-white/10 pb-5">
                    <div>
                        <h3 class="text-lg font-bold text-brand-white flex items-center gap-2">
                            <span>🇬🇭</span> Staff Payroll & Banking Ledger
                        </h3>
                        <p class="text-xs text-brand-ash mt-1">Auto-calculates statutory SSNIT (5.5% employee / 13% employer) and GRA PAYE monthly tax brackets.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        {{-- HR Bulk Email Action --}}
                        <form method="POST" action="{{ route('portal.payroll.distribute') }}" onsubmit="return confirm('Distribute official PDF payslips to all active staff emails for period: {{ now()->format('F Y') }}?')">
                            @csrf
                            <input type="hidden" name="period" value="{{ now()->format('Y-m') }}">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-500 transition-all shadow-lg">
                                ✉️ Issue & Distribute Payslips (Email All)
                            </button>
                        </form>

                        {{-- HR Payroll Export --}}
                        <a href="{{ route('portal.payroll.export-register') }}" class="inline-flex items-center gap-2 rounded-xl border border-brand-white/10 bg-brand-white/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/20 transition-all">
                            📊 Export Payroll Register (CSV)
                        </a>

                        {{-- HR Staff Directory Export --}}
                        <a href="{{ route('portal.payroll.export-staff-directory') }}" class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-amber-300 hover:bg-amber-500/20 transition-all">
                            👥 Export Staff Directory
                        </a>
                    </div>
                </div>

                {{-- Payroll Breakdown Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1400px] text-left text-sm text-brand-white/70">
                        <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash border-b border-brand-white/10 bg-brand-black/40">
                            <tr>
                                <th class="px-4 py-3.5">Staff Member</th>
                                <th class="px-4 py-3.5">Gross Base</th>
                                <th class="px-4 py-3.5">SSNIT 5.5%</th>
                                <th class="px-4 py-3.5">SSNIT 13%</th>
                                <th class="px-4 py-3.5">GRA PAYE Tax</th>
                                <th class="px-4 py-3.5">Deductions</th>
                                <th class="px-4 py-3.5">Bonuses</th>
                                <th class="px-4 py-3.5">Net Pay</th>
                                <th class="px-4 py-3.5">Bank / MoMo</th>
                                <th class="px-4 py-3.5 text-right">HR Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @foreach($staffPayroll as $item)
                                @php
                                    $staff = $item['user'];
                                    $calc = $item['calculation'];
                                @endphp
                                <tr class="hover:bg-brand-white/[0.02] transition-colors align-top">
                                    <td class="px-4 py-4">
                                        <p class="font-bold text-brand-white text-xs">{{ $staff->name }}</p>
                                        <p class="text-[10px] text-brand-white/40">{{ \App\Models\User::departmentLabel($staff->department) }} • {{ $staff->job_level ?: 'Staff' }}</p>
                                    </td>
                                    <td class="px-4 py-4 font-mono text-xs text-brand-white">GHS {{ number_format($calc['gross_salary'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-amber-300">GHS {{ number_format($calc['ssnit_employee'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-brand-ash">GHS {{ number_format($calc['ssnit_employer'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-red-400">GHS {{ number_format($calc['paye_tax'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-red-300">GHS {{ number_format($calc['other_deductions'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-emerald-400">+GHS {{ number_format($calc['bonuses'], 2) }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-emerald-400 font-bold">GHS {{ number_format($calc['net_salary'], 2) }}</td>
                                    <td class="px-4 py-4 text-xs">
                                        @if($staff->bank_account_number)
                                            <p class="text-brand-white font-medium">{{ $staff->bank_name }}</p>
                                            <p class="text-[10px] text-brand-white/50">A/C: {{ $staff->bank_account_number }}</p>
                                        @elseif($staff->momo_number)
                                            <p class="text-brand-white font-medium">MoMo: {{ $staff->momo_number }}</p>
                                        @else
                                            <span class="text-brand-white/30 italic">Not set</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <details class="relative inline-block text-left">
                                            <summary class="cursor-pointer rounded-lg border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[11px] font-semibold text-brand-white hover:bg-brand-white/10 transition-all">
                                                Edit Salary
                                            </summary>
                                            <div class="absolute right-0 top-full mt-2 z-30 w-96 rounded-xl border border-brand-white/10 bg-brand-black/95 p-4 shadow-2xl backdrop-blur-xl text-left">
                                                <form method="POST" action="{{ route('portal.payroll.salary', $staff) }}" enctype="multipart/form-data" class="space-y-3">
                                                    @csrf
                                                    <p class="text-xs font-bold text-brand-white">Edit Salary & Contracts for {{ $staff->name }}</p>
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <div>
                                                            <label class="block text-[9px] uppercase text-brand-ash mb-1">Gross Salary</label>
                                                            <input type="number" name="salary" step="0.01" min="0" value="{{ old('salary', $staff->salary) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black px-2 py-1.5 text-xs text-brand-white">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[9px] uppercase text-brand-ash mb-1">Deductions</label>
                                                            <input type="number" name="payroll_deductions" step="0.01" min="0" value="{{ old('payroll_deductions', $staff->payroll_deductions) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black px-2 py-1.5 text-xs text-brand-white">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[9px] uppercase text-brand-ash mb-1">Bonus</label>
                                                            <input type="number" name="payroll_rewards_bonus" step="0.01" min="0" value="{{ old('payroll_rewards_bonus', $staff->payroll_rewards_bonus) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black px-2 py-1.5 text-xs text-brand-white">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] uppercase text-brand-ash mb-1">Payroll Notes</label>
                                                        <textarea name="payroll_notes" rows="2" class="w-full rounded-lg border border-brand-white/10 bg-brand-black px-2 py-1.5 text-xs text-brand-white">{{ old('payroll_notes', $staff->payroll_notes) }}</textarea>
                                                    </div>
                                                    <button type="submit" class="w-full rounded-lg bg-brand-red py-2 text-xs font-bold uppercase tracking-wider text-white">Save Changes</button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
