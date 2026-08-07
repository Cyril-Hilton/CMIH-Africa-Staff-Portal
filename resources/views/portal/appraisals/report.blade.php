<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Accountability Report - {{ $user->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>
        body {
            font-family: 'Sora', sans-serif;
            background-color: #0f0f12;
            color: #f3f4f6;
        }
        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-card {
                background: #ffffff !important;
                border: 1px solid #e5e7eb !important;
                color: #000000 !important;
            }
            .print-text-muted {
                color: #4b5563 !important;
            }
            .print-border {
                border-color: #d1d5db !important;
            }
            .print-bg-gray {
                background-color: #f3f4f6 !important;
            }
            .chart-bar {
                background-color: #d97706 !important; /* Gold/amber in print */
            }
            @page {
                size: landscape;
                margin: 0.8cm;
            }
        }
        .chart-bar {
            transition: height 0.5s ease-in-out;
        }
    </style>
</head>
<body class="p-6 md:p-10">
    {{-- Control Panel --}}
    <div class="no-print max-w-7xl mx-auto mb-8 flex justify-between items-center bg-brand-white/5 border border-brand-white/10 rounded-2xl p-4 glass-panel">
        <div>
            <h1 class="text-lg font-bold text-white">Performance Accountability Report</h1>
            <p class="text-xs text-gray-400">Generate a physical copy of this staff member's ledger and KPIs.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('portal.appraisals.index') }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider rounded-xl bg-gray-800 hover:bg-gray-700 text-white transition-all">
                ← Back
            </a>
            <button onclick="window.print()" class="px-5 py-2 text-xs font-semibold uppercase tracking-wider rounded-xl bg-amber-500 hover:bg-amber-600 text-black font-bold transition-all shadow-lg shadow-amber-500/20">
                🖨️ Print Report
            </button>
        </div>
    </div>

    {{-- Report Page Container --}}
    <div class="max-w-7xl mx-auto bg-[#141418] print-card border border-brand-white/10 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
        {{-- Luxury Accent Lines --}}
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-600"></div>

        {{-- Header Section --}}
        <div class="flex flex-wrap justify-between items-start gap-6 border-b border-brand-white/10 pb-6 print-border">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-amber-500">Cyril-Hilton M&I House</p>
                <h2 class="text-2xl font-bold tracking-tight text-white mt-1">STAFF PERFORMANCE LEDGER</h2>
                <p class="text-xs text-gray-400 mt-1 print-text-muted">Accountability Sheet & Appraisal Audit Dossier</p>
            </div>
            <div class="text-right text-xs">
                <p class="text-gray-400 print-text-muted">Report Date: <span class="text-white font-semibold">{{ now()->format('d M Y, h:i A') }}</span></p>
                <p class="text-gray-400 print-text-muted mt-1">Authentication: <span class="text-amber-500 font-mono">PAR-{{ strtoupper(uniqid()) }}</span></p>
            </div>
        </div>

        {{-- Staff Bio Information --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 my-6 bg-brand-white/5 print-bg-gray rounded-2xl p-5 border border-brand-white/5 print-border">
            <div>
                <p class="text-[10px] uppercase tracking-wider text-gray-400 print-text-muted">Staff Name</p>
                <p class="text-sm font-bold text-white mt-0.5">{{ $user->name }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-wider text-gray-400 print-text-muted">Department</p>
                <p class="text-sm font-bold text-amber-500 mt-0.5">{{ $user->department ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-wider text-gray-400 print-text-muted">Access Role</p>
                <p class="text-sm font-bold text-white mt-0.5">{{ ucfirst($user->access_role) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-wider text-gray-400 print-text-muted">Email Address</p>
                <p class="text-sm font-bold text-white mt-0.5">{{ $user->email }}</p>
            </div>
        </div>

        {{-- KPI Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {{-- Task Card --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs uppercase tracking-wider text-gray-400 print-text-muted">Task Completion Velocity</span>
                        <span class="text-emerald-500 text-xs font-semibold">Rate: {{ $completionRate }}%</span>
                    </div>
                    <div class="text-3xl font-bold mt-2 text-white">{{ $completedCount }} / {{ $totalTasks }}</div>
                    <p class="text-xs text-gray-400 print-text-muted mt-1">Total assigned tasks in queue</p>
                </div>
                <div class="border-t border-brand-white/5 print-border mt-4 pt-3 flex justify-between text-xs">
                    <span class="text-emerald-400">On Time: <strong>{{ $completedEarly }}</strong></span>
                    <span class="text-amber-400">Late: <strong>{{ $completedLate }}</strong></span>
                    <span class="text-gray-400 print-text-muted">Pending: <strong>{{ $pendingCount }}</strong></span>
                </div>
            </div>

            {{-- Punctuality Card --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs uppercase tracking-wider text-gray-400 print-text-muted">Punctuality Score</span>
                        <span class="text-sky-400 text-xs font-semibold">On Time: {{ $punctuality }}%</span>
                    </div>
                    <div class="text-3xl font-bold mt-2 text-white">{{ $totalDays > 1 ? $totalDays : 0 }} Days</div>
                    <p class="text-xs text-gray-400 print-text-muted mt-1">Total attendance check-in instances</p>
                </div>
                <div class="border-t border-brand-white/5 print-border mt-4 pt-3 flex justify-between text-xs">
                    <span class="text-rose-400">Latenesses: <strong>{{ $latenesses }}</strong></span>
                    <span class="text-amber-400">Avg Late Delay: <strong>{{ $avgDelayMinutes }} mins</strong></span>
                </div>
            </div>

            {{-- Overtime Card --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs uppercase tracking-wider text-gray-400 print-text-muted">Overtime Accumulation</span>
                        <span class="text-amber-400 text-xs font-semibold">GH₵ Cost Equivalent</span>
                    </div>
                    <div class="text-3xl font-bold mt-2 text-amber-500">{{ $overtimeHours }} Hours</div>
                    <p class="text-xs text-gray-400 print-text-muted mt-1">Total approved overtime logged</p>
                </div>
                <div class="border-t border-brand-white/5 print-border mt-4 pt-3 text-xs text-gray-400 print-text-muted">
                    Active overtime is automatically computed post 6:00 PM check-out.
                </div>
            </div>
        </div>

        {{-- Core Details Split: Recent Tasks & Attendance Logs --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {{-- Tasks Ledger --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4 flex items-center gap-2">
                    <span>📋</span> Task Completion Velocity Ledger
                </h3>
                <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 print-border text-gray-400 print-text-muted">
                                <th class="py-2">Task Title</th>
                                <th class="py-2">Due Date</th>
                                <th class="py-2">Status</th>
                                <th class="py-2 text-right">Completion Velocity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 print-border">
                            @forelse($allTasks->take(15) as $task)
                                <tr>
                                    <td class="py-2.5 pr-2 font-medium text-white min-w-[180px]">{{ $task->title }}</td>
                                    <td class="py-2.5 text-gray-400 print-text-muted">{{ $task->due_on ? $task->due_on->format('d M Y') : 'N/A' }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($task->isApprovedForPerformance()) bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                            @else bg-amber-500/10 text-amber-400 border border-amber-500/20 @endif">
                                            {{ $task->status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-right">
                                        @if($task->isApprovedForPerformance())
                                            @if($task->due_on)
                                                @php
                                                    $completedDate = \Illuminate\Support\Carbon::parse($task->updated_at)->startOfDay();
                                                    $dueDate = \Illuminate\Support\Carbon::parse($task->due_on)->startOfDay();
                                                    $isEarly = $completedDate->lte($dueDate);
                                                @endphp
                                                @if($isEarly)
                                                    <span class="text-emerald-400">On Time / Early</span>
                                                @else
                                                    <span class="text-rose-400">Late ({{ $completedDate->diffInDays($dueDate) }}d)</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 print-text-muted">On Time</span>
                                            @endif
                                        @else
                                            <span class="text-amber-500 italic">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-500 italic">No tasks assigned to this staff member.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Attendance Ledger --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4 flex items-center gap-2">
                    <span>⏰</span> Attendance & Overtime Ledger
                </h3>
                <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 print-border text-gray-400 print-text-muted">
                                <th class="py-2">Date</th>
                                <th class="py-2">Clock In</th>
                                <th class="py-2">Clock Out</th>
                                <th class="py-2">Lateness</th>
                                <th class="py-2 text-right">Overtime</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 print-border">
                            @forelse($allAttendances->take(15) as $att)
                                <tr>
                                    <td class="py-2.5 font-medium text-white">{{ $att->clock_in_at ? $att->clock_in_at->format('d M Y') : 'N/A' }}</td>
                                    <td class="py-2.5 text-gray-400 print-text-muted">{{ $att->clock_in_at ? $att->clock_in_at->format('h:i A') : 'N/A' }}</td>
                                    <td class="py-2.5 text-gray-400 print-text-muted">{{ $att->clock_out_at ? $att->clock_out_at->format('h:i A') : 'Pending' }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold
                                            @if($att->status === 'Late') bg-rose-500/10 text-rose-400 border border-rose-500/20
                                            @else bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 @endif">
                                            {{ $att->status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-right font-semibold text-amber-400">
                                        {{ $att->overtime_minutes > 0 ? round($att->overtime_minutes / 60, 1) . ' hrs' : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-500 italic">No attendance records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Monthly Overtime Chart & Appraisals History --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {{-- Pure CSS Overtime Chart --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4">
                    📈 Monthly Overtime Log (Last 6 Months)
                </h3>
                <div class="flex items-end justify-between gap-2 h-48 pt-6 border-b border-brand-white/10 print-border px-4">
                    @forelse($monthlyOvertime as $month => $hours)
                        <div class="flex flex-col items-center flex-1 group">
                            <span class="touch-visible text-[10px] text-amber-400 font-bold mb-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 print:opacity-100">
                                {{ $hours }}h
                            </span>
                            @php
                                $maxHours = max(1, $monthlyOvertime->max());
                                $percentage = ($hours / $maxHours) * 100;
                            @endphp
                            <div style="height: {{ max(6, $percentage) }}%" class="w-12 bg-amber-500/80 group-hover:bg-amber-400 rounded-t-lg chart-bar flex items-center justify-center">
                                @if($percentage > 20)
                                    <span class="text-[9px] text-black font-bold rotate-90 lg:rotate-0">{{ $hours }}h</span>
                                @endif
                            </div>
                            <span class="text-[9px] text-gray-400 print-text-muted mt-2 truncate max-w-full text-center">{{ $month }}</span>
                        </div>
                    @empty
                        <div class="w-full flex items-center justify-center text-gray-500 italic h-full pb-8">
                            No overtime hours recorded.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Appraisal History --}}
            <div class="bg-brand-white/5 print-card border border-brand-white/10 rounded-2xl p-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4">
                    🏛️ Approved Appraisal History
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 print-border text-gray-400 print-text-muted">
                                <th class="py-2">Cycle (Quarter/Year)</th>
                                <th class="py-2">Self Score</th>
                                <th class="py-2">Manager Score</th>
                                <th class="py-2 text-right">Final Approved Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 print-border">
                            @forelse($appraisals as $ap)
                                <tr>
                                    <td class="py-3 font-semibold text-white">{{ $ap->quarter }} {{ $ap->year }}</td>
                                    <td class="py-3 text-emerald-400 font-bold">{{ $ap->avg_self_score ?? '-' }} / 10</td>
                                    <td class="py-3 text-sky-400 font-bold">{{ $ap->avg_manager_score ?? '-' }} / 10</td>
                                    <td class="py-3 text-right text-amber-500 font-bold text-sm">{{ $ap->final_score ?? '-' }} / 10</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-500 italic">No approved appraisals found for this staff member.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Sign-Off Matrix --}}
        <div class="mt-12 pt-8 border-t border-brand-white/10 print-border">
            <h3 class="text-xs font-bold uppercase tracking-widest text-amber-500 mb-8 text-center">
                Review & Validation Signatures
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                {{-- Employee --}}
                <div class="flex flex-col items-center">
                    <div class="w-48 border-b border-brand-white/20 print-border h-10 flex items-end justify-center">
                        <span class="text-[10px] text-gray-500 italic print:hidden">System Authenticated</span>
                    </div>
                    <p class="text-xs font-bold text-white mt-2">{{ $user->name }}</p>
                    <p class="text-[10px] text-gray-400 print-text-muted">Staff Member</p>
                </div>

                {{-- Manager --}}
                <div class="flex flex-col items-center">
                    <div class="w-48 border-b border-brand-white/20 print-border h-10"></div>
                    <p class="text-xs font-bold text-white mt-2">__________________________</p>
                    <p class="text-[10px] text-gray-400 print-text-muted">Line Manager / Reviewer</p>
                </div>

                {{-- CVO --}}
                <div class="flex flex-col items-center">
                    <div class="w-48 border-b border-brand-white/20 print-border h-10 flex items-end justify-center">
                        <span class="text-xs font-serif italic text-amber-500/60 font-bold">Cyril Hilton</span>
                    </div>
                    <p class="text-xs font-bold text-white mt-2">Chief Vision Officer (CVO)</p>
                    <p class="text-[10px] text-gray-400 print-text-muted">Executive Oversight Sign-off</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
