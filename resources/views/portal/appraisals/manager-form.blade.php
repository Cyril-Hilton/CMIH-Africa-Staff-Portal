<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Step 2 of 3 · Line Manager Review</p>
            <h2 class="text-3xl font-display text-brand-white">Manager Scorecard — {{ $appraisal->quarter }} {{ $appraisal->year }}</h2>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto">
        <div class="glass-panel rounded-2xl p-8 border border-brand-white/10 bg-brand-white/5">

            {{-- Employee self-assessment context --}}
            @if(!empty($appraisal->self_assessment['comments']))
                <div class="mb-6 rounded-xl border border-sky-500/20 bg-sky-500/5 p-4">
                    <p class="text-xs uppercase tracking-widest text-sky-400 font-semibold mb-2">Employee's Self-Assessment Comments</p>
                    <p class="text-sm text-brand-white/70">{!! $appraisal->self_assessment['comments'] !!}</p>
                </div>
            @endif

            <p class="text-sm text-brand-white/60 mb-6">
                Review the employee's self-assessment and provide your independent <span class="text-brand-white font-bold">1–10 manager scores</span>.
                Employee's own scores are shown as reference in grey.
            </p>

            <form method="POST" action="{{ route('portal.appraisals.manager.submit', $appraisal) }}" class="space-y-6">
                @csrf

                @foreach(['General' => 'General Performance', 'Technical' => 'Technical Competency', 'Leadership' => 'Leadership & Teamwork'] as $cat => $catLabel)
                    @php $catMetrics = $metrics->where('category', $cat); @endphp
                    @if($catMetrics->isNotEmpty())
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-brand-red border-b border-brand-white/10 pb-2 mb-4">{{ $catLabel }}</h4>
                            <div class="space-y-5">
                                @foreach($catMetrics as $metric)
                                    @if($metric->metric_type === 'table')
                                        {{-- Table (Grid) Type Metric --}}
                                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 p-4 space-y-4"
                                             x-data="{ 
                                                rows: {{ json_encode($appraisal->self_table_data[$metric->id] ?? []) }}
                                             }">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm font-semibold text-brand-white">{{ $metric->name }}</p>
                                                    @if($metric->description)
                                                        <p class="text-xs text-brand-white/50 mt-0.5">{!! $metric->description !!}</p>
                                                    @endif
                                                </div>
                                                <span class="rounded bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-sky-400 shrink-0">Grid Evaluation</span>
                                            </div>

                                            <div class="overflow-x-auto border border-brand-white/10 rounded-xl">
                                                <table class="w-full text-xs text-left text-brand-white">
                                                    <thead class="bg-brand-white/5 text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                                        <tr>
                                                            @foreach($metric->table_template ?? [] as $col)
                                                                <th class="px-3 py-2" style="width: {{ $col['width'] ?? 'auto' }}">{{ $col['label'] }}</th>
                                                            @endforeach
                                                            <th class="px-3 py-2 text-sky-400" style="width: 15%">Manager Score (1-10)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(row, idx) in rows" :key="idx">
                                                            <tr class="border-b border-brand-white/5 bg-brand-black/20">
                                                                @foreach($metric->table_template ?? [] as $col)
                                                                    <td class="px-3 py-2 text-brand-white/70">
                                                                        <input type="hidden" :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][{{ $col['key'] }}]'" x-model="row.{{ $col['key'] }}">
                                                                        <span x-text="row.{{ $col['key'] }}"></span>
                                                                    </td>
                                                                @endforeach
                                                                <td class="px-2 py-2">
                                                                    <select :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][score]'" required
                                                                            class="w-full rounded border border-brand-white/10 bg-brand-black/40 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                                                        <option value="">Rate</option>
                                                                        @for($i=1; $i<=10; $i++)
                                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                                        @endfor
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Slider (Standard) Type Metric --}}
                                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 p-4">
                                            <div class="flex items-start justify-between gap-4 mb-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-brand-white">{{ $metric->name }}</p>
                                                    @if($metric->description)
                                                        <p class="text-xs text-brand-white/50 mt-0.5">{!! $metric->description !!}</p>
                                                    @endif
                                                </div>
                                                @php $selfScore = $selfScores[$metric->id] ?? null; @endphp
                                                @if($selfScore)
                                                    <span class="text-brand-white/40 text-xs shrink-0">Staff rated: <span class="font-bold text-brand-white/60">{{ $selfScore }}/10</span></span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <input type="range" name="scores[{{ $metric->id }}]" id="mgr_score_{{ $metric->id }}"
                                                       min="1" max="10" value="{{ $selfScore ?? 5 }}" step="1"
                                                       class="flex-1 h-2 appearance-none rounded-full bg-brand-white/20 accent-sky-500 cursor-pointer"
                                                       oninput="document.getElementById('mgr_val_{{ $metric->id }}').textContent = this.value">
                                                <span id="mgr_val_{{ $metric->id }}"
                                                      class="w-10 text-center rounded-lg border border-sky-500/30 bg-sky-500/10 text-sky-400 font-bold text-sm py-1">{{ $selfScore ?? 5 }}</span>
                                            </div>
                                            <div class="flex justify-between text-[10px] text-brand-white/30 mt-1 px-0.5">
                                                <span>1 · Poor</span><span>5 · Average</span><span>10 · Excellent</span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                <div>
                    <x-input-label for="manager_comment" :value="__('Manager Evaluation Summary & Comments')" />
                    <textarea id="manager_comment" name="manager_comment" rows="5"
                              class="wysiwyg-editor mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30"
                              placeholder="Provide your professional assessment of this staff member's performance this quarter..."></textarea>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2">
                    <a href="{{ route('portal.appraisals.index') }}" class="text-xs text-brand-ash hover:text-brand-white transition-colors">← Back to Appraisals</a>
                    <button type="submit" class="rounded-xl bg-sky-600 hover:bg-sky-700 px-8 py-3 text-xs uppercase tracking-[0.2em] font-semibold text-white transition-all">
                        Submit Manager Review →
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
