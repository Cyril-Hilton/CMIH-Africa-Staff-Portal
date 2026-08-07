<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Password Reset</p>
            <h2 class="text-3xl font-display text-brand-white">Recover Your Access</h2>
            <p class="text-sm text-brand-white/70">
                Enter your company email or contact email to receive a reset link.
                @if (($portal ?? null) === 'merchandisers')
                    Merchandiser reset links are sent to the contact email on file.
                @endif
            </p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ ($portal ?? null) === 'merchandisers' ? route('merchandisers.password.email') : route('password.email') }}" class="space-y-5">
            @csrf
            @if (($portal ?? null) === 'merchandisers')
                <input type="hidden" name="portal" value="merchandisers">
            @endif

            <div>
                <x-input-label for="email" :value="__('Company or Contact Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="johndoe@cmih.africa or personal@email.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center">
                Email Password Reset Link
            </x-primary-button>
        </form>

        <p class="text-xs text-brand-white/60">
            Remembered your password?
            <a href="{{ ($portal ?? null) === 'merchandisers' ? route('merchandisers.login') : route('login') }}" class="text-brand-white underline decoration-white/30">Return to login</a>.
        </p>
    </div>
</x-guest-layout>


