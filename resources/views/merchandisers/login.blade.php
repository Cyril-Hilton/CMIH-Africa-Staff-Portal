<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Portal Access</p>
            <h2 class="text-3xl font-display text-brand-white">Field Agent Login</h2>
            <p class="text-sm text-brand-white/70">External merchandisers see the field dashboard. Brands team members can sign in with their existing CMIH credentials to manage merchandisers.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('merchandisers.login') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email Address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="yourname@domain.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required placeholder="Enter your password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between text-xs text-brand-white/70">
                <label for="remember_me" class="inline-flex items-center gap-2">
                    <input id="remember_me" type="checkbox" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red" name="remember">
                    Remember me
                </label>
                <a href="{{ route('merchandisers.password.request') }}" class="font-semibold text-amber-400 underline decoration-amber-400/40 hover:text-amber-300">
                    Forgot password?
                </a>
            </div>

            <x-primary-button class="w-full justify-center">
                Access Field Dashboard
            </x-primary-button>
        </form>

        <p class="text-xs text-brand-white/60">
            Need an account? <a href="{{ route('merchandisers.register') }}" class="text-amber-500 hover:text-amber-400 font-semibold underline">Register here</a>.
        </p>

        <div class="pt-4 border-t border-brand-white/10 mt-4">
            <a href="{{ route('merchandisers.portal') }}" class="text-xs text-brand-white/60 hover:text-brand-white underline">
                ← Back to Gate selection
            </a>
        </div>
    </div>
</x-guest-layout>
