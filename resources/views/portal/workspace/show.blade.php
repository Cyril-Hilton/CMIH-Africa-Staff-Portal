<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Workspace Document</p>
                <h2 class="text-3xl font-display text-brand-white">{{ $workspace->title }}</h2>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('portal.workspace.index') }}" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white bg-brand-white/10 hover:bg-brand-white/15 transition border border-brand-white/10">
                    ← Back to Dashboard
                </a>
                <a href="{{ route('portal.workspace.export', $workspace) }}" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-emerald-500 hover:bg-emerald-400 transition">
                    📥 Export Document
                </a>
                @if ($permission === 'edit' || auth()->user()->hasRole('super_admin'))
                    <a href="{{ route('portal.workspace.edit', $workspace) }}" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition">
                        ✏️ Edit Content
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="glass-panel border-l-4 border-emerald-500 rounded-xl p-4 text-sm text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="glass-panel border-l-4 border-brand-red rounded-xl p-4 text-sm text-brand-red">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Document Metadata Grid -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="glass-panel rounded-2xl p-4 bg-brand-black/20 border border-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Author / Creator</p>
                <p class="text-sm font-semibold text-brand-white mt-1">{{ $workspace->creator->name }}</p>
            </div>
            <div class="glass-panel rounded-2xl p-4 bg-brand-black/20 border border-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Current Status</p>
                <span class="inline-block px-2.5 py-0.5 mt-1 rounded text-[10px] font-bold uppercase tracking-wider 
                    {{ $workspace->status === 'finalized' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : '' }}
                    {{ $workspace->status === 'draft' ? 'bg-brand-white/10 text-brand-white/60' : '' }}
                    {{ $workspace->status === 'under_review' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : '' }}
                ">
                    {{ str_replace('_', ' ', $workspace->status) }}
                </span>
            </div>
            <div class="glass-panel rounded-2xl p-4 bg-brand-black/20 border border-brand-white/5">
                <p class="text-[10px] uppercase tracking-wider text-brand-ash">Current Holder</p>
                <p class="text-sm font-semibold text-amber-400 mt-1">{{ $workspace->holder ? $workspace->holder->name : 'None' }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
            
            <!-- Left Column: Content Viewer -->
            <div class="space-y-6">
                <!-- CKEditor Content Card -->
                <div class="glass-panel rounded-2xl p-6 min-h-[400px]">
                    <h3 class="text-lg font-semibold text-brand-white border-b border-brand-white/10 pb-3 mb-6">Document Body</h3>
                    @if ($workspace->content)
                        <div class="prose prose-invert max-w-none text-brand-white/95">
                            {!! $workspace->content !!}
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-24 text-brand-white/30 italic">
                            <span class="text-4xl mb-3">📝</span>
                            <p>This document has no written body text yet.</p>
                        </div>
                    @endif
                </div>

                <!-- Attached File Card -->
                @if ($workspace->file_path)
                    <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                        <h3 class="text-base font-semibold text-brand-white mb-4">📎 Imported File Attachment</h3>
                        <div class="flex items-center justify-between p-4 rounded-xl bg-brand-black/50 border border-brand-white/5">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl">📄</span>
                                <div>
                                    <p class="text-sm font-medium text-brand-white">{{ $workspace->file_name }}</p>
                                    <p class="text-xs text-brand-ash">Uploaded: {{ $workspace->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>
                            <a href="{{ route('portal.workspace.export', $workspace) }}" class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-emerald-500 hover:bg-emerald-400 transition">
                                Download File
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Collaborators & Workflows -->
            <div class="space-y-6">
                
                <!-- Collaborators Management Panel -->
                <div class="glass-panel rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-brand-white mb-4">Collaborators</h3>
                    
                    @if ($workspace->created_by === auth()->id() || auth()->user()->hasRole('super_admin'))
                        <!-- Owner: Add/Manage Collaborators Form -->
                        <form action="{{ route('portal.workspace.collaborators', $workspace) }}" method="POST" class="space-y-4">
                            @csrf
                            <p class="text-[10px] text-brand-ash uppercase tracking-wider">Invite or Change Permissions</p>
                            
                            <!-- Collaborators select dropdown -->
                            <div x-data="{
                                selectedCollabs: {{ json_encode($workspace->collaborators->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'permission' => $c->pivot->permission])) }},
                                allUsers: {{ json_encode($allUsers) }},
                                addCollaborator(userId) {
                                    if (!userId) return;
                                    if (this.selectedCollabs.some(c => c.id == userId)) return;
                                    const u = this.allUsers.find(x => x.id == userId);
                                    if (u) {
                                        this.selectedCollabs.push({ id: u.id, name: u.name, permission: 'view' });
                                    }
                                },
                                removeCollab(id) {
                                    this.selectedCollabs = this.selectedCollabs.filter(c => c.id != id);
                                }
                            }" class="space-y-3">
                                
                                <div class="flex gap-2">
                                    <select @change="addCollaborator($event.target.value); $event.target.value = ''" class="flex-1 rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-xs focus:outline-none">
                                        <option value="">-- Choose Staff to Add --</option>
                                        <template x-for="user in allUsers">
                                            <option :value="user.id" x-text="user.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                                    <template x-for="(collab, index) in selectedCollabs" :key="collab.id">
                                        <div class="flex items-center justify-between p-2 rounded bg-brand-white/5 border border-brand-white/10 text-xs">
                                            <span class="text-brand-white truncate font-medium flex-1 mr-2" x-text="collab.name"></span>
                                            
                                            <div class="flex items-center gap-2">
                                                <input type="hidden" :name="'collabs[' + index + '][id]'" :value="collab.id" />
                                                <select :name="'collabs[' + index + '][permission]'" x-model="collab.permission" class="rounded border border-brand-white/10 bg-brand-black text-brand-white px-1 py-0.5 text-[10px] focus:outline-none">
                                                    <option value="view">View Only</option>
                                                    <option value="edit">Can Edit</option>
                                                </select>
                                                <button type="button" @click="removeCollab(collab.id)" class="text-brand-red font-bold hover:text-red-400 px-1.5 py-0.5">
                                                    ✕
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <button type="submit" class="w-full py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition">
                                    Save Collaborators
                                </button>
                            </div>
                        </form>
                    @else
                        <!-- Non-owner: View Collaborator List -->
                        <div class="space-y-2">
                            @forelse ($workspace->collaborators as $collab)
                                <div class="flex items-center justify-between p-2.5 rounded bg-brand-white/5 border border-brand-white/10 text-xs">
                                    <span class="text-brand-white font-medium">{{ $collab->name }}</span>
                                    <span class="px-2 py-0.5 rounded text-[8px] uppercase tracking-wider font-bold bg-brand-white/10 text-brand-white/60">
                                        {{ $collab->pivot->permission === 'edit' ? 'Can Edit' : 'View Only' }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-brand-white/40 italic">No other collaborators invited yet.</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                <!-- SUBMIT / ROUTING PANEL (Owner/Holder action) -->
                @if (($workspace->created_by === auth()->id() || $workspace->current_holder_id === auth()->id()) && in_array($workspace->status, ['draft']))
                    <div class="glass-panel rounded-2xl p-6 border border-amber-500/20 bg-amber-500/5">
                        <h3 class="text-base font-semibold text-brand-white mb-3 flex items-center gap-2">
                            <span>📤</span> Submit for Review
                        </h3>
                        
                        <form action="{{ route('portal.workspace.submit', $workspace) }}" method="POST" x-data="{ routeType: @js(old('route_target', 'manager')) }" class="space-y-4">
                            @csrf

                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-2">Reviewer Type</label>
                                <select name="route_target" x-model="routeType" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-xs focus:outline-none">
                                    <option value="manager">Send to Line Manager</option>
                                    <option value="user">Send to Coworker / Peer</option>
                                </select>
                            </div>

                            <div x-show="routeType === 'manager'" x-transition>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-2">Select Line Manager</label>
                                <select name="recipient_id" :disabled="routeType !== 'manager'" :required="routeType === 'manager'" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-xs focus:outline-none">
                                    <option value="">-- Select Line Manager --</option>
                                    @foreach ($lineManagers as $manager)
                                        <option value="{{ $manager->id }}" @selected(old('route_target', 'manager') === 'manager' && (string) old('recipient_id', $lineManager?->id) === (string) $manager->id)>
                                            {{ $manager->name }} ({{ ucwords(str_replace('_', ' ', $manager->department ?? 'N/A')) }})
                                        </option>
                                    @endforeach
                                </select>
                                @if ($lineManagers->isEmpty())
                                    <p class="mt-2 rounded-xl bg-brand-black/40 border border-brand-red/20 px-3 py-2 text-xs text-brand-red font-semibold">⚠️ No active line managers are available. Please select coworker routing or contact admin.</p>
                                @else
                                    <p class="mt-2 text-[10px] leading-relaxed text-brand-white/45">Select the manager who should review this document. Your saved manager is preselected when available.</p>
                                @endif
                            </div>

                            <div x-show="routeType === 'user'" x-transition>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-2">Select Recipient Coworker</label>
                                <select name="recipient_id" :disabled="routeType !== 'user'" :required="routeType === 'user'" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-3 py-2 text-xs focus:outline-none">
                                    <option value="">-- Select Peer Staff --</option>
                                    @foreach ($coworkerRecipients as $peer)
                                        <option value="{{ $peer->id }}" @selected(old('route_target') === 'user' && (string) old('recipient_id') === (string) $peer->id)>
                                            {{ $peer->name }} ({{ ucwords(str_replace('_', ' ', $peer->department ?? 'N/A')) }})
                                        </option>
                                    @endforeach
                                </select>
                                @if ($coworkerRecipients->isEmpty())
                                    <p class="mt-2 rounded-xl bg-brand-black/40 border border-brand-red/20 px-3 py-2 text-xs text-brand-red font-semibold">⚠️ No coworker recipients are currently available.</p>
                                @endif
                            </div>

                            <button type="submit" class="w-full py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                                Submit for Review
                            </button>
                        </form>
                    </div>
                @endif

                <!-- WORKFLOW ACTIONS PANEL (If I am the reviewer) -->
                @if ($workspace->current_holder_id === auth()->id() && $workspace->status === 'under_review')
                    <div class="glass-panel rounded-2xl p-6 border border-emerald-500/30 bg-emerald-500/5">
                        <h3 class="text-base font-semibold text-brand-white mb-4 flex items-center gap-2">
                            <span>⚡</span> Action Required
                        </h3>
                        
                        <div x-data="{ actionType: 'approve' }" class="space-y-4">
                            <div class="flex gap-2 p-1 bg-brand-black rounded-xl border border-brand-white/10">
                                <button type="button" @click="actionType = 'approve'" :class="actionType === 'approve' ? 'bg-emerald-500/20 text-emerald-400 font-bold' : 'text-brand-white/60'" class="flex-1 py-1.5 rounded-lg text-xs uppercase tracking-wider transition">
                                    Approve / Finalize
                                </button>
                                <button type="button" @click="actionType = 'reject'" :class="actionType === 'reject' ? 'bg-brand-red/25 text-brand-red font-bold' : 'text-brand-white/60'" class="flex-1 py-1.5 rounded-lg text-xs uppercase tracking-wider transition">
                                    Reject / Return
                                </button>
                            </div>

                            <form action="{{ route('portal.workspace.action', $workspace) }}" method="POST" class="space-y-4">
                                @csrf
                                <input type="hidden" name="action" :value="actionType" />

                                <div x-show="actionType === 'approve'" class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs text-emerald-400/90 leading-relaxed">
                                    Approving this document will finalize it. It will return to the author's folder in a Finalized state.
                                </div>

                                <div x-show="actionType === 'reject'" class="p-3 bg-brand-red/10 border border-brand-red/20 rounded-xl text-xs text-brand-red/90 leading-relaxed">
                                    Rejecting this document will return it to the author's folder as a Draft for editing.
                                </div>

                                <button type="submit" :class="actionType === 'approve' ? 'bg-emerald-500 hover:bg-emerald-400 text-brand-black shadow-emerald-500/20' : 'bg-brand-red hover:bg-red-500 text-brand-white shadow-brand-red/20'" class="w-full py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-lg">
                                    <span x-text="actionType === 'approve' ? 'Finalize Document' : 'Reject Document'"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
