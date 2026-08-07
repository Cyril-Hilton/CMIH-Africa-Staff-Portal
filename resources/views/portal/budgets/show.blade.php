<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
                <h2 class="text-3xl font-display text-brand-white">{{ $budget->title }}</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('portal.finance.budgets.index') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">
                    Back to Budgets
                </a>
                @if ($budget->canEdit(auth()->user()))
                    <a href="{{ route('portal.finance.budgets.edit', $budget) }}" class="rounded-full bg-brand-white/10 hover:bg-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white transition-all">
                        ✏️ Edit Details
                    </a>
                @endif
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

    <div class="space-y-6">
        <!-- Budget Meta Details -->
        <div class="grid gap-6 md:grid-cols-4">
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Total Budget Value</p>
                <p class="mt-2 text-2xl font-bold text-emerald-400 font-mono">
                    {{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}
                </p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Owner / Creator</p>
                <p class="mt-2 text-sm font-semibold text-brand-white">
                    {{ $budget->creator?->name }}
                </p>
                <p class="text-[10px] text-brand-ash font-mono mt-0.5">
                    {{ $budget->creator?->email }}
                </p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Status</p>
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
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[9px] uppercase tracking-wider font-bold mt-2 {{ $badges[$budget->status] ?? 'bg-white/10 border-white/20' }}">
                    {{ $budget->status }}
                </span>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Associated Task</p>
                <p class="mt-2 text-xs font-semibold text-brand-white truncate">
                    {{ $budget->task?->title ?? 'None associated' }}
                </p>
            </div>
        </div>

        @if($budget->notes)
            <div class="glass-panel rounded-2xl p-4 border border-brand-white/5 bg-brand-white/[0.02] text-xs text-brand-white/80 italic">
                "{{ $budget->notes }}"
            </div>
        @endif

        <!-- Detailed Specifications (Separate Full-Width Block and Row) -->
        @if($budget->content)
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-3 overflow-x-auto">
                <h3 class="text-xs uppercase tracking-wider text-brand-ash font-bold border-b border-brand-white/5 pb-2">Detailed Specifications</h3>
                <div class="text-sm text-brand-white/90 leading-relaxed max-w-none prose prose-invert overflow-x-auto w-full">
                    {!! $budget->content !!}
                </div>
            </div>
        @endif

        <!-- Spreadsheet line items ledger (Separate Full-Width Block and Row) -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 overflow-x-auto w-full">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Spreadsheet Line Items</h3>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('portal.finance.budgets.export', $budget) }}" class="rounded bg-brand-white/10 hover:bg-brand-white/20 px-3 py-1.5 text-[10px] uppercase font-bold text-brand-white">
                                📤 Export CSV
                            </a>
                        </div>
                    </div>

                    <!-- Line items table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-2.5">Category</th>
                                    <th class="py-2.5">Description</th>
                                    <th class="py-2.5">Quantity</th>
                                    <th class="py-2.5">Unit Price</th>
                                    <th class="py-2.5">Total ({{ $budget->currency }})</th>
                                    @if ($budget->created_by === auth()->id() || $budget->canEdit(auth()->user()) || $isFinance)
                                        <th class="py-2.5 text-right">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse ($budget->items as $item)
                                    <tr>
                                        <td class="py-3 font-semibold text-brand-white">{{ $item->category ?? 'General' }}</td>
                                        <td class="py-3">{{ $item->description }}</td>
                                        <td class="py-3">{{ $item->quantity }}</td>
                                        <td class="py-3 font-mono font-semibold">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="py-3 text-emerald-400 font-bold font-mono">{{ number_format($item->total, 2) }}</td>
                                        @if ($budget->created_by === auth()->id() || $budget->canEdit(auth()->user()) || $isFinance)
                                            <td class="py-3 text-right">
                                                <form method="POST" action="{{ route('portal.finance.budgets.items.destroy', [$budget, $item]) }}" onsubmit="return confirm('Remove line item?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-brand-red/70 hover:text-brand-red font-semibold">
                                                        ✕ Remove
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-xs text-brand-white/40 italic">No line items loaded. Add line items or import CSV below.</td>
                                    </tr>
                                @endforelse
                                <tr class="font-bold border-t border-brand-white/10">
                                    <td colspan="4" class="py-4 text-right text-brand-ash uppercase tracking-wider">Total Value:</td>
                                    <td class="py-4 text-emerald-400 text-sm font-mono">{{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}</td>
                                    @if ($budget->created_by === auth()->id() || $budget->canEdit(auth()->user()) || $isFinance)
                                        <td></td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Line Item Form -->
                    @if ($budget->created_by === auth()->id() || $budget->canEdit(auth()->user()) || $isFinance)
                        <div class="mt-6 border-t border-brand-white/5 pt-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-ash mb-3">Add Budget Line Item</h4>
                            <form method="POST" action="{{ route('portal.finance.budgets.items.store', $budget) }}" class="grid gap-3 sm:grid-cols-4 items-end">
                                @csrf
                                <div>
                                    <label class="block text-[9px] uppercase tracking-widest text-brand-ash mb-1">Category</label>
                                    <input type="text" name="category" placeholder="e.g. Media, Catering" 
                                           class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white">
                                </div>
                                <div>
                                    <label class="block text-[9px] uppercase tracking-widest text-brand-ash mb-1">Description *</label>
                                    <input type="text" name="description" required placeholder="Item description" 
                                           class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[9px] uppercase tracking-widest text-brand-ash mb-1">Qty *</label>
                                        <input type="number" name="quantity" required min="1" value="1" 
                                               class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] uppercase tracking-widest text-brand-ash mb-1">Unit Price *</label>
                                        <input type="number" name="unit_price" required min="0" step="0.01" placeholder="0.00" 
                                               class="w-full rounded-lg border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white">
                                    </div>
                                </div>
                                <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark py-2 text-xs font-bold uppercase tracking-wider text-white transition-all">
                                    + Add Item
                                </button>
                            </form>
                        </div>

                        <!-- CSV Bulk Import -->
                        <div class="mt-6 border-t border-brand-white/5 pt-4 bg-brand-white/[0.01] rounded-xl p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-ash mb-2">📥 Bulk Load via CSV</h4>
                            <p class="text-[10px] text-brand-white/50 mb-3">Upload a CSV file containing columns: <strong class="text-brand-white">Description, Quantity, Unit Price, Category</strong> in order. Quotation marks will be parsed out automatically.</p>
                            <form method="POST" action="{{ route('portal.finance.budgets.import', $budget) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                                @csrf
                                <input type="file" name="csv_file" required accept=".csv,text/csv" 
                                       class="text-xs text-brand-ash file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20">
                                <button type="submit" class="rounded bg-emerald-500 hover:bg-emerald-600 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-brand-black transition">
                                    Load CSV spreadsheet
                                </button>
                            </form>
                        </div>
                    @endif
        </div>

        <!-- Status & Collaborators Layout (Responsive 2-column grid at bottom) -->
        <div class="grid gap-6 md:grid-cols-2">
            <!-- Workflow Action panel -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4">
                    <h3 class="text-xs uppercase tracking-wider text-brand-ash font-bold border-b border-brand-white/5 pb-2">Status & Actions</h3>
                    
                    <div class="space-y-3">
                        @if (in_array($budget->status, ['Draft', 'Rejected', 'Returned for Correction', 'Updated']))
                            @if ($budget->created_by === auth()->id() || auth()->user()->hasRole('super_admin'))
                                <form method="POST" action="{{ route('portal.finance.budgets.submit', $budget) }}" class="space-y-2">
                                    @csrf
                                    <button type="submit" name="submit_target" value="finance" class="w-full rounded-xl bg-blue-600 hover:bg-blue-500 text-white py-2 text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-blue-600/20">
                                        📤 Submit to Finance for Approval
                                    </button>
                                    <button type="submit" name="submit_target" value="cvo" class="w-full rounded-xl bg-amber-500 hover:bg-amber-400 text-brand-black py-2.5 text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-amber-500/20">
                                        ⚡ Submit directly to CVO
                                    </button>
                                </form>
                            @endif
                            @if ($budget->created_by === auth()->id() || auth()->user()->hasRole('super_admin'))
                                <form method="POST" action="{{ route('portal.finance.budgets.destroy', $budget) }}" class="block pt-2" onsubmit="return confirm('Permanently delete this project budget?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full rounded-xl border border-brand-red/35 bg-brand-red/5 hover:bg-brand-red/10 text-brand-red py-2.5 text-xs uppercase tracking-wider transition">
                                        🗑️ Delete Draft Budget
                                    </button>
                                </form>
                            @endif
                        @elseif (in_array($budget->status, ['Submitted to Finance', 'Submitted', 'Returned to Finance']))
                            @if ($isFinance)
                                <div class="bg-blue-500/10 border border-blue-500/20 p-4 rounded-xl space-y-3">
                                    <p class="text-xs text-blue-300 font-semibold">Awaiting Finance Approval Action</p>
                                    <div class="flex flex-col gap-2">
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('portal.finance.budgets.action', [$budget, 'approve']) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded bg-emerald-500 hover:bg-emerald-400 text-brand-black py-2 text-xs font-bold uppercase tracking-widest transition">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.finance.budgets.action', [$budget, 'reject']) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded border border-brand-red bg-brand-red/10 hover:bg-brand-red/20 text-brand-red py-2 text-xs uppercase tracking-widest transition">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                        <form method="POST" action="{{ route('portal.finance.budgets.action', [$budget, 'send_back']) }}" class="w-full">
                                            @csrf
                                            <button type="submit" class="w-full rounded border border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 py-2 text-xs uppercase tracking-widest transition">
                                                ↩ Send Back for Correction
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <p class="text-xs text-blue-400 font-semibold italic">⏳ Submitted to Finance. Awaiting verification.</p>
                            @endif
                        @elseif ($budget->status === 'Finance Approved')
                            @if ($isCVO)
                                <div class="bg-purple-500/10 border border-purple-500/20 p-4 rounded-xl space-y-3">
                                    <p class="text-xs text-purple-300 font-semibold">Awaiting CVO Final Approval Action</p>
                                    <div class="flex flex-col gap-2">
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('portal.finance.budgets.cvo.action', [$budget, 'approve']) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded bg-purple-500 hover:bg-purple-400 text-brand-black py-2 text-xs font-bold uppercase tracking-widest transition">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('portal.finance.budgets.cvo.action', [$budget, 'reject']) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded border border-brand-red bg-brand-red/10 hover:bg-brand-red/20 text-brand-red py-2 text-xs uppercase tracking-widest transition">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                        <form method="POST" action="{{ route('portal.finance.budgets.cvo.action', [$budget, 'send_back_finance']) }}" class="w-full">
                                            @csrf
                                            <button type="submit" class="w-full rounded border border-orange-500/40 bg-orange-500/10 hover:bg-orange-500/20 text-orange-400 py-2 text-xs uppercase tracking-widest transition">
                                                ↩ Send Back to Finance
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <p class="text-xs text-indigo-400 font-semibold italic">⏳ Approved by Finance. Awaiting final CVO executive approval.</p>
                            @endif
                        @elseif ($budget->status === 'CVO Approved')
                            <p class="text-xs text-purple-300 font-semibold">✓ Approved by CVO. Ready for payment processing.</p>
                            @if ($isFinance)
                                <div class="pt-2">
                                    <form method="POST" action="{{ route('portal.finance.budgets.action', [$budget, 'reject']) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded bg-brand-red/15 hover:bg-brand-red/25 border border-brand-red/35 py-2 text-xs uppercase tracking-wider text-brand-red transition">
                                            Reject & Flag Budget
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Collaborator management widgets (creator only) -->
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4"
                     x-data="{
                        collaborators: {{ json_encode($budget->collaborators->map(fn($c) => ['user_id' => $c->id, 'name' => $c->name, 'permission' => $c->pivot->permission])) }},
                        newUserId: '',
                        newPermission: 'view',
                        addCollaborator() {
                            if (!this.newUserId) return;
                            const selectEl = document.getElementById('new_user_select');
                            const userName = selectEl.options[selectEl.selectedIndex].text;
                            
                            // Prevent duplicate
                            if (this.collaborators.some(c => c.user_id == this.newUserId)) {
                                alert('This user is already a collaborator.');
                                return;
                            }
                            
                            this.collaborators.push({
                                user_id: this.newUserId,
                                name: userName,
                                permission: this.newPermission
                            });
                            
                            this.newUserId = '';
                        },
                        removeCollaborator(index) {
                            this.collaborators.splice(index, 1);
                        }
                     }">
                    <h3 class="text-xs uppercase tracking-wider text-brand-ash font-bold border-b border-brand-white/5 pb-2">Collaborators</h3>
                    
                    <!-- Collaborator sync form -->
                    <form method="POST" action="{{ route('portal.finance.budgets.collaborators', $budget) }}" class="space-y-4">
                        @csrf
                        
                        <!-- List of selected collaborators -->
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <template x-for="(collab, index) in collaborators" :key="collab.user_id">
                                <div class="flex items-center justify-between gap-3 bg-brand-white/5 border border-brand-white/10 rounded-xl p-2.5">
                                    <div>
                                        <p class="text-xs font-semibold text-brand-white" x-text="collab.name"></p>
                                        <p class="text-[10px] text-brand-ash capitalize" x-text="collab.permission + ' permission'"></p>
                                    </div>
                                    
                                    <!-- Hidden Inputs to submit arrays -->
                                    <input type="hidden" :name="'collaborators[' + index + '][user_id]'" :value="collab.user_id">
                                    <input type="hidden" :name="'collaborators[' + index + '][permission]'" :value="collab.permission">

                                     @if ($budget->created_by === auth()->id() || auth()->user()->hasRole('super_admin'))
                                         <button type="button" @click="removeCollaborator(index)" class="text-brand-red/80 hover:text-brand-red text-xs">
                                             ✕ Remove
                                         </button>
                                     @endif
                                </div>
                            </template>
                            <template x-if="collaborators.length === 0">
                                <p class="text-xs text-brand-white/30 italic text-center py-4">No collaborators assigned.</p>
                            </template>
                        </div>

                        <!-- Add new collaborator (creator & super admin) -->
                        @if ($budget->created_by === auth()->id() || auth()->user()->hasRole('super_admin'))
                            <div class="border-t border-brand-white/5 pt-3 space-y-3">
                                <input type="hidden" name="sync_collaborators" value="1">
                                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Invite Coworker</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <select id="new_user_select" x-model="newUserId" class="rounded-lg border border-brand-white/10 bg-brand-black text-xs text-brand-white p-2">
                                        <option value="">Select staff member...</option>
                                        @foreach($allStaff as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    <select x-model="newPermission" class="rounded-lg border border-brand-white/10 bg-brand-black text-xs text-brand-white p-2">
                                        <option value="view">View Only</option>
                                        <option value="edit">View & Edit</option>
                                    </select>
                                </div>
                                <button type="button" @click="addCollaborator()" class="w-full rounded bg-brand-white/10 hover:bg-brand-white/15 py-1.5 text-xs font-semibold text-brand-white transition">
                                    + Add to List
                                </button>
                                
                                <div class="pt-2 border-t border-brand-white/5">
                                    <button type="submit" class="w-full rounded bg-amber-500 hover:bg-amber-400 text-brand-black py-2 text-xs font-bold uppercase tracking-wider transition">
                                        💾 Save Collaborators List
                                    </button>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
</x-app-layout>
