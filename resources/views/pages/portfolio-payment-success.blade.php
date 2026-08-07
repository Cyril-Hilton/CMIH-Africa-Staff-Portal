@extends('layouts.site')

@section('title', 'Payment Successful - CMIH Africa')
@section('description', 'Your transaction was successful. Thank you for requesting from CMIH.')

@section('content')
    <section class="section-padding relative overflow-hidden bg-brand-black min-h-[80vh] flex items-center">
        {{-- Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-20 -right-20 w-[600px] h-[600px] bg-brand-red/30 rounded-full blur-[120px] aurora-blend"></div>
            <div class="absolute -bottom-20 -left-20 w-[500px] h-[500px] bg-brand-red-dark/30 rounded-full blur-[100px] aurora-blend"></div>
            <div class="absolute inset-0 opacity-10 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-2xl px-6 w-full py-12">
            <div class="glass-panel rounded-2xl p-8 border border-emerald-500/20 bg-brand-black/60 shadow-2xl relative">
                
                {{-- Success Indicator Glow --}}
                <div class="absolute -top-8 left-1/2 -translate-x-1/2 flex items-center justify-center h-16 w-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 shadow-lg shadow-emerald-500/20">
                    <svg class="h-8 w-8 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <div class="text-center mt-6">
                    <h1 class="text-3xl font-display text-brand-white">Payment Successful</h1>
                    <p class="mt-2 text-sm text-brand-white/70">Thank you! Your request and payment have been securely processed.</p>
                </div>

                {{-- Receipt Card --}}
                <div class="mt-8 rounded-xl border border-brand-white/10 bg-brand-white/5 p-6 space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-brand-white/10 text-xs">
                        <span class="text-brand-ash uppercase tracking-wider">Transaction Reference</span>
                        <span class="font-mono text-brand-white">{{ $payment->reference }}</span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-brand-white/10 text-xs">
                        <span class="text-brand-ash uppercase tracking-wider">Client Name</span>
                        <span class="font-semibold text-brand-white">{{ $payment->name }}</span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-brand-white/10 text-xs">
                        <span class="text-brand-ash uppercase tracking-wider">Client Email</span>
                        <span class="text-brand-white">{{ $payment->email }}</span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-brand-white/10 text-xs">
                        <span class="text-brand-ash uppercase tracking-wider">Requested Item</span>
                        <span class="font-semibold text-brand-red">{{ $payment->itemLabel() }}</span>
                    </div>

                    @if($payment->description)
                        <div class="pb-3 border-b border-brand-white/10 text-xs space-y-1">
                            <span class="text-brand-ash uppercase tracking-wider block">Description</span>
                            <span class="text-brand-white/80 block italic bg-brand-black/20 p-2 rounded border border-brand-white/5">{{ $payment->description }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-brand-ash text-xs uppercase tracking-wider font-semibold">Total Paid</span>
                        <span class="text-2xl font-bold text-brand-white">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</span>
                    </div>
                </div>

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('portfolio') }}" class="flex-1 text-center rounded-full bg-brand-red px-6 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-white hover:bg-brand-red/90 transition-all shadow-lg shadow-brand-red/25">
                        Back to Portfolio
                    </a>
                    <a href="{{ route('home') }}" class="flex-1 text-center rounded-full border border-brand-white/20 bg-brand-white/5 px-6 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white hover:bg-brand-white/10 transition-all">
                        Go to Homepage
                    </a>
                </div>

            </div>
        </div>
    </section>
@endsection
