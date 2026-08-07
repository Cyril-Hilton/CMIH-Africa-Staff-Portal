<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Partners</p>
            <h2 class="text-3xl font-display text-brand-white">Brand Logos</h2>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
        <!-- Upload Form -->
        <div class="glass-panel rounded-2xl p-6 h-fit">
            <h3 class="mb-4 text-lg font-semibold text-brand-white">Add New Brand</h3>
            <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" value="Brand Name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" placeholder="e.g. Guinness" required />
                </div>
                
                <div>
                    <x-input-label for="logo" value="Logo File" />
                    <input id="logo" name="logo" type="file" class="mt-1 block w-full text-sm text-gray-400 file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-white hover:file:bg-brand-white/20" accept="image/*" required />
                    <p class="mt-2 text-xs text-brand-white/50">Recommended: PNG with transparent background. Max 2MB.</p>
                </div>

                <div>
                    <x-input-label for="logo_dark" value="Dark Mode Logo (Optional)" />
                    <input id="logo_dark" name="logo_dark" type="file" class="mt-1 block w-full text-sm text-gray-400 file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-white hover:file:bg-brand-white/20" accept="image/*" />
                    <p class="mt-2 text-xs text-brand-white/50">Use for dark backgrounds. If empty, the light logo is used everywhere.</p>
                </div>

                <div class="pt-2">
                    <x-primary-button class="w-full justify-center">Add Brand</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Brand List -->
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-brand-white">Manage Logos</h3>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ count($brands) }} Brands</span>
            </div>

            <div x-data class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($brands as $brand)
                    @php
                        $darkLogo = $brand->logo_dark_path ?: $brand->logo_path;
                    @endphp
                    <div class="group relative flex aspect-square flex-col gap-2 rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 transition hover:border-brand-white/20">
                        <div class="flex flex-1 items-center justify-center rounded-lg bg-brand-white/10 px-2">
                            <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }} (Light)" class="max-h-12 w-full object-contain filter grayscale transition group-hover:grayscale-0">
                        </div>
                        <div class="flex flex-1 items-center justify-center rounded-lg bg-brand-black/70 px-2">
                            <img src="{{ asset('storage/'.$darkLogo) }}" alt="{{ $brand->name }} (Dark)" class="max-h-12 w-full object-contain filter grayscale transition group-hover:grayscale-0">
                        </div>
                        <p class="text-center text-[10px] uppercase tracking-wider text-brand-white/60">{{ $brand->name }}</p>

                        <div class="touch-visible absolute inset-0 flex items-center justify-center rounded-xl bg-black/80 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                            <button
                                type="button"
                                @click="$dispatch('open-confirm-modal', { url: '{{ route('admin.brands.destroy', $brand) }}' })"
                                class="rounded-full bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
                
                @if($brands->isEmpty())
                    <div class="col-span-full py-12 text-center">
                        <p class="text-brand-white/40">No brands uploaded yet.</p>
                    </div>
                @endif
            </div>
            <div class="mt-8">
                {{ $brands->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
