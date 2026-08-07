<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Verify Email</p>
            <h2 class="text-3xl font-display text-brand-white">Confirm Your Address</h2>
            <p class="text-sm text-brand-white/70">Check your inbox and verify your company email to continue.</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-xl border border-green-400/40 bg-green-400/10 px-4 py-3 text-sm text-green-200">
                A new verification link has been sent to the email address you provided.
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <x-primary-button>
                    Resend Verification Email
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="text-xs uppercase tracking-[0.3em] text-brand-white/60 underline decoration-white/20">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>


