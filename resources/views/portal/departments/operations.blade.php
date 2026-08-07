<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
            <h2 class="text-3xl font-display text-brand-white">Operations & Projects Dashboard</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8">
        <!-- BTL Campaigns & Live-Share Panel -->
        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4 sm:p-6">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">BTL Campaigns & Client Live-Share Panel</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Create campaigns, track weekly activation activity, and manage client live-share links.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('portal.import.show', ['table' => 'campaigns']) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white transition hover:bg-brand-white/10">
                        Import Campaigns
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'campaigns']) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white transition hover:bg-brand-white/10">
                        Export Campaigns
                    </a>
                </div>
            </div>
            <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(22rem,0.42fr)_minmax(0,1fr)]">
                
                <!-- Create Campaign Form -->
                <form method="POST" action="{{ route('portal.campaigns.store') }}" class="min-w-0 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="campaign_name" :value="__('Campaign / Activation Name')" />
                        <x-text-input id="campaign_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Coca Cola Townstorm Accra" />
                    </div>
                    <div>
                        <x-input-label for="client_name" :value="__('Client Brand')" />
                        <x-text-input id="client_name" name="client_name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Coca Cola West Africa" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="start_date" :value="__('Start Date')" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" />
                        </div>
                        <div>
                            <x-input-label for="end_date" :value="__('End Date')" />
                            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="duration" :value="__('Duration')" />
                            <x-text-input id="duration" name="duration" type="number" step="1" min="0" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="14" />
                        </div>
                        <div>
                            <x-input-label for="status_update" :value="__('Status Update')" />
                            <x-text-input id="status_update" name="status_update" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Done, TBC, In Progress, Pending" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="project_lead_id" :value="__('Project Lead')" />
                        <select id="project_lead_id" name="project_lead_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option value="">Select Project Lead</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="location_brief" :value="__('Location / Venue Brief')" />
                        <textarea id="location_brief" name="location_brief" rows="5" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Accounts, locations, venues, activation notes...">{{ old('location_brief') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Create Campaign
                    </button>
                </form>

                <!-- Active Campaigns Table -->
                <div class="min-w-0 space-y-4" x-data="{ openCampaign: null, editingCampaign: null }">
                    <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash">Active BTL Campaigns</h4>
                    <div class="max-h-[36rem] min-w-0 overflow-auto rounded-xl border border-brand-white/10 bg-brand-black/20">
                        <table class="w-full min-w-[980px] text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-2">Campaign</th>
                                    <th class="py-2">Client</th>
                                    <th class="py-2">Dates</th>
                                    <th class="py-2">Duration</th>
                                    <th class="py-2">Status Update</th>
                                    <th class="py-2">Project Lead</th>
                                    <th class="py-2 text-right">Client Share Link</th>
                                    <th class="py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse($campaigns as $camp)
                                    @php
                                        $campaignActivity = collect();
                                        $campaignLocationText = trim(strip_tags((string) $camp->location_brief));
                                        $campaignLocationText = $campaignLocationText !== ''
                                            ? \Illuminate\Support\Str::limit($campaignLocationText, 140)
                                            : 'Location not specified';

                                        foreach ($camp->assetLogs as $log) {
                                            $campaignActivity->push([
                                                'date' => $log->created_at,
                                                'account' => $log->user?->name ?? 'Asset account',
                                                'location' => $log->notes ?: $campaignLocationText,
                                                'summary' => ($log->asset?->name ?? 'Asset') . ' activated',
                                                'report_shared' => false,
                                                'report_pending' => true,
                                            ]);
                                        }

                                        foreach ($camp->campaignPhotos as $photo) {
                                            $campaignActivity->push([
                                                'date' => $photo->created_at,
                                                'account' => 'Client live-share',
                                                'location' => $photo->caption ?: $campaignLocationText,
                                                'summary' => $photo->caption ?: 'Campaign report/photo shared',
                                                'report_shared' => true,
                                                'report_pending' => false,
                                            ]);
                                        }

                                        foreach ($camp->tasks as $task) {
                                            $isReportShared = $task->isApprovedForPerformance();
                                            $campaignActivity->push([
                                                'date' => $task->due_on ?? $task->created_at,
                                                'account' => $task->assignee?->name ?? 'Unassigned',
                                                'location' => $task->client_name ?: $campaignLocationText,
                                                'summary' => $task->title,
                                                'report_shared' => $isReportShared,
                                                'report_pending' => ! $isReportShared,
                                            ]);
                                        }

                                        $weeklyActivity = $campaignActivity
                                            ->filter(fn ($item) => $item['date'])
                                            ->sortBy('date')
                                            ->groupBy(function ($item) {
                                                $week = \Illuminate\Support\Carbon::parse($item['date']);

                                                return $week->copy()->startOfWeek()->format('M d') . ' - ' . $week->copy()->endOfWeek()->format('M d, Y');
                                            });
                                    @endphp
                                    <tr class="align-top">
                                        <td class="py-3 pr-3">
                                            <button type="button" x-on:click="openCampaign = openCampaign === {{ $camp->id }} ? null : {{ $camp->id }}" class="text-left font-semibold text-brand-white hover:text-brand-red transition">
                                                {{ $camp->name }}
                                                <span class="mt-1 block text-[9px] uppercase tracking-wider text-brand-white/35">View week-on-week</span>
                                            </button>
                                        </td>
                                        <td class="py-3 text-brand-red">{{ $camp->client_name }}</td>
                                        <td class="py-3 text-[10px] text-brand-white/60">
                                            {{ $camp->start_date?->format('M d') ?? 'N/A' }} - {{ $camp->end_date?->format('M d, Y') ?? 'N/A' }}
                                        </td>
                                        <td class="py-3 font-mono">{{ $camp->duration !== null ? number_format($camp->duration) : 'N/A' }}</td>
                                        <td class="py-3 text-brand-white/70">{{ $camp->status_update ?: 'Pending' }}</td>
                                        <td class="py-3 text-brand-white/70">{{ $camp->projectLead?->name ?? 'Unassigned' }}</td>
                                        <td class="py-3 text-right">
                                            @if($camp->share_token)
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="{{ route('campaign.share.view', $camp->share_token) }}" target="_blank" class="rounded bg-emerald-500/20 hover:bg-emerald-500/40 px-2 py-1 text-[9px] font-bold text-emerald-400 uppercase transition-all">
                                                        View Feed
                                                    </a>
                                                    <button onclick="navigator.clipboard.writeText('{{ route('campaign.share.view', $camp->share_token) }}'); alert('Link copied to clipboard!');" class="rounded bg-brand-white/5 hover:bg-brand-white/10 border border-brand-white/10 px-2 py-1 text-[9px] font-bold text-brand-white uppercase transition-all">
                                                        Copy
                                                    </button>
                                                </div>
                                            @else
                                                <form method="POST" action="{{ route('portal.campaigns.generate-share', $camp) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded bg-purple-500/20 hover:bg-purple-500/40 px-2.5 py-1 text-[9px] font-bold text-purple-400 uppercase transition-all">
                                                        Generate Link
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" x-on:click="openCampaign = {{ $camp->id }}; editingCampaign = editingCampaign === {{ $camp->id }} ? null : {{ $camp->id }}" class="rounded border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-[9px] font-bold uppercase text-sky-300 transition hover:bg-sky-500/20">
                                                    Edit
                                                </button>
                                                <form method="POST" action="{{ route('portal.campaigns.destroy', $camp) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Delete "' . $camp->name . '"? This removes the campaign and live-share photos. Linked tasks and asset logs stay as history.') }});">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-[9px] font-bold uppercase text-red-300 transition hover:bg-red-500/20">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="openCampaign === {{ $camp->id }}" x-transition x-cloak>
                                        <td colspan="8" class="pb-4 pt-1">
                                            <div class="rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <p class="text-[10px] uppercase tracking-[0.18em] text-brand-ash">Week-on-week activation view</p>
                                                        <p class="text-xs text-brand-white/50">{{ $campaignLocationText === 'Location not specified' ? 'No location brief recorded.' : $campaignLocationText }}</p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 text-[10px] uppercase tracking-wider">
                                                        <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-emerald-300">{{ $campaignActivity->where('report_shared', true)->count() }} shared</span>
                                                        <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-amber-300">{{ $campaignActivity->where('report_pending', true)->count() }} pending</span>
                                                    </div>
                                                </div>

                                                <form x-show="editingCampaign === {{ $camp->id }}" x-cloak x-transition method="POST" action="{{ route('portal.campaigns.update', $camp) }}" class="mb-4 grid gap-4 rounded-xl border border-sky-500/20 bg-sky-500/5 p-4 md:grid-cols-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <x-input-label for="edit_campaign_name_{{ $camp->id }}" :value="__('Campaign / Activation Name')" />
                                                        <x-text-input id="edit_campaign_name_{{ $camp->id }}" name="name" type="text" required value="{{ old('name', $camp->name) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white placeholder-brand-white/30" />
                                                    </div>
                                                    <div>
                                                        <x-input-label for="edit_client_name_{{ $camp->id }}" :value="__('Client Brand')" />
                                                        <x-text-input id="edit_client_name_{{ $camp->id }}" name="client_name" type="text" required value="{{ old('client_name', $camp->client_name) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white placeholder-brand-white/30" />
                                                    </div>
                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <x-input-label for="edit_start_date_{{ $camp->id }}" :value="__('Start Date')" />
                                                            <x-text-input id="edit_start_date_{{ $camp->id }}" name="start_date" type="date" value="{{ old('start_date', $camp->start_date?->toDateString()) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white" />
                                                        </div>
                                                        <div>
                                                            <x-input-label for="edit_end_date_{{ $camp->id }}" :value="__('End Date')" />
                                                            <x-text-input id="edit_end_date_{{ $camp->id }}" name="end_date" type="date" value="{{ old('end_date', $camp->end_date?->toDateString()) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white" />
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <x-input-label for="edit_duration_{{ $camp->id }}" :value="__('Duration')" />
                                                            <x-text-input id="edit_duration_{{ $camp->id }}" name="duration" type="number" min="0" step="1" value="{{ old('duration', $camp->duration) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white" />
                                                        </div>
                                                        <div>
                                                            <x-input-label for="edit_status_{{ $camp->id }}" :value="__('Campaign Status')" />
                                                            <select id="edit_status_{{ $camp->id }}" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white">
                                                                @foreach(['active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $statusValue => $statusLabel)
                                                                    <option value="{{ $statusValue }}" @selected(old('status', $camp->status) === $statusValue)>{{ $statusLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <x-input-label for="edit_project_lead_id_{{ $camp->id }}" :value="__('Project Lead')" />
                                                        <select id="edit_project_lead_id_{{ $camp->id }}" name="project_lead_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white">
                                                            <option value="">Select Project Lead</option>
                                                            @foreach ($staff as $member)
                                                                <option value="{{ $member->id }}" @selected((int) old('project_lead_id', $camp->project_lead_id) === (int) $member->id)>{{ $member->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <x-input-label for="edit_status_update_{{ $camp->id }}" :value="__('Status Update')" />
                                                        <x-text-input id="edit_status_update_{{ $camp->id }}" name="status_update" type="text" value="{{ old('status_update', $camp->status_update) }}" class="mt-1 w-full border border-brand-white/10 bg-brand-black/60 text-brand-white placeholder-brand-white/30" placeholder="Done, TBC, In Progress, Pending" />
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <x-input-label for="edit_location_brief_{{ $camp->id }}" :value="__('Week-on-week Activation Brief')" />
                                                        <textarea id="edit_location_brief_{{ $camp->id }}" name="location_brief" rows="6" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white">{{ old('location_brief', $camp->location_brief) }}</textarea>
                                                    </div>
                                                    <div class="flex justify-end gap-2 md:col-span-2">
                                                        <button type="button" x-on:click="editingCampaign = null" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">
                                                            Cancel
                                                        </button>
                                                        <button type="submit" class="rounded-xl bg-brand-red px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark">
                                                            Save Campaign
                                                        </button>
                                                    </div>
                                                </form>

                                                @forelse($weeklyActivity as $weekLabel => $items)
                                                    <div class="border-t border-brand-white/10 py-3 first:border-t-0 first:pt-0">
                                                        <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-brand-white">{{ $weekLabel }}</p>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-left text-[11px] text-brand-white/65">
                                                                <thead class="text-[9px] uppercase tracking-wider text-brand-ash">
                                                                    <tr>
                                                                        <th class="pb-2 pr-3">Account</th>
                                                                        <th class="pb-2 pr-3">Location Activated</th>
                                                                        <th class="pb-2 pr-3">Report Shared</th>
                                                                        <th class="pb-2">Report Pending</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-brand-white/5">
                                                                    @foreach($items as $activity)
                                                                        <tr>
                                                                            <td class="py-2 pr-3 text-brand-white">{{ $activity['account'] }}</td>
                                                                            <td class="py-2 pr-3">{{ $activity['location'] }}</td>
                                                                            <td class="py-2 pr-3">
                                                                                <span class="{{ $activity['report_shared'] ? 'text-emerald-300' : 'text-brand-white/30' }}">{{ $activity['report_shared'] ? 'Yes' : 'No' }}</span>
                                                                            </td>
                                                                            <td class="py-2">
                                                                                <span class="{{ $activity['report_pending'] ? 'text-amber-300' : 'text-brand-white/30' }}">{{ $activity['report_pending'] ? $activity['summary'] : 'Cleared' }}</span>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <p class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-3 text-xs text-brand-white/45">No weekly activation, asset, task, or report activity has been linked to this campaign yet.</p>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-6 text-center text-brand-white/30 italic">No campaigns created yet. Start by filling the form on the left.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid layout: Vendor Matrix & Asset Logs -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Vendor Matrix -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">Third-Party Vendor Management Matrix</h3>
                <form method="POST" action="{{ route('portal.operations.vendors.store') }}" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="vendor_name" :value="__('Vendor Name')" />
                            <x-text-input id="vendor_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Focus Printing Ltd" />
                        </div>
                        <div>
                            <x-input-label for="vendor_cat" :value="__('Category')" />
                            <select id="vendor_cat" name="category" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                                <option value="Projects">Projects (BTL/Activations)</option>
                                <option value="Office">Office Support</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="assigned_project" :value="__('Assigned Campaign / Project')" />
                        <select id="assigned_project" name="assigned_project_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option value="">No Active Project</option>
                            @foreach ($projects as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="review_notes" :value="__('Performance Review Notes')" />
                        <textarea id="review_notes" name="performance_review_notes" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Vendor reliability, print quality, timeliness..."></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Save Vendor Matrix
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Registered Vendors</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($vendors as $vendor)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-brand-white">{{ $vendor->name }}</span>
                                <span class="rounded-full bg-brand-white/10 px-2 py-0.5 text-[9px] uppercase tracking-wider text-brand-white/70">{{ $vendor->category }}</span>
                            </div>
                            @if($vendor->project)
                                <p class="text-brand-red mt-1">Project: {{ $vendor->project->title }}</p>
                            @endif
                            @if($vendor->performance_review_notes)
                                <p class="text-brand-white/60 mt-1 italic">{!! $vendor->performance_review_notes !!}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No vendors registered yet.</p>
                    @endforelse
                </div>
                @if($vendors instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $vendors->links() }}
                    </div>
                @endif
            </div>

            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Asset Checkout & Inventory Logs</h3>
                    <a href="{{ route('portal.export', ['table' => 'asset_logs']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        Export Logs
                    </a>
                </div>
                
                <!-- Checkout Form -->
                <form method="POST" action="{{ route('portal.operations.assets.checkout') }}" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="checkout_asset" :value="__('Select Asset')" />
                            <select id="checkout_asset" name="asset_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                                <option value="">Select Asset</option>
                                @foreach ($assets->where('status', 'available') as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->condition }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="checkout_staff" :value="__('Checkout To Staff')" />
                            <select id="checkout_staff" name="user_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                                <option value="">Select Staff</option>
                                @foreach (\App\Models\User::internalStaff()->orderBy('name')->get() as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="checkout_notes" :value="__('Checkout Notes')" />
                        <x-text-input id="checkout_notes" name="notes" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Campaign name or usage event" />
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Checkout Asset
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Active Checkouts & Returns</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($assetLogs->where('action', 'checkout') as $log)
                        @php
                            $isReturned = !$assets->firstWhere('id', $log->asset_id) || $assets->firstWhere('id', $log->asset_id)->status === 'available';
                        @endphp
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 text-xs flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $log->asset?->name ?? 'Asset' }}</p>
                                <p class="text-brand-white/60">Issued to: {{ $log->user?->name ?? 'Staff' }}</p>
                                @if($log->notes)
                                    <p class="text-brand-white/40 mt-1">Notes: {{ $log->notes }}</p>
                                @endif
                            </div>
                            <div>
                                @if(!$isReturned)
                                    <form method="POST" action="{{ route('portal.operations.assets.checkin', $log) }}" class="space-y-1">
                                        @csrf
                                        <select name="reported_condition" class="rounded bg-brand-black/60 text-[10px] text-brand-white border-brand-white/10 px-2 py-0.5" required>
                                            <option value="Good">Good</option>
                                            <option value="Damaged">Damaged</option>
                                            <option value="Needs Repair">Needs Repair</option>
                                        </select>
                                        <button type="submit" class="w-full rounded bg-emerald-500 hover:bg-emerald-600 px-2 py-1 text-[9px] font-bold text-brand-white uppercase transition-all">
                                            Return / In
                                        </button>
                                    </form>
                                @else
                                    <span class="rounded bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 text-[9px] uppercase font-bold text-emerald-400">Returned</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No active checkouts recorded.</p>
                    @endforelse
                </div>
                @if($assetLogs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $assetLogs->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Freelance Promoter Directory</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('portal.import.show', ['table' => 'freelance_promoters']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        Import Promoters
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'freelance_promoters']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        Export Promoters
                    </a>
                </div>
            </div>
            <div class="grid gap-6 lg:grid-cols-[0.35fr_0.65fr]">
                <form method="POST" action="{{ route('portal.operations.promoters.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="promoter_name" :value="__('Full Name')" />
                        <x-text-input id="promoter_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Ama Serwaa" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="promoter_contact" :value="__('Contact No')" />
                            <x-text-input id="promoter_contact" name="contact" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="+233 24 000 0000" />
                        </div>
                        <div>
                            <x-input-label for="promoter_city" :value="__('Primary City')" />
                            <x-text-input id="promoter_city" name="city" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Accra" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="promoter_lang" :value="__('Languages Spoken')" />
                        <x-text-input id="promoter_lang" name="language" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="English, Twi, Ga" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="promoter_size" :value="__('T-Shirt Size')" />
                            <select id="promoter_size" name="tshirt_size" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="S">S</option>
                                <option value="M" selected>M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="promoter_height" :value="__('Height')" />
                            <x-text-input id="promoter_height" name="height" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="1.75m" />
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Register Promoter
                    </button>
                </form>

                <div>
                    <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3">Field Representatives</h4>
                    <div class="overflow-x-auto max-h-[350px] overflow-y-auto">
                        <table class="w-full text-left text-xs text-brand-white/70">
                            <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                <tr>
                                    <th class="py-2">Name</th>
                                    <th class="py-2">Contact</th>
                                    <th class="py-2">City</th>
                                    <th class="py-2">Languages</th>
                                    <th class="py-2">Shirt</th>
                                    <th class="py-2">Height</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-white/5">
                                @forelse($promoters as $promoter)
                                    <tr>
                                        <td class="py-3 font-semibold text-brand-white">{{ $promoter->name }}</td>
                                        <td class="py-3">{{ $promoter->contact }}</td>
                                        <td class="py-3">{{ $promoter->city }}</td>
                                        <td class="py-3">{{ $promoter->language ?? 'N/A' }}</td>
                                        <td class="py-3 font-bold text-brand-red">{{ $promoter->tshirt_size ?? 'M' }}</td>
                                        <td class="py-3">{{ $promoter->height ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-4 text-center text-brand-white/30 italic">No field representatives registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($promoters instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4 pt-4 border-t border-brand-white/10">
                            {{ $promoters->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
