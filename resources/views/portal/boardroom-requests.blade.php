<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Boardroom Requests</h1></x-slot>
    <div class="mx-auto max-w-6xl space-y-5">
        @if(session('status'))<p role="status" class="rounded-xl border border-emerald-400/30 p-3 text-sm text-emerald-300">{{ session('status') }}</p>@endif
        @if($errors->any())<p role="alert" class="rounded-xl border border-brand-red/40 p-3 text-sm">{{ $errors->first() }}</p>@endif

        @if($canReadAll)
            <section class="glass-panel rounded-2xl border border-brand-white/10 p-5">
                <h2 class="font-semibold">Last recorded access</h2>
                @if($lastUse)
                    <p class="mt-1 text-sm">{{ $lastUse->accessed_by_name ?? $lastUse->requester_name }} · {{ $lastUse->checked_in_at->timezone('Africa/Accra')->format('d M Y, H:i') }} GMT</p>
                    <a class="mt-2 inline-block text-sm underline" href="{{ route('portal.boardroom-requests', ['booking'=>$lastUse->id]) }}#booking-{{ $lastUse->id }}">Open access record</a>
                @else
                    <p class="mt-1 text-sm text-brand-ash">No check-ins have been recorded.</p>
                @endif
            </section>
        @endif

        <details class="glass-panel rounded-2xl border border-brand-white/10 p-5" @if(old('purpose')) open @endif>
            <summary class="cursor-pointer font-semibold">Request the boardroom</summary>
            <p class="mt-3 text-sm text-brand-ash">HR/Admin will review your request and record access when you collect and return the key. All times are Ghana time.</p>
            <form method="POST" action="{{ route('portal.boardroom-requests.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                @include('portal.partials.boardroom-meeting-fields',['meeting'=>null])
                <button class="w-fit rounded-lg bg-brand-red px-4 py-2 text-sm font-semibold text-white">Submit request</button>
            </form>
        </details>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ $canReadAll ? 'Bookings & Access History' : 'My Requests & Meeting Invitations' }}</h2>
                <form method="GET" action="{{ route('portal.boardroom-requests') }}" class="flex flex-wrap items-end gap-2">
                    <label class="text-sm">Date<input name="date" type="date" value="{{ request('date') }}" class="mt-1 block rounded-lg bg-brand-black p-2"></label>
                    <label class="text-sm">Status
                        <select name="status" class="mt-1 block rounded-lg bg-brand-black p-2">
                            <option value="">All statuses</option>
                            @foreach(\App\Models\BoardroomRequest::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="rounded-lg border border-brand-white/20 px-3 py-2 text-sm">Filter bookings</button>
                    <a class="px-2 py-2 text-sm underline" href="{{ route('portal.boardroom-requests') }}">Clear</a>
                </form>
            </div>
            @forelse($records as $record)
                @php($start = $record->starts_at->timezone('Africa/Accra'))
                @php($end = $record->ends_at->timezone('Africa/Accra'))
                <details id="booking-{{ $record->id }}" class="glass-panel rounded-xl border border-brand-white/10 p-4" @if(request()->integer('booking') === $record->id) open @endif>
                    <summary class="cursor-pointer">
                        <strong>{{ $start->format('d M Y') }} · {{ $start->format('H:i') }}–{{ $end->format('H:i') }} GMT</strong>
                        <span class="ml-2 text-sm text-brand-ash">{{ $record->requester_name }} · {{ \App\Models\BoardroomRequest::STATUSES[$record->status] }}</span>
                    </summary>
                    @include('portal.partials.executive-record-fields', ['fields' => [
                        'Purpose / Agenda' => $record->purpose, 'Total attendees' => $record->attendees, 'Invited staff' => $record->invitedStaff->pluck('name')->join(', ') ?: 'No staff selected',
                        'Booked duration' => (int) $start->diffInMinutes($end).' min',
                        'Reviewed by' => $record->reviewer?->name, 'Review note' => $record->review_note,
                        'Access holder' => $record->accessed_by_name, 'Checked in' => $record->checked_in_at,
                        'Check-in recorded by' => $record->checkInRecorder?->name, 'Checked out' => $record->checked_out_at,
                        'Check-out recorded by' => $record->checkOutRecorder?->name,
                        'Actual duration' => $record->checked_out_at ? (int) $record->checked_in_at->diffInMinutes($record->checked_out_at).' min' : null,
                        'Room condition' => match($record->room_condition) { 'good' => 'No issue reported', 'issue_reported' => 'Issue reported', default => null },
                        'Condition notes' => $record->condition_notes,
                    ]])
                    @if(($canManage || (int)$record->user_id===auth()->id()) && in_array($record->status,['pending','approved'],true))
                        <details class="mt-4 rounded-xl border border-brand-white/10 p-4"><summary class="cursor-pointer text-sm font-semibold">Edit meeting & attendees</summary><p class="mt-2 text-xs text-brand-ash">Attendees receive the updated details. Revised bookings go back to HR/Admin for room approval.</p>
                            <form method="POST" action="{{ route('portal.boardroom-requests.update',$record) }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
                                @include('portal.partials.boardroom-meeting-fields',['meeting'=>$record])
                                <button class="w-fit rounded-lg bg-brand-red px-4 py-2 text-sm text-white">Save & notify attendees</button>
                            </form>
                        </details>
                    @endif
                    @if($canManage && in_array($record->status, ['pending','approved','checked_in'],true))
                        <form method="POST" action="{{ route('portal.boardroom-requests.action', $record) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-brand-white/10 pt-4">
                            @csrf
                            @if($record->status === 'pending')
                                <label class="text-sm">Review note<input name="review_note" maxlength="2000" class="mt-1 block rounded-lg bg-brand-black p-2"></label>
                                <button name="action" value="approve" class="rounded-lg border border-emerald-400/40 px-3 py-2 text-sm">Approve</button>
                                <button name="action" value="reject" class="rounded-lg border border-brand-red/40 px-3 py-2 text-sm">Decline</button>
                            @elseif($record->status === 'approved')
                                <label class="text-sm">Staff member collecting the key<input name="accessed_by_name" required value="{{ $record->requester_name }}" maxlength="255" class="mt-1 block rounded-lg bg-brand-black p-2"></label>
                                <button name="action" value="check_in" class="rounded-lg border border-sky-400/40 px-3 py-2 text-sm">Record check-in</button>
                            @else
                                <label class="text-sm">Room condition
                                    <select name="room_condition" required class="mt-1 block rounded-lg bg-brand-black p-2">
                                        <option value="">Select condition</option><option value="good">No issue reported</option><option value="issue_reported">Damage or issue reported</option>
                                    </select>
                                </label>
                                <label class="text-sm">Condition notes<input name="condition_notes" maxlength="2000" class="mt-1 block rounded-lg bg-brand-black p-2"></label>
                                <button name="action" value="check_out" class="rounded-lg border border-sky-400/40 px-3 py-2 text-sm">Record check-out</button>
                            @endif
                        </form>
                    @endif
                    @if(($canManage || (int) $record->user_id === auth()->id()) && in_array($record->status, ['pending','approved'], true))
                        <form method="POST" action="{{ route('portal.boardroom-requests.action', $record) }}" data-confirm="Cancel this boardroom request?" class="mt-3">
                            @csrf
                            <button name="action" value="cancel" class="text-sm underline">Cancel booking</button>
                        </form>
                    @endif
                </details>
            @empty
                <p class="glass-panel rounded-xl p-4 text-sm text-brand-ash">No boardroom requests match these filters.</p>
            @endforelse
            {{ $records->links() }}
        </section>
    </div>
</x-app-layout>
