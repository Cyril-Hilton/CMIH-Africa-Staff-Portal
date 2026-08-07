<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portfolio</p>
                <h2 class="text-3xl font-display text-brand-white">Manage Albums</h2>
            </div>
            <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-album-modal')">
                {{ __('Add New Album') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-6">
        <!-- Album List -->
        <div x-data class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
            @forelse($albums as $album)
                <div class="relative overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 group hover:border-brand-red/30 transition-colors">
                    <div class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-black/20">
                        <img src="{{ asset('storage/' . $album->cover_image) }}" alt="{{ $album->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    </div>
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-wider text-brand-white/50">{{ $album->brand }}</p>
                        <h3 class="text-lg font-semibold text-brand-white">{{ $album->title }}</h3>
                        <p class="text-xs text-brand-white/40">{{ $album->images_count }} images</p>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('admin.portfolio.edit', $album) }}" class="flex-1 rounded-md bg-brand-white/10 py-2 text-center text-sm font-medium text-brand-white hover:bg-brand-white/20 transition-colors">Edit Album</a>
                        <button
                            type="button"
                            @click="$dispatch('open-confirm-modal', { url: '{{ route('admin.portfolio.destroy', $album) }}' })"
                            class="rounded-md bg-red-500/10 px-3 py-2 text-red-400 hover:bg-red-500/20 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center">
                    <p class="text-brand-white/60">No albums yet. Start showcasing your work!</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-6">
            {{ $albums->links() }}
        </div>
    </div>

    <!-- Create Modal -->
    <x-modal name="create-album-modal" :show="$errors->isNotEmpty()" maxWidth="5xl" focusable>
        <div class="p-6 bg-brand-black/95">
            <h2 class="text-lg font-medium text-brand-white">
                {{ __('Create New Portfolio Album') }}
            </h2>

            <form method="POST" action="{{ route('admin.portfolio.store') }}" class="mt-6 space-y-4" enctype="multipart/form-data">
                @csrf
                <div>
                    <x-input-label for="title" value="{{ __('Title (Execution Name)') }}" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" placeholder="e.g. Roadshow Momentum" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="brand" value="{{ __('Brand / Client') }}" />
                    <x-text-input id="brand" name="brand" type="text" class="mt-1 block w-full" placeholder="e.g. Coca-Cola" required />
                    <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="date" value="{{ __('Event Date') }}" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>
                
                <div>
                    <x-input-label for="description" value="{{ __('Description') }}" />
                    <textarea id="description" name="description" class="wysiwyg-editor mt-1 block w-full rounded-md border-brand-white/10 bg-brand-black/40 text-brand-white focus:border-brand-red focus:ring-brand-red" rows="3"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="cover_image" value="{{ __('Cover Image') }}" />
                    <input id="cover_image" name="cover_image" type="file" class="mt-1 block w-full text-sm text-gray-400 file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-white hover:file:bg-brand-white/20" accept="image/*" required />
                    <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <x-secondary-button x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-primary-button class="ml-3">
                        {{ __('Create Album') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>
</x-app-layout>
