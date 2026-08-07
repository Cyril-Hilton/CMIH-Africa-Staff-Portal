@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-green-400/40 bg-green-400/10 px-4 py-3 text-sm text-green-200']) }}>
        {{ $status }}
    </div>
@endif
