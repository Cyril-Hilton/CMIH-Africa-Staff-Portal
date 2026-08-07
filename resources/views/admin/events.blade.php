<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Events</p>
            <h2 class="text-3xl font-display text-brand-white">Event Management</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="space-y-6">
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Publish an Event</h3>
            <form method="POST" action="{{ route('admin.events.store') }}" class="mt-4 space-y-4" enctype="multipart/form-data">
                @csrf
                <div>
                    <x-input-label for="title" :value="__('Event Title')" />
                    <x-text-input id="title" name="title" type="text" required placeholder="CMIH Strategy Summit" />
                </div>
                <div>
                    <x-input-label for="summary" :value="__('Summary')" />
                    <textarea id="summary" name="summary" rows="4" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Describe the event focus and audience"></textarea>
                </div>
                <div>
                    <x-input-label for="location" :value="__('Location')" />
                    <x-text-input id="location" name="location" type="text" placeholder="Accra, Ghana" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="starts_at" :value="__('Start Date')" />
                        <x-text-input id="starts_at" name="starts_at" type="date" required />
                    </div>
                    <div>
                        <x-input-label for="ends_at" :value="__('End Date (Optional)')" />
                        <x-text-input id="ends_at" name="ends_at" type="date" />
                    </div>
                </div>
                <div>
                    <x-input-label for="registration_url" :value="__('Registration Link (Optional)')" />
                    <x-text-input id="registration_url" name="registration_url" type="url" placeholder="https://example.com/register" />
                </div>
                <div>
                    <x-input-label for="image" :value="__('Event Banner (Optional)')" />
                    <input id="image" name="image" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-xs file:uppercase file:tracking-[0.3em] file:text-brand-white" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <x-primary-button class="w-full justify-center">Publish Event</x-primary-button>
            </form>
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Events Calendar</h3>
            <div x-data class="mt-4 space-y-4">
                @forelse ($events as $event)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $event->starts_at->format('M d, Y') }}@if ($event->ends_at) - {{ $event->ends_at->format('M d, Y') }}@endif</p>
                                <p class="mt-2 text-lg font-semibold text-brand-white">{{ $event->title }}</p>
                                <p class="text-sm text-brand-white/70">{{ $event->location ?? 'Location TBD' }}</p>
                            </div>
                            <span class="rounded-full border border-brand-white/10 bg-brand-white/5 px-3 py-1 text-xs uppercase tracking-[0.3em] text-brand-white/70">{{ $event->status }}</span>
                        </div>
                        @if ($event->summary)
                            <div class="mt-3 text-sm text-brand-white/70">{!! $event->summary !!}</div>
                        @endif
                        @if ($event->registration_url)
                            <a href="{{ $event->registration_url }}" target="_blank" rel="noreferrer" class="mt-3 inline-flex text-xs uppercase tracking-[0.3em] text-brand-red">Registration Link</a>
                        @endif
                        <details class="mt-4 rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                            <summary class="cursor-pointer text-xs uppercase tracking-[0.3em] text-brand-white/70">
                                Edit Event
                            </summary>
                            <form method="POST" action="{{ route('admin.events.update', $event) }}" class="mt-4 space-y-4" enctype="multipart/form-data">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <x-input-label for="title-{{ $event->id }}" :value="__('Event Title')" />
                                    <x-text-input id="title-{{ $event->id }}" name="title" type="text" required value="{{ $event->title }}" />
                                </div>
                                <div>
                                    <x-input-label for="summary-{{ $event->id }}" :value="__('Summary')" />
                                    <textarea id="summary-{{ $event->id }}" name="summary" rows="4" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">{{ $event->summary }}</textarea>
                                </div>
                                <div>
                                    <x-input-label for="location-{{ $event->id }}" :value="__('Location')" />
                                    <x-text-input id="location-{{ $event->id }}" name="location" type="text" value="{{ $event->location }}" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="starts_at-{{ $event->id }}" :value="__('Start Date')" />
                                        <x-text-input id="starts_at-{{ $event->id }}" name="starts_at" type="date" required value="{{ $event->starts_at->format('Y-m-d') }}" />
                                    </div>
                                    <div>
                                        <x-input-label for="ends_at-{{ $event->id }}" :value="__('End Date (Optional)')" />
                                        <x-text-input id="ends_at-{{ $event->id }}" name="ends_at" type="date" value="{{ optional($event->ends_at)->format('Y-m-d') }}" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="registration_url-{{ $event->id }}" :value="__('Registration Link (Optional)')" />
                                    <x-text-input id="registration_url-{{ $event->id }}" name="registration_url" type="url" value="{{ $event->registration_url }}" />
                                </div>
                                <div>
                                    <x-input-label for="image-{{ $event->id }}" :value="__('Event Banner (Optional)')" />
                                    <input id="image-{{ $event->id }}" name="image" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-xs file:uppercase file:tracking-[0.3em] file:text-brand-white" />
                                    @if ($event->image_path)
                                        <p class="mt-2 text-xs text-brand-white/60">Current: <a href="{{ asset('storage/'.$event->image_path) }}" target="_blank" rel="noreferrer" class="text-brand-red">View</a></p>
                                    @endif
                                </div>
                                <div>
                                    <x-input-label for="status-{{ $event->id }}" :value="__('Status')" />
                                    <select id="status-{{ $event->id }}" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                        <option value="published" @selected($event->status === 'published')>Published</option>
                                        <option value="draft" @selected($event->status === 'draft')>Draft</option>
                                    </select>
                                </div>
                                <x-primary-button class="w-full justify-center">Update Event</x-primary-button>
                            </form>
                        </details>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button 
                                    type="button"
                                    @click="$dispatch('open-confirm-modal', { url: '{{ route('admin.events.destroy', $event) }}' })"
                                    class="text-brand-red hover:text-brand-red-dark"
                                >
                                    Delete
                                </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-brand-white/60">Calendar is open. Plan an event!</p>
                @endforelse
            </div>

            @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-4">
                    {{ $events->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
