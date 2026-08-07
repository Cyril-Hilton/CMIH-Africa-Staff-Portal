<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Workspace</p>
            <h2 class="text-3xl font-display text-brand-white">Edit Document</h2>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-brand-white/10">
                <h3 class="text-lg font-semibold text-brand-white">Modify Document Details</h3>
                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider 
                    {{ $workspace->doc_type === 'budget' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-blue-500/20 text-blue-400' }}
                ">
                    {{ $workspace->doc_type }}
                </span>
            </div>

            <form action="{{ route('portal.workspace.update', $workspace) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Title -->
                <div>
                    <label for="title" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Document Title</label>
                    <input type="text" id="title" name="title" value="{{ old('title', $workspace->title) }}" required placeholder="e.g. Q3 Marketing Budget" 
                        class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" />
                </div>

                <!-- Editor (CKEditor) -->
                <div>
                    <label for="content" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Document Content (CKEditor)</label>
                    <div class="rounded-xl overflow-hidden border border-brand-white/10 bg-brand-black">
                        <textarea id="content" name="content" rows="12" class="wysiwyg-editor w-full bg-brand-black text-brand-white p-4 focus:outline-none">{{ old('content', $workspace->content) }}</textarea>
                    </div>
                </div>

                <!-- Current file preview & replace -->
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 space-y-3">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-brand-ash">Imported File</label>
                        @if ($workspace->file_path)
                            <p class="text-xs text-brand-white mt-1">
                                Current File: <a href="{{ route('portal.workspace.export', $workspace) }}" class="text-amber-400 underline font-semibold hover:text-amber-300">{{ $workspace->file_name }}</a>
                            </p>
                        @else
                            <p class="text-xs text-brand-white/40 mt-1 italic">No imported attachment files yet.</p>
                        @endif
                    </div>

                    <div class="pt-2 border-t border-brand-white/5">
                        <label for="file" class="block text-xs uppercase tracking-wider text-brand-ash mb-1">Replace or Upload File</label>
                        <p class="text-[10px] text-brand-white/50 mb-2">Upload spreadsheet, document, powerpoint or PDF (Excel, Word, PPT, PDF, CSV up to 10MB)</p>
                        <input type="file" id="file" name="file" 
                            class="w-full text-xs text-brand-white/60 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-500/20 file:text-amber-400 hover:file:bg-amber-500/30 cursor-pointer" />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-brand-white/10">
                    <a href="{{ route('portal.workspace.show', $workspace) }}" class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
