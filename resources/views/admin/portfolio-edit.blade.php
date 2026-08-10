<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.portfolio') }}" class="text-xs uppercase tracking-wider text-brand-ash hover:text-white transition-colors">← Back to Albums</a>
            <h2 class="mt-2 text-3xl font-display text-brand-white">Manage: <span class="text-brand-red">{{ $album->title }}</span></h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Details Form -->
        <div class="glass-panel rounded-2xl p-6 h-fit">
            <h3 class="mb-4 text-lg font-semibold text-brand-white">Album Details</h3>
            <form method="POST" action="{{ route('admin.portfolio.update', $album) }}" class="space-y-4" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="title" value="{{ __('Title') }}" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $album->title)" required />
                </div>

                <div>
                    <x-input-label for="brand" value="{{ __('Brand') }}" />
                    <x-text-input id="brand" name="brand" type="text" class="mt-1 block w-full" :value="old('brand', $album->brand)" required />
                </div>

                <div>
                    <x-input-label for="date" value="{{ __('Date') }}" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', $album->date?->format('Y-m-d'))" />
                </div>

                <div>
                    <x-input-label for="description" value="{{ __('Description') }}" />
                    <textarea id="description" name="description" class="wysiwyg-editor mt-1 block w-full rounded-md border-brand-white/10 bg-brand-black/40 text-brand-white focus:border-brand-red focus:ring-brand-red" rows="4">{{ old('description', $album->description) }}</textarea>
                </div>

                <div>
                    <x-input-label for="cover_image" value="{{ __('Update Cover (Optional)') }}" />
                    <input id="cover_image" name="cover_image" type="file" class="mt-1 block w-full text-sm text-gray-400 file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-white hover:file:bg-brand-white/20" accept="image/*" />
                    <div class="mt-2 h-24 w-full overflow-hidden rounded-lg border border-brand-white/10">
                        <img src="{{ Storage::disk('public')->url($album->cover_image) }}" class="h-full w-full object-cover">
                    </div>
                </div>

                <div class="pt-2">
                    <x-primary-button class="w-full justify-center">{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Gallery Management -->
        <div class="space-y-6">
            <!-- Upload Area -->
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="mb-4 text-lg font-semibold text-brand-white">Upload Gallery Images</h3>
                <form method="POST" action="{{ route('admin.portfolio.upload', $album) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <input id="images" name="images[]" type="file" multiple class="block w-full text-sm text-gray-400 file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-white hover:file:bg-brand-white/20" accept="image/*" required />
                        </div>
                        <x-primary-button>{{ __('Upload') }}</x-primary-button>
                    </div>
                    <p class="text-xs text-brand-white/40">You can select multiple images at once.</p>
                </form>
            </div>

            <!-- Image Grid -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($album->images as $image)
                    <div class="group relative aspect-square overflow-hidden rounded-lg border border-brand-white/10 bg-black/40">
                        <img src="{{ Storage::disk('public')->url($image->image_path) }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-110">
                        <form method="POST" action="{{ route('admin.portfolio.image.destroy', $image) }}" class="touch-visible absolute inset-0 flex items-center justify-center bg-black/60 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-full bg-brand-red p-2 text-white hover:bg-brand-red-dark transition-colors" title="Delete Image" onclick="return confirm('Remove this image?');">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
            
            @if($album->images->isEmpty())
                <div class="rounded-xl border border-dashed border-brand-white/10 p-12 text-center">
                    <p class="text-sm text-brand-white/30">No gallery images uploaded yet.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
