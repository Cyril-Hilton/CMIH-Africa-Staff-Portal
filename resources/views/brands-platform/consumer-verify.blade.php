@extends('layouts.site')

@section('title', $brand->name.' Consumer Verification')

@section('content')
    <section class="min-h-[calc(100vh-88px)] bg-brand-black">
        <div class="mx-auto flex w-full max-w-3xl flex-col px-5 py-10 sm:px-8 lg:px-10">
            <a href="{{ route('brands-platform.show', $brand->slug ?: $brand->id) }}" class="text-xs font-bold uppercase tracking-[0.25em] text-brand-white/50 transition hover:text-brand-white">Back to activation</a>

            <div class="mt-6 rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-6">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Phone Verification</p>
                <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">Complete Entry</h1>
                <p class="mt-3 text-sm leading-7 text-brand-white/60">
                    Verify the consumer phone number to complete this activation journey and issue the reward token.
                </p>

                @if(session('status'))
                    <div class="mt-5 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @if(session('otp_preview'))
                    <div class="mt-3 rounded-md border border-brand-white/10 bg-brand-black/45 p-3 text-xs text-brand-white/60">
                        Test OTP: <strong class="text-brand-white">{{ session('otp_preview') }}</strong>
                    </div>
                @endif

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Consumer</p>
                        <p class="mt-2 text-lg font-semibold text-brand-white">{{ $entry->name }}</p>
                        <p class="text-xs text-brand-white/45">{{ $entry->phone }}</p>
                    </div>
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Status</p>
                        <p class="mt-2 text-lg font-semibold {{ $entry->otp_verified_at ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $entry->otp_verified_at ? 'Verified' : 'Pending OTP' }}
                        </p>
                    </div>
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                        <p class="text-[10px] uppercase tracking-wider text-brand-white/40">Reward Code</p>
                        <p class="mt-2 text-lg font-semibold text-brand-white">{{ $entry->reward_code ?: 'Not issued yet' }}</p>
                    </div>
                </div>

                @unless($entry->otp_verified_at)
                    <form method="POST" action="{{ route('brands-platform.consumer-entry.complete', [$brand->slug ?: $brand->id, $entry->verification_token]) }}" class="mt-6 grid gap-3 sm:grid-cols-[1fr_auto]">
                        @csrf
                        <input name="otp_code" required placeholder="Enter six digit OTP" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <button class="rounded-md bg-brand-red px-5 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">Verify</button>
                        @error('otp_code')
                            <p class="text-xs text-brand-red sm:col-span-2">{{ $message }}</p>
                        @enderror
                    </form>
                @endunless
            </div>
        </div>
    </section>
@endsection
