<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                {{-- Back Button --}}
                <a href="{{ route('portal.surveys.index') }}"
                   class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-brand-white/15 hover:border-brand-white/40 bg-brand-white/5 hover:bg-brand-white/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-white/70 hover:text-brand-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal · Surveys</p>
                    <h2 class="text-3xl font-display text-brand-white">{{ $survey->title }}</h2>
                    <div class="flex items-center gap-2 mt-1">
                        @if($survey->is_anonymous)
                            <span class="inline-block rounded-full border border-yellow-500/20 bg-yellow-500/5 px-2 py-0.5 text-[9px] uppercase font-bold text-yellow-400">Anonymous Mode</span>
                        @else
                            <span class="inline-block rounded-full border border-blue-500/20 bg-blue-500/5 px-2 py-0.5 text-[9px] uppercase font-bold text-blue-400">Public Mode</span>
                        @endif
                        <span class="text-xs text-brand-white/40">| Created by {{ $survey->creator?->name ?? 'Staff' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('surveys.show', $survey->slug) }}" target="_blank"
                   class="rounded-xl border border-brand-white/20 hover:bg-brand-white/5 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition">
                    👁️ Public Link
                </a>
                {{-- Edit Button --}}
                <a href="{{ route('portal.surveys.edit', $survey) }}"
                   class="rounded-xl border border-brand-white/25 hover:border-brand-white/50 bg-brand-white/5 hover:bg-brand-white/10 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition">
                    ✏️ Edit Survey
                </a>
                {{-- Broadcast Email --}}
                @if(!$survey->is_anonymous && $totalResponses > 0)
                    <a href="{{ route('portal.surveys.broadcast', $survey) }}"
                       class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-white transition shadow-lg">
                        📣 Broadcast Email
                    </a>
                @endif
                @if($totalResponses > 0)
                    <a href="{{ route('portal.surveys.export', $survey) }}"
                       class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition shadow-lg">
                        📥 Export CSV
                    </a>
                @endif
            </div>

        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8">
        {{-- High-level Cards --}}
        <div class="grid gap-6 sm:grid-cols-3">
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 flex flex-col justify-between">
                <p class="text-xs uppercase tracking-wider text-brand-ash">Total Submissions</p>
                <p class="text-4xl font-display text-brand-white mt-2">{{ $totalResponses }}</p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 flex flex-col justify-between">
                <p class="text-xs uppercase tracking-wider text-brand-ash">Linked Event</p>
                <p class="text-lg font-semibold text-brand-white mt-2 truncate">{{ $survey->event->title ?? 'None' }}</p>
            </div>
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 flex flex-col justify-between">
                <p class="text-xs uppercase tracking-wider text-brand-ash">Survey Status</p>
                <p class="text-lg font-semibold mt-2 capitalize flex items-center gap-2">
                    @if($survey->status === 'published')
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Open / Active
                    @elseif($survey->status === 'closed')
                        <span class="h-2.5 w-2.5 rounded-full bg-brand-red"></span> Closed
                    @else
                        <span class="h-2.5 w-2.5 rounded-full bg-brand-white/40"></span> Draft
                    @endif
                </p>
            </div>
        </div>

        @if($totalResponses > 0)
            {{-- Demographic Analytics (only for public surveys) --}}
            @if(!$survey->is_anonymous)
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-6 border-b border-brand-white/10 pb-2">👥 Consumer Demographics</h3>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <h4 class="text-xs uppercase tracking-wider text-brand-ash text-center">Gender Split</h4>
                            <div class="h-64 relative">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-xs uppercase tracking-wider text-brand-ash text-center">Age Distribution</h4>
                            <div class="h-64 relative">
                                <canvas id="ageChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Custom Questions Analytics --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-6 border-b border-brand-white/10 pb-2">📊 Question Responses Summary</h3>
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach($survey->questions as $q)
                        {{-- Choice charts --}}
                        @if(in_array($q->question_type, ['radio', 'checkbox', 'dropdown']))
                            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 flex flex-col justify-between">
                                <div>
                                    <span class="inline-block rounded-full bg-brand-red/10 border border-brand-red/20 px-2 py-0.5 text-[8px] uppercase font-bold text-brand-red mb-2">{{ str_replace('_', ' ', $q->question_type) }}</span>
                                    <h4 class="text-sm font-semibold text-brand-white mb-4">{{ $q->question_text }}</h4>
                                </div>
                                <div class="h-56 relative">
                                    <canvas id="chart-q-{{ $q->id }}"></canvas>
                                </div>
                            </div>
                        @endif

                        {{-- Open-ended answers scroll feed --}}
                        @if(in_array($q->question_type, ['short_text', 'paragraph']))
                            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 flex flex-col h-[320px]">
                                <div class="shrink-0">
                                    <span class="inline-block rounded-full bg-brand-white/10 border border-brand-white/20 px-2 py-0.5 text-[8px] uppercase font-bold text-brand-white/60 mb-2">Text Response</span>
                                    <h4 class="text-sm font-semibold text-brand-white border-b border-brand-white/10 pb-2 mb-3 truncate" title="{{ $q->question_text }}">{{ $q->question_text }}</h4>
                                </div>
                                <div class="flex-1 overflow-y-auto space-y-2 pr-2 scrollbar-none">
                                    @php $hasTextAnswers = false; @endphp
                                    @foreach($survey->responses as $r)
                                        @php $ans = $r->answers[$q->id] ?? null; @endphp
                                        @if($ans)
                                            @php $hasTextAnswers = true; @endphp
                                            <div class="bg-brand-black/40 rounded p-2.5 text-xs border border-brand-white/5">
                                                <p class="text-brand-white/80">{!! nl2br(e($ans)) !!}</p>
                                                @if(!$survey->is_anonymous)
                                                    <p class="text-[9px] text-brand-white/40 mt-1 text-right">— {{ $r->name ?? 'Consumer' }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                    @if(!$hasTextAnswers)
                                        <div class="h-full flex items-center justify-center text-xs text-brand-white/30 italic">No responses to this question yet.</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Response Ledger / Data Grid --}}
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📋 Individual Submissions Ledger</h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="font-normal pb-3 text-left w-12">S/N</th>
                                @if(!$survey->is_anonymous)
                                    <th class="font-normal pb-3 text-left">Consumer details</th>
                                    <th class="font-normal pb-3 text-left">Age/Gender</th>
                                @endif
                                <th class="font-normal pb-3 text-left">Submitted At</th>
                                <th class="font-normal pb-3 text-left">Response Summary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @foreach($survey->responses as $index => $r)
                                <tr class="align-top">
                                    <td class="py-4 font-mono text-[10px] text-brand-white/40">{{ $index + 1 }}</td>
                                    @if(!$survey->is_anonymous)
                                        <td class="py-4">
                                            <p class="font-semibold text-brand-white">{{ $r->name }}</p>
                                            <p class="text-[10px] text-brand-white/40">{{ $r->email }}</p>
                                            <p class="text-[10px] text-brand-white/40">{{ $r->phone }}</p>
                                        </td>
                                        <td class="py-4">
                                            <p class="text-brand-white/80">Age: {{ $r->age ?? 'N/A' }}</p>
                                            <p class="text-brand-white/60">Gender: {{ $r->gender ?? 'N/A' }}</p>
                                        </td>
                                    @endif
                                    <td class="py-4 font-mono text-[10px] text-brand-white/40">
                                        {{ $r->created_at->format('d M Y h:i A') }}
                                        <p class="text-[9px] text-brand-white/20 mt-0.5">IP: {{ $r->ip_address }}</p>
                                    </td>
                                    <td class="py-4">
                                        <details class="group cursor-pointer">
                                            <summary class="text-[10px] uppercase tracking-wider text-brand-red font-semibold select-none group-open:text-brand-white transition">
                                                View Answers ({{ count($r->answers ?? []) }})
                                            </summary>
                                            <div class="mt-2 space-y-2 bg-brand-black/40 rounded-xl p-3 border border-brand-white/5 cursor-default">
                                                @foreach($survey->questions as $q)
                                                    @php $ans = $r->answers[$q->id] ?? null; @endphp
                                                    <div class="text-[11px]">
                                                        <p class="text-brand-ash font-medium">{{ $q->question_text }}</p>
                                                        @if($ans)
                                                            <p class="text-brand-white mt-0.5 font-sans">
                                                                @if(is_array($ans))
                                                                    {{ implode(', ', $ans) }}
                                                                @else
                                                                    {{ $ans }}
                                                                @endif
                                                            </p>
                                                        @else
                                                            <p class="text-brand-white/30 italic mt-0.5">Left blank</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @else
            <div class="glass-panel rounded-2xl p-12 text-center border border-brand-white/10 bg-brand-white/5 text-brand-white/40 italic text-sm">
                No submissions received yet. Share the public link to get responses!
            </div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                @if($totalResponses > 0)
                    {{-- Standard Demographics Chart.js (Public Survey Only) --}}
                    @if(!$survey->is_anonymous)
                        // Gender chart
                        const genderCtx = document.getElementById('genderChart');
                        if (genderCtx) {
                            new Chart(genderCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: {!! json_encode(array_keys($stats['genders'])) !!},
                                    datasets: [{
                                        data: {!! json_encode(array_values($stats['genders'])) !!},
                                        backgroundColor: ['#E50914', '#3B82F6', '#10B981', '#F59E0B'],
                                        borderWidth: 0,
                                        hoverOffset: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: {
                                                color: 'rgba(255, 255, 255, 0.7)',
                                                font: { size: 10, family: 'Sora' },
                                                padding: 12
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Age chart
                        const ageCtx = document.getElementById('ageChart');
                        if (ageCtx) {
                            new Chart(ageCtx, {
                                type: 'bar',
                                data: {
                                    labels: {!! json_encode(array_keys($stats['ages'])) !!},
                                    datasets: [{
                                        label: 'Consumers',
                                        data: {!! json_encode(array_values($stats['ages'])) !!},
                                        backgroundColor: '#E50914',
                                        borderRadius: 4,
                                        borderWidth: 0
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            grid: { display: false },
                                            ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { size: 10, family: 'Sora' } }
                                        },
                                        y: {
                                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                            ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { size: 10, family: 'Sora' }, precision: 0 }
                                        }
                                    },
                                    plugins: {
                                        legend: { display: false }
                                    }
                                }
                            });
                        }
                    @endif

                    {{-- Dynamic Custom Choice Questions Chart.js --}}
                    @foreach($survey->questions as $q)
                        @if(in_array($q->question_type, ['radio', 'checkbox', 'dropdown']))
                            (function() {
                                const ctx = document.getElementById('chart-q-{{ $q->id }}');
                                if (ctx) {
                                    const dataMap = {!! json_encode($stats['questions'][$q->id] ?? []) !!};
                                    new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: Object.keys(dataMap),
                                            datasets: [{
                                                data: Object.values(dataMap),
                                                backgroundColor: '#E50914',
                                                borderRadius: 4,
                                                borderWidth: 0
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    grid: { display: false },
                                                    ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { size: 10, family: 'Sora' } }
                                                },
                                                y: {
                                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                                    ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { size: 10, family: 'Sora' }, precision: 0, beginAtZero: true }
                                                }
                                            },
                                            plugins: {
                                                legend: { display: false }
                                            }
                                        }
                                    });
                                }
                            })();
                        @endif
                    @endforeach
                @endif
            });
        </script>
    @endpush
</x-app-layout>
