<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex w-full items-center justify-center rounded-full border border-brand-red/50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-red transition hover:bg-brand-red/10 sm:w-auto sm:px-5 sm:text-xs']) }}>
    {{ $slot }}
</button>
