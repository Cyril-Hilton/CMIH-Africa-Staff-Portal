<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Config</p>
            <h2 class="text-3xl font-display text-brand-white">Manage SKUs</h2>
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
        
        <!-- Add SKU Box -->
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit">
            <h3 class="text-lg font-semibold text-brand-white mb-4">🆕 Add New SKU</h3>
            <form method="POST" action="{{ route('portal.merchandisers-admin.skus.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('SKU Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" placeholder="e.g. Malta Guinness Bottle 330ml" required />
                </div>
                <x-primary-button class="w-full justify-center">Create SKU</x-primary-button>
            </form>
        </div>

        <!-- SKUs List Grid -->
        <div class="lg:col-span-2 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40">
            <h3 class="text-lg font-semibold text-brand-white mb-4">📦 Active SKUs ({{ $skus->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3">SKU Name</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($skus as $sku)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-3.5 font-medium text-brand-white">{{ $sku->name }}</td>
                                <td class="py-3.5 text-right">
                                    <form method="POST" action="{{ route('portal.merchandisers-admin.skus.destroy', $sku) }}" onsubmit="return confirm('Are you sure you want to delete this SKU?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-brand-red/10 border border-brand-red/20 text-brand-red hover:bg-brand-red/20 text-xs font-semibold uppercase tracking-wider transition-all">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-6 text-center text-brand-white/40">No SKUs configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>
