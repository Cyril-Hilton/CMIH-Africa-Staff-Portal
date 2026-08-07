<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RestrictSplitAppAccess
{
    /**
     * Keep the future split apps pointed at their own modules.
     *
     * The current production app runs with CMIH_APP_KIND=all, so this middleware
     * is inert until one of the copied apps is deployed as website/staff/brands.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $kind = (string) config('cmih.app_kind', 'all');

        if ($kind === 'all' || app()->runningInConsole()) {
            return $next($request);
        }

        $path = $request->path();

        if ($this->isAllowed($kind, $path)) {
            return $next($request);
        }

        return redirect()->away($this->targetUrlFor($path));
    }

    private function isAllowed(string $kind, string $path): bool
    {
        return match ($kind) {
            'website' => $this->matches($path, [
                '/',
                'about',
                'services',
                'portfolio',
                'portfolio/*',
                'news',
                'contact',
                'privacy-policy',
                'terms-of-service',
                'legal-notice',
                'disclaimer',
                'shared/campaign/*',
                'surveys/*',
                ...$this->staticPatterns(),
            ]),
            'staff' => $this->matches($path, [
                '/',
                'dashboard',
                'dashboard/*',
                'portal',
                'portal/*',
                'admin',
                'admin/*',
                'profile',
                'profile/*',
                'shared/campaign/*',
                'surveys/*',
                ...$this->authPatterns(),
                ...$this->staticPatterns(),
            ]),
            'brands' => $this->matches($path, [
                '/',
                'merchandisers',
                'merchandisers/*',
                ...$this->authPatterns(),
                ...$this->staticPatterns(),
            ]),
            default => true,
        };
    }

    private function targetUrlFor(string $path): string
    {
        if ($this->matches($path, ['merchandisers', 'merchandisers/*'])) {
            return $this->buildUrl(config('cmih.urls.brands'), $path);
        }

        if ($this->matches($path, [
            'dashboard',
            'dashboard/*',
            'portal',
            'portal/*',
            'admin',
            'admin/*',
            'profile',
            'profile/*',
            ...$this->authPatterns(),
        ])) {
            return $this->buildUrl(config('cmih.urls.staff'), $path === '/' ? 'dashboard' : $path);
        }

        return $this->buildUrl(config('cmih.urls.website'), $path === '/' ? '' : $path);
    }

    private function buildUrl(?string $baseUrl, string $path): string
    {
        $baseUrl = rtrim($baseUrl ?: config('app.url'), '/');
        $path = trim($path, '/');

        return $path === '' ? $baseUrl : "{$baseUrl}/{$path}";
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matches(string $path, array $patterns): bool
    {
        return Str::is($patterns, $path);
    }

    /**
     * @return array<int, string>
     */
    private function authPatterns(): array
    {
        return [
            'login',
            'login/*',
            'register',
            'forgot-password',
            'reset-password/*',
            'verify-email',
            'verify-email/*',
            'email/verification-notification',
            'confirm-password',
            'password',
            'logout',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function staticPatterns(): array
    {
        return [
            'up',
            'favicon.ico',
            'manifest.json',
            'sw.js',
            'build/*',
            'images/*',
            'storage/*',
            'css/*',
            'js/*',
        ];
    }
}
