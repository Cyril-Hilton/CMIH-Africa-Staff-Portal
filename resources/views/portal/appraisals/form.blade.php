<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Step 1 of 3</p>
            <h2 class="text-3xl font-display text-brand-white">Self-Assessment — {{ $appraisal->quarter }} {{ $appraisal->year }}</h2>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto">
        <div class="glass-panel rounded-2xl p-8 border border-brand-white/10 bg-brand-white/5">
            <p class="text-sm text-brand-white/60 mb-6">
                Rate yourself honestly on each metric below using a <span class="text-brand-white font-bold">1–10 scale</span>
                (1 = Needs significant improvement · 10 = Exceptional / Exceeds all expectations).
            </p>

            <form method="POST" action="{{ route('portal.appraisals.self.submit', $appraisal) }}" class="space-y-6">
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
                                                rows: Array.from({ length: {{ $metric->default_rows ?: 3 }} }, () => ({
                                                    @foreach($metric->table_template ?? [] as $col)
                                                        {{ $col['key'] }}: '',
                                                    @endforeach
                                                }))
                                             }">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm font-semibold text-brand-white">{{ $metric->name }}</p>
                                                    @if($metric->description)
                                                        <p class="text-xs text-brand-white/50 mt-0.5">{!! $metric->description !!}</p>
                                                    @endif
                                                </div>
                                                <span class="rounded bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-amber-400 shrink-0">Grid Evaluation</span>
                                            </div>

                                            <div class="overflow-x-auto border border-brand-white/10 rounded-xl">
                                                <table class="w-full text-xs text-left text-brand-white">
                                                    <thead class="bg-brand-white/5 text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                                                        <tr>
                                                            @foreach($metric->table_template ?? [] as $col)
                                                                <th class="px-3 py-2" style="width: {{ $col['width'] ?? 'auto' }}">{{ $col['label'] }}</th>
                                                            @endforeach
                                                            <th class="px-2 py-2 w-10 text-center">✕</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(row, idx) in rows" :key="idx">
                                                            <tr class="border-b border-brand-white/5 bg-brand-black/20 hover:bg-brand-white/5 transition">
                                                                @foreach($metric->table_template ?? [] as $col)
                                                                    <td class="px-2 py-2">
                                                                        @if($col['type'] === 'score')
                                                                            <select x-model="row.{{ $col['key'] }}" :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][{{ $col['key'] }}]'" required
                                                                                    class="w-full rounded border border-brand-white/10 bg-brand-black/40 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                                                                <option value="">Rating</option>
                                                                                @for($i=1; $i<=10; $i++)
                                                                                    <option value="{{ $i }}">{{ $i }}</option>
                                                                                @endfor
                                                                            </select>
                                                                        @elseif($col['type'] === 'number')
                                                                            <input type="number" x-model="row.{{ $col['key'] }}" :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][{{ $col['key'] }}]'" required
                                                                                   class="w-full rounded border border-brand-white/10 bg-brand-black/40 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0" placeholder="0">
                                                                        @elseif($col['type'] === 'textarea')
                                                                            <textarea x-model="row.{{ $col['key'] }}" :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][{{ $col['key'] }}]'" rows="1" required
                                                                                      class="w-full rounded border border-brand-white/10 bg-brand-black/40 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0" placeholder="..."></textarea>
                                                                        @else
                                                                            <input type="text" x-model="row.{{ $col['key'] }}" :name="'table_data[' + {{ $metric->id }} + '][' + idx + '][{{ $col['key'] }}]'" required
                                                                                   class="w-full rounded border border-brand-white/10 bg-brand-black/40 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0" placeholder="Detail...">
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                                <td class="px-2 py-2 text-center">
                                                                    <button type="button" @click="rows.splice(idx, 1)" class="text-brand-red hover:text-red-400 font-bold">✕</button>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <button type="button" @click="rows.push({
                                                @foreach($metric->table_template ?? [] as $col)
                                                    {{ $col['key'] }}: '',
                                                @endforeach
                                            })" class="text-xs text-brand-red hover:underline font-semibold">
                                                ＋ Add target row
                                            </button>
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
                                                <span class="text-brand-white/40 text-xs shrink-0">Score: 1–10</span>
                                            </div>
                                            {{-- Slider + number display --}}
                                            <div class="flex items-center gap-4">
                                                <input type="range" name="scores[{{ $metric->id }}]" id="score_{{ $metric->id }}"
                                                       min="1" max="10" value="5" step="1"
                                                       class="flex-1 h-2 appearance-none rounded-full bg-brand-white/20 accent-brand-red cursor-pointer"
                                                       oninput="document.getElementById('score_val_{{ $metric->id }}').textContent = this.value">
                                                <span id="score_val_{{ $metric->id }}"
                                                      class="w-10 text-center rounded-lg border border-brand-white/10 bg-brand-red/20 text-brand-red font-bold text-sm py-1">5</span>
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

                {{-- Qualitative Comments --}}
                <div>
                    <x-input-label for="comments" :value="__('Overall Self-Assessment Comments')" />
                    <textarea id="comments" name="comments" rows="5"
                              class="wysiwyg-editor mt-1 w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white placeholder-brand-white/30"
                              placeholder="Describe your key achievements, challenges faced, and what support you may need..."></textarea>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2">
                    <a href="{{ route('portal.appraisals.index') }}" class="text-xs text-brand-ash hover:text-brand-white transition-colors">← Back to Appraisals</a>
                    <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-8 py-3 text-xs uppercase tracking-[0.2em] font-semibold text-white transition-all">
                        Submit Self-Assessment →
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
