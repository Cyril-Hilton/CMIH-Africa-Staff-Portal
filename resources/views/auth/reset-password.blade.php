<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Reset Password</p>
            <h2 class="text-3xl font-display text-brand-white">Set a New Password</h2>
            <p class="text-sm text-brand-white/70">Create a new password for your company account. You can use either your company email or contact email.</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            @if ($request->query('portal') === 'merchandisers')
                <input type="hidden" name="portal" value="merchandisers">
            @endif

            <div>
                <x-input-label for="email" :value="__('Company or Contact Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('New Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 9 characters, e.g. Cmih2026!" />
                <p class="text-[10px] text-brand-ash mt-1">Hint: use more than 8 characters with at least one letter, one number, and one symbol.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center">
                Reset Password
            </x-primary-button>
        </form>
    </div>
</x-guest-layout>


