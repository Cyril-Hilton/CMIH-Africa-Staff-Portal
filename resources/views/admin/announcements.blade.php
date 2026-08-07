<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Announcements</p>
            <h2 class="text-3xl font-display text-brand-white">Company Broadcasts</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="space-y-6">
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">New Announcement</h3>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <x-input-label for="title" :value="__('Title')" />
                    <x-text-input id="title" name="title" type="text" required placeholder="Agency wide update" />
                </div>
                <div>
                    <x-input-label for="body" :value="__('Message')" />
                    <textarea id="body" name="body" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Share the announcement"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-brand-white/70">
                    <input type="checkbox" name="pinned" value="1" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                    Pin this announcement
                </label>
                <x-primary-button class="w-full justify-center">Publish</x-primary-button>
            </form>
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Recent Announcements</h3>
            <div class="mt-4 space-y-4">
                @forelse ($announcements as $announcement)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $announcement->title }}</p>
                            <p class="text-xs text-brand-white/60">{{ $announcement->created_at->format('M d, Y') }}</p>
                        </div>
                        <p class="mt-2 text-sm text-brand-white/70">{!! nl2br(e($announcement->plainBody())) !!}</p>
                        <p class="mt-2 text-xs text-brand-white/50">By {{ $announcement->user?->name ?? 'Admin' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-brand-white/60">No announcements published yet.</p>
                @endforelse

                @if ($announcements instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="pt-4">
                        {{ $announcements->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>


