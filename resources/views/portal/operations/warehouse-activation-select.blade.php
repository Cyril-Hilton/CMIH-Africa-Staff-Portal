<label class="block text-xs text-brand-white/70">Project handover, activation or campaign <small>(optional)</small>
    <select name="activation_link" class="mt-2 block w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white">
        <option value="">No activation link</option>
        <optgroup label="Approved project handovers">
            @foreach(\App\Models\OperationsHandover::with('project','lead')->whereNotNull('handover_approved_at')->whereNotNull('lead_id')->whereNotIn('stage',['cancelled','completed'])->latest('id')->get() as $handover)
                <option value="handover:{{ $handover->id }}" @selected($selectedActivationLink==='handover:'.$handover->id)>{{ $handover->project->project_name }} · {{ $handover->lead?->name }}</option>
            @endforeach
        </optgroup>
        @if($activationOptions->isNotEmpty())
            <optgroup label="Operations Activations">
                @foreach($activationOptions as $activation)
                    <option value="activation:{{ $activation->id }}" @selected($selectedActivationLink === 'activation:'.$activation->id)>{{ $activation->title }} · Week of {{ $activation->period_start->format('d M Y') }}</option>
                @endforeach
            </optgroup>
        @endif
        @if($campaignOptions->isNotEmpty())
            <optgroup label="Campaigns">
                @foreach($campaignOptions as $campaign)
                    <option value="campaign:{{ $campaign->id }}" @selected($selectedActivationLink === 'campaign:'.$campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </optgroup>
        @endif
    </select>
</label>
