<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Config</p>
            <h2 class="text-3xl font-display text-brand-white">Manage Outlets & Stores</h2>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Add Outlet -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit">
            <h3 class="text-lg font-semibold text-brand-white mb-4">🆕 Register Retail Outlet</h3>
            <form method="POST" action="{{ route('portal.merchandisers-admin.outlets.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('Outlet Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full text-sm" placeholder="e.g. Accra Mall Shoprite" required />
                </div>
                <div>
                    <x-input-label for="code" :value="__('Unique Outlet Code')" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full text-sm" placeholder="e.g. ACC-SR-001" required />
                </div>
                <div>
                    <x-input-label for="kd_id" :value="__('Key Distributor')" />
                    <select name="kd_id" id="kd_id" required class="mt-1 block w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        <option value="">-- Select KD --</option>
                        @foreach($kds as $kd)
                            <option value="{{ $kd->id }}">{{ $kd->name }} ({{ $kd->region->name ?? 'N/A' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="channel_type" :value="__('Channel Type')" />
                    <select name="channel_type" id="channel_type" required class="mt-1 block w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        <option value="GT">GT (General Trade)</option>
                        <option value="SSM">SSM (Supermarket)</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="address" :value="__('Physical Address')" />
                    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full text-sm" placeholder="e.g. Tetteh Quarshie Interchange" required />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="latitude" :value="__('Locked Latitude')" />
                        <x-text-input id="latitude" name="latitude" type="number" step="0.00000001" class="mt-1 block w-full text-xs" placeholder="e.g. 5.6174" required />
                    </div>
                    <div>
                        <x-input-label for="longitude" :value="__('Locked Longitude')" />
                        <x-text-input id="longitude" name="longitude" type="number" step="0.00000001" class="mt-1 block w-full text-xs" placeholder="e.g. -0.1681" required />
                    </div>
                </div>
                <x-primary-button class="w-full justify-center">Register Outlet</x-primary-button>
            </form>
        </div>

        <!-- Outlets Directory -->
        <div class="lg:col-span-2 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40">
            <h3 class="text-lg font-semibold text-brand-white mb-4">🏪 Active Retail Outlets ({{ $outlets->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70 min-w-[750px]">
                    <thead class="uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3">Outlet Details</th>
                            <th class="py-3">KD / Channel</th>
                            <th class="py-3">Address & GPS coordinates</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($outlets as $outlet)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-3.5 pr-2">
                                    <span class="font-bold text-brand-white text-sm block">{{ $outlet->name }}</span>
                                    <span class="text-brand-white/40">Code: {{ $outlet->code }}</span>
                                </td>
                                <td class="py-3.5 pr-2 space-y-1">
                                    <p class="font-medium text-brand-white">{{ $outlet->keyDistributor->name ?? 'Unassigned' }}</p>
                                    <span class="inline-flex px-2 py-0.5 rounded text-[9px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase">
                                        {{ $outlet->channel_type }}
                                    </span>
                                </td>
                                <td class="py-3.5 pr-2 text-brand-white/60">
                                    <p>{{ $outlet->address }}</p>
                                    <p class="text-[10px] text-brand-white/40 mt-0.5">GPS: {{ $outlet->latitude }}, {{ $outlet->longitude }}</p>
                                </td>
                                <td class="py-3.5 text-right space-y-1">
                                    <button onclick="toggleEditOutlet('{{ $outlet->id }}')" class="px-2 py-1 bg-brand-white/10 border border-brand-white/20 text-brand-white hover:bg-brand-white/20 uppercase font-semibold rounded mr-1">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.outlets.destroy', $outlet) }}" onsubmit="return confirm('Are you sure you want to delete this outlet?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 bg-brand-red/10 border border-brand-red/20 text-brand-red hover:bg-brand-red/20 uppercase font-semibold rounded">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Outlet Form Row -->
                            <tr id="edit-outlet-{{ $outlet->id }}" class="hidden bg-brand-white/5">
                                <td colspan="4" class="p-4 border-b border-brand-white/10">
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.outlets.update', $outlet) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Outlet Name</label>
                                            <input type="text" name="name" value="{{ $outlet->name }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Key Distributor</label>
                                            <select name="kd_id" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                @foreach($kds as $kd)
                                                    <option value="{{ $kd->id }}" {{ $outlet->kd_id == $kd->id ? 'selected' : '' }}>{{ $kd->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Channel</label>
                                            <select name="channel_type" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                                <option value="GT" {{ $outlet->channel_type === 'GT' ? 'selected' : '' }}>GT</option>
                                                <option value="SSM" {{ $outlet->channel_type === 'SSM' ? 'selected' : '' }}>SSM</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Address</label>
                                            <input type="text" name="address" value="{{ $outlet->address }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Latitude</label>
                                                <input type="number" name="latitude" step="0.00000001" value="{{ $outlet->latitude }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-brand-ash uppercase tracking-wider mb-1">Longitude</label>
                                                <input type="number" name="longitude" step="0.00000001" value="{{ $outlet->longitude }}" required class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-brand-white focus:border-amber-500 focus:ring-0 text-xs w-full">
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-black font-bold uppercase tracking-wider rounded-lg transition-all text-xs">
                                                Save
                                            </button>
                                            <button type="button" onclick="toggleEditOutlet('{{ $outlet->id }}')" class="py-2 px-3 bg-brand-white/10 hover:bg-brand-white/20 text-brand-white rounded-lg text-xs font-semibold">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-brand-white/40">No outlets registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function toggleEditOutlet(outletId) {
            let row = document.getElementById('edit-outlet-' + outletId);
            if (row.classList.contains('hidden')) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
