<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex w-full items-center justify-center rounded-full border border-brand-white/20 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-white/70 transition hover:text-brand-white hover:border-brand-white/60 sm:w-auto sm:px-5 sm:text-xs']) }}>
    {{ $slot }}
</button>

