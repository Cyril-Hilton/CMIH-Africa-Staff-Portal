<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">OTP Login</p>
            <h2 class="text-3xl font-display text-brand-white">Sign in with SMS Code</h2>
            <p class="text-sm text-brand-white/70">Use the phone number on your profile to receive a one-time login code.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login.otp.send') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="phone" :value="__('Phone Number')" />
                <x-text-input id="phone" type="text" name="phone" :value="old('phone')" required autofocus placeholder="+233 54 220 4282" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center">
                Send OTP
            </x-primary-button>
        </form>

        <p class="text-xs text-brand-white/60">
            Prefer password login? <a href="{{ route('login') }}" class="text-brand-white underline decoration-white/30">Back to sign in</a>.
        </p>
    </div>
</x-guest-layout>
