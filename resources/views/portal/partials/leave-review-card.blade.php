@include('portal.partials.leave-handover',['handover'=>$approval])
<div class="p-4 rounded-xl border border-brand-white/10 bg-brand-white/5 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-[16rem] flex-1 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-brand-white">{{ $approval->user->name }}</p>
            @if($approval->lineManager && $approval->line_manager_id !== $user->id && $user->isActingLineManagerFor((int) $approval->line_manager_id))
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-400/30 bg-amber-400/10 text-amber-300">
                    <x-ui-icon name="arrow" /> On behalf of {{ $approval->lineManager->name }}
                </span>
            @endif
        </div>
        <div class="grid gap-2 text-xs text-brand-white/55 sm:grid-cols-2 lg:grid-cols-3">
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Leave Type</span><span class="capitalize">{{ $approval->leave_type }}</span></p>
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Dates</span>{{ $approval->start_date->format('M d, Y') }} to {{ $approval->end_date->format('M d, Y') }}</p>
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Working Days</span>{{ $approval->workingDays() }}</p>
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Covering Duty</span>{{ $approval->coveringStaff?->name ?? 'Not selected' }}</p>
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Line Manager</span>{{ $approval->lineManager?->name ?? 'Direct HR/CVO approval' }}</p>
            @if($approval->delegateLineManager)
                <p><span class="block text-[10px] uppercase tracking-[0.2em] text-amber-400 font-semibold">Acting Line Manager</span><span class="text-amber-300 font-medium">{{ $approval->delegateLineManager->name }}</span></p>
            @endif
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Submitted</span>{{ $approval->created_at?->format('M d, Y h:i A') }}</p>
            <p><span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Current Stage</span>{{ ucwords(str_replace('_', ' ', $approval->status)) }}</p>
        </div>
        <div class="rounded-xl border border-brand-white/10 bg-brand-black/30 p-3 text-xs text-brand-white/65">
            <span class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash">Reason / Notes</span>
            <p class="mt-1 whitespace-pre-wrap">{{ trim(strip_tags($approval->comments ?? '')) ?: 'No reason provided.' }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <!-- Approve form -->
        <form method="POST" action="{{ route('portal.leaves.approve', $approval) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 text-xs font-semibold uppercase tracking-wider hover:bg-green-500/20">
                Approve
            </button>
        </form>

        <!-- Reject form block -->
        <form method="POST" action="{{ route('portal.leaves.reject', $approval) }}" class="flex items-center gap-2">
            @csrf
            <input type="text" id="rej-comm-{{ $approval->id }}" name="rejection_comments" placeholder="Correction/Rejection notes" class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1.5 text-xs text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/30 w-44">
            <button type="submit" class="px-4 py-2 rounded-lg bg-brand-red/10 text-brand-red border border-brand-red/20 text-xs font-semibold uppercase tracking-wider hover:bg-brand-red/20">
                Reject
            </button>
            <button type="button" onclick="const inputVal = document.getElementById('rej-comm-{{ $approval->id }}').value; if (!inputVal) { alert('Please enter notes in the notes field first.'); return; } const f = document.getElementById('ret-form-{{ $approval->id }}'); f.rejection_comments.value = inputVal; f.submit();"
                    class="px-4 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-xs font-semibold uppercase tracking-wider hover:bg-cyan-500/20">
                Return
            </button>
        </form>
        <form id="ret-form-{{ $approval->id }}" method="POST" action="{{ route('portal.leaves.return', $approval) }}" class="hidden">
            @csrf
            <input type="hidden" name="rejection_comments" value="">
        </form>
    </div>
</div>
