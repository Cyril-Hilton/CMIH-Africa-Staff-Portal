<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DropboxService
{
    protected ?string $accessToken;
    protected ?string $appKey;
    protected ?string $appSecret;
    protected ?string $refreshToken;

    public function __construct()
    {
        $this->accessToken = config('services.dropbox.access_token') ?? env('DROPBOX_ACCESS_TOKEN');
        $this->appKey = config('services.dropbox.app_key') ?? env('DROPBOX_APP_KEY');
        $this->appSecret = config('services.dropbox.app_secret') ?? env('DROPBOX_APP_SECRET');
        $this->refreshToken = config('services.dropbox.refresh_token') ?? env('DROPBOX_REFRESH_TOKEN');
    }

    /**
     * Get active token (handles optional token refreshing if refresh token is available)
     */
    protected function getToken(): ?string
    {
        // If we have a refresh token, we can fetch a fresh access token
        if ($this->refreshToken && $this->appKey && $this->appSecret) {
            try {
                $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'client_id' => $this->appKey,
                    'client_secret' => $this->appSecret,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }
            } catch (\Exception $e) {
                Log::warning('Dropbox token refresh failed: ' . $e->getMessage());
            }
        }

        return $this->accessToken;
    }

    /**
     * Test connection to Dropbox API
     */
    public function testConnection(): bool
    {
        $token = $this->getToken();
        if (!$token) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->post('https://api.dropboxapi.com/2/users/get_current_account');

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Dropbox connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a folder in Dropbox
     */
    public function createFolder(string $path): bool
    {
        $token = $this->getToken();
        if (!$token) {
            return false;
        }

        $formattedPath = '/' . ltrim($path, '/');

        try {
            $response = Http::withToken($token)
                ->json()
                ->post('https://api.dropboxapi.com/2/files/create_folder_v2', [
                    'path' => $formattedPath,
                    'autorename' => false,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Dropbox createFolder warning: ' . $response->body());
            // Check if folder already exists (error code path/conflict)
            if (Str::contains($response->body(), 'path/conflict')) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Dropbox createFolder exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload a file to Dropbox and return its shared link
     */
    public function uploadFile(string $path, string $content): ?string
    {
        $token = $this->getToken();
        if (!$token) {
            return null;
        }

        $formattedPath = '/' . ltrim($path, '/');

        try {
            // 1. Upload the file
            $response = Http::withToken($token)
                ->withHeaders([
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $formattedPath,
                        'mode' => 'overwrite',
                        'autorename' => true,
                        'mute' => false,
                        'strict_conflict' => false,
                    ]),
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($content, 'application/octet-stream')
                ->post('https://content.dropboxapi.com/2/files/upload');

            if (!$response->successful()) {
                Log::error('Dropbox upload failed: ' . $response->body());
                return null;
            }

            $uploadedPath = $response->json('path_display') ?? $formattedPath;

            // 2. Create a shared link for the uploaded file
            $linkResponse = Http::withToken($token)
                ->json()
                ->post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', [
                    'path' => $uploadedPath,
                    'settings' => [
                        'requested_visibility' => 'public',
                    ],
                ]);

            if ($linkResponse->successful()) {
                $url = $linkResponse->json('url');
                return $this->formatDirectUrl($url);
            }

            // If link already exists, retrieve existing shared links
            if (Str::contains($linkResponse->body(), 'shared_link_already_exists')) {
                $listResponse = Http::withToken($token)
                    ->json()
                    ->post('https://api.dropboxapi.com/2/sharing/list_shared_links', [
                        'path' => $uploadedPath,
                        'direct_only' => true,
                    ]);

                if ($listResponse->successful()) {
                    $links = $listResponse->json('links');
                    if (!empty($links)) {
                        return $this->formatDirectUrl($links[0]['url']);
                    }
                }
            }

            Log::error('Dropbox create shared link failed: ' . $linkResponse->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Dropbox uploadFile exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List files and folders inside a Dropbox path
     */
    public function listFolder(string $path = ''): array
    {
        $token = $this->getToken();
        if (!$token) {
            return [];
        }

        $formattedPath = $path === '' ? '' : '/' . ltrim($path, '/');

        try {
            $response = Http::withToken($token)
                ->json()
                ->post('https://api.dropboxapi.com/2/files/list_folder', [
                    'path' => $formattedPath,
                    'recursive' => false,
                ]);

            if ($response->successful()) {
                $entries = $response->json('entries') ?? [];
                return array_map(function ($entry) {
                    return [
                        'name' => $entry['name'],
                        'path' => $entry['path_display'],
                        'type' => $entry['.tag'] ?? 'file', // 'folder' or 'file'
                        'size' => $entry['size'] ?? 0,
                    ];
                }, $entries);
            }

            Log::error('Dropbox listFolder failed: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Dropbox listFolder exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert standard Dropbox link to direct download link
     * e.g., changing dl=0 to raw=1
     */
    protected function formatDirectUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        return str_replace('?dl=0', '?raw=1', $url);
    }
}
