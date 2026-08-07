@php
    $logoLightFallback = asset('images/logo/logo-light.png');
    $logoDarkFallback = asset('images/logo/logo-dark.png');
    $logoLightValue = \App\Models\SiteContent::where('key', 'logo_light')->value('value');
    $logoDarkValue = \App\Models\SiteContent::where('key', 'logo_dark')->value('value');
    $logoLight = $logoLightValue ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoLightValue) : $logoLightFallback;
    $logoDark = $logoDarkValue ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoDarkValue) : $logoDarkFallback;
@endphp
<div {{ $attributes }}>
    <img
        src="{{ $logoDark }}"
        data-theme-src-light="{{ $logoLight }}"
        data-theme-src-dark="{{ $logoDark }}"
        alt="CMIH Africa"
        class="w-auto h-full object-contain"
        decoding="async"
    />
</div>
