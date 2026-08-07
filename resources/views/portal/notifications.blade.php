<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Notifications Hub</p>
                <h2 class="text-3xl font-display text-brand-white">Notifications & Announcements</h2>
            </div>
            @if($unreadCount > 0)
                <form action="{{ route('portal.notifications.readAll') }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-4 py-2 text-xs font-semibold text-brand-white transition uppercase tracking-wider">
                        Mark All as Read
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-12">
        <!-- Personal Notifications (8 Columns) -->
        <div class="lg:col-span-8 space-y-4" data-silent-region="personal-notifications">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-ash">Your Notifications</h3>
                <span class="rounded-full bg-brand-red/10 border border-brand-red/20 px-2 py-0.5 text-[10px] text-brand-red font-bold">
                    {{ $unreadCount }} Unread
                </span>
            </div>

            <div class="space-y-3">
                @forelse ($notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                    @endphp
                    <a href="{{ route('portal.notifications.read', $notification->id) }}" 
                       class="block glass-panel rounded-2xl p-4 border transition-all duration-300 hover:scale-[1.01] {{ $isUnread ? 'border-brand-red/30 bg-brand-red/5 hover:bg-brand-red/10' : 'border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 opacity-70' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex gap-3">
                                @if($isUnread)
                                    <span class="flex h-2.5 w-2.5 translate-y-1.5 rounded-full bg-brand-red shrink-0"></span>
                                @else
                                    <span class="flex h-2.5 w-2.5 translate-y-1.5 rounded-full bg-brand-white/30 shrink-0"></span>
                                @endif
                                <div>
                                    <h4 class="text-sm font-semibold text-brand-white {{ $isUnread ? 'font-bold' : '' }}">
                                        {{ $notification->title }}
                                    </h4>
                                    <p class="mt-1 text-xs text-brand-white/70">{{ $notification->message }}</p>
                                    @if($notification->url)
                                        <span class="inline-flex items-center gap-1 mt-2 text-[10px] uppercase tracking-wider text-amber-400 font-bold hover:underline">
                                            Go to item ➜
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <span class="text-[10px] uppercase tracking-wider text-brand-ash shrink-0 mt-0.5">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="glass-panel rounded-2xl p-6 text-sm text-brand-white/60 text-center">
                        No notifications yet.
                    </div>
                @endforelse

                <div class="pt-2">
                    <x-dashboard-pagination
                        :paginator="$notifications->appends(request()->except('notifications_page'))"
                        item-label="notifications"
                    />
                </div>
            </div>
        </div>

        <!-- Company Announcements (4 Columns) -->
        <div class="lg:col-span-4 space-y-4" data-silent-region="company-announcements">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-ash">Company Announcements</h3>
                <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                    {{ $announcements->total() }}
                </span>
            </div>
            
            <div class="space-y-4">
                @forelse ($announcements as $announcement)
                    <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 space-y-3">
                        <div class="flex items-center justify-between gap-2 border-b border-brand-white/5 pb-2">
                            <span class="text-[10px] uppercase tracking-wider text-amber-400 font-semibold">
                                {{ $announcement->user->name }}
                            </span>
                            <span class="text-[10px] uppercase tracking-wider text-brand-ash">
                                {{ $announcement->created_at->format('M d, Y') }}
                            </span>
                        </div>
                        <h4 class="text-sm font-semibold text-brand-white">{{ $announcement->title }}</h4>
                        <p class="text-xs text-brand-white/70 leading-relaxed">{!! nl2br(e($announcement->plainBody())) !!}</p>
                    </div>
                @empty
                    <div class="glass-panel rounded-2xl p-5 text-sm text-brand-white/60 text-center">
                        No company announcements yet.
                    </div>
                @endforelse

                <div class="pt-2">
                    <x-dashboard-pagination
                        :paginator="$announcements->appends(request()->except('announcements_page'))"
                        item-label="announcements"
                    />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
