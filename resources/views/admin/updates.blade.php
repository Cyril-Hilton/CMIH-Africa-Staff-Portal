<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Team Updates</p>
            <h2 class="text-3xl font-display text-brand-white">Manage Progress Updates</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="glass-panel rounded-2xl p-6">
        <div x-data class="space-y-4">
            @forelse ($updates as $update)
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $update->user?->name ?? 'Unknown' }}</p>
                            <p class="mt-2 text-sm text-brand-white">{{ $update->title }}</p>
                            <div class="mt-2 text-xs text-brand-white/60">{!! $update->summary !!}</div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ ucwords(str_replace('_', ' ', $update->status)) }}</p>
                            <p class="mt-2 text-sm text-brand-white">{{ $update->progress }}%</p>
                            <p class="text-xs text-brand-white/60">Target {{ $update->due_on?->format('M d, Y') ?? 'TBD' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.updates.edit', $update) }}" class="rounded-full border border-brand-white/20 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-white/70">Edit</a>
                        <button 
                            type="button"
                            @click="$dispatch('open-confirm-modal', { url: '{{ route('admin.updates.destroy', $update) }}' })"
                            class="rounded-full border border-brand-red/40 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-red"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-brand-white/60">No updates shared yet.</p>
            @endforelse
        </div>

        @if ($updates instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-4">
                {{ $updates->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
