<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">CMIH</p>
                <h2 class="text-3xl font-display text-brand-white">Messenger</h2>
            </div>
        </div>
    </x-slot>

    @php
        $authUser = auth()->user();
        $convType = $conversation ? $conversation->type : null;
        $displayName = $conversation ? $conversation->getDisplayName($authUser) : null;
        $displayPhoto = $conversation ? $conversation->getDisplayPhoto($authUser) : null;
        
        // Find if this is a DM to show online status
        $dmUser = null;
        if ($conversation && $convType === 'direct') {
            $dmUser = $conversation->users->firstWhere('id', '!=', $authUser->id);
        }
        $groupModalHasErrors = $errors->has('name')
            || $errors->has('description')
            || $errors->has('members')
            || $errors->has('members.*');
        $groupMemberNames = ($conversation && $convType === 'group')
            ? $members->pluck('name')->filter()->values()
            : collect();
        $groupMemberList = $groupMemberNames->implode(', ');
        $groupMemberPreview = $groupMemberNames->take(5)->implode(', ');
        if ($groupMemberNames->count() > 5) {
            $groupMemberPreview .= ' +' . ($groupMemberNames->count() - 5) . ' more';
        }
    @endphp

    <div x-data="{
            newGroupModal: @json($groupModalHasErrors),
            newDmModal: false,
            manageMembersModal: false,
            forwardModal: false,
            forwardMessageId: null,
            forwardBody: '',
            editMessageId: null,
            editMessageBody: '',
            replyToMessage: null,
            dmSearch: '',
            fabOpen: false,
            deleteModal: false,
            deleteAction: '',
            chatTab: 'feed',
            get filteredStaff() {
                return Array.from(document.querySelectorAll('[data-staff-item]'))
                    .filter(el => el.dataset.staffName.toLowerCase().includes(this.dmSearch.toLowerCase()));
            }
        }"
         class="relative flex h-[calc(100vh-7rem)] min-h-[600px] overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-black/80 shadow-2xl">

        {{-- ═══════════════════════ SIDEBAR ═══════════════════════ --}}
        <aside class="flex w-72 shrink-0 flex-col border-r border-brand-white/10 bg-brand-black/60 backdrop-blur-xl">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-brand-white/10">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-ash">Messages</p>
                <div class="flex items-center gap-2">
                    {{-- New Group --}}
                    <button type="button"
                            data-open-group-chat
                            @click="newGroupModal = true"
                            title="New Group"
                            class="rounded-lg bg-brand-white/5 p-1.5 text-brand-white/60 hover:bg-brand-white/10 hover:text-brand-white transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                    {{-- New DM --}}
                    <button type="button"
                            @click="newDmModal = true"
                            title="New Message"
                            class="rounded-lg bg-brand-white/5 p-1.5 text-brand-white/60 hover:bg-brand-white/10 hover:text-brand-white transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto py-2 space-y-1 px-2">
                {{-- Dynamic Chat Dropdown Selector --}}
                <div class="px-3 py-2 border-b border-brand-white/10 mb-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash mb-1">Direct Chat Selector</label>
                    <select onchange="if(this.value) { document.getElementById('sidebar-dm-start-' + this.value).submit(); }" class="w-full text-xs rounded-xl border border-brand-white/10 bg-brand-black text-brand-white py-1.5 focus:outline-none focus:border-brand-red cursor-pointer">
                        <option value="">Choose staff member...</option>
                        @foreach($allStaff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @foreach($allStaff as $staff)
                        <form id="sidebar-dm-start-{{ $staff->id }}" method="POST" action="{{ route('portal.messages.dms.start') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="recipient_id" value="{{ $staff->id }}">
                        </form>
                    @endforeach
                </div>

                {{-- ── Broadcast ── --}}
                <p class="mt-2 mb-1 px-3 text-[10px] uppercase tracking-[0.3em] text-brand-ash/60">Channels</p>
                @if($broadcast)
                    @php $broadcastUnread = $broadcast->unreadCountFor($authUser); @endphp
                    <a href="{{ route('portal.messages.show', $broadcast) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition
                              {{ $conversation && $conversation->id === $broadcast->id ? 'bg-brand-red/20 text-brand-white' : 'text-brand-white/60 hover:bg-brand-white/5 hover:text-brand-white' }}">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-white/10">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold">All Staff</p>
                        </div>
                        @if($broadcastUnread > 0)
                            <span data-conversation-unread-count="{{ $broadcastUnread }}" data-conversation-id="{{ $broadcast->id }}" class="shrink-0 rounded-full bg-brand-red px-2 py-0.5 text-[10px] font-bold leading-tight text-white">
                                {{ $broadcastUnread }}
                            </span>
                        @endif
                    </a>
                @endif

                {{-- ── Groups ── --}}
                <p class="mt-4 mb-1 px-3 text-[10px] uppercase tracking-[0.3em] text-brand-ash/60">Groups</p>
                @forelse($groups as $group)
                    @php $groupUnread = $group->unreadCountFor($authUser); @endphp
                    <a href="{{ route('portal.messages.show', $group) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition
                              {{ $conversation && $conversation->id === $group->id ? 'bg-brand-red/20 text-brand-white' : 'text-brand-white/60 hover:bg-brand-white/5 hover:text-brand-white' }}">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-red/20 text-brand-red text-sm font-bold uppercase">
                            {{ mb_substr($group->name ?? 'G', 0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold">{{ $group->name }}</p>
                            <p class="truncate text-[10px] opacity-60">{{ $group->users->count() }} members</p>
                        </div>
                        @if($groupUnread > 0)
                            <span data-conversation-unread-count="{{ $groupUnread }}" data-conversation-id="{{ $group->id }}" class="shrink-0 rounded-full bg-brand-red px-2 py-0.5 text-[10px] font-bold leading-tight text-white">
                                {{ $groupUnread }}
                            </span>
                        @endif
                    </a>
                @empty
                    <p class="px-3 text-[11px] text-brand-white/30 italic">No groups yet</p>
                @endforelse

                {{-- ── DMs ── --}}
                <p class="mt-4 mb-1 px-3 text-[10px] uppercase tracking-[0.3em] text-brand-ash/60">Direct Messages</p>
                @forelse($directChats as $dm)
                    @php
                        $dmOther = $dm->users->firstWhere('id', '!=', $authUser->id);
                        $dmUnread = $dm->unreadCountFor($authUser);
                    @endphp
                    @if($dmOther)
                    <a href="{{ route('portal.messages.show', $dm) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition
                              {{ $conversation && $conversation->id === $dm->id ? 'bg-brand-red/20 text-brand-white' : 'text-brand-white/60 hover:bg-brand-white/5 hover:text-brand-white' }}">
                        <div class="relative shrink-0">
                            <img src="{{ $dmOther->profilePhotoUrl() }}" alt="{{ $dmOther->name }}"
                                 class="h-9 w-9 rounded-full object-cover">
                            {{-- Online Indicator --}}
                            @if($dmOther->isOnline())
                                <div class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-green-500 border border-brand-black"></div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold">{{ $dmOther->name }}</p>
                            <p class="truncate text-[10px] opacity-60">
                                @if($dmOther->isOnline())
                                    <span class="text-green-400 font-medium">Online</span>
                                @else
                                    {{ $dmOther->job_title ?? 'Staff' }}
                                @endif
                            </p>
                        </div>
                        @if($dmUnread > 0)
                            <span data-conversation-unread-count="{{ $dmUnread }}" data-conversation-id="{{ $dm->id }}" class="shrink-0 rounded-full bg-brand-red px-2 py-0.5 text-[10px] font-bold leading-tight text-white">
                                {{ $dmUnread }}
                            </span>
                        @endif
                    </a>
                    @endif
                @empty
                    <p class="px-3 text-[11px] text-brand-white/30 italic">No direct messages yet</p>
                @endforelse

            </div>

            {{-- ── Sidebar FAB (New Conversation) ── --}}
            <div class="border-t border-brand-white/10">
                {{-- Inline expanding menu (no absolute positioning – avoids overflow-hidden clipping) --}}
                <div x-show="fabOpen"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="border-b border-brand-white/10 bg-brand-black/90 p-2 space-y-0.5">
                    <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-brand-ash/60">New Conversation</p>

                    <button type="button"
                            @click="newDmModal = true; fabOpen = false"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-medium text-brand-white/80 hover:bg-brand-white/5 hover:text-brand-white transition">
                        <svg class="h-4 w-4 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        New Direct Chat
                    </button>

                    <button type="button"
                            data-open-group-chat
                            @click="newGroupModal = true; fabOpen = false"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-medium text-brand-white/80 hover:bg-brand-white/5 hover:text-brand-white transition">
                        <svg class="h-4 w-4 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Create Group Chat
                    </button>

                    <form method="POST" action="{{ route('portal.messages.broadcast.create') }}">
                        @csrf
                        <button type="submit" @click="fabOpen = false"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-medium text-brand-white/80 hover:bg-brand-white/5 hover:text-brand-white transition">
                            <svg class="h-4 w-4 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                            Create Broadcast
                        </button>
                    </form>
                </div>

                {{-- Trigger button --}}
                <div class="p-3">
                    <button type="button"
                            @click="fabOpen = !fabOpen"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-brand-red/80 transition active:scale-95">
                        <svg class="h-4 w-4 transition-transform duration-200" :class="{'rotate-45': fabOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span x-text="fabOpen ? 'Close' : 'New Conversation'"></span>
                    </button>
                </div>
            </div>
        </aside>

        {{-- ═══════════════════════ MAIN CHAT AREA ═══════════════════════ --}}
        <div class="flex flex-1 flex-col overflow-hidden">

            @if(! $conversation)
                {{-- Welcome Screen / Empty Chat State --}}
                <div class="flex-1 flex flex-col items-center justify-center bg-brand-black/40 p-8 text-center relative">
                    <div class="max-w-md space-y-6">
                        <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-brand-red/10 text-brand-red mb-2">
                            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-2xl font-display text-brand-white">You have no active chats</h3>
                            <p class="text-sm text-brand-white/50">Select an inbox from the sidebar to chat, or start a new interaction using the options below.</p>
                        </div>
                        
                        {{-- Center Grid Buttons --}}
                        <div class="grid grid-cols-1 gap-4 pt-4 sm:grid-cols-3">
                            <button type="button"
                                    @click="newDmModal = true"
                                    class="flex flex-col items-center justify-center gap-3 rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 hover:bg-brand-white/10 hover:border-brand-red/40 transition group">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-red/10 text-brand-red group-hover:bg-brand-red group-hover:text-white transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-brand-white/90">New Direct Chat</span>
                            </button>
                            
                            <button type="button"
                                    data-open-group-chat
                                    @click="newGroupModal = true"
                                    class="flex flex-col items-center justify-center gap-3 rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 hover:bg-brand-white/10 hover:border-brand-red/40 transition group">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-red/10 text-brand-red group-hover:bg-brand-red group-hover:text-white transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-brand-white/90">Create Group Chat</span>
                            </button>

                            <form method="POST" action="{{ route('portal.messages.broadcast.create') }}" class="w-full">
                                @csrf
                                <button type="submit"
                                        class="w-full h-full flex flex-col items-center justify-center gap-3 rounded-xl border border-brand-white/10 bg-brand-white/5 p-5 hover:bg-brand-white/10 hover:border-brand-red/40 transition group">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-red/10 text-brand-red group-hover:bg-brand-red group-hover:text-white transition">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-brand-white/90">Create Broadcast</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                {{-- Top Bar --}}
                <div class="flex items-center justify-between border-b border-brand-white/10 bg-brand-black/40 px-6 py-4">
                    <div class="flex items-center gap-3">
                        @if($convType === 'broadcast')
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-white/10">
                                <svg class="h-5 w-5 text-brand-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                            </div>
                        @elseif($displayPhoto)
                            <div class="relative">
                                <img src="{{ $displayPhoto }}" alt="{{ $displayName }}" class="h-10 w-10 rounded-full object-cover">
                                @if($dmUser && $dmUser->isOnline())
                                    <div class="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-green-500 border-2 border-brand-black"></div>
                                @endif
                            </div>
                        @else
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-red/20 text-brand-red font-bold uppercase">
                                {{ mb_substr($displayName, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-brand-white flex items-center gap-2">
                                {{ $displayName }}
                            </p>
                            <p class="text-xs text-brand-white/50">
                                @if($convType === 'broadcast') Company-wide channel
                                @elseif($convType === 'group') {{ $members->count() }} members &middot; {{ $conversation->description ?? 'Group chat' }}
                                @elseif($dmUser)
                                    @if($dmUser->isOnline())
                                        <span class="text-green-400 font-medium">Online</span>
                                    @else
                                        {{ $dmUser->lastSeenLabel() }}
                                    @endif
                                @else Direct message
                                @endif
                            </p>
                            @if($convType === 'group' && $groupMemberPreview)
                                <p data-group-member-names class="mt-0.5 max-w-[34rem] truncate text-[11px] text-brand-white/35" title="{{ $groupMemberList }}">
                                    {{ $groupMemberPreview }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @php
                            $mediaMessages = $conversation ? $messages->filter(fn($m) => $m->hasAttachment() && !$m->is_deleted) : collect();
                        @endphp
                        @if($conversation)
                            <div class="flex items-center gap-1 rounded-xl border border-brand-white/10 bg-brand-white/5 p-1">
                                <button type="button" @click="chatTab = 'feed'" :class="chatTab === 'feed' ? 'bg-brand-red text-white' : 'text-brand-white/60 hover:text-white'" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition">Chat</button>
                                <button type="button" @click="chatTab = 'gallery'" :class="chatTab === 'gallery' ? 'bg-brand-red text-white' : 'text-brand-white/60 hover:text-white'" class="rounded-lg px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition">Gallery ({{ $mediaMessages->count() }})</button>
                            </div>
                        @endif
                        @if($convType === 'group')
                            <button type="button"
                                    @click="manageMembersModal = true"
                                    class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-widest text-brand-white/70 hover:bg-brand-white/10 hover:text-brand-white transition">
                                Members
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Wrapped Feed and Input --}}
                <div x-show="chatTab === 'feed'" class="flex-1 flex flex-col overflow-hidden">
                    {{-- Messages Feed --}}
                    <div id="chat-messages" class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                        @forelse($messages as $msg)
                            @php $isMine = $msg->user_id === $authUser->id; @endphp
                            <div data-message-id="{{ $msg->id }}" class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} items-end gap-2 group relative">
                                
                                {{-- Avatar (left side, not mine) --}}
                                @if(! $isMine)
                                    <img src="{{ $msg->user->profilePhotoUrl() }}" alt="{{ $msg->user->name }}"
                                         class="h-7 w-7 shrink-0 rounded-full object-cover self-end mb-1">
                                @endif

                                {{-- Bubble Wrapper --}}
                                <div class="max-w-sm space-y-1 relative" x-data="{ openMenu: false }">
                                    @if(! $isMine && $convType !== 'direct')
                                        <p class="ml-1 text-[10px] uppercase tracking-wider text-brand-ash">{{ $msg->user->name }}</p>
                                    @endif

                                    {{-- Reply preview block --}}
                                    @if($msg->replyTo && !$msg->is_deleted)
                                        <div class="rounded-t-2xl border-l-4 border-brand-red bg-white/5 px-3 py-1.5 text-xs text-brand-white/80 mb-[-6px]">
                                            <p class="font-bold text-[10px] text-brand-red">{{ $msg->replyTo->user->id === $authUser->id ? 'You' : $msg->replyTo->user->name }}</p>
                                            <p class="truncate opacity-70">{{ $msg->replyTo->displayBody() }}</p>
                                        </div>
                                    @endif

                                    <div class="rounded-2xl px-4 py-2.5 text-sm relative
                                                {{ $isMine
                                                    ? 'rounded-br-sm bg-brand-red text-white'
                                                    : 'rounded-bl-sm bg-brand-white/10 text-brand-white/90 backdrop-blur' }}">
                                        
                                        {{-- Inline edit field --}}
                                        <div x-show="editMessageId === {{ $msg->id }}" x-cloak class="space-y-2">
                                            <form method="POST" action="{{ route('portal.messages.edit', $msg) }}">
                                                @csrf
                                                <textarea x-model="editMessageBody"
                                                          name="body"
                                                          class="w-full rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-1.5 text-xs text-brand-white focus:outline-none resize-none"></textarea>
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" @click="editMessageId = null"
                                                            class="text-[10px] uppercase tracking-wider text-brand-ash hover:text-white transition">Cancel</button>
                                                    <button type="submit"
                                                            class="text-[10px] uppercase tracking-wider text-green-400 font-semibold hover:text-green-300 transition">Save</button>
                                                </div>
                                            </form>
                                        </div>

                                        {{-- Message Body Display --}}
                                        <div x-show="editMessageId !== {{ $msg->id }}">
                                            @if($msg->is_deleted)
                                                <p class="italic text-white/50 text-xs flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                    This message was deleted
                                                </p>
                                            @else
                                                @if($msg->body)
                                                    <p class="leading-relaxed whitespace-pre-line">{{ $msg->body }}</p>
                                                @endif

                                                {{-- Attachment --}}
                                                @if($msg->hasAttachment())
                                                    @if($msg->isImage())
                                                        <a href="{{ $msg->attachmentUrl() }}" target="_blank">
                                                            <img src="{{ $msg->attachmentUrl() }}" alt="Image"
                                                                 class="mt-2 max-h-60 w-full rounded-xl object-cover">
                                                        </a>
                                                    @elseif($msg->isVideo())
                                                        <video controls class="mt-2 max-h-60 w-full rounded-xl">
                                                            <source src="{{ $msg->attachmentUrl() }}">
                                                        </video>
                                                    @else
                                                        <a href="{{ $msg->attachmentUrl() }}" target="_blank"
                                                           class="mt-2 flex items-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-xs hover:bg-white/10 transition">
                                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
                                                            <span class="truncate">Download attachment</span>
                                                        </a>
                                                    @endif
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Bottom row with status --}}
                                    <div class="flex items-center gap-1.5 px-1 text-[10px] text-brand-white/30 {{ $isMine ? 'justify-end' : 'justify-start' }}">
                                        @if($msg->is_edited && !$msg->is_deleted)
                                            <span class="italic text-[9px]">edited</span>
                                            <span>&middot;</span>
                                        @endif
                                        <span>{{ $msg->created_at->format('g:i A') }}</span>
                                        
                                        @if($isMine && !$msg->is_deleted)
                                            @if($msg->readCount() > 0)
                                                <svg class="h-3.5 w-3.5 text-sky-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M0.41 13.41L6 19L7.41 17.59L1.83 12L0.41 13.41ZM6 19L21.59 3.41L23 4.83L6 21.83L1.83 17.66L3.24 16.24L6 19ZM16.59 8.59L15.17 7.17L11.59 10.76L13 12.17L16.59 8.59Z"/>
                                                </svg>
                                            @else
                                                <svg class="h-3.5 w-3.5 text-white/40" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                </svg>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- Options Dropdown --}}
                                    @if(!$msg->is_deleted)
                                    <div class="touch-visible absolute right-2 top-2 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 z-10">
                                        <button type="button"
                                                @click="openMenu = !openMenu"
                                                class="rounded-full bg-brand-black/60 p-2 text-brand-white/70 hover:bg-brand-black hover:text-white transition sm:p-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        
                                        <div x-show="openMenu"
                                             @click.outside="openMenu = false"
                                             x-cloak
                                             class="absolute right-0 mt-1 w-28 rounded-lg border border-brand-white/10 bg-brand-black p-1 shadow-xl z-20">
                                            <button type="button"
                                                    @click="replyToMessage = {id: {{ $msg->id }}, name: '{{ $msg->user->name }}', body: '{{ addslashes(mb_substr($msg->body ?? 'Attachment', 0, 40)) }}'}; openMenu = false"
                                                    class="flex w-full items-center px-2 py-1 text-[11px] text-brand-white/70 hover:bg-brand-white/5 hover:text-white rounded transition">
                                                Reply
                                            </button>
                                            <button type="button"
                                                    @click="forwardMessageId = {{ $msg->id }}; forwardBody = '{{ addslashes($msg->body ?? '') }}'; forwardModal = true; openMenu = false"
                                                    class="flex w-full items-center px-2 py-1 text-[11px] text-brand-white/70 hover:bg-brand-white/5 hover:text-white rounded transition">
                                                Forward
                                            </button>
                                            @if($isMine)
                                                <button type="button"
                                                        @click="editMessageId = {{ $msg->id }}; editMessageBody = '{{ addslashes($msg->body ?? '') }}'; openMenu = false"
                                                        class="flex w-full items-center px-2 py-1 text-[11px] text-brand-white/70 hover:bg-brand-white/5 hover:text-white rounded transition">
                                                    Edit
                                                </button>
                                            @endif
                                            @if($isMine || ($convType === 'group' && $isGroupAdmin))
                                                <button type="button"
                                                        @click="deleteModal = true; deleteAction = '{{ route('portal.messages.delete', $msg) }}'; openMenu = false"
                                                        class="flex w-full items-center px-2 py-1 text-[11px] text-red-400 hover:bg-red-500/10 rounded transition">
                                                    Delete
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    @endif

                                </div>

                                @if($isMine)
                                    <img src="{{ $authUser->profilePhotoUrl() }}" alt="{{ $authUser->name }}"
                                         class="h-7 w-7 shrink-0 rounded-full object-cover self-end mb-1">
                                @endif
                            </div>
                        @empty
                            <div class="flex h-full items-center justify-center">
                                <p class="text-sm text-brand-white/30 italic">No messages yet. Say hello!</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Message Input --}}
                    <div class="border-t border-brand-white/10 bg-brand-black/40 px-6 py-4">
                        
                        {{-- Reply Preview Bar --}}
                        <div x-show="replyToMessage"
                             x-cloak
                             class="mb-3 flex items-center justify-between rounded-xl bg-white/5 px-4 py-2 border-l-4 border-brand-red transition-all">
                            <div>
                                <p class="text-[10px] text-brand-red font-bold uppercase tracking-wider">Replying to <span x-text="replyToMessage?.name"></span></p>
                                <p class="text-xs text-brand-white/70 truncate max-w-xl" x-text="replyToMessage?.body"></p>
                            </div>
                            <button type="button"
                                    @click="replyToMessage = null"
                                    class="text-brand-white/40 hover:text-white transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST"
                              action="{{ route('portal.messages.send', $conversation) }}"
                              enctype="multipart/form-data"
                              x-data="{
                                  fileName: null,
                                  emojiOpen: false,
                                  emojiCodes: [0x1F600, 0x1F602, 0x1F60A, 0x1F44D, 0x1F64F, 0x1F389, 0x1F525, 0x1F44F, 0x270C],
                                  insertEmoji(code) {
                                      const input = this.$refs.bodyInput;
                                      if (! input) return;

                                      const emoji = String.fromCodePoint(code);
                                      const start = input.selectionStart ?? input.value.length;
                                      const end = input.selectionEnd ?? start;
                                      input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
                                      input.dispatchEvent(new Event('input', { bubbles: true }));

                                      this.$nextTick(() => {
                                          input.focus();
                                          const cursor = start + emoji.length;
                                          input.setSelectionRange(cursor, cursor);
                                      });
                                  }
                              }"
                              class="flex items-end gap-3">
                            @csrf
                            
                            <input type="hidden" name="reply_to_id" :value="replyToMessage?.id">

                            {{-- Attachment --}}
                            <label class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-brand-white/5 text-brand-white/50 hover:bg-brand-white/10 hover:text-brand-white transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <input type="file" name="attachment" class="sr-only"
                                       accept="image/*,video/mp4,video/mov,video/avi,.pdf,.doc,.docx,.xls,.xlsx"
                                       @change="fileName = $event.target.files[0]?.name">
                            </label>

                            <div class="relative shrink-0" @click.outside="emojiOpen = false">
                                <button type="button"
                                        aria-label="Insert emoji"
                                        title="Insert emoji"
                                        @click="emojiOpen = ! emojiOpen"
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-white/5 text-brand-white/50 hover:bg-brand-white/10 hover:text-brand-white transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.828 14.828a4 4 0 01-5.656 0M9 9h.01M15 9h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <div x-show="emojiOpen"
                                     x-transition
                                     x-cloak
                                     class="absolute bottom-12 left-0 z-30 grid w-44 grid-cols-3 gap-1 rounded-2xl border border-brand-white/10 bg-brand-black p-2 shadow-2xl">
                                    <template x-for="code in emojiCodes" :key="code">
                                        <button type="button"
                                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-white/5 text-xl hover:bg-brand-white/10 transition"
                                                @click="insertEmoji(code); emojiOpen = false"
                                                x-text="String.fromCodePoint(code)">
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="relative flex-1">
                                <textarea name="body"
                                          x-ref="bodyInput"
                                          rows="1"
                                          placeholder="Type a message... (Enter to send, Shift+Enter for new line)"
                                          class="w-full resize-none rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red/60 focus:outline-none focus:ring-0 transition"
                                          style="max-height: 120px; overflow-y: auto;"
                                          @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                          @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.closest('form').requestSubmit(); }"></textarea>
                                <template x-if="fileName">
                                    <p class="absolute -top-6 left-2 text-[10px] text-brand-ash truncate max-w-xs" x-text="'Attached: ' + fileName"></p>
                                </template>
                            </div>

                            <button type="submit"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-red text-white hover:bg-brand-red/80 transition shadow-lg">
                                <svg class="h-5 w-5 -rotate-45" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </form>
                        @error('body') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        @error('attachment') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Wrapped Media Gallery Panel --}}
                <div x-show="chatTab === 'gallery'" x-cloak class="flex-1 overflow-y-auto px-6 py-6 bg-brand-black/40">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-white mb-4">Conversation Media Gallery</h3>
                    @if($mediaMessages->count() > 0)
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            @foreach($mediaMessages as $mediaMsg)
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-2 flex flex-col justify-between group relative hover:border-brand-red/50 transition">
                                    <div class="aspect-square w-full overflow-hidden rounded-lg bg-black flex items-center justify-center relative">
                                        @if($mediaMsg->isImage())
                                            <img src="{{ $mediaMsg->attachmentUrl() }}" class="h-full w-full object-cover">
                                        @elseif($mediaMsg->isVideo())
                                            <video class="h-full w-full object-cover">
                                                <source src="{{ $mediaMsg->attachmentUrl() }}">
                                            </video>
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                                                <svg class="h-10 w-10 text-white/80" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center gap-2 text-brand-white/60">
                                                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span class="text-[10px] text-center px-2 truncate max-w-full" title="{{ basename($mediaMsg->attachment_path) }}">{{ basename($mediaMsg->attachment_path) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="text-[9px] text-brand-ash truncate">{{ $mediaMsg->user->name }}</span>
                                        <a href="{{ $mediaMsg->attachmentUrl() }}" download target="_blank" class="rounded bg-brand-white/10 hover:bg-brand-red px-2 py-1 text-[10px] font-semibold text-brand-white transition flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Save
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-brand-white/40 italic">No media shared in this conversation yet.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- FAB moved to sidebar bottom --}}

    </div>

    {{-- ═══════════════════════ MODALS (inside root x-data scope) ═══════════════════════ --}}

    {{-- Delete Confirmation Modal --}}
    <div x-show="deleteModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/70"
         @keydown.escape.window="deleteModal = false">
        <div @click.outside="deleteModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-90"
             class="w-full max-w-sm rounded-2xl border border-red-500/20 bg-brand-black shadow-2xl overflow-hidden">
            <div class="p-6 text-center space-y-4">
                {{-- Icon --}}
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500/10 mx-auto">
                    <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                {{-- Text --}}
                <div class="space-y-1">
                    <h3 class="text-lg font-semibold text-brand-white">Delete Message?</h3>
                    <p class="text-sm text-brand-white/50">This action cannot be undone. The message will be permanently removed.</p>
                </div>
                {{-- Actions --}}
                <div class="flex gap-3 pt-2">
                    <button type="button"
                            @click="deleteModal = false"
                            class="flex-1 rounded-xl border border-brand-white/10 py-2.5 text-sm font-medium text-brand-white/70 hover:bg-brand-white/5 transition">
                        Cancel
                    </button>
                    <form :action="deleteAction" method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 active:scale-95 transition">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Forward Message Modal --}}
    <div x-show="forwardModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60"
         @keydown.escape.window="forwardModal = false">
        <div @click.outside="forwardModal = false"
             class="w-full max-w-md rounded-2xl border border-brand-white/10 bg-brand-black shadow-2xl">
            <div class="border-b border-brand-white/10 px-6 py-4">
                <h3 class="text-lg font-semibold text-brand-white">Forward Message</h3>
                <p class="text-xs text-brand-white/50 mt-1">Select a conversation to forward this message to</p>
                <div class="mt-3 p-2 rounded bg-white/5 text-xs text-brand-white/70 italic truncate" x-text="forwardBody"></div>
            </div>
            <div class="max-h-60 overflow-y-auto p-3 space-y-1">
                @foreach($forwardConversations as $fConv)
                    <form method="POST" :action="'/portal/messages/forward/' + forwardMessageId">
                        @csrf
                        <input type="hidden" name="conversation_id" value="{{ $fConv->id }}">
                        <button type="submit"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left hover:bg-brand-white/5 transition">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-white/10 text-brand-white text-xs font-bold uppercase shrink-0">
                                {{ mb_substr($fConv->getDisplayName($authUser), 0, 1) }}
                            </div>
                            <div>
                                <p class="text-xs font-medium text-brand-white">{{ $fConv->getDisplayName($authUser) }}</p>
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
            <div class="border-t border-brand-white/10 px-6 py-3">
                <button type="button"
                        @click="forwardModal = false"
                        class="w-full rounded-xl border border-brand-white/10 py-2 text-sm text-brand-white/70 hover:bg-brand-white/5 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- New Group Chat Modal --}}
    <div x-show="newGroupModal"
         x-cloak
         id="new-group-chat-modal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60"
         @keydown.escape.window="newGroupModal = false">
        <div @click.outside="newGroupModal = false"
             class="w-full max-w-lg rounded-2xl border border-brand-white/10 bg-brand-black shadow-2xl">
            <div class="border-b border-brand-white/10 px-6 py-4">
                <h3 class="text-lg font-semibold text-brand-white">New Group Chat</h3>
                <p class="text-xs text-brand-white/50 mt-0.5">Create a group and invite team members</p>
            </div>
            <form method="POST" action="{{ route('portal.messages.groups.create') }}" class="p-6 space-y-4">
                @csrf
                @if($errors->has('name') || $errors->has('description') || $errors->has('members') || $errors->has('members.*'))
                    <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 p-3 text-xs text-brand-white/80">
                        <p class="font-semibold text-brand-red mb-1">Group chat could not be created:</p>
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach($errors->get('name') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @foreach($errors->get('description') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @foreach($errors->get('members') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @foreach($errors->get('members.*') as $fieldErrors)
                                @foreach($fieldErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div>
                    <label class="block text-xs uppercase tracking-wider text-brand-ash mb-1">Group Name *</label>
                    <input type="text" name="name" required maxlength="100"
                           value="{{ old('name') }}"
                           placeholder="e.g. Marketing Committee"
                           class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red/60 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-brand-ash mb-1">Description</label>
                    <textarea name="description" rows="2" maxlength="500"
                              placeholder="What is this group for?"
                              class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2.5 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red/60 focus:outline-none resize-none">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Add Members *</label>
                    <div class="max-h-48 overflow-y-auto space-y-2 rounded-xl border border-brand-white/10 p-3 bg-brand-white/5">
                        @foreach($allStaff as $staff)
                            <label class="flex items-center gap-3 cursor-pointer rounded-lg px-2 py-1.5 hover:bg-brand-white/5 transition">
                                <input type="checkbox" name="members[]" value="{{ $staff->id }}"
                                       @checked(in_array($staff->id, old('members', [])))
                                       class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                                <img src="{{ $staff->profilePhotoUrl() }}" alt="{{ $staff->name }}"
                                     class="h-7 w-7 rounded-full object-cover">
                                <div>
                                    <p class="text-sm text-brand-white">{{ $staff->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ $staff->job_title ?? $staff->department ?? '' }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button"
                            data-close-group-chat
                            @click="newGroupModal = false"
                            class="rounded-xl border border-brand-white/10 px-5 py-2.5 text-sm text-brand-white/70 hover:bg-brand-white/5 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded-xl bg-brand-red px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-red/80 transition">
                        Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- New DM Modal --}}
    <div x-show="newDmModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60"
         @keydown.escape.window="newDmModal = false">
        <div @click.outside="newDmModal = false"
             class="w-full max-w-md rounded-2xl border border-brand-white/10 bg-brand-black shadow-2xl">
            <div class="border-b border-brand-white/10 px-6 py-4">
                <h3 class="text-lg font-semibold text-brand-white">New Direct Message</h3>
                <div class="mt-3">
                    <input type="text" x-model="dmSearch" placeholder="Search staff..."
                           class="w-full rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red/60 focus:outline-none">
                </div>
            </div>
            <div class="max-h-72 overflow-y-auto p-3 space-y-1">
                @foreach($allStaff as $staff)
                    <div data-staff-item data-staff-name="{{ strtolower($staff->name) }}"
                         x-show="$el.dataset.staffName.includes(dmSearch.toLowerCase())">
                        <form method="POST" action="{{ route('portal.messages.dms.start') }}">
                            @csrf
                            <input type="hidden" name="recipient_id" value="{{ $staff->id }}">
                            <button type="submit"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-brand-white/5 transition">
                                <div class="relative shrink-0">
                                    <img src="{{ $staff->profilePhotoUrl() }}" alt="{{ $staff->name }}"
                                         class="h-9 w-9 rounded-full object-cover">
                                    @if($staff->isOnline())
                                        <div class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-green-500 border border-brand-black"></div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-brand-white">{{ $staff->name }}</p>
                                    <p class="text-[10px] text-brand-ash">{{ $staff->job_title ?? $staff->department ?? 'Staff' }}</p>
                                </div>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-brand-white/10 px-6 py-3">
                <button type="button"
                        @click="newDmModal = false"
                        class="w-full rounded-xl border border-brand-white/10 py-2 text-sm text-brand-white/70 hover:bg-brand-white/5 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Members Modal --}}
    @if($conversation && $convType === 'group')
    <div x-show="manageMembersModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/60"
         @keydown.escape.window="manageMembersModal = false">
        <div @click.outside="manageMembersModal = false"
             class="w-full max-w-lg rounded-2xl border border-brand-white/10 bg-brand-black shadow-2xl">
            <div class="border-b border-brand-white/10 px-6 py-4">
                <h3 class="text-lg font-semibold text-brand-white">{{ $isGroupAdmin ? 'Manage Members' : 'Group Members' }}</h3>
                <p class="text-xs text-brand-white/50 mt-0.5">{{ $displayName }} &middot; {{ $members->count() }} members</p>
            </div>
            <div class="p-6 space-y-4">
                {{-- Current Members --}}
                <div>
                    <p class="text-xs uppercase tracking-wider text-brand-ash mb-2">Current Members</p>
                    <div class="space-y-2 max-h-48 overflow-y-auto rounded-xl border border-brand-white/10 p-3 bg-brand-white/5">
                        @foreach($members as $member)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $member->profilePhotoUrl() }}" alt="{{ $member->name }}"
                                         class="h-7 w-7 rounded-full object-cover">
                                    <div>
                                        <p class="text-sm text-brand-white">{{ $member->name }}</p>
                                        @if($member->pivot->is_admin)
                                            <p class="text-[10px] text-brand-red uppercase tracking-wider">Admin</p>
                                        @endif
                                    </div>
                                </div>
                                @if($isGroupAdmin && $member->id !== $authUser->id)
                                    <form method="POST" action="{{ route('portal.messages.members.remove', [$conversation, $member]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border border-red-500/30 px-2 py-1 text-[10px] uppercase tracking-wider text-red-400 hover:bg-red-500/10 transition"
                                                onclick="return confirm('Remove {{ $member->name }} from the group?')">
                                            Remove
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($isGroupAdmin)
                    {{-- Add New Members --}}
                    @php
                        $memberIds = $members->pluck('id')->toArray();
                        $nonMembers = $allStaff->whereNotIn('id', $memberIds);
                    @endphp
                    @if($nonMembers->count() > 0)
                    <div>
                        <p class="text-xs uppercase tracking-wider text-brand-ash mb-2">Add Members</p>
                        <div class="max-h-40 overflow-y-auto space-y-1 rounded-xl border border-brand-white/10 p-3 bg-brand-white/5">
                            @foreach($nonMembers as $staff)
                                <form method="POST" action="{{ route('portal.messages.members.add', $conversation) }}"
                                      class="flex items-center justify-between gap-3">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $staff->id }}">
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $staff->profilePhotoUrl() }}" alt="{{ $staff->name }}"
                                             class="h-7 w-7 rounded-full object-cover">
                                        <p class="text-sm text-brand-white">{{ $staff->name }}</p>
                                    </div>
                                    <button type="submit"
                                            class="rounded-lg border border-brand-white/20 px-3 py-1 text-[10px] uppercase tracking-wider text-brand-white/60 hover:bg-brand-white/10 hover:text-brand-white transition">
                                        Add
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif
            </div>
            <div class="border-t border-brand-white/10 px-6 py-3">
                <button type="button"
                        @click="manageMembersModal = false"
                        class="w-full rounded-xl border border-brand-white/10 py-2 text-sm text-brand-white/70 hover:bg-brand-white/5 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Auto-scroll to bottom and real-time polling script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const groupModal = document.getElementById('new-group-chat-modal');
            const groupRoot = groupModal?.closest('[x-data]');
            const setGroupModalState = (isOpen) => {
                if (!groupRoot || !window.Alpine || typeof window.Alpine.$data !== 'function') {
                    return;
                }

                const data = window.Alpine.$data(groupRoot);
                if (data && Object.prototype.hasOwnProperty.call(data, 'newGroupModal')) {
                    data.newGroupModal = isOpen;
                }
            };
            const showGroupModal = () => {
                if (!groupModal) return;
                setGroupModalState(true);
                groupModal.removeAttribute('x-cloak');
                groupModal.classList.remove('hidden');
                groupModal.style.setProperty('display', 'flex', 'important');
                groupModal.querySelector('input[name="name"]')?.focus({ preventScroll: true });
            };
            const hideGroupModal = () => {
                if (!groupModal) return;
                setGroupModalState(false);
                groupModal.style.setProperty('display', 'none', 'important');
            };

            document.querySelectorAll('[data-open-group-chat]').forEach((button) => {
                button.addEventListener('click', showGroupModal);
            });

            document.querySelectorAll('[data-close-group-chat]').forEach((button) => {
                button.addEventListener('click', hideGroupModal);
            });

            groupModal?.addEventListener('click', (event) => {
                if (event.target === groupModal) {
                    hideGroupModal();
                }
            });

            const feed = document.getElementById('chat-messages');
            if (feed) feed.scrollTop = feed.scrollHeight;

            // 60-Second Real-Time Polling refresh
            setInterval(function() {
                const editFieldActive = document.querySelector('textarea[name="body"][x-model="editMessageBody"]');
                if (editFieldActive) return; // do not poll while user is editing inline
                
                const currentConvId = "{{ $conversation ? $conversation->id : '' }}";
                if (!currentConvId) return;

                fetch(window.location.href)
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newFeed = doc.getElementById('chat-messages');
                        const oldFeed = document.getElementById('chat-messages');
                        
                        if (newFeed && oldFeed) {
                            const oldMsgCount = oldFeed.querySelectorAll('[data-message-id]').length;
                            const newMsgCount = newFeed.querySelectorAll('[data-message-id]').length;
                            
                            if (newMsgCount !== oldMsgCount || newFeed.innerHTML !== oldFeed.innerHTML) {
                                const wasAtBottom = oldFeed.scrollHeight - oldFeed.scrollTop <= oldFeed.clientHeight + 100;
                                oldFeed.innerHTML = newFeed.innerHTML;
                                if (wasAtBottom) {
                                    oldFeed.scrollTop = oldFeed.scrollHeight;
                                }
                            }
                        }
                    });
            }, 60000); // Poll every 60 seconds
        });
    </script>

</div>{{-- END root x-data --}}
</x-app-layout>
