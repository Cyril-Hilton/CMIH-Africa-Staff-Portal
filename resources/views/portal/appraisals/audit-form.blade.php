<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Step 3 of 3 · HR Final Audit</p>
            <h2 class="text-3xl font-display text-brand-white">HR Sign-off — {{ $appraisal->quarter }} {{ $appraisal->year }}</h2>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto">
        <div class="glass-panel rounded-2xl p-8 border border-brand-white/10 bg-brand-white/5 space-y-6">

            {{-- Score Summary --}}
            <div class="grid grid-cols-3 gap-4">
                @php
                    $selfAvg = $appraisal->avg_self_score;
                    $mgrAvg  = $appraisal->avg_manager_score;
                    $final   = $appraisal->final_score;
                @endphp
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 text-center">
                    <p class="text-xs text-brand-ash uppercase tracking-widest mb-1">Self Score</p>
                    <p class="text-3xl font-bold text-emerald-400">{{ $selfAvg }}<span class="text-lg text-brand-white/40">/10</span></p>
                </div>
                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 text-center">
                    <p class="text-xs text-brand-ash uppercase tracking-widest mb-1">Manager Score</p>
                    <p class="text-3xl font-bold text-sky-400">{{ $mgrAvg }}<span class="text-lg text-brand-white/40">/10</span></p>
                </div>
                <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-center">
                    <p class="text-xs text-amber-400 uppercase tracking-widest mb-1">Combined Final</p>
                    <p class="text-3xl font-bold text-amber-400">{{ $final }}<span class="text-lg text-amber-400/50">/10</span></p>
                </div>
            </div>

            {{-- Self Comments --}}
            @if(!empty($appraisal->self_assessment['comments']))
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-400 mb-2">Employee Comments</p>
                    <p class="text-sm text-brand-white/70">{!! $appraisal->self_assessment['comments'] !!}</p>
                </div>
            @endif

            {{-- Manager Comments --}}
            @if(!empty($mgr['manager_comment']))
                <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-sky-400 mb-2">Manager's Evaluation — {{ $mgr['reviewer_name'] ?? '' }}</p>
                    <p class="text-sm text-brand-white/70">{!! $mgr['manager_comment'] !!}</p>
                </div>
            @endif

            {{-- Metric Comparison Table --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-widest text-brand-red mb-3">Score Breakdown</h4>
                <div class="overflow-x-auto rounded-xl border border-brand-white/10">
                    <table class="w-full text-xs text-brand-white/70">
                        <thead class="border-b border-brand-white/10 text-brand-ash">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold uppercase tracking-widest text-left">Metric</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-widest text-left">Category</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-widest text-left">Self</th>
                                <th class="px-4 py-3 font-semibold uppercase tracking-widest text-left">Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                             @foreach($metrics as $m)
                                 @if($m->metric_type === 'table')
                                     {{-- Grid comparison --}}
                                     <tr class="border-b border-brand-white/5 hover:bg-brand-white/5 bg-brand-black/10">
                                         <td colspan="4" class="px-4 py-3">
                                             <div class="mb-2">
                                                 <p class="text-brand-white font-semibold">{{ $m->name }} <span class="ml-2 rounded bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-amber-400">Grid</span></p>
                                                 @if($m->description)
                                                     <p class="text-brand-white/60 text-[10px] mt-0.5">{!! $m->description !!}</p>
                                                 @endif
                                             </div>
                                             <div class="overflow-x-auto border border-brand-white/10 rounded-xl mt-2">
                                                 <table class="w-full text-[11px] text-left text-brand-white">
                                                     <thead class="bg-brand-white/5 text-[9px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                                         <tr>
                                                             @foreach($m->table_template ?? [] as $col)
                                                                 @if($col['type'] !== 'score')
                                                                     <th class="px-3 py-1.5">{{ $col['label'] }}</th>
                                                                 @endif
                                                             @endforeach
                                                             <th class="px-3 py-1.5 text-emerald-400">Staff Rating</th>
                                                             <th class="px-3 py-1.5 text-sky-400">Mgr Rating</th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>
                                                         @php
                                                             $selfRows = $appraisal->self_table_data[$m->id] ?? [];
                                                             $mgrRows = $appraisal->manager_table_data[$m->id] ?? [];
                                                         @endphp
                                                         @forelse($selfRows as $idx => $sRow)
                                                             @php $mRow = $mgrRows[$idx] ?? []; @endphp
                                                             <tr class="border-b border-brand-white/5">
                                                                 @foreach($m->table_template ?? [] as $col)
                                                                     @if($col['type'] !== 'score')
                                                                         <td class="px-3 py-1.5 text-brand-white/70">{{ $sRow[$col['key']] ?? '—' }}</td>
                                                                     @endif
                                                                 @endforeach
                                                                 <td class="px-3 py-1.5 text-emerald-400 font-bold">{{ $sRow['score'] ?? '—' }}/10</td>
                                                                 <td class="px-3 py-1.5 text-sky-400 font-bold">{{ $mRow['score'] ?? '—' }}/10</td>
                                                             </tr>
                                                         @empty
                                                             <tr>
                                                                 <td colspan="10" class="px-3 py-2 text-center text-brand-white/30 italic">No objectives evaluated.</td>
                                                             </tr>
                                                         @endforelse
                                                     </tbody>
                                                 </table>
                                             </div>
                                         </td>
                                     </tr>
                                 @else
                                     <tr class="border-b border-brand-white/5 hover:bg-brand-white/5">
                                         <td class="px-4 py-3 text-brand-white font-semibold">{{ $m->name }}</td>
                                         <td class="px-4 py-3 text-brand-ash">{{ $m->category }}</td>
                                         <td class="px-4 py-3 text-emerald-400 font-bold">{{ $selfScores[$m->id] ?? '–' }}/10</td>
                                         <td class="px-4 py-3 text-sky-400 font-bold">{{ $mgr['scores'][$m->id] ?? '–' }}/10</td>
                                     </tr>
                                 @endif
                             @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- HR Audit Form --}}
            <form method="POST" action="{{ route('portal.appraisals.audit.submit', $appraisal) }}" class="space-y-4 border-t border-brand-white/10 pt-6">
                @csrf
                <div>
                    <x-input-label for="hr_notes" :value="__('HR Audit Notes & Final Observations')" />
                    <textarea id="hr_notes" name="hr_notes" rows="5" required
                              class="wysiwyg-editor mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30"
                              placeholder="Document the final HR assessment including validation notes and recommendations..."></textarea>
                </div>
                <div>
                    <x-input-label for="final_decision" :value="__('Final Decision')" />
                    <select id="final_decision" name="final_decision" required
                            class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        <option value="approved">✅ Approve & Finalise</option>
                        <option value="revision_requested">🔄 Request Revision (send back)</option>
                    </select>
                </div>
                <div class="flex items-center justify-between gap-4 pt-2">
                    <a href="{{ route('portal.appraisals.index') }}" class="text-xs text-brand-ash hover:text-brand-white transition-colors">← Back to Appraisals</a>
                    <button type="submit" class="rounded-xl bg-purple-600 hover:bg-purple-700 px-8 py-3 text-xs uppercase tracking-[0.2em] font-semibold text-white transition-all">
                        Submit Final Decision →
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
