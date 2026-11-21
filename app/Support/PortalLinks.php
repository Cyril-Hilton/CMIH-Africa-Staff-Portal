<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class PortalLinks
{
    public static function configureConsoleRoot(): void
    {
        if (! app()->runningInConsole() || ! app()->environment('production')) return;
        $kind = config('cmih.app_kind');
        if (in_array($kind, ['staff', 'brands', 'website'], true)) {
            $base = config('cmih.urls.'.$kind);
            if (is_string($base) && in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true) && ! self::loopback((string) parse_url($base, PHP_URL_HOST))) {
                URL::forceRootUrl(rtrim($base, '/'));
                URL::forceScheme(parse_url($base, PHP_URL_SCHEME));
            }
        }
    }

    public static function notification(?string $url): ?string
    {
        if (! $url || ! app()->environment('production') || ! self::loopback((string) parse_url($url, PHP_URL_HOST))) return $url;
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $kind = preg_match('~^/(brands|merchandisers)(/|$)~', $path) ? 'brands' : 'staff';
        $base = rtrim(config('cmih.urls.'.$kind), '/');
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        return $base.$path.($query !== null ? '?'.$query : '').($fragment !== null ? '#'.$fragment : '');
    }

    private static function loopback(string $host): bool
    {
        $host = strtolower($host);
        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($host, '.localhost');
    }
}
