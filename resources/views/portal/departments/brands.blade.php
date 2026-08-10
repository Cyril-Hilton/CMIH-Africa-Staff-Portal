<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
            <h2 class="text-3xl font-display text-brand-white">Brands & Marketing Hub</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8">
        <!-- POSM Inventory & Strategy Blueprint Row -->
        <div class="space-y-6">
            
            <!-- POSM Material Stock list -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📦 Merchandiser & POSM Stock Hub</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="py-3">Material Name</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Allocation Condition</th>
                                <th class="py-3">Location</th>
                                <th class="py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($materials as $posm)
                                <tr>
                                    <td class="py-3 font-semibold text-brand-white">{{ $posm->name }}</td>
                                    <td class="py-3">{{ $posm->type }}</td>
                                    <td class="py-3 text-brand-red">{{ $posm->condition }}</td>
                                    <td class="py-3">{{ $posm->location ?? 'Warehouse' }}</td>
                                    <td class="py-3">
                                        <span class="inline-block rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 text-[9px] uppercase font-bold text-emerald-400">
                                            {{ $posm->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <!-- Show static list if db is fresh -->
                                @foreach([
                                    ['name' => 'Brand Rollup Banners (CMIH)', 'type' => 'POSM', 'cond' => 'Excellent', 'loc' => 'Accra Office', 'status' => 'Available'],
                                    ['name' => 'CMIH T-shirts (M/L/XL)', 'type' => 'POSM', 'cond' => 'Brand New', 'loc' => 'Warehouse A', 'status' => 'In Stock'],
                                    ['name' => 'Promotional Flyers Bundle', 'type' => 'POSM', 'cond' => 'Freshly Printed', 'loc' => 'Warehouse B', 'status' => 'Available']
                                ] as $mockPosm)
                                    <tr>
                                        <td class="py-3 font-semibold text-brand-white">{{ $mockPosm['name'] }}</td>
                                        <td class="py-3">{{ $mockPosm['type'] }}</td>
                                        <td class="py-3 text-brand-red">{{ $mockPosm['cond'] }}</td>
                                        <td class="py-3">{{ $mockPosm['loc'] }}</td>
                                        <td class="py-3">
                                            <span class="inline-block rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 text-[9px] uppercase font-bold text-emerald-400">
                                                {{ $mockPosm['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($materials instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $materials->links() }}
                    </div>
                @endif
            </div>

            <!-- Upload Brand Blueprint / Strategy Strategy Blueprint upload -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🚀 Compliance Parameters & Strategy Blueprints</h3>
                <form method="POST" action="{{ route('portal.brands.strategy.store') }}" enctype="multipart/form-data" class="space-y-4 mb-6">
                    @csrf
                    <div>
                        <x-input-label for="blueprint_name" :value="__('Document Name / Blueprint Title')" />
                        <x-text-input id="blueprint_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Brand Launch Campaign Strategy" />
                    </div>
                    <div>
                        <x-input-label for="blueprint_desc" :value="__('Brief Description / Goals')" />
                        <textarea id="blueprint_desc" name="description" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Compliance guidelines or marketing targets..."></textarea>
                    </div>
                    <div>
                        <x-input-label for="blueprint_file" :value="__('Select Strategy File (PDF, DOCX)')" />
                        <input id="blueprint_file" name="file" type="file" required class="mt-1 w-full text-xs text-brand-ash file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Upload Strategy Blueprint
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Strategy Documents Registry</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($blueprints as $bp)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $bp->name }}</p>
                                @if($bp->description)
                                    <p class="text-brand-white/60 text-[10px] mt-1">{!! $bp->description !!}</p>
                                @endif
                            </div>
                            <a href="{{ Storage::disk('public')->url($bp->image_path) }}" target="_blank" class="rounded-full bg-brand-white/10 p-2 text-brand-white hover:bg-brand-white/20 transition-all">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </a>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No strategy blueprints uploaded yet.</p>
                    @endforelse
                </div>
                @if($blueprints instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $blueprints->links() }}
                    </div>
                @endif
            </div>

        </div>

        {{-- ══════════════════ PHASE 3: POSM & MATERIALS LEDGER ══════════════════ --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="mb-5">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Phase 3</p>
                <h3 class="text-lg font-display text-brand-white uppercase">📋 POSM & Materials Deployment Ledger</h3>
            </div>
            <div class="space-y-6">
                {{-- Create POSM Entry --}}
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 h-fit">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-3">Log Deployment / Stock</h4>
                    <form method="POST" action="{{ route('portal.brands.posm.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Item Name *</label>
                            <input type="text" name="item_name" required
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="e.g. Pull-up Banner A">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Item Type *</label>
                            <select name="item_type" required
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="POSM">POSM</option>
                                <option value="Uniform">Uniform</option>
                                <option value="Banner">Banner</option>
                                <option value="Tablet">Tablet</option>
                                <option value="AV">AV</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Client / Brand</label>
                            <input type="text" name="client_brand"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="e.g. Nestle">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Qty In *</label>
                                <input type="number" name="quantity_in" required min="0" value="0"
                                       class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Qty Out *</label>
                                <input type="number" name="quantity_out" required min="0" value="0"
                                       class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Location</label>
                            <input type="text" name="location"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="e.g. Warehouse B / Event Venue">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Notes / Purpose</label>
                            <textarea name="notes" rows="2"
                                      class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                      placeholder="Deployment details..."></textarea>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                            Submit Log Entry
                        </button>
                    </form>
                </div>
                {{-- POSM Ledger Table --}}
                <div class="overflow-x-auto rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 max-h-[550px] overflow-y-auto">
                    <table class="w-full text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="py-2.5">Item Name</th>
                                <th class="py-2.5">Type</th>
                                <th class="py-2.5">Client / Brand</th>
                                <th class="py-2.5">In</th>
                                <th class="py-2.5">Out</th>
                                <th class="py-2.5">Net Bal</th>
                                <th class="py-2.5">Location</th>
                                <th class="py-2.5">Logged By</th>
                                <th class="py-2.5">Date</th>
                                <th class="py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($posmEntries ?? [] as $entry)
                                <tr>
                                    <td class="py-3 font-semibold text-brand-white">
                                        {{ $entry->item_name }}
                                        @if($entry->notes)
                                            <div class="text-[10px] text-brand-white/40 font-normal mt-0.5">{!! $entry->notes !!}</div>
                                        @endif
                                    </td>
                                    <td class="py-3">{{ $entry->item_type }}</td>
                                    <td class="py-3">{{ $entry->client_brand ?? '—' }}</td>
                                    <td class="py-3 text-emerald-400 font-semibold">+{{ $entry->quantity_in }}</td>
                                    <td class="py-3 text-brand-red font-semibold">-{{ $entry->quantity_out }}</td>
                                    @php $net = $entry->quantity_in - $entry->quantity_out; @endphp
                                    <td class="py-3 font-bold {{ $net >= 0 ? 'text-emerald-400' : 'text-brand-red' }}">
                                        {{ $net }}
                                    </td>
                                    <td class="py-3 text-brand-white/60">{{ $entry->location ?? 'Warehouse' }}</td>
                                    <td class="py-3 text-brand-white/60">{{ $entry->creator?->name ?? 'System' }}</td>
                                    <td class="py-3 text-brand-ash">{{ $entry->created_at?->format('d M H:i') }}</td>
                                    <td class="py-3 text-right">
                                        <form method="POST" action="{{ route('portal.brands.posm.destroy', $entry) }}" onsubmit="return confirm('Remove log entry?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red/60 hover:text-brand-red transition-colors">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-6 text-center text-xs text-brand-white/40 italic">No POSM/materials ledger entries logged today.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($posmEntries instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $posmEntries->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-app-layout>
