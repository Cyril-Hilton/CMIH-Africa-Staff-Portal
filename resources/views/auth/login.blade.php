<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal Login</p>
            <h2 class="text-3xl font-display text-brand-white">Staff Login</h2>
            <p class="text-sm text-brand-white/70">Sign in with your company credentials to access your dashboard.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        @if (session('generated_email'))
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-sm text-brand-white/80">
                Your company email has been created: <span class="text-brand-white">{{ session('generated_email') }}</span>. Check your personal email for the temporary password.
            </div>
        @endif

        @if (session()->has('email_sent') && !session('email_sent'))
            <div class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">
                We could not send the credential email. Please contact an admin to confirm your login details.
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf



            <div>
                <x-input-label for="email" :value="__('Company Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="johndoe@cmih.africa" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" />
                <p class="text-[10px] text-brand-ash mt-1">New passwords must be more than 8 characters and include a letter, number, and symbol.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-brand-white/70">
                <label for="remember_me" class="inline-flex items-center gap-2">
                    <input id="remember_me" type="checkbox" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red" name="remember">
                    Remember me
                </label>

                @if (Route::has('password.request'))
                    <a class="text-brand-white/70 underline decoration-white/30 hover:text-brand-white" href="{{ route('password.request') }}">
                        Forgot your password?
                    </a>
                @endif
            </div>

            <x-primary-button class="w-full justify-center">
                Access Dashboard
            </x-primary-button>
        </form>

        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-xs text-brand-white/70">
            Need OTP instead? <a href="{{ route('login.otp') }}" class="text-brand-white underline decoration-white/30">Sign in with SMS</a>.
        </div>

        <p class="text-xs text-brand-white/60">
            New to CMIH? <a href="{{ route('register') }}" class="text-brand-white underline decoration-white/30">Create an account</a> and wait for admin approval.
        </p>

        <div class="pt-6 border-t border-brand-white/10 mt-6">
            <a href="{{ route('merchandisers.portal') }}" class="group flex items-center justify-between gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-2.5 text-xs font-semibold text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/40 transition-all duration-300">
                <span class="flex items-center gap-2">
                    <span>✨</span> Merchandiser Portal Access
                </span>
                <span class="transform translate-x-0 group-hover:translate-x-1 transition-transform duration-300 text-sm">→</span>
            </a>
        </div>
    </div>
</x-guest-layout>

