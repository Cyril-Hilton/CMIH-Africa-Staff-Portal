<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\DropboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisionCampaignDropboxFolder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId)
    {
        $this->onConnection(config('performance.background_jobs.connection', 'deferred'));
        $this->onQueue('integrations');
    }

    public function handle(DropboxService $dropbox): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        try {
            if ($dropbox->testConnection()) {
                $dropbox->createFolder('campaigns/' . Str::slug($campaign->name));
            }
        } catch (\Throwable $exception) {
            Log::warning('Campaign Dropbox folder provisioning failed: ' . $exception->getMessage(), [
                'campaign_id' => $campaign->id,
            ]);
        }
    }
}
