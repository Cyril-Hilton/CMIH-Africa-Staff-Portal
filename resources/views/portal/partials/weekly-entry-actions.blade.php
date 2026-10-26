@php
$editorActor=$editorActor??auth()->user();$entryDepartment=\App\Models\User::normalizeDepartmentKey($item->department);
$canEditEntry=$editorActor && ($editorActor->isCvoOrSuperAdmin()||($editorActor->isEffectiveLineManager() && (\App\Models\User::normalizeDepartmentKey($editorActor->department)===$entryDepartment||(int)$item->created_by===$editorActor->id||(int)$item->lead_staff_id===$editorActor->id||$editorActor->isActingLineManagerFor((int)$item->created_by)||$editorActor->isActingLineManagerFor((int)$item->lead_staff_id))));
@endphp
@if($canEditEntry)
@php
$entryIsBrands=$entryDepartment==='brands_marketing';$entryIsCreative=$entryDepartment==='creatives';
$departments=config('performance.departments');$editSupportRows=$item->supporting_staff_with_roles;
$allStaff=\App\Models\User::where('status','active')->orWhereIn('id',collect($item->supporting_staff_ids??[])->push($item->lead_staff_id)->filter())->orderBy('name')->get();
$itemColumns=\App\Models\WeeklyConsolidatedColumn::where('user_id',$item->created_by)->where('department',$entryDepartment)->orderBy('order')->get();
@endphp
<div class="mt-5 min-w-0 border-t border-brand-white/15 pt-4" data-weekly-entry-actions="{{ $item->id }}">
@include('portal.partials.weekly-report-controls')
<details class="mt-3 min-w-0" data-weekly-entry-editor>
<summary class="cursor-pointer rounded-lg border border-brand-white/20 px-4 py-3 text-sm font-semibold">Edit Weekly Update</summary>
<div class="mt-4 min-w-0 rounded-xl border border-brand-white/15 p-4">
<form method="POST" action="{{ route('portal.dashboard.weekly-consolidated.update', $item) }}" class="space-y-4" data-weekly-edit-form data-no-ajax data-no-silent>
    @csrf
    @method('PATCH')
    <p class="text-xs text-brand-ash">Reporting dates are permanent. Extend the project timeline below; ongoing work gets a separate weekly draft.</p>
    <div class="grid gap-3 sm:grid-cols-2"><label>Project Start<input type="date" name="project_starts_on" value="{{ ($item->project_starts_on??$item->week_start)?->toDateString() }}"></label><label>Expected Project End<input type="date" name="project_ends_on" value="{{ ($item->project_ends_on??$item->week_end)?->toDateString() }}"></label></div>
    <label class="block text-xs">Correction / update reason<input name="correction_reason" maxlength="2000" @required($item->week_end?->isPast()||in_array($item->report_status,['submitted','reviewed'])) class="mt-2 w-full rounded-lg border border-brand-white/15 bg-brand-black p-3" placeholder="What changed and why?"></label>
    <div class="grid gap-2 md:grid-cols-3">
        @if($entryIsBrands)
            <input type="text" name="brands_task_id" value="{{ $item->brandsTaskId() }}" placeholder="Task ID (e.g. BR-GNS-001)" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
        @endif
        <select name="department" required class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
            @foreach($departments as $key => $label)
                <option value="{{ $key }}" @selected(\App\Models\User::normalizeDepartmentKey($item->department) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" aria-label="Permanent Reporting Start" name="week_start" value="{{ $item->week_start?->toDateString() }}" readonly required class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
        <input type="date" aria-label="Permanent Reporting End" name="week_end" value="{{ $item->week_end?->toDateString() }}" readonly title="{{ $entryIsBrands ? 'Due Date' : 'Week End' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
        @if($entryIsCreative)<label class="block text-xs">Date brief was received<input type="date" name="brief_received_on" value="{{ $item->brief_received_on?->toDateString() }}" class="mt-1 rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white"></label>@endif
        <input type="text" name="client_name" value="{{ $item->client_name }}" placeholder="{{ $entryIsBrands ? 'Task Name' : 'Client' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
        <input type="text" name="campaign_name" value="{{ $item->campaign_name }}" placeholder="{{ $entryIsBrands ? 'Project' : 'Campaign' }}" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
        <select name="lead_staff_id" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
            <option value="">Lead staff...</option>
            @foreach($allStaff as $staff)
                <option value="{{ $staff->id }}" @selected((int) $item->lead_staff_id === (int) $staff->id)>{{ $staff->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-[10px] uppercase tracking-widest text-brand-ash">Supporting Staff + Role</p>
            <button type="button" data-weekly-support-add="weekly-support-rows-{{ $item->id }}" class="rounded-lg border border-brand-white/10 px-3 py-1.5 text-[10px] uppercase tracking-widest text-brand-white/60 hover:bg-brand-white/10">+ Add Staff</button>
        </div>
        <div id="weekly-support-rows-{{ $item->id }}" class="mt-3 space-y-2">
            @forelse($editSupportRows as $supportStaff)
                <div class="grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row">
                    <select name="supporting_staff_ids[]" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                        <option value="">Supporting staff...</option>
                        @foreach($allStaff as $staff)
                            <option value="{{ $staff->id }}" @selected((int) $supportStaff->id === (int) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="supporting_roles[]" value="{{ $supportStaff->weekly_role }}" placeholder="Role on this weekly action" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                    <button type="button" data-weekly-support-remove class="rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red">Remove</button>
                </div>
            @empty
                <div class="grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row">
                    <select name="supporting_staff_ids[]" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white">
                        <option value="">Supporting staff...</option>
                        @foreach($allStaff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="supporting_roles[]" placeholder="Role on this weekly action" class="rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30">
                    <button type="button" data-weekly-support-remove class="rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red">Remove</button>
                </div>
            @endforelse
        </div>
    </div>

    <h6 class="mb-2 text-sm font-semibold">Weekly Progress / Deliverables</h6>
    <textarea aria-label="Weekly Progress / Deliverables" name="deliverables" rows="4" required class="weekly-editor-pending w-full rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->deliverables }}</textarea>
    @if(true)
        <div class="grid gap-2 md:grid-cols-3">
            <label class="block min-w-0 text-xs">Targets & KPI Commitments<textarea aria-label="Targets & KPI Commitments" name="target_breakdown" rows="4" class="weekly-editor-pending rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->target_breakdown }}</textarea></label>
            <label class="block min-w-0 text-xs">Reported Results<textarea aria-label="Reported Results" name="achieved_breakdown" rows="4" class="weekly-editor-pending rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->achieved_breakdown }}</textarea></label>
            <label class="block min-w-0 text-xs">Gaps & Follow-up<textarea aria-label="Gaps & Follow-up" name="gap_breakdown" rows="4" class="weekly-editor-pending rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->gap_breakdown }}</textarea></label>
        </div>
    @endif
    @if($itemColumns->isNotEmpty())
        <div class="grid gap-2 md:grid-cols-2">
            @foreach($itemColumns as $column)
                <div>
                    <label class="mb-1 block text-[10px] uppercase tracking-widest text-brand-ash">{{ $column->label }}</label>
                    <textarea name="custom_fields[{{ $column->column_key }}]" rows="3" class="weekly-editor-pending w-full rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">{{ $item->customFieldValue($column) }}</textarea>
                </div>
            @endforeach
        </div>
    @endif
    @include('portal.partials.weekly-accountability-fields', ['accountabilityItem' => $item])
    <div class="flex flex-wrap items-center gap-2">
        <select name="status" class="rounded-lg border border-brand-white/10 bg-brand-black/80 px-2 py-2 text-xs text-brand-white">
            @foreach(['Planned', 'In Progress', 'Done', 'Blocked', 'Deferred'] as $status)
                <option value="{{ $status }}" @selected($item->status === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-brand-red px-3 py-2 text-xs font-bold uppercase tracking-widest text-white">Update</button>
    </div>
</form>
</div></details>
@if(!$item->week_start?->lt(now()->startOfWeek()) && !in_array($item->report_status,['submitted','reviewed']))
<form method="POST" data-no-ajax data-no-silent action="{{ route('portal.dashboard.weekly-consolidated.destroy',$item) }}" data-confirm="Remove this current weekly draft?" class="mt-3">@csrf @method('DELETE')<button class="rounded-lg border border-brand-red/30 px-4 py-2 text-xs text-brand-red">Delete Current Draft</button></form>
@endif
</div>
@endif
