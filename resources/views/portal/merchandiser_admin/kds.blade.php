<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Config</p>
            <h2 class="text-3xl font-display text-brand-white">Manage Key Distributors</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <!-- Error Alerts -->
    @if (session('kd_error') || $errors->has('kd_error'))
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
            {{ session('kd_error') ?? $errors->first('kd_error') }}
        </div>
    @endif

    <!-- Cascading Reassignment Wizard Alert Box -->
    @if(session('show_reassign_wizard_for'))
        @php
            $targetKd = \App\Models\KeyDistributor::find(session('show_reassign_wizard_for'));
            $dependents = session('dependents');
            $otherKds = \App\Models\KeyDistributor::where('id', '!=', $targetKd->id)->get();
        @endphp
        @if($targetKd)
            <div class="mb-8 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">⚡</span>
                    <div>
                        <h3 class="text-lg font-bold text-amber-400">Cascading Reassignment Wizard</h3>
                        <p class="text-sm text-brand-white/80 mt-1">
                            You are deleting Key Distributor <strong>{{ $targetKd->name }}</strong>. However, there are dependent nodes in the network database. To prevent unlinked floating nodes, you must select another Key Distributor to adopt them.
                        </p>
                    </div>
                </div>

                <!-- Dependent list breakdown -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs bg-brand-black/40 p-4 rounded-xl border border-brand-white/5">
                    <div>
                        <p class="text-brand-white/50 uppercase tracking-wider font-semibold">🏪 Dependent Outlets ({{ count($dependents['outlets']) }})</p>
                        <ul class="list-disc pl-4 mt-2 text-brand-white/80 space-y-1">
                            @foreach($dependents['outlets'] as $out)
                                <li>{{ $out->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-brand-white/50 uppercase tracking-wider font-semibold">👥 Merchandisers ({{ count($dependents['merchandisers']) }})</p>
                        <ul class="list-disc pl-4 mt-2 text-brand-white/80 space-y-1">
                            @foreach($dependents['merchandisers'] as $merch)
                                <li>{{ $merch->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-brand-white/50 uppercase tracking-wider font-semibold">👔 Territory Managers ({{ count($dependents['tms']) }})</p>
                        <ul class="list-disc pl-4 mt-2 text-brand-white/80 space-y-1">
                            @foreach($dependents['tms'] as $tm)
                                <li>{{ $tm->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-brand-white/50 uppercase tracking-wider font-semibold">💼 DSR Staff ({{ count($dependents['dsrs']) }})</p>
                        <ul class="list-disc pl-4 mt-2 text-brand-white/80 space-y-1">
                            @foreach($dependents['dsrs'] as $dsr)
                                <li>{{ $dsr->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Wizard Form -->
                <form method="POST" action="{{ route('portal.merchandisers-admin.kds.destroy', $targetKd) }}" class="flex flex-col sm:flex-row items-end gap-4 max-w-xl">
                    @csrf
                    @method('DELETE')
                    <div class="w-full">
                        <label class="block text-xs text-brand-white/70 font-semibold mb-2">Reassign Dependents to Target KD:</label>
                        <select name="reassign_kd_id" required class="w-full rounded-md border border-brand-white/10 bg-brand-black/80 px-3 py-2.5 text-sm text-brand-white focus:border-amber-500 focus:ring-amber-500">
                            <option value="">-- Choose Adopting Key Distributor --</option>
                            @foreach($otherKds as $other)
                                <option value="{{ $other->id }}">{{ $other->name }} ({{ $other->region->name ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-black font-bold uppercase tracking-wider text-xs rounded-xl shadow-lg transition-all shrink-0">
                        Confirm Reassign & Delete
                    </button>
                </form>
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Add KD -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit">
            <h3 class="text-lg font-semibold text-brand-white mb-4">🆕 Register Key Distributor</h3>
            <form method="POST" action="{{ route('portal.merchandisers-admin.kds.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('KD Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full text-sm" placeholder="e.g. Ama Jessica Dist" required />
                </div>
                <div>
                    <x-input-label for="region_id" :value="__('Region Division')" />
                    <select name="region_id" id="region_id" required class="mt-1 block w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        <option value="">-- Select Region --</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->timezone }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="address" :value="__('Office Address')" />
                    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full text-sm" placeholder="e.g. Accra Central Market Area" required />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="latitude" :value="__('Latitude')" />
                        <x-text-input id="latitude" name="latitude" type="number" step="0.00000001" class="mt-1 block w-full text-xs" placeholder="e.g. 5.5560" required />
                    </div>
                    <div>
                        <x-input-label for="longitude" :value="__('Longitude')" />
                        <x-text-input id="longitude" name="longitude" type="number" step="0.00000001" class="mt-1 block w-full text-xs" placeholder="e.g. -0.2045" required />
                    </div>
                </div>
                <x-primary-button class="w-full justify-center">Register KD</x-primary-button>
            </form>
        </div>

        <!-- KD Directory -->
        <div class="lg:col-span-2 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40">
            <h3 class="text-lg font-semibold text-brand-white mb-4">🏬 Active Key Distributors Directory ({{ $kds->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70 min-w-[700px]">
                    <thead class="uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3">KD Name</th>
                            <th class="py-3">Region</th>
                            <th class="py-3">Address & Coordinates</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($kds as $kd)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-3.5 pr-2">
                                    <span class="font-bold text-brand-white text-sm block">{{ $kd->name }}</span>
                                    <span class="text-brand-white/40">ID: KD-{{ str_pad($kd->id, 3, '0', STR_PAD_LEFT) }}</span>
                                </td>
                                <td class="py-3.5 pr-2">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] bg-brand-white/10 text-brand-white border border-brand-white/5">
                                        {{ $kd->region->name ?? 'Unmapped' }}
                                    </span>
                                </td>
                                <td class="py-3.5 pr-2 text-brand-white/60">
                                    <p>{{ $kd->address }}</p>
                                    <p class="text-[10px] text-brand-white/40 mt-0.5">GPS: {{ $kd->latitude }}, {{ $kd->longitude }}</p>
                                </td>
                                <td class="py-3.5 text-right space-y-1">
                                    <button onclick="toggleEditKd('{{ $kd->id }}')" class="px-2 py-1 bg-brand-white/10 border border-brand-white/20 text-brand-white hover:bg-brand-white/20 uppercase font-semibold rounded mr-1">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.kds.destroy', $kd) }}" onsubmit="return confirm('Are you sure you want to delete this Key Distributor? Depended staff and outlets will trigger the Reassignment Wizard.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 bg-brand-red/10 border border-brand-red/20 text-brand-red hover:bg-brand-red/20 uppercase font-semibold rounded">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit KD Form Row -->
                            <tr id="edit-kd-{{ $kd->id }}" class="hidden bg-brand-white/5">
                                <td colspan="4" class="p-4 border-b border-brand-white/10">
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.kds.update', $kd) }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">KD Name</label>
                                            <input type="text" name="name" value="{{ $kd->name }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Region</label>
                                            <select name="region_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                @foreach($regions as $r)
                                                    <option value="{{ $r->id }}" {{ $kd->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Address</label>
                                            <input type="text" name="address" value="{{ $kd->address }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Latitude</label>
                                                <input type="number" name="latitude" step="0.00000001" value="{{ $kd->latitude }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Longitude</label>
                                                <input type="number" name="longitude" step="0.00000001" value="{{ $kd->longitude }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-black font-bold uppercase tracking-wider rounded-lg transition-all text-xs">
                                                Save
                                            </button>
                                            <button type="button" onclick="toggleEditKd('{{ $kd->id }}')" class="py-2 px-3 bg-brand-white/10 hover:bg-brand-white/20 text-brand-white rounded-lg text-xs font-semibold">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-brand-white/40">No Key Distributors registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function toggleEditKd(kdId) {
            let row = document.getElementById('edit-kd-' + kdId);
            if (row.classList.contains('hidden')) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
