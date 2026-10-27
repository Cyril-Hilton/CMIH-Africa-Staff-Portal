<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
            <h2 class="text-3xl font-display text-brand-white">HR & Admin Workspace</h2>
        </div>
    </x-slot>
    <style>
        .hr-module-summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;cursor:pointer;list-style:none;color:var(--brand-white,#fff)}
        .hr-module-summary::-webkit-details-marker{display:none}.hr-module-summary:focus-visible{outline:2px solid #ef4444;outline-offset:3px;border-radius:1rem}
        .hr-module-description{display:block;margin-top:.35rem;font-size:.75rem;line-height:1.5;opacity:.65;font-weight:400}
        .hr-module-chevron{font-size:1.5rem;transition:transform .15s}details[open]>.hr-module-summary .hr-module-chevron{transform:rotate(180deg)}
        details[open]>.hr-module-summary{border-bottom:1px solid rgba(128,128,128,.2)}
        @media print{details:not([open])>*:not(summary){display:block!important}.hr-module-chevron{display:none}}
    </style>
    <div data-hr-group="reporting" class="hr-workspace-tool"><details class="rounded-xl border border-brand-white/20 p-4"><summary class="cursor-pointer font-semibold">KPI Targets, Results & Measurement Guide</summary>@include('portal.modules.kpis',['kpiDepartment'=>'hr_admin'])</details></div>
    <div data-hr-group="reporting" class="hr-workspace-tool grid gap-3 sm:grid-cols-2">
        @if(auth()->user()->hasFullHrAccess() || in_array(auth()->user()->hrLevel(),[1,2],true))<a class="rounded-xl border border-brand-white/20 bg-brand-white/5 p-4 text-brand-white" href="{{ route('portal.hr-reporting.index') }}"><strong>Human Resources Reporting</strong><span class="block text-xs text-brand-ash mt-1">Recruitment, employees, attendance, training, performance and HR priorities</span></a>@endif
        <a class="rounded-xl border border-brand-white/20 bg-brand-white/5 p-4 text-brand-white" href="{{ route('portal.admin-weekly.index') }}"><strong>Administrator Reporting</strong><span class="block text-xs text-brand-ash mt-1">Office administration, fleet, drivers, assets and next-period priorities</span></a>
    </div>

    @php
        // Only HR Managers (Level 1) and Super Admin can access sensitive HR data
        $canDoSensitiveHr = auth()->user()->hasFullHrAccess();
        $canManageHrAnnouncements = auth()->user()->canManageHrAnnouncements();
    @endphp

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-400/30 p-4"><strong>Please Check Your Entries</strong><ul class="mt-2 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('portal.partials.hr-workspace-navigation')

    @if(auth()->user()->canReviewIdentityDocuments())
        <details data-hr-group="people" class="hr-workspace-tool glass-panel rounded-2xl border border-brand-white/10"><summary class="hr-module-summary"><span><strong>Identity Verification Register</strong><span class="hr-module-description">Review authorised identity documents when needed.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-4 sm:p-6">@include('portal.partials.identity-document-register', ['identityDocuments' => $identityDocuments])</div></details>
    @endif

    @if($canDoSensitiveHr)
        <div data-hr-group="people" class="hr-workspace-tool rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6">
            <h3 class="text-xl font-display text-brand-white">Staff records</h3>
            <p class="mt-2 text-sm text-brand-ash">View complete staff profiles, select information to export, and import staff updates.</p>
            <a href="{{ route('portal.hr.staff.index') }}" class="mt-4 inline-block rounded-xl bg-brand-red text-white px-5 py-3 font-semibold text-white">Open staff register</a>
        </div>
    @endif

    <div class="space-y-8">
        @if($canManageHrAnnouncements)
        <details data-hr-group="communications" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Staff communications</strong><span class="hr-module-description">Create announcements and review recent notices.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Communication</p>
                    <h3 class="text-lg font-display text-brand-white uppercase">HR Announcement Blast</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Send a notice to all internal staff, selected departments, or specific staff members. Each recipient receives a portal notification.</p>
                </div>
                <a href="{{ route('portal.announcements') }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">
                    View Announcements
                </a>
            </div>

            <form method="POST" action="{{ route('portal.hr.announcements.store') }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.5fr)]">
                @csrf
                <div class="space-y-4">
                    <div>
                        <x-input-label for="announcement_title" :value="__('Announcement Title')" />
                        <x-text-input id="announcement_title" name="title" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Mandatory staff briefing" />
                    </div>
                    <div>
                        <x-input-label for="announcement_body" :value="__('Message')" />
                        <textarea id="announcement_body" name="body" rows="6" required class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Type the announcement here..."></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-brand-white/70">
                        <input type="checkbox" name="pinned" value="1" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                        Pin this announcement
                    </label>
                </div>

                <div class="space-y-4 rounded-2xl border border-brand-white/10 bg-brand-black/30 p-4">
                    <div>
                        <x-input-label for="audience_type" :value="__('Recipients')" />
                        <select id="audience_type" name="audience_type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" onchange="toggleHrAnnouncementAudience(this.value)">
                            <option value="all">All internal staff</option>
                            <option value="departments">Selected departments</option>
                            <option value="selected">Selected staff</option>
                        </select>
                    </div>

                    <div id="announcement_departments" class="hidden">
                        <x-input-label :value="__('Departments')" />
                        <div class="mt-2 grid gap-2 text-xs text-brand-white/70">
                            @foreach([
                                'hr_admin' => 'HR & Admin',
                                'finance' => 'Finance',
                                'client_relations' => 'Client Relations',
                                'operations_projects' => 'Operations',
                                'brands_marketing' => 'Brands & Marketing',
                                'creatives' => 'Creatives',
                            ] as $key => $label)
                                <label class="flex items-center gap-2 rounded-lg border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2">
                                    <input type="checkbox" name="department_keys[]" value="{{ $key }}" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div id="announcement_staff" class="hidden">
                        <x-input-label for="recipient_ids" :value="__('Staff Members')" />
                        <select id="recipient_ids" name="recipient_ids[]" multiple size="8" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->department ?? 'No department' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-3 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Send Announcement
                    </button>
                </div>
            </form>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                @forelse($recentAnnouncements as $announcement)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-brand-white">{{ $announcement->title }}</p>
                            <span class="rounded-full border border-brand-white/10 bg-brand-black/30 px-2 py-0.5 text-[9px] uppercase text-brand-white/50">{{ $announcement->audience_type ?? 'all' }}</span>
                        </div>
                        <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-brand-white/55">{!! nl2br(e($announcement->plainBody(220))) !!}</p>
                        <p class="mt-3 text-[10px] text-brand-ash">By {{ $announcement->user?->name ?? 'HR Admin' }} on {{ $announcement->created_at->format('M d, Y') }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4 text-xs text-brand-white/50 md:col-span-3">
                        No staff announcements have been published yet.
                    </div>
                @endforelse
            </div>
        </div></details>
        @endif

        @if($canDoSensitiveHr)
        <details data-hr-group="pay" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Salary advance policy</strong><span class="hr-module-description">Manage default terms and staff-specific agreements.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Finance Policy</p>
                    <h3 class="text-lg font-display text-brand-white uppercase">Salary Advance Installment Terms</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Set the default loan rules, then override maximum payout and installment terms for individual staff agreements where needed.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-right">
                        <p class="text-[10px] uppercase tracking-widest text-amber-300">Default Installment Min</p>
                        <p class="mt-1 text-xl font-semibold text-brand-white">GH₵ {{ number_format($salaryAdvanceDefaultMinimum ?? 500, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-right">
                        <p class="text-[10px] uppercase tracking-widest text-sky-300">Default Max Payout</p>
                        <p class="mt-1 text-xl font-semibold text-brand-white">{{ number_format($salaryAdvanceDefaultMaximumMultiplier ?? 2, 2) }}x Salary</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('portal.hr.salary-advance-settings.update') }}" class="mt-5 grid gap-4 rounded-xl border border-brand-white/10 bg-brand-black/30 p-4 lg:grid-cols-3 lg:items-end">
                @csrf
                <div>
                    <x-input-label for="default_min_monthly_deduction" :value="__('Default Minimum Monthly Deduction (GHC)')" />
                    <input id="default_min_monthly_deduction" name="default_min_monthly_deduction" type="number" step="0.01" min="0.01"
                           value="{{ old('default_min_monthly_deduction', number_format($salaryAdvanceDefaultMinimum ?? 500, 2, '.', '')) }}"
                           class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                    <p class="mt-1 text-[10px] text-brand-white/45">Used unless HR sets a staff-specific installment agreement below.</p>
                </div>
                <div>
                    <x-input-label for="default_max_salary_multiplier" :value="__('Default Maximum Payout Multiplier')" />
                    <input id="default_max_salary_multiplier" name="default_max_salary_multiplier" type="number" step="0.01" min="0.01" max="100"
                           value="{{ old('default_max_salary_multiplier', number_format($salaryAdvanceDefaultMaximumMultiplier ?? 2, 2, '.', '')) }}"
                           class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">
                    <p class="mt-1 text-[10px] text-brand-white/45">Example: 1.50 means staff can request up to 1.5x salary unless overridden below.</p>
                </div>
                <div class="lg:row-span-2">
                    <x-input-label for="terms_note" :value="__('Loan Terms & Conditions Note')" />
                    <textarea id="terms_note" name="terms_note" rows="5" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-amber-500 focus:outline-none">{{ old('terms_note', $salaryAdvanceTermsNote ?? '') }}</textarea>
                </div>
                <button type="submit" class="rounded-xl bg-brand-red px-5 py-2.5 text-xs font-semibold uppercase tracking-wider text-brand-white transition hover:bg-brand-red-dark lg:col-span-2">
                    Save Loan Policy
                </button>
            </form>

            <div class="mt-5 overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full min-w-[860px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-black/40 text-[10px] uppercase tracking-widest text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Monthly Salary</th>
                            <th class="px-4 py-3">Effective Minimum</th>
                            <th class="px-4 py-3">Effective Maximum</th>
                            <th class="px-4 py-3">Staff Agreement Overrides</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @foreach($staff as $employee)
                            @php
                                $overrideMinimum = $employee->salary_advance_min_monthly_deduction;
                                $effectiveMinimum = $overrideMinimum ?: ($salaryAdvanceDefaultMinimum ?? 500);
                                $overrideMaximum = $employee->salary_advance_max_amount;
                                $effectiveMaximum = \App\Support\SalaryAdvancePolicy::effectiveMaximumAmount($employee);
                            @endphp
                            <tr class="align-middle hover:bg-brand-white/[0.02]">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-brand-white">{{ $employee->name }}</p>
                                    <p class="mt-1 text-[10px] text-brand-white/40">{{ $employee->email }}</p>
                                </td>
                                <td class="px-4 py-3 font-mono">GH₵ {{ number_format((float) ($employee->salary ?? 0), 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold text-emerald-300">
                                        GH₵ {{ number_format((float) $effectiveMinimum, 2) }}
                                    </span>
                                    <p class="mt-1 text-[10px] text-brand-white/35">{{ $overrideMinimum ? 'Staff-specific agreement' : 'Using HR default' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-[10px] font-semibold text-sky-300">
                                        GH₵ {{ number_format((float) $effectiveMaximum, 2) }}
                                    </span>
                                    <p class="mt-1 text-[10px] text-brand-white/35">{{ $overrideMaximum ? 'Staff-specific cap' : 'Using default multiplier' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <form id="loan-minimum-{{ $employee->id }}" method="POST" action="{{ route('portal.hr.salary-advance-minimum.update', $employee) }}" class="grid gap-2 md:grid-cols-2">
                                        @csrf
                                        <input name="min_monthly_deduction" type="number" step="0.01" min="0.01"
                                               value="{{ old('min_monthly_deduction', $overrideMinimum ? number_format((float) $overrideMinimum, 2, '.', '') : '') }}"
                                               placeholder="Min deduction"
                                               class="w-40 rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:outline-none">
                                        <input name="max_advance_amount" type="number" step="0.01" min="0.01"
                                               value="{{ old('max_advance_amount', $overrideMaximum ? number_format((float) $overrideMaximum, 2, '.', '') : '') }}"
                                               placeholder="Max payout"
                                               class="w-40 rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:outline-none">
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="submit" form="loan-minimum-{{ $employee->id }}" class="rounded-lg bg-amber-500/20 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-amber-300 hover:bg-amber-500/30">
                                        Save Terms
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div></details>
        @endif

        @if($canDoSensitiveHr)
        @php
            $loanStatusStyles = [
                'pending_hr' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                'pending_finance' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                'pending_cvo' => 'border-purple-500/30 bg-purple-500/10 text-purple-300',
                'returned_for_correction' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',
                'repayment_active' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'approved' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'fully_paid' => 'border-green-500/30 bg-green-500/10 text-green-300',
                'rejected' => 'border-brand-red/30 bg-brand-red/10 text-brand-red',
            ];
        @endphp
        <details data-hr-group="pay" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Staff loan management</strong><span class="hr-module-description">Review requests, repayments and agreements.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Finance Policy</p>
                    <h3 class="text-lg font-display text-brand-white uppercase">Staff Loan Manager</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Review all salary advance requests, approve the agreed staff terms, and monitor payback progress with Finance.</p>
                </div>
                <a href="{{ route('portal.finance.advances.index') }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">
                    Open Finance View
                </a>
            </div>

            <div class="mt-5 overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full min-w-[1180px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-black/40 text-[10px] uppercase tracking-widest text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Request</th>
                            <th class="px-4 py-3">Terms</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payback</th>
                            <th class="px-4 py-3">Notes</th>
                            <th class="px-4 py-3 text-right">HR Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($salaryAdvanceRequests as $loan)
                            @php
                                $paid = $loan->totalRepaid();
                                $balance = $loan->balance();
                                $approvedMonthly = $loan->approvedMonthlyDeduction();
                                $staffMinimum = \App\Support\SalaryAdvancePolicy::effectiveMonthlyDeductionMinimum($loan->user);
                                $loanColor = $loanStatusStyles[$loan->status] ?? 'border-brand-white/20 bg-brand-white/5 text-brand-white/60';
                            @endphp
                            <tr class="align-top hover:bg-brand-white/[0.02]">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-brand-white">{{ $loan->user?->name ?? 'Unknown staff' }}</p>
                                    <p class="mt-1 text-[10px] text-brand-white/40">{{ $loan->user?->email ?? 'No email' }}</p>
                                    <p class="mt-1 text-[10px] text-brand-ash">{{ \App\Models\User::departmentLabel($loan->user?->department) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-mono text-brand-white">GH₵ {{ number_format($loan->amount, 2) }}</p>
                                    <p class="mt-1 text-[10px] text-brand-white/45">{{ $loan->created_at?->format('M d, Y h:i A') }}</p>
                                    <p class="mt-2 max-w-[16rem] text-[10px] leading-snug text-brand-white/55">{{ trim(strip_tags((string) $loan->reason)) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="capitalize text-brand-white">{{ str_replace('_', ' ', $loan->repayment_style) }}</p>
                                    @if($loan->repayment_style === 'monthly_deduction')
                                        <p class="mt-1 text-[10px] text-brand-white/50">Requested: GH₵ {{ number_format($loan->monthly_deduction_amount, 2) }}</p>
                                        <p class="text-[10px] text-emerald-300">Approved: {{ $approvedMonthly ? 'GH₵ '.number_format($approvedMonthly, 2) : 'Pending HR' }}</p>
                                        <p class="text-[10px] text-brand-white/35">Staff minimum: GH₵ {{ number_format($staffMinimum, 2) }}</p>
                                    @else
                                        <p class="mt-1 text-[10px] text-amber-300">One-time payback agreement</p>
                                    @endif
                                    @if($loan->repayment_start_date)
                                        <p class="mt-1 text-[10px] text-brand-white/45">Starts {{ $loan->repayment_start_date->format('M d, Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $loanColor }}">
                                        {{ ucwords(str_replace('_', ' ', $loan->status)) }}
                                    </span>
                                    @if($loan->hrReviewer)
                                        <p class="mt-2 text-[10px] text-brand-white/45">HR: {{ $loan->hrReviewer->name }}</p>
                                    @endif
                                    @if($loan->financeReviewer)
                                        <p class="text-[10px] text-brand-white/45">Finance: {{ $loan->financeReviewer->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-[10px] text-brand-white/45">Paid</p>
                                    <p class="font-mono text-emerald-300">GH₵ {{ number_format($paid, 2) }}</p>
                                    <p class="mt-2 text-[10px] text-brand-white/45">Balance</p>
                                    <p class="font-mono text-brand-white">GH₵ {{ number_format($balance, 2) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if($loan->hr_feedback)
                                        <p class="max-w-[14rem] whitespace-pre-wrap text-[10px] leading-snug text-sky-200/80">HR: {{ $loan->hr_feedback }}</p>
                                    @endif
                                    @if($loan->finance_feedback)
                                        <p class="mt-2 max-w-[14rem] whitespace-pre-wrap text-[10px] leading-snug text-amber-200/80">Finance/CVO: {{ $loan->finance_feedback }}</p>
                                    @endif
                                    @if(! $loan->hr_feedback && ! $loan->finance_feedback)
                                        <span class="text-[10px] text-brand-white/35">No notes</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if($loan->status === 'pending_hr')
                                        <div class="min-w-[18rem] space-y-2 text-right">
                                            <form method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}" class="space-y-2 rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-left">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                @if($loan->repayment_style === 'monthly_deduction')
                                                    <input name="approved_monthly_deduction_amount" type="number" step="0.01" min="{{ number_format($staffMinimum, 2, '.', '') }}" value="{{ old('approved_monthly_deduction_amount', number_format((float) $loan->monthly_deduction_amount, 2, '.', '')) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white focus:border-amber-500 focus:ring-0">
                                                @endif
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input name="repayment_start_date" type="date" class="rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white focus:border-amber-500 focus:ring-0">
                                                    <input name="repayment_months" type="number" min="1" max="120" placeholder="Months" class="rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:ring-0">
                                                </div>
                                                <input name="feedback" type="text" placeholder="HR note, optional" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:ring-0">
                                                <button type="submit" class="w-full rounded-lg bg-emerald-500/15 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-emerald-300 hover:bg-emerald-500/25">
                                                    Approve Terms
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}" class="flex gap-2">
                                                @csrf
                                                <input type="hidden" name="action" value="correction">
                                                <input name="feedback" required type="text" placeholder="Correction note" class="min-w-0 flex-1 rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-cyan-400 focus:ring-0">
                                                <button type="submit" class="rounded-lg bg-cyan-500/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-cyan-300 hover:bg-cyan-500/20">
                                                    Send Back
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.hr.salary-advances.action', $loan) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="feedback" value="Rejected by HR Manager">
                                                <button type="submit" class="rounded-lg bg-brand-red/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-brand-red hover:bg-brand-red/20">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($loan->status === 'pending_finance')
                                        <span class="text-[10px] text-amber-300/80">Awaiting Finance payment decision</span>
                                    @elseif($loan->status === 'repayment_active')
                                        <span class="text-[10px] text-emerald-300/80">Finance is tracking repayments</span>
                                    @elseif($loan->status === 'fully_paid')
                                        <span class="text-[10px] text-green-300/80">Loan fully settled</span>
                                    @else
                                        <span class="text-[10px] text-brand-white/35">No HR action required</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-brand-white/50">No salary advance requests have been submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($salaryAdvanceRequests instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 border-t border-brand-white/10 pt-4">
                    {{ $salaryAdvanceRequests->links() }}
                </div>
            @endif
        </div></details>
        @endif

        @if($canManageLeaves)
        @php
            $leaveStatusStyles = [
                'approved' => 'border-green-500/30 bg-green-500/10 text-green-300',
                'rejected' => 'border-brand-red/30 bg-brand-red/10 text-brand-red',
                'pending_manager' => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-300',
                'pending_cvo' => 'border-purple-500/30 bg-purple-500/10 text-purple-300',
                'pending_hr' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                'returned_for_correction' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',
            ];
            $today = today();
        @endphp
        <details data-hr-group="leave" id="staff-leave-manager" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Leave management</strong><span class="hr-module-description">Review staff requests and manage leave decisions.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">HR Workflow</p>
                    <h3 class="text-lg font-display text-brand-white uppercase">All Staff Leave Manager</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Review every leave request, see its current lifecycle, and complete final HR approval. Saturdays and Sundays are excluded from working-day totals.</p>
                </div>
                <a href="{{ route('portal.leaves') }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">
                    Open Leave Portal
                </a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['label' => 'Pending review', 'value' => $leaveStats['pending'], 'class' => 'border-yellow-500/20 bg-yellow-500/5'],
                    ['label' => 'Approved', 'value' => $leaveStats['approved'], 'class' => 'border-green-500/20 bg-green-500/5'],
                    ['label' => 'Currently on leave', 'value' => $leaveStats['active'], 'class' => 'border-sky-500/20 bg-sky-500/5'],
                    ['label' => 'Completed', 'value' => $leaveStats['completed'], 'class' => 'border-brand-white/10 bg-brand-white/[0.03]'],
                ] as $stat)
                    <div class="rounded-xl border px-4 py-3 {{ $stat['class'] }}">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-brand-ash">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-brand-white">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full min-w-[1120px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-black/40 text-[10px] uppercase tracking-widest text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Requested</th>
                            <th class="px-4 py-3">Days Off</th>
                            <th class="px-4 py-3">Working Days</th>
                            <th class="px-4 py-3">Cover / Acting LM</th>
                            <th class="px-4 py-3">Approval</th>
                            <th class="px-4 py-3">Leave Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($allLeaves as $leave)
                            @php
                                $leaveLifecycle = match (true) {
                                    $leave->status === 'approved' && $leave->start_date->gt($today) => 'Scheduled',
                                    $leave->status === 'approved' && $leave->end_date->lt($today) => 'Completed',
                                    $leave->status === 'approved' => 'Currently on leave',
                                    $leave->status === 'returned_for_correction' => 'Correction requested',
                                    $leave->status === 'rejected' => 'Not approved',
                                    default => 'Pending approval',
                                };
                                $canApproveAtThisStage = $leave->status === 'pending_hr' && $canManageLeaves;
                            @endphp
                            <tr class="align-top hover:bg-brand-white/[0.02]">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-brand-white">{{ $leave->user?->name ?? 'Unknown staff' }}</p>
                                    <p class="mt-1 text-[10px] text-brand-white/40">{{ $leave->user?->email ?? 'No email' }}</p>
                                    <p class="mt-1 text-[10px] text-brand-ash">{{ \App\Models\User::departmentLabel($leave->user?->department) }}</p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    {{ $leave->created_at?->format('M d, Y') ?? 'N/A' }}<br>
                                    <span class="text-[10px] text-brand-ash">{{ $leave->created_at?->format('h:i A') }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="capitalize">{{ $leave->leave_type }}</span><br>
                                    <span class="text-[10px] text-brand-white/50">{{ $leave->start_date->format('M d, Y') }} to {{ $leave->end_date->format('M d, Y') }}</span>
                                </td>
                                <td class="px-4 py-4 font-mono text-brand-white">{{ $leave->workingDays() }}</td>
                                <td class="px-4 py-4">
                                    <p>{{ $leave->coveringStaff?->name ?? 'None selected' }}</p>
                                    @if($leave->delegateLineManager)
                                        <p class="mt-1 text-[10px] text-amber-300">Acting LM: {{ $leave->delegateLineManager->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $leaveStatusStyles[$leave->status] ?? 'border-brand-white/20 text-brand-white/60' }}">
                                        {{ ucwords(str_replace('_', ' ', $leave->status)) }}
                                    </span>
                                    @if($leave->comments)
                                        <p class="mt-2 max-w-[14rem] whitespace-pre-wrap text-[10px] leading-snug text-brand-white/45">{{ trim(strip_tags($leave->comments)) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border border-brand-white/10 bg-brand-white/[0.03] px-2.5 py-1 text-[10px] font-semibold uppercase text-brand-white/60">
                                        {{ $leaveLifecycle }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    @if($canApproveAtThisStage)
                                        <div class="flex min-w-[15rem] flex-col items-end gap-2">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <form method="POST" action="{{ route('portal.leaves.approve', $leave) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-green-500/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-green-300 hover:bg-green-500/20">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('portal.leaves.reject', $leave) }}">
                                                    @csrf
                                                    <input type="hidden" name="rejection_comments" value="Rejected by HR Manager">
                                                    <button type="submit" class="rounded-lg bg-brand-red/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-brand-red hover:bg-brand-red/20">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                            <form method="POST" action="{{ route('portal.leaves.return', $leave) }}" class="flex w-full items-center justify-end gap-2">
                                                @csrf
                                                <input type="text" name="rejection_comments" required placeholder="Correction notes" class="min-w-0 flex-1 rounded-lg border border-brand-white/10 bg-brand-black/40 px-2.5 py-2 text-[10px] text-brand-white placeholder-brand-white/30 focus:border-cyan-400 focus:ring-0">
                                                <button type="submit" class="shrink-0 rounded-lg bg-cyan-500/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-cyan-300 hover:bg-cyan-500/20">
                                                    Send back
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($leave->status === 'pending_manager')
                                        <span class="text-[10px] text-yellow-300/70">Awaiting line manager</span>
                                    @elseif($leave->status === 'pending_cvo')
                                        <span class="text-[10px] text-purple-300/70">Awaiting CVO</span>
                                    @else
                                        <span class="text-[10px] text-brand-white/35">No action required</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm text-brand-white/50">No staff leave requests have been submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($allLeaves instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 border-t border-brand-white/10 pt-4">
                    {{ $allLeaves->links() }}
                </div>
            @endif
        </div></details>
        @endif

        <!-- Grid layout: Visitor Log & LifeCycle -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Visitor Log Form and Listing -->
            <details data-hr-group="workplace" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Visitor Arrivals</strong><span class="hr-module-description">Register arrivals and review recent visits.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4"><x-ui-icon name="logout" /> Visitor Management System</h3>
                <form method="POST" action="{{ route('portal.hr.visitors.store') }}" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="visitor_name" :value="__('Visitor Name')" />
                            <x-text-input id="visitor_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="John Doe" />
                        </div>
                        <div>
                            <x-input-label for="visitor_company" :value="__('Company / Agency')" />
                            <x-text-input id="visitor_company" name="company" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Google Inc." />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="visitor_purpose" :value="__('Purpose of Visit')" />
                        <x-text-input id="visitor_purpose" name="purpose" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Pitch meeting / Strategy review" />
                    </div>
                    <div>
                        <x-input-label for="host_id" :value="__('Host / Staff Met')" />
                        <select id="host_id" name="host_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="">Select Host</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Log Check-In
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Recent Visits</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($visitors as $visitor)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $visitor->name }} <span class="text-brand-white/40">from {{ $visitor->company ?? 'N/A' }}</span></p>
                                <p class="text-brand-white/60">Meeting: {{ $visitor->purpose }}</p>
                                <p class="text-brand-ash text-[10px]">Host: {{ $visitor->host?->name ?? 'Staff' }}</p>
                            </div>
                            <div class="text-right">
                                @if($visitor->status === 'checked_in')
                                    <form method="POST" action="{{ route('portal.hr.visitors.checkout', $visitor) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full bg-brand-red px-3 py-1 text-[10px] text-brand-white uppercase font-bold hover:bg-brand-red-dark transition-all">
                                            Check Out
                                        </button>
                                    </form>
                                @else
                                    <span class="text-emerald-400 font-semibold uppercase text-[10px]">Out ({{ optional($visitor->time_out)->format('H:i') }})</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No visitor logs recorded today.</p>
                    @endforelse
                </div>
                @if($visitors instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $visitors->links() }}
                    </div>
                @endif
            </div></details>

            {{-- Employee Lifecycle Tracker: HR Manager only --}}
            @if($canDoSensitiveHr)
            <details data-hr-group="people" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Employee lifecycle &amp; contracts</strong><span class="hr-module-description">Manage employment changes and contract milestones.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4"><x-ui-icon name="chart" /> Employee Lifecycle & Contracts</h3>
                <a href="{{ route('portal.hr.staff.index') }}" class="mb-4 inline-block rounded-xl bg-brand-red px-4 py-2 text-sm text-white">Manage staff records</a>
                <div class="space-y-4">
                    @foreach($staff->take(5) as $employee)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $employee->profilePhotoUrl() }}" alt="{{ $employee->name }}" class="h-10 w-10 rounded-full object-cover">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-white">{{ $employee->name }}</p>
                                        <p class="text-xs text-brand-white/50">{{ ucfirst($employee->department ?? 'Operations') }} · {{ $employee->job_title ?? 'Staff' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-0.5 text-[10px] uppercase font-bold text-emerald-400">
                                        Contract: Active
                                    </span>
                                    <p class="text-[10px] text-brand-ash mt-1">Expiry: {{ $employee->id_card_expires_at?->format('M Y') ?? 'Dec 2027' }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div></details>
            @endif{{-- /canDoSensitiveHr --}}
        </div>{{-- /Visitor+Lifecycle grid --}}

        @if($canDoSensitiveHr)
        <details data-hr-group="leave" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Leave entitlements &amp; exports</strong><span class="hr-module-description">Update entitlements and download HR reports.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Leave Entitlements & HR Exports</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Set each staff member's remaining leave days. Approved leave requests deduct from this balance automatically.</p>
                </div>
                <a href="{{ route('portal.export', ['table' => 'assets']) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white transition hover:bg-brand-white/10">
                    Export Asset Inventory
                </a>
            </div>
            <div class="overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full min-w-[760px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-black/40 text-[10px] uppercase tracking-widest text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Current Balance</th>
                            <th class="px-4 py-3 text-right">HR Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @foreach($staff as $member)
                            <tr class="align-middle hover:bg-brand-white/[0.02]">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-brand-white">{{ $member->name }}</p>
                                    <p class="text-[10px] text-brand-white/35">{{ $member->email }}</p>
                                </td>
                                <td class="px-4 py-3">{{ \App\Models\User::departmentLabel($member->department) }}</td>
                                <td class="px-4 py-3 font-mono text-brand-white">{{ (int) $member->leave_balance }} day(s)</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('portal.hr.leave-balance.update', $member) }}" class="ml-auto flex max-w-xs items-center justify-end gap-2">
                                        @csrf
                                        <input type="number" name="leave_balance" min="0" max="365" step="1" value="{{ old('leave_balance', $member->leave_balance) }}" class="w-24 rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        <button type="submit" class="rounded-lg bg-brand-red px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div></details>
        @endif

        {{-- ── Employee Lifecycle & Contracts: HR Manager only ──────────────── --}}
        @if($canDoSensitiveHr)
        <details data-hr-group="people" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Appraisal tools</strong><span class="hr-module-description">Build assessment forms and manage parameters.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white"><x-ui-icon name="award" /> Appraisal Form Builder</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('portal.import.show', ['table' => 'appraisal_metrics']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        <x-ui-icon name="download" /> Import Metrics
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'appraisal_metrics']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        <x-ui-icon name="upload" /> Export Metrics
                    </a>
                </div>
            </div>
            <div class="space-y-6">
                <form method="POST" action="{{ route('portal.hr.appraisals.metrics.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="metric_name" :value="__('Metric Name')" />
                        <x-text-input id="metric_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Critical Thinking or punctuality" />
                    </div>
                    <div>
                        <x-input-label for="metric_category" :value="__('Category')" />
                        <select id="metric_category" name="category" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="General">General Performance</option>
                            <option value="Technical">Technical Competency</option>
                            <option value="Leadership">Leadership & Teamwork</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="metric_type" :value="__('Metric Type')" />
                        <select id="metric_type" name="metric_type" onchange="toggleTableSettings(this.value)"
                            class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option value="slider">1–10 Slider rating</option>
                            <option value="table">Target / Objective Grid (Table)</option>
                        </select>
                    </div>

                    {{-- Table Settings Panel --}}
                    <div id="table_settings_panel" class="hidden space-y-4 border border-brand-white/10 rounded-xl p-4 bg-brand-white/5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-brand-ash">Table Template Builder</h4>
                        <div>
                            <x-input-label for="default_rows" :value="__('Default Empty Rows')" />
                            <input id="default_rows" name="default_rows" type="number" min="1" max="15" value="3"
                                class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" />
                        </div>
                        
                        <div>
                            <x-input-label :value="__('Columns Configuration')" />
                            <div id="columns_list" class="space-y-2 mt-2">
                                {{-- Columns will be rendered here dynamically --}}
                            </div>
                            <button type="button" onclick="addColumnRow()" class="mt-2 text-xs text-brand-red font-semibold hover:underline">
                                ＋ Add Column definition
                            </button>
                        </div>
                        
                        <input type="hidden" name="table_template" id="table_template_input" value="[]" />
                    </div>

                    <div>
                        <x-input-label for="metric_description" :value="__('Description')" />
                        <textarea id="metric_description" name="description" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Describe the review criteria"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Add Metric Row
                    </button>
                </form>

                <div>
                    <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3">Active Assessment Parameters</h4>
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        @foreach(['General' => 'General Performance', 'Technical' => 'Technical Competency', 'Leadership' => 'Leadership & Teamwork'] as $cat => $title)
                            @php
                                $catMetrics = $metrics->where('category', $cat);
                            @endphp
                            <div class="space-y-2">
                                <h5 class="text-[10px] font-bold uppercase tracking-wider text-brand-red border-b border-brand-white/10 pb-1">{{ $title }}</h5>
                                @forelse($catMetrics as $m)
                                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                                        <div>
                                            <p class="font-semibold text-brand-white">
                                                {{ $m->name }}
                                                @if($m->metric_type === 'table')
                                                    <span class="ml-2 rounded bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-amber-400">Grid</span>
                                                @else
                                                    <span class="ml-2 rounded bg-brand-white/10 border border-brand-white/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-brand-white/50">Slider</span>
                                                @endif
                                            </p>
                                            @if($m->description)
                                                <p class="text-brand-white/60 text-[10px] mt-1">{!! $m->description !!}</p>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('portal.hr.appraisals.metrics.destroy', $m) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-brand-red hover:text-red-400 transition-colors">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-[10px] text-brand-white/30 italic">No metrics listed in this section.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>

                <script>
                    let columns = [
                        { key: 'objective', label: 'Objective / KPI', type: 'text', width: '30%' },
                        { key: 'weight', label: 'Weight (%)', type: 'number', width: '10%' },
                        { key: 'target', label: 'Target', type: 'text', width: '20%' },
                        { key: 'actual', label: 'Actual', type: 'text', width: '20%' },
                        { key: 'score', label: 'Score (1–10)', type: 'score', width: '10%' },
                        { key: 'remarks', label: 'Remarks', type: 'textarea', width: '10%' }
                    ];

                    function toggleTableSettings(type) {
                        const panel = document.getElementById('table_settings_panel');
                        if (type === 'table') {
                            panel.classList.remove('hidden');
                            renderColumns();
                        } else {
                            panel.classList.add('hidden');
                        }
                    }

                    function renderColumns() {
                        const container = document.getElementById('columns_list');
                        container.innerHTML = '';
                        
                        columns.forEach((col, index) => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-2 border-b border-brand-white/5 pb-2 text-xs';
                            row.innerHTML = `
                                <input type="text" placeholder="Key" value="${col.key}" onchange="updateCol(${index}, 'key', this.value)"
                                    class="w-1/4 rounded border border-brand-white/10 bg-brand-black/60 px-2 py-1 text-xs text-brand-white" required />
                                <input type="text" placeholder="Label" value="${col.label}" onchange="updateCol(${index}, 'label', this.value)"
                                    class="w-1/3 rounded border border-brand-white/10 bg-brand-black/60 px-2 py-1 text-xs text-brand-white" required />
                                <select onchange="updateCol(${index}, 'type', this.value)"
                                    class="w-1/4 rounded border border-brand-white/10 bg-brand-black/60 px-1 py-1 text-xs text-brand-white">
                                    <option value="text" ${col.type === 'text' ? 'selected' : ''}>Text</option>
                                    <option value="number" ${col.type === 'number' ? 'selected' : ''}>Number</option>
                                    <option value="score" ${col.type === 'score' ? 'selected' : ''}>Score</option>
                                    <option value="textarea" ${col.type === 'textarea' ? 'selected' : ''}>Textarea</option>
                                </select>
                                <input type="text" placeholder="Width" value="${col.width}" onchange="updateCol(${index}, 'width', this.value)"
                                    class="w-16 rounded border border-brand-white/10 bg-brand-black/60 px-1 py-1 text-xs text-brand-white" />
                                <button type="button" onclick="deleteCol(${index})" class="text-brand-red font-bold hover:text-red-400"></button>
                            `;
                            container.appendChild(row);
                        });
                        
                        document.getElementById('table_template_input').value = JSON.stringify(columns);
                    }

                    function updateCol(index, key, val) {
                        columns[index][key] = val;
                        document.getElementById('table_template_input').value = JSON.stringify(columns);
                    }

                    function addColumnRow() {
                        columns.push({ key: '', label: '', type: 'text', width: '15%' });
                        renderColumns();
                    }

                    function deleteCol(index) {
                        columns.splice(index, 1);
                        renderColumns();
                    }
                </script>
            </div>
        </div></details>{{-- /Appraisal Form Builder --}}

        {{-- ── Central Vault: HR Manager only ─────────────────────────────── --}}
        <details data-hr-group="communications" class="glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Templates &amp; guides</strong><span class="hr-module-description">Access HR reference materials.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4"><x-ui-icon name="folder" /> Central Vault (Templates & Guides)</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach([
                    ['label'=>'Staff Import Template','description'=>'Download the formatted Excel template for staff records.','url'=>route('portal.hr.staff.import.template')],
                    ['label'=>'Staff Records & Documents','description'=>'Open staff records and their authorised documents.','url'=>route('portal.hr.staff.index')],
                    ['label'=>'Performance Definitions','description'=>'Review departmental objectives and KPI definitions.','url'=>route('portal.performance.index',['department'=>'hr_admin','view'=>'dictionary'])]
                ] as $resource)
                    <a href="{{ $resource['url'] }}" class="block min-w-0 rounded-xl border border-brand-white/15 bg-brand-white/5 p-4 transition hover:border-brand-red/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-red">
                        <strong class="block text-sm text-brand-white">{{ $resource['label'] }}</strong>
                        <span class="mt-2 block text-xs text-brand-ash">{{ $resource['description'] }}</span>
                    </a>
                @endforeach
            </div>
        </div></details>{{-- /Central Vault --}}
        @endif{{-- /canDoSensitiveHr --}}
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PHASE 3 PANELS: Visitor Pre-Ticketing & Phone/Vendor Directory
    ══════════════════════════════════════════════════════════════════════ --}}

    {{-- Visitor Pre-Ticketing ──────────────────────────────────────────────── --}}
    <details data-hr-group="workplace" class="mt-8 glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Scheduled visitors</strong><span class="hr-module-description">Schedule visits and manage upcoming arrivals.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Visitor Services</p>
                <h3 class="text-lg font-display text-brand-white uppercase"><x-ui-icon name="id" /> Visitor Pre-Ticketing</h3>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Add Pre-Ticket --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Schedule a Visitor</h4>
                <form method="POST" action="{{ route('portal.hr.pre-tickets.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Visitor Name *</label>
                            <input type="text" name="visitor_name" required maxlength="255"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Full name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Company</label>
                            <input type="text" name="visitor_company" maxlength="255"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Company name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Phone</label>
                            <input type="text" name="visitor_phone" maxlength="30"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="+233 XX XXX XXXX">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Host *</label>
                            <select name="host_id" required
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="">Select Host</option>
                                @foreach($staff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Purpose *</label>
                            <input type="text" name="purpose" required maxlength="1000"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Purpose of visit (meeting, interview, delivery...)">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Expected Arrival *</label>
                            <input type="datetime-local" name="expected_arrival" required
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                        <x-ui-icon name="id" /> Create Pre-Ticket
                    </button>
                </form>
            </div>
            {{-- Pre-Ticket Ledger --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Upcoming Visitors</h4>
                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($preTickets ?? [] as $pt)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 p-3 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-brand-white">{{ $pt->visitor_name }}
                                        @if($pt->visitor_company)
                                            <span class="text-brand-white/40 font-normal">— {{ $pt->visitor_company }}</span>
                                        @endif
                                    </p>
                                    <p class="text-brand-ash text-[10px] mt-0.5">{{ $pt->purpose }}</p>
                                    <p class="text-brand-ash text-[10px]">Host: {{ $pt->host?->name }} · {{ $pt->expected_arrival?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($pt->status === 'arrived')
                                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-[10px] text-emerald-400"><x-ui-icon name="check" /> Arrived</span>
                                    @elseif($pt->status === 'pending')
                                        <form method="POST" action="{{ route('portal.hr.pre-tickets.arrive', $pt) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="rounded-lg bg-sky-600/20 border border-sky-500/30 px-2 py-1 text-[10px] text-sky-400 hover:bg-sky-600/40 transition-all">
                                                Mark Arrived
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/30 italic text-center py-8">No visitor pre-tickets scheduled.</p>
                    @endforelse
                </div>
                @if($preTickets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $preTickets->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div></details>

    {{-- Phone & Vendor Directory ────────────────────────────────────────────── --}}
    <details data-hr-group="workplace" class="mt-6 glass-panel rounded-2xl p-0 border border-brand-white/10 bg-brand-white/5"><summary class="hr-module-summary"><span><strong>Contacts &amp; vendors</strong><span class="hr-module-description">Maintain the corporate contact directory.</span></span><span class="hr-module-chevron" aria-hidden="true">⌄</span></summary><div class="min-w-0 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Visitor Services</p>
                <h3 class="text-lg font-display text-brand-white uppercase"><x-ui-icon name="phone" /> Corporate Phone & Vendor Directory</h3>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Add Entry Form --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Add Directory Entry</h4>
                <form method="POST" action="{{ route('portal.hr.directory.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Name *</label>
                            <input type="text" name="name" required
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Full name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Category *</label>
                            <select name="category" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="staff">Staff</option>
                                <option value="vendor">Vendor</option>
                                <option value="client">Client</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Job Title</label>
                            <input type="text" name="job_title"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Company</label>
                            <input type="text" name="company"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Phone</label>
                            <input type="text" name="phone" maxlength="30"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="+233 XX XXX XXXX">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Email</label>
                            <input type="email" name="email"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                        Add to Directory
                    </button>
                </form>
            </div>
            {{-- Directory Listing --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Directory</h4>
                <div class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($directoryEntries ?? [] as $entry)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 px-4 py-3 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $entry->name }}</p>
                                <p class="text-brand-ash text-[10px]">{{ $entry->job_title }} · {{ $entry->company }}</p>
                                <p class="text-brand-ash text-[10px]"><x-ui-icon name="phone" /> {{ $entry->phone ?? '—' }} @if($entry->email) ·  {{ $entry->email }} @endif</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full border border-brand-white/10 px-2 py-0.5 text-[10px] text-brand-white/50 capitalize">{{ $entry->category }}</span>
                                <form method="POST" action="{{ route('portal.hr.directory.destroy', $entry) }}" data-confirm="Remove entry?">
                                    @csrf @method('DELETE')
                                    <button aria-label="Close" type="submit" class="text-brand-red/60 hover:text-brand-red transition-colors"><x-ui-icon name="close" /></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/30 italic text-center py-8">Directory is empty. Add entries using the form.</p>
                    @endforelse
                </div>
                @if($directoryEntries instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $directoryEntries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div></details>

    <script>
        function toggleHrAnnouncementAudience(value) {
            const departments = document.getElementById('announcement_departments');
            const staff = document.getElementById('announcement_staff');

            if (!departments || !staff) return;

            departments.classList.toggle('hidden', value !== 'departments');
            staff.classList.toggle('hidden', value !== 'selected');
        }
    </script>
    @include('portal.partials.hr-workspace-controller')
</x-app-layout>
