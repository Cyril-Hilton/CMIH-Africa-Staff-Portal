@php($selectedMeetingStaff=array_map('intval',old('attendee_ids',$meeting?->invitedStaff->pluck('id')->all()??[])))
<label class="text-sm">Date<input name="date" type="date" required min="{{ now('Africa/Accra')->toDateString() }}" value="{{ old('date',$meeting?->starts_at->timezone('Africa/Accra')->format('Y-m-d')) }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5"></label>
<label class="text-sm">Start time<input name="start" type="time" required value="{{ old('start',$meeting?->starts_at->timezone('Africa/Accra')->format('H:i')) }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5"></label>
<label class="text-sm">End time<input name="end" type="time" required value="{{ old('end',$meeting?->ends_at->timezone('Africa/Accra')->format('H:i')) }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5"></label>
<label class="text-sm">Total attendees (including guests)<input name="attendees" type="number" min="1" max="1000" required value="{{ old('attendees',$meeting?->attendees??1) }}" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5"></label>
<label class="sm:col-span-2 lg:col-span-4 text-sm">Purpose / Agenda<textarea name="purpose" rows="2" maxlength="2000" required class="mt-1 block w-full rounded-lg bg-brand-black p-2.5">{{ old('purpose',$meeting?->purpose) }}</textarea></label>
<fieldset class="sm:col-span-2 lg:col-span-4 rounded-xl border border-brand-white/10 p-4" x-data="{search:''}">
    <legend class="text-sm font-semibold">Invite staff to this meeting</legend>
    <p class="text-xs text-brand-ash">Selected staff receive the date, start and end time, boardroom location and agenda. Invitations are tentative until HR/Admin approves the room. Select yourself too if you are attending.</p>
    <label class="mt-3 block text-sm">Find staff<input type="search" x-model="search" placeholder="Search names" class="mt-1 block w-full rounded-lg bg-brand-black p-2.5"></label>
    <div class="mt-3 grid max-h-48 overflow-y-auto gap-2 sm:grid-cols-2">
        @foreach($meetingStaff as $person)<label x-show="{{ \Illuminate\Support\Js::from(mb_strtolower($person->name)) }}.includes(search.toLowerCase())" class="flex items-center gap-2 rounded-lg border border-brand-white/10 p-2 text-sm"><input type="checkbox" name="attendee_ids[]" value="{{ $person->id }}" @checked(in_array($person->id,$selectedMeetingStaff,true)) class="rounded border-brand-white/20 text-brand-red"><span>{{ $person->name }}<small class="block text-brand-ash">{{ \App\Models\User::departmentLabel($person->department) }}</small></span></label>@endforeach
    </div>
</fieldset>
