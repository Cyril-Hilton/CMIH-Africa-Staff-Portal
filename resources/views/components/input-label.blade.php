@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs uppercase tracking-[0.3em] text-brand-white/60']) }}>
    {{ $value ?? $slot }}
</label>

