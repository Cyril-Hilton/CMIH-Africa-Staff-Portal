<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\ArchiveCampaignPhotoToDropbox;
use App\Jobs\ProvisionCampaignDropboxFolder;
use App\Models\Campaign;
use App\Models\CampaignPhoto;
use App\Models\AssetLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampaignShareController extends Controller
{
    /**
     * Generate or regenerate client live-share token
     */
    public function generateShareLink(Campaign $campaign): RedirectResponse
    {
        $campaign->update([
            'share_token' => Str::random(32),
        ]);

        return back()->with('status', 'Client live-share link generated successfully!');
    }

    /**
     * Store new Campaign
     */
    public function storeCampaign(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'location_brief' => ['nullable', 'string', 'max:5000'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'status_update' => ['nullable', 'string', 'max:255'],
            'project_lead_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $campaign = Campaign::create([
            'name' => $request->input('name'),
            'client_name' => $request->input('client_name'),
            'project_lead_id' => $request->input('project_lead_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'location_brief' => $request->input('location_brief'),
            'duration' => $request->input('duration'),
            'status_update' => $request->input('status_update'),
            'created_by' => auth()->user()->id,
            'status' => 'active',
        ]);

        ProvisionCampaignDropboxFolder::dispatch($campaign->id);

        return back()->with('status', 'BTL Campaign created. Dropbox folder provisioning will continue in the background.');
    }

    public function updateCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'location_brief' => ['nullable', 'string', 'max:10000'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'status_update' => ['nullable', 'string', 'max:255'],
            'project_lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:active,paused,completed,cancelled'],
        ]);

        $campaign->update([
            'name' => $validated['name'],
            'client_name' => $validated['client_name'],
            'project_lead_id' => $validated['project_lead_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'location_brief' => $validated['location_brief'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'status_update' => $validated['status_update'] ?? null,
            'status' => $validated['status'] ?? $campaign->status,
        ]);

        return back()->with('status', "\"{$campaign->name}\" activation updated successfully.");
    }

    /**
     * Delete an operations campaign while preserving linked operational history.
     */
    public function destroyCampaign(Campaign $campaign): RedirectResponse
    {
        $campaignName = $campaign->name;

        $campaign->delete();

        return back()->with('status', "\"{$campaignName}\" campaign deleted successfully.");
    }

    /**
     * Display the read-only live campaign view for corporate clients (No Login Required)
     */
    public function viewSharedCampaign(string $token): View
    {
        $campaign = Campaign::with('projectLead')->where('share_token', $token)->firstOrFail();
        
        $tasks = $campaign->tasks()->latest()->get();
        
        // Load photos using dedicated CampaignPhoto model
        $photos = $campaign->campaignPhotos()->latest()->get();

        return view('portal.share', compact('campaign', 'tasks', 'photos'));
    }

    /**
     * Allow field staff or clients to upload active campaign photos (Dropbox support integrated)
     */
    public function uploadSharedPhoto(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif,gif', 'max:6144'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $campaign = Campaign::where('share_token', $token)->firstOrFail();
        $file = $request->file('photo');
        $caption = $request->input('caption', 'Field Photo Update');

        $fileName = 'campaign_' . $campaign->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $localPath = $file->storeAs('campaigns', $fileName, 'public');
        $filePath = Storage::disk('public')->url($localPath);

        // Save entry using CampaignPhoto model
        $photo = CampaignPhoto::create([
            'campaign_id' => $campaign->id,
            'user_id' => auth()->user()?->id,
            'image_path' => $filePath,
            'caption' => $caption,
        ]);

        ArchiveCampaignPhotoToDropbox::dispatch($photo->id, $localPath);

        return back()->with('status', 'Field photo uploaded. Dropbox archival will continue in the background.');
    }
}
