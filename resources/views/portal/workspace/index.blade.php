<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Collaborative Hub</p>
                <h2 class="text-3xl font-display text-brand-white">Collaborative Workspace</h2>
            </div>
            <a href="{{ route('portal.workspace.create') }}" class="px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                + Create Document
            </a>
        </div>
    </x-slot>

    <div class="space-y-8">
        @if (session('status'))
            <div class="glass-panel border-l-4 border-emerald-500 rounded-xl p-4 text-sm text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        <!-- Action Queue (Requires My Approval / Review) -->
        @if ($actionQueue->count() > 0)
            <div class="glass-panel rounded-2xl p-6 border border-amber-500/30 bg-amber-500/5">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-2xl">⚡</span>
                    <h3 class="text-lg font-semibold text-brand-white">Pending Your Review</h3>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($actionQueue as $doc)
                        <div class="rounded-xl border border-amber-500/25 bg-brand-black/60 p-4 flex flex-col justify-between hover:border-amber-500/50 transition">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] uppercase font-bold tracking-wider bg-amber-500/20 text-amber-400">
                                        {{ str_replace('_', ' ', $doc->status) }}
                                    </span>
                                </div>
                                <h4 class="font-semibold text-brand-white text-base truncate mb-1">{{ $doc->title }}</h4>
                                <p class="text-xs text-brand-ash mb-3">Submitted by: {{ $doc->creator->name }}</p>
                            </div>
                            <div class="flex items-center justify-between pt-3 border-t border-brand-white/10 mt-2">
                                <span class="text-[10px] text-brand-white/50">Updated: {{ $doc->updated_at->diffForHumans() }}</span>
                                <a href="{{ route('portal.workspace.show', $doc) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                                    Review Now →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Grid: My Workspace vs Shared Documents -->
        <div class="grid gap-6 xl:grid-cols-2">
            
            <!-- My Documents -->
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-brand-white mb-4">My Documents</h3>
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @forelse ($myDocuments as $doc)
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-white/5 flex items-center justify-between hover:border-amber-500/30 transition">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs text-brand-white/60">
                                        Status: <strong class="text-amber-500 uppercase text-[9px]">{{ str_replace('_', ' ', $doc->status) }}</strong>
                                    </span>
                                    @if ($doc->file_path)
                                        <span class="text-[10px] text-brand-ash flex items-center gap-1">
                                            📎 File Attachment
                                        </span>
                                    @endif
                                </div>
                                <h4 class="font-semibold text-brand-white text-sm truncate">{{ $doc->title }}</h4>
                                <p class="text-xs text-brand-white/40">Current Holder: {{ $doc->holder ? $doc->holder->name : 'None' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('portal.workspace.show', $doc) }}" class="p-2 rounded bg-brand-white/10 hover:bg-brand-white/15 text-brand-white text-xs transition" title="View Document">
                                    👁️
                                </a>
                                <a href="{{ route('portal.workspace.edit', $doc) }}" class="p-2 rounded bg-amber-500/10 hover:bg-amber-500/15 text-amber-400 text-xs transition" title="Edit Document">
                                    ✏️
                                </a>
                                <a href="{{ route('portal.workspace.export', $doc) }}" class="p-2 rounded bg-emerald-500/10 hover:bg-emerald-500/15 text-emerald-400 text-xs transition" title="Export File">
                                    📥
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="text-4xl mb-2">📁</div>
                            <p class="text-sm text-brand-white/40 italic">You haven't created any documents yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Shared Documents -->
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-brand-white mb-4">Shared With Me</h3>
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @forelse ($sharedDocuments as $doc)
                        @php
                            $collabRecord = $doc->collaborators->first();
                            $perm = $collabRecord ? $collabRecord->pivot->permission : 'view';
                        @endphp
                        <div class="p-4 rounded-xl border border-brand-white/10 bg-brand-white/5 flex items-center justify-between hover:border-amber-500/30 transition">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2.5 py-0.5 rounded text-[8px] uppercase tracking-wider font-bold 
                                        {{ $perm === 'edit' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-brand-white/10 text-brand-white/60' }}
                                    ">
                                        {{ $perm === 'edit' ? 'Can Edit' : 'View Only' }}
                                    </span>
                                    @if ($doc->file_path)
                                        <span class="text-[10px] text-brand-ash">
                                            📎 File Attachment
                                        </span>
                                    @endif
                                </div>
                                <h4 class="font-semibold text-brand-white text-sm truncate">{{ $doc->title }}</h4>
                                <p class="text-xs text-brand-white/50">Owner: {{ $doc->creator->name }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('portal.workspace.show', $doc) }}" class="p-2 rounded bg-brand-white/10 hover:bg-brand-white/15 text-brand-white text-xs transition" title="View Document">
                                    👁️
                                </a>
                                @if ($perm === 'edit')
                                    <a href="{{ route('portal.workspace.edit', $doc) }}" class="p-2 rounded bg-amber-500/10 hover:bg-amber-500/15 text-amber-400 text-xs transition" title="Edit Document">
                                        ✏️
                                    </a>
                                @endif
                                <a href="{{ route('portal.workspace.export', $doc) }}" class="p-2 rounded bg-emerald-500/10 hover:bg-emerald-500/15 text-emerald-400 text-xs transition" title="Export File">
                                    📥
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="text-4xl mb-2">🤝</div>
                            <p class="text-sm text-brand-white/40 italic">No documents shared with you yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
