<details class="rounded-xl border border-brand-white/20 p-4" data-cr-support-picker @if(count($selected)) open @endif>
    <summary class="cursor-pointer font-semibold">Supporting Staff <span class="text-xs font-normal text-brand-ash">(optional)</span></summary>
    <p class="mt-2 text-xs text-brand-ash">Select the people helping with this entry. The account owner remains responsible for it.</p>
    <input type="hidden" name="supporting_staff_present" value="1">
    <div class="mt-3 grid max-h-52 gap-1 overflow-y-auto sm:grid-cols-2" data-cr-support-options>
        @foreach($options as $person)
            @if(in_array((int) $person->id, $selected, true) || ($person->status === 'active' && (int) $person->id !== (int) $ownerId))
                <label class="cr-checkbox-label flex items-center gap-2 px-1 py-1">
                    <input type="checkbox" name="supporting_staff_ids[]" value="{{ $person->id }}" @checked(in_array((int) $person->id, $selected, true))>
                    <span>{{ $person->name }}</span>
                </label>
            @endif
        @endforeach
    </div>
</details>
