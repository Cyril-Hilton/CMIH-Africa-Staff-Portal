<section id="pass-out" class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6 space-y-5">
    <div><p class="text-xs uppercase tracking-widest text-brand-ash">HRM · Leave & Absences</p><h3 class="text-xl font-semibold text-brand-white">Half-day Pass-out</h3><p class="mt-2 text-sm text-brand-ash">One afternoon half-day per calendar month, starting at 1:00 pm or later on a weekday. Supervisor approval → HOD approval → HR recording. This allowance is tracked separately from annual leave; it does not excuse a missed morning clock-in.</p></div>
    <details @if(old('request_date')) open @endif class="rounded-xl border border-brand-white/10 p-4">
        <summary class="cursor-pointer font-semibold text-brand-white">Request a half-day pass-out</summary>
        <form method="POST" action="{{ route('portal.pass-outs.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf
            <label class="text-sm text-brand-ash">Date<input class="mt-1 block w-full rounded-lg bg-brand-black border-brand-white/20 text-brand-white" type="date" name="request_date" min="{{ today()->toDateString() }}" value="{{ old('request_date',today()->toDateString()) }}" required></label>
            <label class="text-sm text-brand-ash">Departure time<input class="mt-1 block w-full rounded-lg bg-brand-black border-brand-white/20 text-brand-white" type="time" name="departure_time" min="13:00" value="{{ old('departure_time','13:00') }}" required><span class="text-xs">Afternoon half-day only; 1:00 pm onwards.</span></label>
            @foreach(['supervisor_id'=>'Supervisor','hod_id'=>'Head of Department'] as $field=>$label)
                <label class="text-sm text-brand-ash">{{ $label }}<select name="{{ $field }}" required class="mt-1 block w-full rounded-lg bg-brand-black border-brand-white/20 text-brand-white"><option value="">Select {{ strtolower($label) }}</option>@foreach($passOutStaff as $person)@if($field==='supervisor_id' || $person->isLineManager() || $person->isCvoOrSuperAdmin())<option value="{{ $person->id }}" @selected((string)old($field)===(string)$person->id)>{{ $person->name }}</option>@endif @endforeach</select></label>
            @endforeach
            <label class="md:col-span-2 text-sm text-brand-ash">Reason<textarea name="reason" required minlength="5" maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg bg-brand-black border-brand-white/20 text-brand-white">{{ old('reason') }}</textarea></label>
            <div><button class="rounded-xl bg-brand-red px-5 py-3 font-semibold text-white">Submit pass-out request</button></div>
        </form>
    </details>
    <div class="space-y-3">
        <h4 class="font-semibold text-brand-white">{{ $user->hasFullHrAccess() || $user->canAuditHrAndFinance() ? 'Pass-out register' : 'Your requests & assigned reviews' }}</h4>
        @forelse($passOuts as $passOut)
            <article class="rounded-xl border border-brand-white/10 p-4 space-y-3">
                <div class="flex flex-wrap justify-between gap-3"><div><strong class="text-brand-white">{{ $passOut->user->name }}</strong><p class="text-sm text-brand-ash">{{ $passOut->request_date->format('D, d M Y') }} · From {{ substr($passOut->departure_time,0,5) }} · Half-day</p></div><span class="rounded-lg border border-brand-white/20 px-3 py-2 text-sm text-brand-white">{{ $passOut->statusLabel() }}</span></div>
                <p class="text-sm text-brand-white whitespace-pre-line">{{ $passOut->reason }}</p>
                <p class="text-xs text-brand-ash">Supervisor: {{ $passOut->supervisor->name }} · HOD: {{ $passOut->hod->name }}@if($passOut->recorded_at) · Recorded by {{ $passOut->recorder?->name }} on {{ $passOut->recorded_at->format('d M Y H:i') }}@endif</p>
                @php
                    $pending=in_array($passOut->status,['pending_supervisor','pending_hod','pending_hr']);
                    $canPassReview=$pending && $user->id!==(int)$passOut->user_id && ($passOut->status==='pending_hr' ? $user->hasFullHrAccess() : ($user->id===$passOut->reviewerId() || $user->isActingLineManagerFor($passOut->reviewerId())));
                @endphp
                @if($canPassReview)
                    <form method="POST" action="{{ route('portal.pass-outs.review',$passOut) }}" class="flex flex-wrap gap-3 items-end">@csrf
                        <label class="text-sm text-brand-ash flex-1">{{ $passOut->status==='pending_hr' ? 'HR recording notes (optional)' : 'Review note (required if rejecting)' }}<input name="note" maxlength="2000" class="mt-1 block w-full rounded-lg bg-brand-black border-brand-white/20 text-brand-white"></label>
                        <button name="decision" value="approve" class="rounded-xl border border-green-500/30 bg-green-500/10 text-green-300 px-4 py-2">{{ $passOut->status==='pending_hr' ? 'Record & confirm pass-out' : 'Approve this stage' }}</button>
                        <button name="decision" value="reject" class="rounded-xl border border-brand-red/30 px-4 py-2 text-red-200">Reject</button>
                    </form>
                @elseif($pending && (int)$passOut->user_id===$user->id)
                    <form method="POST" action="{{ route('portal.pass-outs.review',$passOut) }}">@csrf<button name="decision" value="cancel" class="text-sm underline text-brand-ash">Cancel this request</button></form>
                @endif
                <details class="text-xs text-brand-ash"><summary class="cursor-pointer">Approval history</summary><ol class="mt-2 space-y-2">@foreach($passOut->history as $event)<li>{{ \Illuminate\Support\Carbon::parse($event['at'])->format('d M Y H:i') }} · {{ $event['actor_name'] }} · {{ ucfirst($event['action']) }}@if(!empty($event['from'])) · {{ str_replace('_',' ',$event['from']) }} → {{ str_replace('_',' ',$event['to']) }}@endif @if(!empty($event['note']))<p class="whitespace-pre-line">{{ $event['note'] }}</p>@endif</li>@endforeach</ol></details>
            </article>
        @empty<p class="text-sm text-brand-ash">No pass-out requests to show.</p>@endforelse
        {{ $passOuts->links() }}
        @if(request()->filled('passout_focus'))<a href="{{ route('portal.leaves') }}#pass-out" class="text-sm underline text-brand-ash">Show all pass-out requests</a>@endif
    </div>
</section>
