<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use App\Models\PortfolioAlbum;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $albums = PortfolioAlbum::latest()->paginate(9);

        $currency = $this->detectCurrencyFromIp($request);

        return view('pages.portfolio', compact('albums', 'currency'));
    }

    public function show(PortfolioAlbum $album): View
    {
        $album->load('images');
        return view('pages.portfolio-show', compact('album'));
    }

    /**
     * Detect the most appropriate currency for the visitor based on their IP.
     * Ghana  → GHS (Cedis)
     * Nigeria → NGN (Naira)
     * Elsewhere → GHS (default)
     */
    private function detectCurrencyFromIp(Request $request): string
    {
        $ip = $request->ip();

        // Skip geo-lookup on local/private IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'GHS';
        }

        $cacheKey = 'ip_currency_' . md5($ip);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($ip) {
            try {
                $apiKey = config('services.ip_geolocation.api_key');

                if (!$apiKey) {
                    return 'GHS';
                }

                $response = Http::timeout(3)->get('https://api.ipgeolocation.io/ipgeo', [
                    'apiKey' => $apiKey,
                    'ip'     => $ip,
                    'fields' => 'country_code2',
                ]);

                $countryCode = $response->json('country_code2');

                return match (strtoupper((string) $countryCode)) {
                    // NGN — Nigeria
                    'NG'                                         => 'NGN',
                    // KES — Kenya
                    'KE'                                         => 'KES',
                    // ZAR — South Africa
                    'ZA'                                         => 'ZAR',
                    // USD — United States, and common USD-preference countries
                    'US', 'CA', 'AU', 'GB', 'NZ',
                    'AE', 'SG', 'HK', 'IN', 'JP',
                    'DE', 'FR', 'IT', 'ES', 'NL',
                    'SE', 'NO', 'DK', 'CH', 'BE'               => 'USD',
                    // GHS — Ghana + everywhere else (safe fallback)
                    default                                      => 'GHS',
                };
            } catch (\Throwable) {
                return 'GHS';
            }
        });
    }
}
