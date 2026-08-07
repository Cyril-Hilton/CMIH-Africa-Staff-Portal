@props(['disabled' => false])

@php
    $type = $attributes->get('type');
    $isPassword = $type === 'password';
@endphp

@if ($isPassword)
    <div class="relative mt-1 w-full" x-data="{ show: false }">
        <input 
            :type="show ? 'text' : 'password'"
            @disabled($disabled) 
            {{ $attributes->merge(['class' => 'w-full rounded-md border border-brand-white/10 bg-brand-black/40 pl-3 pr-10 py-2.5 text-sm text-brand-white placeholder-brand-white/40 focus:border-brand-red focus:ring-brand-red'])->except('type') }}
        >
        <button 
            type="button" 
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-brand-white/50 hover:text-brand-white transition-colors"
            @click="show = !show"
        >
            <!-- Open Eye Icon (when show is false) -->
            <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <!-- Closed Eye/Slash Icon (when show is true) -->
            <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="display: none;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.822 7.822 3 3m-3-3-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
        </button>
    </div>
@else
    <input @disabled($disabled) {{ $attributes->merge(['class' => 'mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white placeholder-brand-white/40 focus:border-brand-red focus:ring-brand-red']) }}>
@endif


