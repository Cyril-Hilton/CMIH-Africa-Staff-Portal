<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Configuration</p>
                <h2 class="text-3xl font-display text-brand-white">System Settings</h2>
            </div>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-8 max-w-2xl mx-auto">
        <h3 class="text-xl font-semibold text-brand-white mb-6">Theme of the Year</h3>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="site_theme" :value="__('Theme Slogan')" />
                <p class="text-xs text-brand-white/50 mb-2">This text will appear in the loader, dashboard, and footer.</p>
                <x-text-input id="site_theme" name="site_theme" type="text" class="mt-1 block w-full text-lg" :value="$theme" required autofocus />
                <x-input-error :messages="$errors->get('site_theme')" class="mt-2" />
            </div>

            <div class="border-t border-brand-white/10 pt-6">
                <x-input-label for="merchandiser_radius" :value="__('Merchandiser Geofence Radius (meters)')" />
                <p class="text-xs text-brand-white/50 mb-2">Maximum distance (in meters) field merchandisers are allowed to be from the outlet coordinates for a successful clock-in.</p>
                <x-text-input id="merchandiser_radius" name="merchandiser_radius" type="number" class="mt-1 block w-full text-lg" :value="$radius" required min="1" max="1000" />
                <x-input-error :messages="$errors->get('merchandiser_radius')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

                @if (session('success'))
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-green-400"
                    >{{ session('success') }}</p>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
