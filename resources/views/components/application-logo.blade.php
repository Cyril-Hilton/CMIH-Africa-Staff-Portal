@php
    $logoLightFallback = asset('images/logo/logo-light.png');
    $logoDarkFallback = asset('images/logo/logo-dark.png');
    $logoLightValue = \App\Models\SiteContent::where('key', 'logo_light')->value('value');
    $logoDarkValue = \App\Models\SiteContent::where('key', 'logo_dark')->value('value');
    $logoUrl = static function ($path, $fallback) {
        if (!$path) return $fallback;
        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        $host = parse_url($url, PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $url = asset('storage/'.ltrim($path, '/'));
        }
        if (parse_url($url, PHP_URL_HOST) === request()->getHost()
            && !is_file(public_path('storage/'.ltrim($path, '/')))) {
            return $fallback;
        }
        return $url;
    };
    $logoLight = $logoUrl($logoLightValue, $logoLightFallback);
    $logoDark = $logoUrl($logoDarkValue, $logoDarkFallback);
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
