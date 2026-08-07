<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Workspace</p>
            <h2 class="text-3xl font-display text-brand-white">Create Workspace Document</h2>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white mb-6">New Document Details</h3>

            <form action="{{ route('portal.workspace.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid gap-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Document Title</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Q3 Brand Activation Plan" 
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" />
                    </div>
                </div>

                <!-- Editor (CKEditor) -->
                <div>
                    <label for="content" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Document Content (CKEditor)</label>
                    <div class="rounded-xl overflow-hidden border border-brand-white/10 bg-brand-black">
                        <textarea id="content" name="content" rows="12" class="wysiwyg-editor w-full bg-brand-black text-brand-white p-4 focus:outline-none">{{ old('content') }}</textarea>
                    </div>
                </div>

                <!-- File Upload / Import -->
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 space-y-2">
                    <label for="file" class="block text-xs uppercase tracking-wider text-brand-ash">Import File (Optional)</label>
                    <p class="text-[10px] text-brand-white/50">Upload spreadsheet, document, powerpoint or PDF (Excel, Word, PPT, PDF, CSV up to 10MB)</p>
                    <input type="file" id="file" name="file" 
                        class="w-full text-xs text-brand-white/60 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-500/20 file:text-amber-400 hover:file:bg-amber-500/30 cursor-pointer" />
                </div>

                <!-- Collaborators (Multi-select) -->
                <div>
                    <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Invite Collaborators (Default: View Only)</label>
                    <p class="text-[10px] text-brand-white/50 mb-3">You can elevate collaborator access to edit later inside the document view.</p>
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 max-h-48 overflow-y-auto pr-2 border border-brand-white/10 rounded-xl p-3 bg-brand-black/30">
                        @foreach ($users as $user)
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-brand-white/5 cursor-pointer text-sm text-brand-white/80">
                                <input type="checkbox" name="collaborators[]" value="{{ $user->id }}" 
                                    class="rounded border-brand-white/25 bg-brand-black text-amber-500 focus:ring-0" />
                                <span>{{ $user->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-brand-white/10">
                    <a href="{{ route('portal.workspace.index') }}" class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                        Save as Draft
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
