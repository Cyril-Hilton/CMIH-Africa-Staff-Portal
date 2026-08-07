<?php

namespace App\Jobs;

use App\Models\CampaignPhoto;
use App\Services\DropboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveCampaignPhotoToDropbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $photoId, public string $localPath)
    {
        $this->onConnection(config('performance.background_jobs.connection', 'deferred'));
        $this->onQueue('integrations');
    }

    public function handle(DropboxService $dropbox): void
    {
        $photo = CampaignPhoto::with('campaign')->find($this->photoId);

        if (! $photo || ! $photo->campaign || ! Storage::disk('public')->exists($this->localPath)) {
            return;
        }

        try {
            if (! $dropbox->testConnection()) {
                return;
            }

            $dropboxPath = 'campaigns/' . Str::slug($photo->campaign->name) . '/' . basename($this->localPath);
            $sharedUrl = $dropbox->uploadFile($dropboxPath, Storage::disk('public')->get($this->localPath));

            if ($sharedUrl) {
                $photo->update(['image_path' => $sharedUrl]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Campaign photo Dropbox archival failed: ' . $exception->getMessage(), [
                'photo_id' => $photo->id,
                'campaign_id' => $photo->campaign_id,
            ]);
        }
    }
}
