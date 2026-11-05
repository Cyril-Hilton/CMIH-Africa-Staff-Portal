        <div data-action-points data-existing-confirmation data-endpoint="{{ route('portal.action-points.table') }}">
            <p data-action-status role="status" aria-live="polite" class="mb-3 text-sm"></p>
            <div class="mb-6 flex justify-end">
                {{-- Add Action Point Modal Trigger --}}
                <details class="relative">
                    <summary class="cursor-pointer inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark transition-all shadow-lg">
                        + Add Action Point
                    </summary>
                    <div class="dashboard-meeting-popover absolute right-0 top-full mt-2 z-30 rounded-2xl border border-brand-white/10 bg-brand-black/95 p-5 shadow-2xl backdrop-blur-xl">
                        <form method="POST" action="{{ route('portal.action-points.store') }}" class="space-y-4" data-no-ajax data-no-silent>
                            @csrf
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Action Point *</label>
                                <textarea name="action_point" rows="3" required placeholder="Type action required from meeting..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-brand-red"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Assignee</label>
                                    <select name="assignee_id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red">
                                        <option value="">Select staff...</option>
                                        @foreach($allStaff as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Status *</label>
                                    <select name="status" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white focus:border-brand-red">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="done">Done</option>
                                        <option value="not_done">Not Done</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-brand-ash mb-1">Comments / Notes</label>
                                <input type="text" name="comments" placeholder="Progress notes or comments..." class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-brand-red">
                            </div>

                            <button type="submit" class="w-full rounded-xl bg-brand-red py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark transition-all">
                                Save Action Point
                            </button>
                        </form>
                    </div>
                </details>
            </div>

            {{-- Action Points Table --}}
            <div class="overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full text-left text-sm text-brand-white/80">
                    <thead class="bg-brand-black/60 text-xs uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="px-4 py-3.5 min-w-[280px]">ACTION POINT</th>
                            <th class="px-4 py-3.5 min-w-[180px]">ASSIGNEE</th>
                            <th class="px-4 py-3.5 min-w-[140px]">STATUS</th>
                            <th class="px-4 py-3.5 min-w-[260px]">COMMENTS</th>
                            <th class="px-4 py-3.5 min-w-[120px] text-right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($actionPoints as $point)
                            <tr class="hover:bg-brand-white/[0.02] transition-colors">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-brand-white text-xs whitespace-pre-wrap">{{ $point->action_point }}</p>
                                    <span class="mt-1 block text-[10px] text-brand-white/30">Added by {{ $point->creator?->name ?? 'Staff' }} on {{ $point->created_at->format('M d, Y') }}</span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="font-semibold text-xs text-brand-white">{{ $point->display_assignee }}</span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <select data-update-url="{{ route('portal.action-points.update', $point) }}"
                                            data-current-status="{{ $point->status }}" class="action-point-quick-status-select rounded-xl border border-brand-white/15 bg-brand-black/90 px-2.5 py-1.5 text-xs font-bold text-brand-white focus:border-brand-red focus:outline-none cursor-pointer hover:border-brand-white/30 transition-all">
                                        @foreach(['pending' => 'Pending', 'in_progress' => 'In Progress', 'done' => 'Done', 'not_done' => 'Not Done'] as $val => $lbl)
                                            <option value="{{ $val }}" @selected($point->status === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="text-xs text-brand-white/70 whitespace-pre-wrap">{{ $point->comments ?: 'No comments' }}</p>
                                </td>
                                <td class="px-4 py-4 align-top text-right">
                                    
                                        <button type="button" data-action-edit="{{ $point->id }}" class="rounded-lg border border-brand-white/20 px-3 py-2 text-xs">Edit</button>
                                        <dialog data-action-dialog="{{ $point->id }}" class="w-[min(94vw,480px)] max-h-[90vh] overflow-y-auto rounded-xl border border-brand-white/20 bg-brand-black p-5 text-brand-white text-left"><div class="mb-4 flex justify-between gap-3"><h4 class="font-semibold">Edit Action Point</h4><button type="button" data-action-close class="border border-brand-white/20 rounded px-3 py-1">Close</button></div>
                                            <form method="POST" action="{{ route('portal.action-points.update', $point) }}" class="space-y-3" data-no-ajax data-no-silent>
                                                @csrf
                                                @method('PATCH')
                                                <div>
                                                    <label class="block text-[10px] uppercase text-brand-ash mb-1">Action Point</label>
                                                    <textarea name="action_point" rows="2" required class="w-full rounded-lg border border-brand-black/70 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">{{ $point->action_point }}</textarea>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-brand-ash mb-1">Assignee</label>
                                                        <select name="assignee_id" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                            <option value="">Unassigned</option>
                                                            @foreach($allStaff as $staff)
                                                                <option value="{{ $staff->id }}" @selected((int)$point->assignee_id === (int)$staff->id)>{{ $staff->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-brand-ash mb-1">Status</label>
                                                        <select name="status" required class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                            <option value="pending" @selected($point->status === 'pending')>Pending</option>
                                                            <option value="in_progress" @selected($point->status === 'in_progress')>In Progress</option>
                                                            <option value="done" @selected($point->status === 'done')>Done</option>
                                                            <option value="not_done" @selected($point->status === 'not_done')>Not Done</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase text-brand-ash mb-1">Comments</label>
                                                    <input type="text" name="comments" value="{{ $point->comments }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-black/70 px-2.5 py-1.5 text-xs text-brand-white">
                                                </div>
                                                <div class="flex items-center justify-between pt-1">
                                                    <button type="submit" class="rounded-lg bg-brand-red px-3 py-1.5 text-[10px] font-bold uppercase text-white hover:bg-brand-red-dark">Update</button>
                                                </div>
                                            </form>
                                            <form method="POST" action="{{ route('portal.action-points.destroy', $point) }}" data-action-delete data-no-ajax data-no-silent class="mt-2 text-right">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[10px] text-red-400 hover:underline">Delete Action Point</button>
                                            </form>
                                        </dialog>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-xs text-brand-white/40 italic">
                                    No meeting action points recorded yet. Click "+ Add Action Point" above to log action items from your meeting.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
