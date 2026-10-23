@php($flaggedTargets = $targets->where('needs_reconciliation', true))
@if($flaggedTargets->isNotEmpty())
<details id="crm-target-corrections" class="my-4 rounded-xl border border-amber-400/40 bg-amber-500/5 p-4">
<summary class="cursor-pointer font-semibold text-amber-400">Action Required · {{ $flaggedTargets->count() }} Imported Targets</summary>
<p class="my-3 text-sm text-brand-ash">Check the dates and amounts below. Unconfirmed targets remain outside performance calculations. Original source records are retained.</p>
@foreach($flaggedTargets as $item)
<details class="my-3 rounded-lg border border-brand-white/15 p-4">
<summary class="cursor-pointer font-semibold">{{ $item->source_reference ?: $item->client_name }} · {{ $item->proposed_correction ? 'Awaiting Confirmation' : 'Needs Correction' }}</summary>
<p class="my-2 text-sm text-brand-ash">Source label: {{ $item->source_period_label }}. Check: {{ !$item->period_start || !$item->period_end ? 'Reporting dates missing or ambiguous. ' : 'Confirm reporting dates. ' }}{{ $item->target_amount === null ? 'Target amount missing.' : 'Confirm source amount.' }}</p>
@if($item->correction_reason)<p class="mb-3 text-sm">Latest Note: {{ $item->correction_reason }}</p>@endif
@if($item->proposed_correction)
<p class="my-3 text-sm">Proposed: {{ $item->proposed_correction['period_start'] }} – {{ $item->proposed_correction['period_end'] }} · {{ ucfirst($item->proposed_correction['frequency']) }} · GHS {{ number_format($item->proposed_correction['target_amount'], 2) }}</p>
@if(auth()->user()->isLineManager() && (int)$item->correction_submitted_by !== auth()->id())
<form method="POST" action="{{ route('portal.crm.target.review', $item) }}" class="flex flex-wrap gap-3">@csrf
<input type="hidden" name="revision" value="{{ $item->revision }}"><input name="reason" required maxlength="2000" placeholder="Confirmation or correction note" aria-label="Review note" class="rounded-lg bg-brand-black/20 border border-brand-white/20 p-2">
<button name="decision" value="confirm" class="rounded-lg bg-emerald-700 text-white px-4 py-2">Confirm Correction</button><button name="decision" value="return" class="rounded-lg border border-brand-white/20 px-4 py-2">Return for Correction</button>
</form>
@else<p class="text-sm text-brand-ash">A different Client Relations manager, CVO or Super Admin must confirm this correction.</p>@endif
@elseif(auth()->user()->isLineManager() || (int)$item->owner_id === auth()->id())
<form method="POST" action="{{ route('portal.crm.target.correct', $item) }}" class="grid gap-3 sm:grid-cols-2">@csrf
<input type="hidden" name="revision" value="{{ $item->revision }}">
<label>Period Start<input type="date" name="period_start" value="{{ $item->period_start?->format('Y-m-d') }}" required class="block w-full rounded-lg bg-brand-black/20 border border-brand-white/20 p-2"></label>
<label>Period End<input type="date" name="period_end" value="{{ $item->period_end?->format('Y-m-d') }}" required class="block w-full rounded-lg bg-brand-black/20 border border-brand-white/20 p-2"></label>
<label>Frequency<select name="frequency" class="block w-full rounded-lg bg-brand-black/20 border border-brand-white/20 p-2">@foreach(['weekly','monthly','yearly','ytd','custom'] as $frequency)<option value="{{ $frequency }}" @selected($item->frequency===$frequency)>{{ ucfirst($frequency) }}</option>@endforeach</select></label>
<label>Confirmed Target (GHS)<input type="number" name="target_amount" min="0" max="9999999999.99" step="0.01" value="{{ $item->target_amount }}" required class="block w-full rounded-lg bg-brand-black/20 border border-brand-white/20 p-2"></label>
<label class="sm:col-span-2">Correction Reason / Supporting Reference<input name="reason" required maxlength="2000" class="block w-full rounded-lg bg-brand-black/20 border border-brand-white/20 p-2"></label>
<button class="rounded-lg bg-brand-red text-white px-4 py-2">Submit Correction</button>
</form>
@endif
</details>
@endforeach
</details>
@endif
