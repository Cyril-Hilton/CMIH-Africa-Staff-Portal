<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Security Check</p>
            <h2 class="text-3xl font-display text-brand-white">Confirm Your Password</h2>
            <p class="text-sm text-brand-white/70">Please re-enter your password to continue.</p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center">
                Confirm
            </x-primary-button>
        </form>
    </div>
</x-guest-layout>


