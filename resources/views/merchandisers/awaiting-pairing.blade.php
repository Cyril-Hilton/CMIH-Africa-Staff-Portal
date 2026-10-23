<x-guest-layout>
    <div class="space-y-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-ash">{{ $merchTenant['name'] ?? 'Merchandiser Portal' }}</p>
        <h1 class="text-3xl font-display text-brand-white">Your Account Is Active</h1>
        <div role="status" class="rounded-2xl border border-amber-400/30 bg-amber-400/10 p-5 text-sm text-brand-white">
            <h2 class="font-semibold">Your Route Setup Is Still Needed</h2>
            <p class="mt-3">Please ask your Brands Team administrator to assign your Key Distributor and region. Your field dashboard will be ready once this is done.</p>
        </div>
        <p class="text-sm text-brand-ash">Signed in as {{ auth()->user()->name }}. Your account and existing work are safe.</p>
        <a class="inline-flex rounded-lg border border-brand-white/20 px-4 py-3 text-sm text-brand-white" href="{{ route('merchandisers.dashboard') }}">Check Setup Again</a>
        <form method="POST" action="{{ route('merchandisers.logout') }}" data-confirm="Sign out of your account?">
            @csrf
            <button class="rounded-lg bg-brand-red px-4 py-3 text-sm font-semibold text-white">Sign Out</button>
        </form>
    </div>
</x-guest-layout>
