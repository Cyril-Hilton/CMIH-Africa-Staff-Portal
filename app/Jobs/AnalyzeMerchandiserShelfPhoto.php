<?php

namespace App\Jobs;

use App\Models\Sku;
use App\Services\SkuShelfAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeMerchandiserShelfPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public string $token, public string $photoPath, public int $userId)
    {
        $this->onConnection(config('performance.background_jobs.connection', 'deferred'));
        $this->onQueue('ai');
    }

    public function handle(SkuShelfAnalyzer $analyzer): void
    {
        $key = $this->cacheKey($this->token);
        Cache::put($key, [
            'job_status' => 'processing',
            'status' => 'queued',
            'message' => 'AI shelf detection is running.',
            'detections' => [],
        ], now()->addMinutes(30));

        try {
            if (! Storage::disk('public')->exists($this->photoPath)) {
                throw new \RuntimeException('Shelf photo no longer exists.');
            }

            $absolutePath = Storage::disk('public')->path($this->photoPath);
            $photo = new UploadedFile(
                $absolutePath,
                basename($this->photoPath),
                Storage::disk('public')->mimeType($this->photoPath) ?: 'image/jpeg',
                null,
                true
            );

            $result = $analyzer->analyze($photo, Sku::with('brand')->orderBy('name')->get());

            Cache::put($key, ['job_status' => 'completed'] + $result, now()->addMinutes(30));
        } catch (\Throwable $exception) {
            Log::warning('Queued AI shelf detection failed: ' . $exception->getMessage(), [
                'token' => $this->token,
                'user_id' => $this->userId,
            ]);

            Cache::put($key, [
                'job_status' => 'failed',
                'status' => 'manual_fallback',
                'message' => 'AI detection could not complete. Continue with manual SKU entry.',
                'provider' => 'manual',
                'model' => null,
                'detections' => [],
                'review_required' => true,
            ], now()->addMinutes(30));
        }
    }

    public static function cacheKey(string $token): string
    {
        return "merchandiser_ai_detection:{$token}";
    }
}
