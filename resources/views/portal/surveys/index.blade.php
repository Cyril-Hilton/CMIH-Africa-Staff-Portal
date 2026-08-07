<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal</p>
                <h2 class="text-3xl font-display text-brand-white">Consumer Surveys</h2>
            </div>
            <a href="{{ route('portal.surveys.create') }}" class="rounded-full bg-brand-red hover:bg-brand-red-dark px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-white transition">
                + Create Survey
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📋 Created Surveys</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-xs text-brand-white/70">
                    <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="font-normal pb-3 text-left">Survey / Activation Title</th>
                            <th class="font-normal pb-3 text-left">Linked Event</th>
                            <th class="font-normal pb-3 text-left">Type</th>
                            <th class="font-normal pb-3 text-center">Responses</th>
                            <th class="font-normal pb-3 text-center">Status</th>
                            <th class="font-normal pb-3 text-left">Public URL</th>
                            <th class="font-normal pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($surveys as $survey)
                            <tr class="align-middle">
                                <td class="py-4 pr-3">
                                    <p class="font-semibold text-brand-white text-sm">{{ $survey->title }}</p>
                                    @if($survey->description)
                                        <p class="text-[10px] text-brand-white/40 truncate max-w-xs">{{ strip_tags($survey->description) }}</p>
                                    @endif
                                </td>
                                <td class="py-4 text-brand-white/80 pr-3">
                                    @if($survey->event)
                                        <span class="text-xs">{{ $survey->event->title }}</span>
                                    @else
                                        <span class="text-brand-white/30 italic">None</span>
                                    @endif
                                </td>
                                <td class="py-4">
                                    @if($survey->is_anonymous)
                                        <span class="inline-block rounded-full border border-yellow-500/20 bg-yellow-500/5 px-2 py-0.5 text-[9px] uppercase font-bold text-yellow-400">Anonymous</span>
                                    @else
                                        <span class="inline-block rounded-full border border-blue-500/20 bg-blue-500/5 px-2 py-0.5 text-[9px] uppercase font-bold text-blue-400">Public</span>
                                    @endif
                                </td>
                                <td class="py-4 text-center font-mono font-semibold text-brand-white">
                                    {{ $survey->responses_count }}
                                </td>
                                <td class="py-4 text-center">
                                    @if($survey->status === 'published')
                                        <span class="inline-block rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[9px] uppercase font-bold text-emerald-400">Published</span>
                                    @elseif($survey->status === 'closed')
                                        <span class="inline-block rounded-full border border-brand-red/30 bg-brand-red/10 px-2.5 py-0.5 text-[9px] uppercase font-bold text-brand-red">Closed</span>
                                    @else
                                        <span class="inline-block rounded-full border border-brand-white/20 bg-brand-white/5 px-2.5 py-0.5 text-[9px] uppercase font-bold text-brand-white/60">Draft</span>
                                    @endif
                                </td>
                                <td class="py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-[10px] text-brand-white/50 bg-brand-black/40 px-2 py-1 rounded select-all max-w-[200px] truncate">{{ route('surveys.show', $survey->slug) }}</span>
                                        <button 
                                            onclick="navigator.clipboard.writeText('{{ route('surveys.show', $survey->slug) }}').then(() => window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Link copied!', type: 'success' } })))"
                                            type="button" 
                                            class="text-brand-white/40 hover:text-brand-white transition"
                                            title="Copy Link"
                                        >
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex items-center justify-end gap-3" x-data>
                                        <a href="{{ route('portal.surveys.show', $survey) }}" class="text-emerald-400 hover:text-emerald-300 font-semibold" title="View Results & Stats">
                                            Results
                                        </a>
                                        <a href="{{ route('portal.surveys.edit', $survey) }}" class="text-blue-400 hover:text-blue-300 font-semibold" title="Edit Survey">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('portal.surveys.destroy', $survey) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="button" 
                                                @click="$dispatch('open-confirm-modal', { url: '{{ route('portal.surveys.destroy', $survey) }}' })"
                                                class="text-brand-red hover:text-brand-red-dark font-semibold"
                                                title="Delete Survey"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-xs text-brand-white/40 italic">No surveys built yet. Click "+ Create Survey" to build one like Google Forms.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($surveys instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-4 pt-4 border-t border-brand-white/10">
                    {{ $surveys->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
