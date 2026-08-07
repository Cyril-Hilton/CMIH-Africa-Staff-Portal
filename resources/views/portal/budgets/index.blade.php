<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
                <h2 class="text-3xl font-display text-brand-white">📊 Project Budgets</h2>
            </div>
            <a href="{{ route('portal.finance.budgets.create') }}" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-4 py-2.5 text-xs uppercase tracking-[0.2em] font-bold text-white transition-all">
                + Create Project Budget
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div x-data="{ activeTab: 'my-budgets' }" class="space-y-6">
        <!-- Tab navigation -->
        <div class="flex border-b border-brand-white/10 space-x-4 mb-6">
            <button @click="activeTab = 'my-budgets'" :class="activeTab === 'my-budgets' ? 'border-amber-500 text-amber-500 font-semibold' : 'border-transparent text-brand-white/60 hover:text-brand-white'" class="py-3 px-4 border-b-2 text-sm uppercase tracking-wider transition-colors focus:outline-none">
                📁 My Budgets
            </button>
            <button @click="activeTab = 'shared-budgets'" :class="activeTab === 'shared-budgets' ? 'border-amber-500 text-amber-500 font-semibold' : 'border-transparent text-brand-white/60 hover:text-brand-white'" class="py-3 px-4 border-b-2 text-sm uppercase tracking-wider transition-colors focus:outline-none">
                🤝 Shared Budgets
            </button>
            @if(strtolower(trim(auth()->user()->department ?? '')) === 'finance' || auth()->user()->access_role === 'super_admin' || auth()->user()->job_level === 'super_admin')
                <button @click="activeTab = 'review-queue'" :class="activeTab === 'review-queue' ? 'border-amber-500 text-amber-500 font-semibold' : 'border-transparent text-brand-white/60 hover:text-brand-white'" class="py-3 px-4 border-b-2 text-sm uppercase tracking-wider transition-colors focus:outline-none">
                    📥 Review Queue
                </button>
            @endif
        </div>

        @php
            $badges = [
                'Draft'                  => 'bg-gray-500/10 border-gray-500/20 text-gray-400',
                'Submitted'              => 'bg-amber-500/10 border-amber-500/20 text-amber-400',
                'Submitted to Finance'   => 'bg-blue-500/10 border-blue-500/20 text-blue-400',
                'Finance Approved'       => 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400',
                'CVO Approved'           => 'bg-purple-500/10 border-purple-500/20 text-purple-400',
                'Rejected'               => 'bg-brand-red/10 border-brand-red/20 text-brand-red',
                'Returned for Correction'=> 'bg-amber-500/10 border-amber-500/20 text-amber-400',
                'Returned to Finance'    => 'bg-orange-500/10 border-orange-500/20 text-orange-400',
                'Updated'                => 'bg-teal-500/10 border-teal-500/20 text-teal-400',
            ];
        @endphp

        <!-- TAB 1: My Budgets -->
        <div x-show="activeTab === 'my-budgets'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📁 Budgets Created By Me</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70">
                    <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3">Title</th>
                            <th class="py-3">Associated Task</th>
                            <th class="py-3">Currency</th>
                            <th class="py-3">Total Amount</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Created Date</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($myBudgets as $budget)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-4 font-semibold text-brand-white">
                                    <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="hover:underline">
                                        {{ $budget->title }}
                                    </a>
                                </td>
                                <td class="py-4 text-brand-ash">{{ $budget->task?->title ?? '—' }}</td>
                                <td class="py-4 font-mono font-bold">{{ $budget->currency }}</td>
                                <td class="py-4 text-emerald-400 font-bold">{{ number_format($budget->total_amount, 2) }}</td>
                                <td class="py-4">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase font-bold {{ $badges[$budget->status] ?? 'bg-white/10 border-white/20' }}">
                                        {{ $budget->status }}
                                    </span>
                                </td>
                                <td class="py-4 text-brand-white/50">{{ $budget->created_at->format('M d, Y') }}</td>
                                <td class="py-4 text-right">
                                    <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="rounded bg-brand-white/10 hover:bg-brand-white/20 px-2.5 py-1 text-[10px] uppercase font-bold text-brand-white">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-brand-white/40 italic">You have not created any project budgets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($myBudgets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 pt-4 border-t border-brand-white/10">
                    {{ $myBudgets->links() }}
                </div>
            @endif
        </div>

        <!-- TAB 2: Shared Budgets -->
        <div x-show="activeTab === 'shared-budgets'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5" style="display: none;">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🤝 Budgets Shared With Me</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70">
                    <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3">Title</th>
                            <th class="py-3">Owner</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Total Amount</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Last Updated</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($sharedBudgets as $budget)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-4 font-semibold text-brand-white">
                                    <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="hover:underline">
                                        {{ $budget->title }}
                                    </a>
                                </td>
                                <td class="py-4 text-brand-white/60">{{ $budget->creator?->name }}</td>
                                <td class="py-4 text-brand-ash capitalize font-semibold">
                                    {{ $budget->collaborators()->where('users.id', auth()->id())->first()?->pivot?->permission ?? 'View' }} Permission
                                </td>
                                <td class="py-4 text-emerald-400 font-bold">{{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}</td>
                                <td class="py-4">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase font-bold {{ $badges[$budget->status] ?? 'bg-white/10 border-white/20' }}">
                                        {{ $budget->status }}
                                    </span>
                                </td>
                                <td class="py-4 text-brand-white/50">{{ $budget->updated_at->format('M d, Y') }}</td>
                                <td class="py-4 text-right">
                                    <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="rounded bg-brand-white/10 hover:bg-brand-white/20 px-2.5 py-1 text-[10px] uppercase font-bold text-brand-white">
                                        View details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-brand-white/40 italic">No project budgets have been shared with you yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sharedBudgets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 pt-4 border-t border-brand-white/10">
                    {{ $sharedBudgets->links() }}
                </div>
            @endif
        </div>

        <!-- TAB 3: Review Queue -->
        @if(strtolower(trim(auth()->user()->department ?? '')) === 'finance' || auth()->user()->access_role === 'super_admin' || auth()->user()->job_level === 'super_admin')
            <div x-show="activeTab === 'review-queue'" class="space-y-6" style="display: none;">
                <!-- Pending review list -->
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📥 Budgets Awaiting Review</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-3">Title</th>
                                    <th class="py-3">Creator</th>
                                    <th class="py-3">Total Amount</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Submission Date</th>
                                    <th class="py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse($pendingBudgets as $budget)
                                    <tr class="hover:bg-brand-white/5 transition-colors">
                                        <td class="py-4 font-semibold text-brand-white">
                                            <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="hover:underline">
                                                {{ $budget->title }}
                                            </a>
                                        </td>
                                        <td class="py-4 text-brand-white/60">{{ $budget->creator?->name }}</td>
                                        <td class="py-4 text-emerald-400 font-bold font-mono">{{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}</td>
                                        <td class="py-4">
                                            <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase font-bold {{ $badges[$budget->status] ?? 'bg-white/10 border-white/20' }}">
                                                {{ $budget->status }}
                                            </span>
                                        </td>
                                        <td class="py-4 text-brand-white/50">{{ $budget->updated_at->format('M d, Y') }}</td>
                                        <td class="py-4 text-right">
                                            <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="rounded bg-amber-500/20 hover:bg-amber-500/30 px-2.5 py-1 text-[10px] uppercase font-bold text-amber-400">
                                                Review
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-xs text-brand-white/40 italic">No budgets currently in the queue.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($pendingBudgets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4 pt-4 border-t border-brand-white/10">
                            {{ $pendingBudgets->links() }}
                        </div>
                    @endif
                </div>

                <!-- Review history list -->
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📜 Review History / Processed Budgets</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-3">Title</th>
                                    <th class="py-3">Creator</th>
                                    <th class="py-3">Total Amount</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Last Updated</th>
                                    <th class="py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse($historyBudgets as $budget)
                                    <tr class="hover:bg-brand-white/5 transition-colors">
                                        <td class="py-4 font-semibold text-brand-white">
                                            <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="hover:underline">
                                                {{ $budget->title }}
                                            </a>
                                        </td>
                                        <td class="py-4 text-brand-white/60">{{ $budget->creator?->name }}</td>
                                        <td class="py-4 text-emerald-400 font-bold font-mono">{{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}</td>
                                        <td class="py-4">
                                            <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase font-bold {{ $badges[$budget->status] ?? 'bg-white/10 border-white/20' }}">
                                                {{ $budget->status }}
                                            </span>
                                        </td>
                                        <td class="py-4 text-brand-white/50">{{ $budget->updated_at->format('M d, Y') }}</td>
                                        <td class="py-4 text-right">
                                            <a href="{{ route('portal.finance.budgets.show', $budget) }}" class="rounded bg-brand-white/10 hover:bg-brand-white/20 px-2.5 py-1 text-[10px] uppercase font-bold text-brand-white">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-xs text-brand-white/40 italic">No processed budgets in history.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($historyBudgets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4 pt-4 border-t border-brand-white/10">
                            {{ $historyBudgets->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
