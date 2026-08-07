<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
            <h2 class="text-3xl font-display text-brand-white">Creative Department</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8">
        <!-- Ingestion Lanes: Design Briefs & Design File Version Control -->
        <div class="space-y-6">
            
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🎨 Submit Creative Design Task</h3>
                <form method="POST" action="{{ route('portal.creative.briefs.store') }}" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="brief_job_description" :value="__('Job Description / Category')" />
                            <select id="brief_job_description" name="job_description" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                                <option value="2D Mockup">2D Mockup</option>
                                <option value="3D Mockup">3D Mockup</option>
                                <option value="4D Mockup">4D Mockup</option>
                                <option value="Flyer / Poster">Flyer / Poster</option>
                                <option value="Animation">Animation</option>
                                <option value="Videography">Videography</option>
                                <option value="Photography">Photography</option>
                                <option value="Website Design">Website Design</option>
                                <option value="Brand Guide">Brand Guide</option>
                                <option value="Print File">Print File</option>
                                <option value="Brand Media Asset">Brand Media Asset</option>
                                <option value="Other">Other (specify)</option>
                            </select>
                            <input type="text" id="brief_job_description_custom" name="job_description_custom" class="hidden mt-2 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Please specify category..." />
                        </div>
                        <div>
                            <x-input-label for="brief_title" :value="__('Brief Title / Campaign')" />
                            <x-text-input id="brief_title" name="title" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Christmas Promo" />
                        </div>
                        <div>
                            <x-input-label for="brief_due" :value="__('Deliverable Deadline')" />
                            <x-text-input id="brief_due" name="due_on" type="date" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="brief_priority" :value="__('Priority')" />
                        <select id="brief_priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="brief_details" :value="__('Creative Requirements & Copy Details')" />
                        <textarea id="brief_details" name="details" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Copy content, color codes, canvas sizes, references..."></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Submit Design Task
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Recent Creative Tasks</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($briefs as $brief)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-brand-white">{{ $brief->title }}</span>
                                <span class="rounded-full bg-brand-red/10 border border-brand-red/20 px-2 py-0.5 text-[9px] uppercase font-bold text-brand-red">{{ $brief->priority }} Priority</span>
                            </div>
                            <p class="text-brand-white/60 mt-2">{!! $brief->details !!}</p>
                            @if($brief->due_on)
                                <p class="text-brand-white/40 mt-1 text-[10px]">Due Date: {{ $brief->due_on->format('d M, Y') }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No active design briefs submitted.</p>
                    @endforelse
                </div>
                @if($briefs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $briefs->links() }}
                    </div>
                @endif
            </div>

            <!-- Design File Version Control Check-in -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📁 Design File Version Control Check-In</h3>
                <form method="POST" action="{{ route('portal.creative.files.store') }}" enctype="multipart/form-data" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="design_job_description" :value="__('Job Description / Category')" />
                            <select id="design_job_description" name="job_description" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                                <option value="2D Mockup">2D Mockup</option>
                                <option value="3D Mockup">3D Mockup</option>
                                <option value="4D Mockup">4D Mockup</option>
                                <option value="Flyer / Poster">Flyer / Poster</option>
                                <option value="Animation">Animation</option>
                                <option value="Videography">Videography</option>
                                <option value="Photography">Photography</option>
                                <option value="Website Design">Website Design</option>
                                <option value="Brand Guide">Brand Guide</option>
                                <option value="Print File">Print File</option>
                                <option value="Brand Media Asset">Brand Media Asset</option>
                                <option value="Other">Other (specify)</option>
                            </select>
                            <input type="text" id="design_job_description_custom" name="job_description_custom" class="hidden mt-2 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Please specify category..." />
                        </div>
                        <div>
                            <x-input-label for="design_name" :value="__('Design Title / Asset Name')" />
                            <x-text-input id="design_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Brand Guidelines Draft v1.0" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="design_desc" :value="__('Check-in Notes / Changes made')" />
                        <textarea id="design_desc" name="description" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="What changed in this revision?"></textarea>
                    </div>
                    <div>
                        <x-input-label for="design_file" :value="__('Upload Design File / Asset (ZIP, PSD, PDF, PNG)')" />
                        <input id="design_file" name="file" type="file" required class="mt-1 w-full text-xs text-brand-ash file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Check In File Version
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">File Revision Log</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($designs as $design)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $design->name }}</p>
                                @if($design->description)
                                    <p class="text-brand-white/60 text-[10px] mt-1">{!! $design->description !!}</p>
                                @endif
                                <p class="text-brand-ash text-[9px] mt-1">Uploaded by: {{ $design->creator?->name ?? 'Designer' }}</p>
                            </div>
                            <a href="{{ asset('storage/' . $design->image_path) }}" target="_blank" class="rounded-full bg-brand-white/10 p-2 text-brand-white hover:bg-brand-white/20 transition-all">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </a>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No design files checked in yet.</p>
                    @endforelse
                </div>
                @if($designs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $designs->links() }}
                    </div>
                @endif
            </div>

        </div>

        {{-- ══════════════════ PHASE 3: CREATIVE PROOFING COMMENTS THREAD ══════════════════ --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="mb-5">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Phase 3</p>
                <h3 class="text-lg font-display text-brand-white uppercase">💬 Creative Proofing & Feedback Chain</h3>
            </div>
            <div class="grid gap-6 lg:grid-cols-[350px_1fr]">
                {{-- Add Comment Form --}}
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 h-fit">
                    <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-3">Add Proofing Comment</h4>
                    <form id="creative-comment-form" method="POST" action="" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Select Design Brief *</label>
                            <select id="creative_comment_task_id" required 
                                    onchange="document.getElementById('creative-comment-form').action = '/portal/creative/briefs/' + this.value + '/comments'"
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="">Select Brief</option>
                                @foreach($briefs as $brief)
                                    <option value="{{ $brief->id }}">{{ $brief->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Version Label</label>
                            <input type="text" name="version_label"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="e.g. v1.0, v2.4">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Review Status *</label>
                            <select name="status" required
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="feedback">General Feedback / Notes</option>
                                <option value="approved">Approved</option>
                                <option value="revision_requested">Revision Requested</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Feedback / Comment *</label>
                            <textarea name="comment" required rows="3"
                                      class="wysiwyg-editor w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                      placeholder="Write your feedback details here..."></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Attachment (Image/PDF)</label>
                            <input name="attachment" type="file" 
                                   class="w-full text-xs text-brand-ash file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                            Post Proofing Comment
                        </button>
                    </form>
                </div>
                {{-- Comments Thread --}}
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 max-h-[500px] overflow-y-auto space-y-4">
                    @forelse($creativeComments ?? [] as $comment)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/25 p-4 text-xs space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <span class="font-semibold text-brand-white">{{ $comment->user?->name }}</span>
                                    <span class="text-brand-white/40">on</span>
                                    <span class="text-brand-red font-medium">{{ $comment->task?->title }}</span>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-brand-ash text-[10px]">{{ $comment->created_at?->format('d M Y h:i A') }}</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2">
                                @if($comment->version_label)
                                    <span class="rounded bg-brand-white/10 px-1.5 py-0.5 text-[9px] text-brand-white font-mono">
                                        {{ $comment->version_label }}
                                    </span>
                                @endif
                                
                                @if($comment->status === 'approved')
                                    <span class="rounded bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 text-[9px] text-emerald-400 font-semibold uppercase">
                                        Approved
                                    </span>
                                @elseif($comment->status === 'revision_requested')
                                    <span class="rounded bg-brand-red/10 border border-brand-red/20 px-1.5 py-0.5 text-[9px] text-brand-red font-semibold uppercase">
                                        Revision Requested
                                    </span>
                                @else
                                    <span class="rounded bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 text-[9px] text-sky-400 font-semibold uppercase">
                                        General Feedback
                                    </span>
                                @endif
                            </div>

                            <div class="text-brand-white/70 leading-relaxed">{!! $comment->comment !!}</div>

                            @if($comment->attachment_path)
                                <div class="pt-2 border-t border-brand-white/5">
                                    <a href="{{ asset('storage/' . $comment->attachment_path) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 text-sky-400 hover:text-sky-300 font-semibold transition-colors">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        View Attachment File
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-12">No proofing comments or revision notes logged yet.</p>
                    @endforelse
                </div>
                @if($creativeComments instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $creativeComments->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const briefSelect = document.getElementById('brief_job_description');
            const briefCustomInput = document.getElementById('brief_job_description_custom');
            if (briefSelect && briefCustomInput) {
                briefSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        briefCustomInput.classList.remove('hidden');
                        briefCustomInput.required = true;
                        briefCustomInput.focus();
                    } else {
                        briefCustomInput.classList.add('hidden');
                        briefCustomInput.required = false;
                        briefCustomInput.value = '';
                    }
                });
            }

            const designSelect = document.getElementById('design_job_description');
            const designCustomInput = document.getElementById('design_job_description_custom');
            if (designSelect && designCustomInput) {
                designSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        designCustomInput.classList.remove('hidden');
                        designCustomInput.required = true;
                        designCustomInput.focus();
                    } else {
                        designCustomInput.classList.add('hidden');
                        designCustomInput.required = false;
                        designCustomInput.value = '';
                    }
                });
            }
        });
    </script>
</x-app-layout>
