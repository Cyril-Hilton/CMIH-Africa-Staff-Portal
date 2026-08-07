<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Merchandiser Portal Access</p>
            <h2 class="text-3xl font-display text-brand-white">Field Portal</h2>
            <p class="text-sm text-brand-white/70">Welcome to the CMIH Merchandiser Field Portal. Choose an action to continue.</p>
        </div>

        <div class="space-y-4">
            <a href="{{ route('merchandisers.login') }}" class="flex items-center justify-between rounded-xl border border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 px-5 py-4 transition group">
                <div>
                    <h3 class="text-base font-semibold text-brand-white group-hover:text-amber-500 transition-colors">🔐 Merchandiser Login</h3>
                    <p class="text-xs text-brand-white/50">Access your active dashboard, clock-in, and capture visits.</p>
                </div>
                <span class="text-brand-white/30 group-hover:text-brand-white text-lg transition-all">→</span>
            </a>

            <a href="{{ route('merchandisers.register') }}" class="flex items-center justify-between rounded-xl border border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 px-5 py-4 transition group">
                <div>
                    <h3 class="text-base font-semibold text-brand-white group-hover:text-amber-500 transition-colors">📝 Apply as Merchandiser</h3>
                    <p class="text-xs text-brand-white/50">Create a new field profile and submit for activation.</p>
                </div>
                <span class="text-brand-white/30 group-hover:text-brand-white text-lg transition-all">→</span>
            </a>
        </div>

        <div class="pt-4 border-t border-brand-white/10 mt-6">
            <a href="{{ route('login') }}" class="text-xs text-brand-white/60 hover:text-brand-white underline">
                ← Go to Staff Login page
            </a>
        </div>
    </div>
</x-guest-layout>
