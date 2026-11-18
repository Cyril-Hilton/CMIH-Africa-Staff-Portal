@php
    $delivery=app(\App\Services\ConnectedProjectWorkflow::class)->formValues($record);
    if($useOld)$delivery=old('delivery',$delivery);
    $routingManagers=\App\Models\User::internalStaff()->where('status','active')->whereIn('department',array_merge(\App\Models\User::departmentAliases('creatives'),\App\Models\User::departmentAliases('operations_projects')))->get();
@endphp
<section data-project-routing class="rounded-xl border border-teal-300/30 bg-teal-300/5 p-4 space-y-4">
    <div><h4 class="font-semibold text-teal-100">Connected Teams & Deadlines</h4><p class="mt-1 text-xs text-brand-ash">Send the project brief and execution plan from this entry. Each HOD assigns their team, including themselves. Requests route after publication; every change keeps its date, time and author.</p></div>
    @foreach(['creative'=>'Creative work','operations'=>'Operations / execution'] as $team=>$label)
        <div class="rounded-xl border border-brand-white/15 p-3 space-y-3">
            <label class="block text-sm">Does this project require {{ strtolower($label) }}?
                <select name="delivery[{{ $team }}_required]" data-project-requirement="{{ $team }}" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2">
                    <option value="" @selected(!isset($delivery[$team.'_required']))>Decide later · setup needed</option>
                    <option value="1" @selected(($delivery[$team.'_required']??null)===true || ($delivery[$team.'_required']??null)==='1')>Yes · send to the HOD</option>
                    <option value="0" @selected(($delivery[$team.'_required']??null)===false || ($delivery[$team.'_required']??null)==='0')>No · skip this team</option>
                </select>
            </label>
            <fieldset data-project-team="{{ $team }}" class="space-y-3" @if(!($delivery[$team.'_required']??false)) hidden disabled @endif>
                <label class="block text-sm">Tag the {{ $team==='creative'?'Creative':'Operations' }} line manager<select name="delivery[{{ $team }}_manager_id]" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2">
                    <option value="">Choose the responsible HOD</option>
                    @foreach($routingManagers->filter(fn($u)=>\App\Models\User::normalizeDepartmentKey($u->department)===($team==='creative'?'creatives':'operations_projects') && ($team==='creative'?\App\Services\Creative\CreativeAccess::lead($u):\App\Services\OperationsHandoverWorkflow::lead($u))) as $manager)
                        <option value="{{ $manager->id }}" @selected((int)($delivery[$team.'_manager_id']??0)===$manager->id)>{{ $manager->name }}</option>
                    @endforeach
                </select></label>
                @if($team==='creative')
                    <label class="block text-sm">Creative brief<textarea name="delivery[creative][brief]" required minlength="3" maxlength="10000" rows="4" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2">{{ $delivery['creative']['brief']??'' }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Deliverable type<select name="delivery[creative][creative_type]" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"><option value="">Choose a type</option>@foreach(config('creative.types') as $type)<option @selected(($delivery['creative']['creative_type']??null)===$type)>{{ $type }}</option>@endforeach</select></label>
                    <label class="text-sm">Client Relations requested deadline · Accra<input type="datetime-local" name="delivery[creative][requested_deadline]" value="{{ $delivery['creative']['requested_deadline']??'' }}" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"></label></div>
                    <label class="block text-sm">Reference link · optional<input type="url" name="delivery[creative][reference_url]" value="{{ $delivery['creative']['reference_url']??'' }}" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"></label>
                    <p class="text-xs text-brand-ash">The Creative HOD records the assignee, planned start and internal delivery commitment separately. Requested and agreed dates remain visible together.</p>
                @else
                    <label class="block text-sm">Execution / handover plan<textarea name="delivery[operations][plan]" required minlength="20" maxlength="12000" rows="3" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2">{{ $delivery['operations']['plan']??'' }}</textarea></label>
                    <label class="block text-sm">Acceptance criteria<textarea name="delivery[operations][acceptance_criteria]" required minlength="10" maxlength="8000" rows="2" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2">{{ $delivery['operations']['acceptance_criteria']??'' }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Setup deadline · Accra<input type="datetime-local" name="delivery[operations][setup_due_at]" value="{{ $delivery['operations']['setup_due_at']??'' }}" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"></label>
                    <label class="text-sm">Execution date / time · Accra<input type="datetime-local" name="delivery[operations][event_at]" value="{{ $delivery['operations']['event_at']??'' }}" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"></label></div>
                    <label class="block text-sm">Execution location<input name="delivery[operations][location]" maxlength="255" value="{{ $delivery['operations']['location']??'' }}" required class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2"></label>
                    <p class="text-xs text-brand-ash">Operations assigns the lead, arranges the handover and records both teams’ sign-off before execution.</p>
                @endif
            </fieldset>
        </div>
    @endforeach
    <label class="block text-sm">Timeline / scope change note · optional<input name="delivery[change_note]" maxlength="2000" class="mt-1 w-full rounded-lg border border-brand-white/15 bg-brand-black px-3 py-2" placeholder="Explain a changed request or deadline so the team has context"></label>
</section>
