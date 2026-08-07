<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Config</p>
            <h2 class="text-3xl font-display text-brand-white">Staff Pairings & Activations</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-8">
        
        <!-- Pending Activations -->
        <div class="glass-panel rounded-2xl p-6 border border-amber-500/20 bg-amber-500/5">
            <h3 class="text-lg font-semibold text-amber-400 mb-4">⏳ Pending Merchandiser Registrations ({{ $pendingMerchandisers->count() }})</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70 min-w-[900px]">
                    <thead class="uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="pb-3">Candidate</th>
                            <th class="pb-3 w-[150px]">Region</th>
                            <th class="pb-3 w-[180px]">Key Distributor</th>
                            <th class="pb-3 w-[150px]">Supervisor</th>
                            <th class="pb-3 w-[150px]">Territory Manager</th>
                            <th class="pb-3 w-[150px]">DSR / RSM</th>
                            <th class="pb-3 text-right w-[120px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($pendingMerchandisers as $m)
                            <tr>
                                <td class="py-4 pr-3">
                                    <p class="font-bold text-brand-white text-sm">{{ $m->name }}</p>
                                    <p class="text-brand-white/40">{{ $m->email }} | {{ $m->phone }}</p>
                                    <p class="text-brand-white/30 mt-0.5">DOB: {{ \Carbon\Carbon::parse($m->date_of_birth)->format('M d, Y') }} ({{ \Carbon\Carbon::parse($m->date_of_birth)->age }} yrs)</p>
                                </td>
                                <form method="POST" action="{{ route('portal.merchandisers-admin.pairings.pair', $m) }}">
                                    @csrf
                                    <td class="py-4 pr-2">
                                        <select name="region_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- Region --</option>
                                            @foreach($regions as $r)
                                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-4 pr-2">
                                        <select name="kd_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- KD --</option>
                                            @foreach($kds as $kd)
                                                <option value="{{ $kd->id }}">{{ $kd->name }} ({{ $kd->region->name ?? 'N/A' }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-4 pr-2">
                                        <select name="supervisor_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- Supervisor --</option>
                                            @foreach($supervisors as $s)
                                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-4 pr-2">
                                        <select name="tm_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- Territory Mgr (Optional) --</option>
                                            @foreach($tms as $tm)
                                                <option value="{{ $tm->id }}">{{ $tm->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-4 pr-2 space-y-1">
                                        <select name="dsr_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- DSR (Optional) --</option>
                                            @foreach($dsrs as $dsr)
                                                <option value="{{ $dsr->id }}">{{ $dsr->name }}</option>
                                            @endforeach
                                        </select>
                                        <select name="rsm_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            <option value="">-- RSM (Optional) --</option>
                                            @foreach($rsms as $rsm)
                                                <option value="{{ $rsm->id }}">{{ $rsm->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-4 text-right">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-black font-bold uppercase tracking-wider transition-all">
                                            Activate
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-brand-white/40">No pending registrations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Merchandisers Map Pairings -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40">
            <h3 class="text-lg font-semibold text-brand-white mb-4">👥 Active Mapped Merchandisers ({{ $activeMerchandisers->count() }})</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70 min-w-[900px]">
                    <thead class="uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="pb-3">Merchandiser</th>
                            <th class="pb-3">Region</th>
                            <th class="pb-3">Assigned KD</th>
                            <th class="pb-3">Supervisor</th>
                            <th class="pb-3">Territory Manager</th>
                            <th class="pb-3">DSR / RSM</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($activeMerchandisers as $m)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-4 pr-3">
                                    <span class="font-bold text-brand-white text-sm block">{{ $m->name }}</span>
                                    <span class="text-brand-white/40">{{ $m->email }} | {{ $m->phone }}</span>
                                </td>
                                <td class="py-4 pr-2 text-brand-white font-medium">
                                    {{ $m->merchandiserRegion->name ?? 'None' }}
                                </td>
                                <td class="py-4 pr-2 text-brand-white font-medium">
                                    {{ $m->merchandiserKd->name ?? 'None' }}
                                </td>
                                <td class="py-4 pr-2">
                                    {{ $m->supervisor->name ?? 'None' }}
                                </td>
                                <td class="py-4 pr-2">
                                    {{ $m->merchandiserTm->name ?? 'None' }}
                                </td>
                                <td class="py-4 pr-2 space-y-0.5">
                                    <p>DSR: {{ $m->merchandiserDsr->name ?? 'None' }}</p>
                                    <p class="text-brand-white/40">RSM: {{ $m->merchandiserRsm->name ?? 'None' }}</p>
                                </td>
                                <td class="py-4 text-right">
                                    <button onclick="toggleEditPairing('{{ $m->id }}')" class="px-3 py-1.5 rounded-lg bg-brand-white/10 border border-brand-white/20 text-brand-white hover:bg-brand-white/20 uppercase tracking-wider font-semibold">
                                        Re-map
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Inline Remap Edit Form -->
                            <tr id="edit-pairing-{{ $m->id }}" class="hidden bg-brand-white/5">
                                <td colspan="7" class="p-4 border-b border-brand-white/10">
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.pairings.pair', $m) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                                        @csrf
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Region</label>
                                            <select name="region_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                @foreach($regions as $r)
                                                    <option value="{{ $r->id }}" {{ $m->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Key Distributor</label>
                                            <select name="kd_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                @foreach($kds as $kd)
                                                    <option value="{{ $kd->id }}" {{ $m->kd_id == $kd->id ? 'selected' : '' }}>{{ $kd->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Supervisor</label>
                                            <select name="supervisor_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                @foreach($supervisors as $s)
                                                    <option value="{{ $s->id }}" {{ $m->supervisor_id == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Territory Manager</label>
                                            <select name="tm_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                <option value="">None</option>
                                                @foreach($tms as $tm)
                                                    <option value="{{ $tm->id }}" {{ $m->tm_id == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">DSR / RSM</label>
                                            <select name="dsr_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                <option value="">No DSR</option>
                                                @foreach($dsrs as $dsr)
                                                    <option value="{{ $dsr->id }}" {{ $m->dsr_id == $dsr->id ? 'selected' : '' }}>{{ $dsr->name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="rsm_id" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                <option value="">No RSM</option>
                                                @foreach($rsms as $rsm)
                                                    <option value="{{ $rsm->id }}" {{ $m->rsm_id == $rsm->id ? 'selected' : '' }}>{{ $rsm->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-black font-bold uppercase tracking-wider rounded-lg transition-all text-xs">
                                                Save Remap
                                            </button>
                                            <button type="button" onclick="toggleEditPairing('{{ $m->id }}')" class="py-2 px-3 bg-brand-white/10 hover:bg-brand-white/20 text-brand-white rounded-lg text-xs font-semibold">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-brand-white/40">No active merchandisers linked.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function toggleEditPairing(userId) {
            let row = document.getElementById('edit-pairing-' + userId);
            if (row.classList.contains('hidden')) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
