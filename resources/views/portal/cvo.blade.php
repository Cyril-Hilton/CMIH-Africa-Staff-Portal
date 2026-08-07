<x-app-layout>
    <x-slot name="header">
        {{-- No standard header, we build our own --}}
    </x-slot>

    <style>
        /* ── CVO Command Centre Styles ───────────────────────────────────── */
        .cvo-gold   { color: #F59E0B; }
        .cvo-gold-2 { color: #D97706; }

        .cvo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 2px 10px;
            border-radius: 99px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }
        .badge-pending  { background: rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.25); }
        .badge-cvo      { background: rgba(245,158,11,.15); color:#FBBF24; border:1px solid rgba(245,158,11,.3); }
        .badge-paid     { background: rgba(34,197,94,.15);  color:#4ADE80; border:1px solid rgba(34,197,94,.25); }
        .badge-rejected { background: rgba(100,116,139,.15);color:#94A3B8; border:1px solid rgba(100,116,139,.25); }
        .badge-flagged  { background: rgba(234,179,8,.15);  color:#EAB308; border:1px solid rgba(234,179,8,.25); }
        .badge-submitted{ background: rgba(99,102,241,.15); color:#818CF8; border:1px solid rgba(99,102,241,.25); }

        .kpi-card {
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.03);
            backdrop-filter: blur(10px);
            transition: transform .2s, border-color .2s;
        }
        .kpi-card:hover { transform: translateY(-3px); border-color: rgba(245,158,11,.3); }

        .kpi-card .kpi-label {
            font-size: .65rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: rgba(255,255,255,.45);
        }
        .kpi-card .kpi-value {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
            margin: .35rem 0 .2rem;
        }
        .kpi-card .kpi-sub { font-size: .7rem; color: rgba(255,255,255,.35); }

        .section-title {
            font-size: .65rem;
            letter-spacing: .25em;
            text-transform: uppercase;
            color: rgba(245,158,11,.8);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(245,158,11,.15);
        }

        .queue-row {
            display: grid;
            align-items: center;
            padding: .85rem 1rem;
            border-radius: 10px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.05);
            transition: background .15s, border-color .15s;
            gap: .5rem;
        }
        .queue-row:hover { background: rgba(255,255,255,.05); border-color: rgba(245,158,11,.2); }

        .approve-btn {
            padding: 4px 14px;
            border-radius: 8px;
            background: rgba(34,197,94,.15);
            color: #4ADE80;
            border: 1px solid rgba(34,197,94,.3);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .1em;
            cursor: pointer;
            transition: background .15s;
        }
        .approve-btn:hover { background: rgba(34,197,94,.25); }

        .reject-btn {
            padding: 4px 14px;
            border-radius: 8px;
            background: rgba(239,68,68,.1);
            color: #F87171;
            border: 1px solid rgba(239,68,68,.25);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .1em;
            cursor: pointer;
            transition: background .15s;
        }
        .reject-btn:hover { background: rgba(239,68,68,.2); }

        .cvo-hero-bar {
            background: linear-gradient(135deg, rgba(245,158,11,.08) 0%, rgba(0,0,0,0) 60%);
            border: 1px solid rgba(245,158,11,.15);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }
        .cvo-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 900;
            color: #000;
            flex-shrink: 0;
            box-shadow: 0 0 30px rgba(245,158,11,.3);
        }

        /* Dept bar chart bars */
        .dept-bar {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #F59E0B, #D97706);
            transition: width .5s ease;
        }

        @keyframes pulse-gold {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
            50% { box-shadow: 0 0 0 8px rgba(245,158,11,0); }
        }
        .cvo-pulse { animation: pulse-gold 2s infinite; }
    </style>

    {{-- ─── Hero / Greeting Bar ─────────────────────────────────────────────── --}}
    <div class="cvo-hero-bar">
        <div class="flex items-center gap-4">
            <div class="cvo-avatar cvo-pulse">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
            <div>
                <p class="text-xs uppercase tracking-[.3em] cvo-gold mb-1">Chief Visionary Officer</p>
                <h1 class="text-2xl font-extrabold text-white">{{ $user->name }}</h1>
                <p class="text-xs text-white/40 mt-1">{{ now()->format('l, F j, Y · g:i A') }}</p>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            @if ($pendingFinanceCount > 0)
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-amber-500/40 bg-amber-500/10 text-amber-300 text-sm font-bold">
                    ⚠️ {{ $pendingFinanceCount }} items awaiting your approval
                </div>
            @else
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-green-500/30 bg-green-500/10 text-green-400 text-sm font-semibold">
                    ✅ All financial items are clear
                </div>
            @endif
            <p class="text-xs text-white/30 mt-2">{{ $pendingLeaves->count() }} leave(s) pending your sign-off</p>
        </div>
    </div>

    {{-- ─── KPI Cards Row ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-10">
        <div class="kpi-card">
            <p class="kpi-label">Total Staff</p>
            <p class="kpi-value cvo-gold">{{ $totalStaff }}</p>
            <p class="kpi-sub">Active employees</p>
        </div>
        <div class="kpi-card">
            <p class="kpi-label">Total Tasks</p>
            <p class="kpi-value text-blue-400">{{ $totalTasks }}</p>
            <p class="kpi-sub">{{ $completedTasks }} completed</p>
        </div>
        <div class="kpi-card">
            <p class="kpi-label">Pending Tasks</p>
            <p class="kpi-value text-orange-400">{{ $pendingTasks }}</p>
            <p class="kpi-sub">{{ $overdueTasks }} overdue</p>
        </div>
        <div class="kpi-card">
            <p class="kpi-label">Finance Queue</p>
            <p class="kpi-value {{ $pendingFinanceCount > 0 ? 'text-red-400' : 'text-green-400' }}">{{ $pendingFinanceCount }}</p>
            <p class="kpi-sub">Awaiting CVO approval</p>
        </div>
        <div class="kpi-card">
            <p class="kpi-label">Leaves Pending</p>
            <p class="kpi-value text-purple-400">{{ $pendingLeaves->count() }}</p>
            <p class="kpi-sub">Awaiting sign-off</p>
        </div>
        <div class="kpi-card">
            <p class="kpi-label">Today's Visitors</p>
            <p class="kpi-value text-cyan-400">{{ $todayVisitors }}</p>
            <p class="kpi-sub">Checked in today</p>
        </div>
    </div>

    @include('portal.partials.identity-document-register', ['identityDocuments' => $identityDocuments])

    {{-- ─── Financial Totals Banner ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10">
        <div class="rounded-2xl border border-green-500/20 bg-green-500/5 p-5">
            <p class="text-[10px] uppercase tracking-widest text-green-400/60 mb-1">Total Paid Out (Claims)</p>
            <p class="text-2xl font-extrabold text-green-400">GH₵ {{ number_format($totalApprovedClaims, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-5">
            <p class="text-[10px] uppercase tracking-widest text-blue-400/60 mb-1">Total Paid Out (Invoices)</p>
            <p class="text-2xl font-extrabold text-blue-400">GH₵ {{ number_format($totalApprovedInvoices, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
            <p class="text-[10px] uppercase tracking-widest text-amber-400/60 mb-1">Total Value Pending Approval</p>
            <p class="text-2xl font-extrabold text-amber-400">GH₵ {{ number_format($totalPendingValue, 2) }}</p>
        </div>
    </div>

    {{-- ─── Main 2-Column Grid ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        {{-- LEFT: Finance Approval Queue --}}
        <div class="xl:col-span-2 space-y-8">

            {{-- ── Pending Claims ──────────────────────────────────────────── --}}
            <div>
                <p class="section-title">💰 Petty Cash / Reimbursement Claims — Awaiting Your Approval</p>
                @forelse ($pendingClaims as $claim)
                    <div class="queue-row mb-2" style="grid-template-columns: 1fr auto auto;">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $claim->user->name ?? 'Unknown' }}</p>
                            <div class="text-xs text-white/40 mt-1">{{ strip_tags((string) $claim->description) }}</div>
                            <p class="text-xs text-white/30 mt-1">Submitted {{ $claim->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="text-right mr-4">
                            <p class="text-base font-extrabold cvo-gold">{{ ($claim->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($claim->currency ?? 'GH₵') }} {{ number_format($claim->amount, 2) }}</p>
                            <span class="cvo-badge badge-pending">{{ $claim->status }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('portal.finance.claims.cvo.action', [$claim->id, 'approve']) }}">
                                @csrf
                                <button type="submit" class="approve-btn">✅ Approve</button>
                            </form>
                            <form method="POST" action="{{ route('portal.finance.claims.cvo.action', [$claim->id, 'reject']) }}">
                                @csrf
                                <button type="submit" class="reject-btn" onclick="return confirm('Reject this claim?')">✗ Reject</button>
                            </form>
                            <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('cvo-ret-claim-{{ $claim->id }}'); f.notes.value = note; f.submit(); }"
                                    class="px-2.5 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px] font-semibold uppercase tracking-wider hover:bg-cyan-500/25">
                                🔄 Return
                            </button>
                            <form id="cvo-ret-claim-{{ $claim->id }}" method="POST" action="{{ route('portal.finance.claims.cvo.action', [$claim->id, 'return']) }}" class="hidden">
                                @csrf
                                <input type="hidden" name="notes" value="">
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-white/30 italic py-4 text-center">No pending claims awaiting approval.</p>
                @endforelse

                @if ($cvoApprovedClaims->count() > 0)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-white/40 hover:text-white/70 mb-2">Show CVO-Approved (Finance processing) → {{ $cvoApprovedClaims->count() }}</summary>
                        <div class="mt-2 space-y-2">
                            @foreach ($cvoApprovedClaims as $claim)
                                <div class="queue-row" style="grid-template-columns: 1fr auto auto;">
                                    <div>
                                        <p class="text-sm font-semibold text-white">{{ $claim->user->name ?? 'Unknown' }}</p>
                                        <div class="text-xs text-white/40 mt-1">{{ strip_tags((string) $claim->description) }}</div>
                                    </div>
                                    <p class="text-sm font-bold cvo-gold">{{ ($claim->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($claim->currency ?? 'GH₵') }} {{ number_format($claim->amount, 2) }}</p>
                                    <span class="cvo-badge badge-cvo">CVO Approved</span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>

            {{-- ── Pending Budgets ─────────────────────────────────────────── --}}
            <div>
                <p class="section-title">📊 Project Budgets — Submitted for CVO Review</p>
                @forelse ($pendingBudgets as $budget)
                    <div class="queue-row mb-2" style="grid-template-columns: 1fr auto auto auto;">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $budget->title }}</p>
                            <p class="text-xs text-white/40">By {{ $budget->creator->name ?? 'N/A' }} · {{ $budget->created_at->diffForHumans() }}</p>
                            @if ($budget->notes)
                                <div class="text-xs text-white/30 mt-1">{!! $budget->notes !!}</div>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-base font-extrabold cvo-gold">{{ ($budget->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($budget->currency ?? 'GH₵') }} {{ number_format($budget->total_amount, 2) }}</p>
                            <span class="cvo-badge badge-submitted">Submitted</span>
                        </div>
                        <form method="POST" action="{{ route('portal.finance.budgets.cvo.action', [$budget->id, 'approve']) }}">
                            @csrf
                            <button type="submit" class="approve-btn">✅ Approve</button>
                        </form>
                        <form method="POST" action="{{ route('portal.finance.budgets.cvo.action', [$budget->id, 'reject']) }}">
                            @csrf
                            <button type="submit" class="reject-btn" onclick="return confirm('Reject this budget?')">✗ Reject</button>
                        </form>
                    </div>
                @empty
                    <p class="text-xs text-white/30 italic py-4 text-center">No budgets awaiting review.</p>
                @endforelse
            </div>

            {{-- ── Pending Invoices ────────────────────────────────────────── --}}
            <div>
                <p class="section-title">🧾 Supplier Invoices — Awaiting Your Approval</p>
                @forelse ($pendingInvoices as $invoice)
                    <div class="queue-row mb-2" style="grid-template-columns: 1fr auto auto;">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $invoice->supplier_name }}</p>
                            <p class="text-xs text-white/40">Inv# {{ $invoice->invoice_number ?? 'N/A' }} · {{ $invoice->created_at->diffForHumans() }}</p>
                            <div class="text-xs text-white/30 mt-1">{{ strip_tags((string) $invoice->description) }}</div>
                        </div>
                        <div class="text-right mr-4">
                            <p class="text-base font-extrabold cvo-gold">{{ ($invoice->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($invoice->currency ?? 'GH₵') }} {{ number_format($invoice->amount, 2) }}</p>
                            <span class="cvo-badge badge-pending">{{ $invoice->status }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('portal.finance.invoices.cvo.action', [$invoice->id, 'approve']) }}">
                                @csrf
                                <button type="submit" class="approve-btn">✅ Approve</button>
                            </form>
                            <form method="POST" action="{{ route('portal.finance.invoices.cvo.action', [$invoice->id, 'reject']) }}">
                                @csrf
                                <button type="submit" class="reject-btn" onclick="return confirm('Reject this invoice?')">✗ Reject</button>
                            </form>
                            <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('cvo-ret-invoice-{{ $invoice->id }}'); f.notes.value = note; f.submit(); }"
                                    class="px-2.5 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px] font-semibold uppercase tracking-wider hover:bg-cyan-500/25">
                                🔄 Return
                            </button>
                            <form id="cvo-ret-invoice-{{ $invoice->id }}" method="POST" action="{{ route('portal.finance.invoices.cvo.action', [$invoice->id, 'return']) }}" class="hidden">
                                @csrf
                                <input type="hidden" name="notes" value="">
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-white/30 italic py-4 text-center">No invoices awaiting approval.</p>
                @endforelse

                @if ($cvoApprovedInvoices->count() > 0)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-white/40 hover:text-white/70 mb-2">Show CVO-Approved invoices → {{ $cvoApprovedInvoices->count() }}</summary>
                        <div class="mt-2 space-y-2">
                            @foreach ($cvoApprovedInvoices as $inv)
                                <div class="queue-row" style="grid-template-columns: 1fr auto auto;">
                                    <div>
                                        <p class="text-sm font-semibold text-white">{{ $inv->supplier_name }}</p>
                                        <div class="text-xs text-white/40 mt-1">{{ strip_tags((string) $inv->description) }}</div>
                                    </div>
                                    <p class="text-sm font-bold cvo-gold">{{ ($inv->currency ?? 'GH₵') === 'GHC' ? 'GH₵' : ($inv->currency ?? 'GH₵') }} {{ number_format($inv->amount, 2) }}</p>
                                    <span class="cvo-badge badge-cvo">CVO Approved</span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>

            {{-- ── Department Performance ─────────────────────────────────── --}}
            @if ($deptTaskStats->count() > 0)
            <div>
                <p class="section-title">📈 Department Task Completion Rates</p>
                <div class="space-y-3">
                    @foreach ($deptTaskStats->sortByDesc('rate') as $stat)
                        <div class="rounded-xl p-4 bg-white/3 border border-white/5">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-white uppercase tracking-wider">{{ str_replace(['_', '-'], ' ', $stat['dept']) }}</p>
                                <p class="text-xs text-white/50">{{ $stat['completed'] }}/{{ $stat['total'] }} · <span class="{{ $stat['rate'] >= 70 ? 'text-green-400' : ($stat['rate'] >= 40 ? 'text-amber-400' : 'text-red-400') }} font-bold">{{ $stat['rate'] }}%</span></p>
                            </div>
                            <div class="w-full h-2 rounded-full bg-white/5">
                                <div class="dept-bar" style="width: {{ $stat['rate'] }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- RIGHT: Leaves, Appraisals, Announcements --}}
        <div class="space-y-8">

            {{-- ── Pending Leaves ──────────────────────────────────────────── --}}
            <div>
                <p class="section-title">🏖️ Pending Leave Requests</p>
                @forelse ($pendingLeaves as $leave)
                    <div class="rounded-xl p-4 bg-white/3 border border-white/5 mb-2">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-semibold text-white">{{ $leave->user->name ?? 'N/A' }}</p>
                            <span class="cvo-badge badge-submitted text-[9px]">{{ str_replace('_', ' ', ucwords($leave->status)) }}</span>
                        </div>
                        <p class="text-xs text-white/50">{{ $leave->leave_type ?? $leave->type ?? 'Leave' }} · {{ isset($leave->start_date) ? \Carbon\Carbon::parse($leave->start_date)->format('M d') : 'N/A' }} – {{ isset($leave->end_date) ? \Carbon\Carbon::parse($leave->end_date)->format('M d, Y') : 'N/A' }}</p>
                        <p class="mt-1 text-[10px] text-white/35">Cover: {{ $leave->coveringStaff?->name ?? 'None' }} | Manager: {{ $leave->lineManager?->name ?? 'Direct approval' }}</p>
                        <p class="text-xs text-white/30 mt-1 line-clamp-2">{{ trim(strip_tags($leave->comments ?? '')) ?: 'No reason provided.' }}</p>
                        <div class="mt-3 flex gap-2">
                            <form method="POST" action="{{ route('portal.leaves.approve', $leave->id) }}">
                                @csrf
                                <button class="approve-btn text-[10px]">✅ Approve</button>
                            </form>
                            <form method="POST" action="{{ route('portal.leaves.reject', $leave->id) }}">
                                @csrf
                                <button class="reject-btn text-[10px]">✗ Reject</button>
                            </form>
                            <button type="button" onclick="const note = prompt('Enter correction reason:'); if (note) { const f = document.getElementById('cvo-ret-leave-{{ $leave->id }}'); f.rejection_comments.value = note; f.submit(); }"
                                    class="px-2.5 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px] font-semibold uppercase tracking-wider hover:bg-cyan-500/25">
                                🔄 Return
                            </button>
                            <form id="cvo-ret-leave-{{ $leave->id }}" method="POST" action="{{ route('portal.leaves.return', $leave->id) }}" class="hidden">
                                @csrf
                                <input type="hidden" name="rejection_comments" value="">
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-white/30 italic py-3 text-center">No leaves pending your approval.</p>
                @endforelse
            </div>

            {{-- ── Appraisals ──────────────────────────────────────────────── --}}
            @if ($pendingAppraisals->count() > 0)
            <div>
                <p class="section-title">⭐ Appraisals Awaiting Review</p>
                @foreach ($pendingAppraisals as $ap)
                    <div class="rounded-xl p-4 bg-white/3 border border-white/5 mb-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $ap->user->name ?? 'N/A' }}</p>
                                <p class="text-xs text-white/40">{{ $ap->period ?? 'Appraisal Period' }}</p>
                            </div>
                            <span class="cvo-badge badge-submitted text-[9px]">{{ str_replace('_',' ', $ap->status) }}</span>
                        </div>
                        <a href="{{ route('portal.appraisals.index') }}" class="mt-2 block text-xs text-amber-400/70 hover:text-amber-400">View appraisal →</a>
                    </div>
                @endforeach
            </div>
            @endif

            {{-- ── Staff by Dept ────────────────────────────────────────────── --}}
            <div>
                <p class="section-title">👥 Staff Distribution</p>
                @php
                    $totalForChart = array_sum($staffByDept) ?: 1;
                    $deptNames = [
                        'finance' => 'Finance',
                        'hr_admin' => 'HR & Admin',
                        'operations_projects' => 'Operations',
                        'brands_marketing' => 'Brands & Mktg',
                        'creatives' => 'Creatives',
                        'client_relations' => 'Client Rel.',
                    ];
                @endphp
                @foreach ($staffByDept as $dept => $count)
                    @php $label = $deptNames[$dept] ?? ucwords(str_replace('_', ' ', $dept)); $pct = round(($count / $totalForChart) * 100); @endphp
                    <div class="rounded-xl p-3 bg-white/3 border border-white/5 mb-2">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-white/70 font-medium">{{ $label }}</p>
                            <p class="text-xs text-white/40">{{ $count }} ({{ $pct }}%)</p>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-white/5">
                            <div class="dept-bar" style="width: {{ $pct }}%;"></div>
                        </div>
                    </div>
                @endforeach
                @if (empty($staffByDept))
                    <p class="text-xs text-white/30 italic text-center py-3">No department data yet.</p>
                @endif
            </div>

            {{-- ── Recent Announcements ─────────────────────────────────────── --}}
            @if ($recentAnnouncements->count() > 0)
            <div>
                <p class="section-title">📢 Recent Announcements</p>
                @foreach ($recentAnnouncements as $ann)
                    <div class="rounded-xl p-3 bg-white/3 border border-white/5 mb-2">
                        <p class="text-sm font-semibold text-white line-clamp-1">{{ $ann->title }}</p>
                        <p class="text-xs text-white/35 mt-0.5">{{ $ann->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
                <a href="{{ route('portal.announcements') }}" class="block text-center text-xs text-amber-400/70 hover:text-amber-400 mt-2">View all announcements →</a>
            </div>
            @endif

            {{-- ── Quick Links ──────────────────────────────────────────────── --}}
            <div>
                <p class="section-title">🔗 Quick Links</p>
                <div class="grid grid-cols-2 gap-2">
                    @php $links = [
                        ['label'=>'Finance Module', 'route'=>'portal.finance', 'icon'=>'💰'],
                        ['label'=>'HR & Admin',     'route'=>'portal.hr',      'icon'=>'👥'],
                        ['label'=>'Appraisals',     'route'=>'portal.appraisals.index', 'icon'=>'⭐'],
                        ['label'=>'Leave Mgmt',     'route'=>'portal.leaves',  'icon'=>'🏖️'],
                        ['label'=>'Team Directory',  'route'=>'portal.directory','icon'=>'📇'],
                        ['label'=>'Admin Panel',    'route'=>'admin.dashboard', 'icon'=>'⚙️'],
                    ]; @endphp
                    @foreach ($links as $lnk)
                        <a href="{{ route($lnk['route']) }}" class="rounded-xl p-3 bg-white/3 border border-white/5 hover:border-amber-500/20 hover:bg-white/5 transition text-center text-xs text-white/60 hover:text-white">
                            <div class="text-lg mb-1">{{ $lnk['icon'] }}</div>
                            {{ $lnk['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="fixed bottom-6 right-6 z-50 rounded-xl bg-green-600/90 text-white px-6 py-4 text-sm shadow-xl backdrop-blur" id="cvo-toast">
            {{ session('status') }}
        </div>
        <script>setTimeout(() => document.getElementById('cvo-toast')?.remove(), 4000);</script>
    @endif

    @if ($errors->any())
        <div class="fixed bottom-6 right-6 z-50 rounded-xl bg-red-600/90 text-white px-6 py-4 text-sm shadow-xl backdrop-blur" id="cvo-err-toast">
            {{ $errors->first() }}
        </div>
        <script>setTimeout(() => document.getElementById('cvo-err-toast')?.remove(), 5000);</script>
    @endif

</x-app-layout>
