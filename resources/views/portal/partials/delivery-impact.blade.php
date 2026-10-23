@php($impactOwner = $work instanceof \App\Models\Task ? \App\Models\User::find($work->assigned_to) : $work->leadStaff)
@if($impactOwner)
<section class="mt-4 rounded-xl border border-brand-white/15 p-4 text-sm text-brand-white">
    <h3 class="font-semibold">Delivery Impact</h3>
    <p class="mt-2 text-brand-ash">{{ \App\Services\DeliveryImpact::points($work,$impactOwner) }} impact points · {{ $work->weight_approved_at ? 'Manager rated' : 'Standard weight' }}</p>
    <p class="mt-2 text-xs text-brand-ash">Completion is weighted by agreed impact, not task count. Break long projects into dated milestones for review. Unrated work uses 1 point.</p>
    @if($work->weight_reason)<p class="mt-2 text-xs">{{ $work->weight_reason }}</p>@endif
    @if($impactOwner && \App\Services\DeliveryImpact::mayRate(auth()->user(),$impactOwner) && !($work instanceof \App\Models\Task ? $work->completion_review_status==='approved' : in_array($work->workflow_state,['verified','closed'],true)) && !($work instanceof \App\Models\WeeklyConsolidatedItem && $work->source_task_id))
    <details class="mt-3"><summary class="cursor-pointer font-semibold">Set Impact Points</summary>
        <form method="POST" action="{{ $work instanceof \App\Models\Task ? route('portal.tasks.impact',$work) : route('portal.performance.impact',$work) }}" class="mt-3 grid gap-3" data-confirm="Save the impact weight for this work?">@csrf
            <label>Impact Points (1–100)<input type="number" name="performance_weight" min="1" max="100" required value="{{ $work->performance_weight ?: 1 }}" class="mt-1 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3"></label>
            <label>Why This Weight?<textarea name="weight_reason" required minlength="10" maxlength="2000" class="mt-1 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3">{{ $work->weight_reason }}</textarea></label>
            <p class="text-xs text-brand-ash">Use the same scale across your team, considering effort, complexity and business impact. Weights lock at final verification.</p>
            <button type="submit" class="rounded-lg bg-brand-red px-4 py-3 font-semibold">Save Impact Points</button>
        </form>
    </details>
    @endif
</section>

@endif
