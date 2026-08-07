<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-brand-red to-brand-red-dark px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/30 transition hover:opacity-90 sm:w-auto sm:px-5 sm:text-xs']) }}>
    {{ $slot }}
</button>

