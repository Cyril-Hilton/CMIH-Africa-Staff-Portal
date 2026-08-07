<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">OTP Verification</p>
            <h2 class="text-3xl font-display text-brand-white">Enter Your Code</h2>
            <p class="text-sm text-brand-white/70">We sent a one-time code to {{ $phone }}.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login.otp.check') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="code" :value="__('OTP Code')" />
                <x-text-input id="code" type="text" name="code" required autofocus placeholder="Enter the 6-digit code" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <x-primary-button class="w-full justify-center">
                Verify & Sign In
            </x-primary-button>
        </form>

        <div class="text-xs text-brand-white/60">
            Didn't get a code? <a href="{{ route('login.otp') }}" class="text-brand-white underline decoration-white/30">Request a new OTP</a>.
        </div>
    </div>
</x-guest-layout>
