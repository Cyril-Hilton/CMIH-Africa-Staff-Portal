<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RestrictSplitAppAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $kind = (string) config('cmih.app_kind', 'all');
        $path = $request->path();

        if ($kind === 'all') {
            if ($this->isWebsiteHost($request->getHost()) && ! $this->isAllowed('website', $path)) {
                return redirect()->away($this->targetUrlFor($request));
            }

            return $next($request);
        }

        if ($this->isAllowed($kind, $path)) {
            return $next($request);
        }

        return redirect()->away($this->targetUrlFor($request));
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
                'profile-photos/*',
                'shared/campaign/*',
                'surveys/*',
                ...$this->authPatterns(),
                ...$this->staticPatterns(),
            ]),
            'brands' => $this->matches($path, [
                '/',
                'brands',
                'brands/*',
                'merchandisers',
                'merchandisers/*',
                ...$this->authPatterns(),
                ...$this->staticPatterns(),
            ]),
            default => true,
        };
    }

    private function targetUrlFor(Request $request): string
    {
        $path = $request->path();
        $queryString = $request->getQueryString();

        if ($this->matches($path, ['brands', 'brands/*', 'merchandisers', 'merchandisers/*'])) {
            return $this->buildUrl(config('cmih.urls.brands'), $path, $queryString);
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
            'profile-photos/*',
            ...$this->authPatterns(),
        ])) {
            return $this->buildUrl(config('cmih.urls.staff'), $path === '/' ? 'dashboard' : $path, $queryString);
        }

        return $this->buildUrl(config('cmih.urls.website'), $path === '/' ? '' : $path, $queryString);
    }

    private function buildUrl(?string $baseUrl, string $path, ?string $queryString = null): string
    {
        $baseUrl = rtrim($baseUrl ?: config('app.url'), '/');
        $path = trim($path, '/');
        $url = $path === '' ? $baseUrl : "{$baseUrl}/{$path}";

        return $queryString ? "{$url}?{$queryString}" : $url;
    }

    private function isWebsiteHost(string $host): bool
    {
        $host = strtolower($host);
        $hosts = [
            $this->hostFromUrl(config('cmih.urls.website')),
        ];

        $expandedHosts = $hosts;

        foreach ($hosts as $candidate) {
            if (! $candidate) {
                continue;
            }

            $expandedHosts[] = str_starts_with($candidate, 'www.')
                ? substr($candidate, 4)
                : "www.{$candidate}";
        }

        return in_array($host, array_unique(array_filter($expandedHosts)), true);
    }

    private function hostFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return strtolower((string) parse_url($url, PHP_URL_HOST));
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
            'reset-password',
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
