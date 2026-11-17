<div data-journey-results class="space-y-5">
    @php
        $stages = \App\Services\ProjectJourney::STAGES;
        $stageLabels = collect($stages)->map(fn ($stage) => $stage[0])->all() + ['complete'=>'Verified complete','cancelled'=>'Closed or cancelled'];
    @endphp
    <p class="text-sm text-brand-ash">
        {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · Updated {{ $computedAt->timezone('Africa/Accra')->format('H:i') }} GMT
    </p>
    @if($view === 'insights')
        <section class="glass-panel space-y-4 rounded-2xl border border-brand-white/10 p-5" aria-label="Project stage chart">
            <div class="grid gap-3 sm:grid-cols-3">
                <p class="rounded-xl border border-brand-white/15 p-4"><strong class="block text-2xl">{{ $total }}</strong> Project records</p>
                <p class="rounded-xl border border-brand-white/15 p-4"><strong class="block text-2xl">{{ $upcoming->count() }}</strong> Upcoming</p>
                <p class="rounded-xl border border-brand-white/15 p-4"><strong class="block text-2xl">{{ $attention }}</strong> Need attention</p>
            </div>
            <h2 class="text-lg font-semibold">Projects by current stage</h2>
            @foreach($stageLabels as $key => $label)
                @php($count = $distribution->get($key, 0))
                <div>
                    <div class="mb-1 flex justify-between gap-3 text-sm">
                        <a class="underline" href="{{ route('portal.project-journey', array_replace(request()->except(['fragment','page']), ['view'=>'journeys','stage'=>$key])) }}">{{ $label }}</a>
                        <span>{{ $count }}</span>
                    </div>
                    <div aria-hidden="true" class="h-2 overflow-hidden rounded-full bg-brand-white/10">
                        <div class="h-full rounded-full bg-sky-400" style="width: {{ $total ? $count * 100 / $total : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
            @if($schedule->isNotEmpty())
                <div class="space-y-3 border-t border-brand-white/10 pt-4">
                    <h2 class="text-lg font-semibold">Upcoming dates by week</h2>
                    @foreach($schedule as $week => $count)
                        <div>
                            <div class="mb-1 flex justify-between gap-3 text-sm"><span>Week of {{ \Illuminate\Support\Carbon::parse($week)->format('d M Y') }}</span><span>{{ $count }}</span></div>
                            <div aria-hidden="true" class="h-2 overflow-hidden rounded-full bg-brand-white/10"><div class="h-full rounded-full bg-emerald-400" style="width: {{ $count * 100 / $schedule->max() }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
    <section class="glass-panel overflow-hidden rounded-2xl border border-brand-white/10">
        <h2 class="p-4 text-lg font-semibold">{{ $view === 'upcoming' ? 'Upcoming project dates' : 'Project status' }}</h2>
        <div class="overflow-x-auto" role="region" aria-label="Project status table" tabindex="0">
            <table class="w-full text-left text-sm">
                <thead class="bg-brand-white/5 text-brand-ash"><tr>
                    <th scope="col" class="p-3">Client / project</th><th scope="col" class="p-3">Account owner</th>
                    <th scope="col" class="p-3">Current stage</th><th scope="col" class="p-3">Scheduled date</th><th scope="col" class="p-3">Details</th>
                </tr></thead>
                @forelse($records as $row)
                    @php($project = $row['project'])
                    <tbody class="border-t border-brand-white/10">
                        <tr>
                            <th scope="row" class="p-3"><span class="block text-xs font-normal text-brand-ash">{{ $project->client_name }}</span>{{ $project->project_name }}</th>
                            <td class="p-3">{{ $project->owner?->name ?? '—' }}</td>
                            <td class="p-3"><span class="font-semibold">{{ $stageLabels[$row['stage']] }}</span>
                                @if($row['needs_attention'])<span class="block text-xs text-amber-300">Needs attention</span>@endif
                            </td>
                            <td class="p-3"><span class="whitespace-nowrap">{{ $row['date']?->format('d M Y') ?? '—' }}</span>
                                @if($row['date'])<span class="block text-xs text-brand-ash">{{ $row['date_label'] }}</span>@endif
                            </td>
                            <td class="p-3"><a class="underline" href="{{ route('portal.project-journey', ['project'=>$project->id]) }}">Open journey</a></td>
                        </tr>
                        <tr><td colspan="5" class="px-3 pb-3">
                            <details data-journey-detail="{{ $project->id }}" @if($focus) open @endif>
                                <summary class="cursor-pointer text-sm text-brand-ash">View stages and linked records</summary>
                                <ol class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3 xl:grid-cols-6" aria-label="Project milestones">
                                    @foreach($stages as $key => [$label, $team])
                                        <li @class(['rounded-xl border p-3', 'border-emerald-400/30 bg-emerald-400/10' => $row['done'][$key], 'border-sky-400/50 bg-sky-400/10' => !$row['done'][$key] && $row['stage'] === $key, 'border-brand-white/15' => !$row['done'][$key] && $row['stage'] !== $key]) @if($row['stage'] === $key) aria-current="step" @endif>
                                            <span class="block text-xs text-brand-ash">{{ $row['done'][$key] ? 'Recorded' : ($row['stage'] === $key ? 'Current stage' : 'Awaiting record') }}</span>
                                            <strong class="mt-1 block">{{ $label }}</strong>
                                            <span class="mt-1 block text-xs text-brand-ash">{{ $team }}</span>
                                            <span class="mt-2 block text-xs">{{ $row['notes'][$key] }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                                    <div class="space-y-2 rounded-xl border border-brand-white/15 p-3">
                                        <h3 class="font-semibold">Creative briefs</h3>
                                        @forelse($row['briefs'] as $brief)
                                            <p><strong>{{ $brief->title }}</strong><span class="block text-xs text-brand-ash">{{ $brief->reference }} · {{ config('creative.stages.'.$brief->stage, \Illuminate\Support\Str::headline($brief->stage)) }} · {{ $brief->assignee?->name ?? 'Unassigned' }}</span></p>
                                            @if($focus && $row['can_unlink']($brief))
                                                <form method="POST" action="{{ route('portal.project-journey.link') }}" data-confirm="Remove this brief link from the project?">
                                                    @csrf
                                                    <input type="hidden" name="action" value="unlink">
                                                    <input type="hidden" name="project_id" value="{{ $project->id }}">
                                                    <input type="hidden" name="brief_id" value="{{ $brief->id }}">
                                                    <button class="text-xs underline">Unlink brief</button>
                                                </form>
                                            @endif
                                            @if($brief->blocker)<p class="text-xs text-amber-300">{{ $brief->blocker }}</p>@endif
                                            @foreach($brief->parts as $part)<p class="pl-3 text-xs">{{ $part->title }} · {{ $part->assignee?->name ?? 'Unassigned' }} · {{ \Illuminate\Support\Str::headline($part->stage) }}</p>@endforeach
                                        @empty<p class="text-xs text-brand-ash">No creative brief is linked.</p>@endforelse
                                    </div>
                                    <div class="space-y-2 rounded-xl border border-brand-white/15 p-3">
                                        <h3 class="font-semibold">Operations handovers</h3>
                                        @forelse($row['handovers'] as $handover)
                                            <p><strong>{{ $handover->title }}</strong><span class="block text-xs text-brand-ash">{{ \Illuminate\Support\Str::headline($handover->stage) }} · {{ $handover->lead?->name ?? 'Unassigned' }}</span></p>
                                            @include('portal.partials.executive-record-fields', ['fields' => [
                                                'Event' => $handover->event_at, 'Location' => $handover->location, 'Deadline' => $handover->setup_due_at,
                                                'Meeting' => $handover->meeting_held_at, 'Client Relations sign-off' => $handover->cr_signoff_at,
                                                'Operations sign-off' => $handover->ops_signoff_at, 'Delivery submitted' => $handover->submitted_at,
                                                'Accepted' => $handover->accepted_at, 'Verified' => $handover->verified_at,
                                            ]])
                                        @empty<p class="text-xs text-brand-ash">No handover is linked.</p>@endforelse
                                    </div>
                                    <div class="space-y-2 rounded-xl border border-brand-white/15 p-3">
                                        <h3 class="font-semibold">Activations</h3>
                                        @forelse($row['activations'] as $activation)
                                            <p><strong>{{ $activation->title }}</strong><span class="block text-xs text-brand-ash">{{ \Illuminate\Support\Str::headline($activation->status) }} · {{ $activation->data['location'] ?? 'Location not recorded' }}</span></p>
                                            @if(auth()->user()->canViewExecutiveActivity())
                                                <a class="text-xs underline" href="{{ route('dashboard', ['activity_department'=>'operations_projects','report_date'=>$activation->period_start?->toDateString(), 'activity_activation'=>$activation->id]) }}#executive-source-reports">Open activation details</a>
                                            @endif
                                        @empty<p class="text-xs text-brand-ash">No activation is linked.</p>@endforelse
                                    </div>
                                </div>
                            </details>
                        </td></tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="5" class="p-6 text-brand-ash">No projects match the selected filters.</td></tr></tbody>
                @endforelse
            </table>
        </div>
        @if($records->hasPages())<div class="p-4">{{ $records->links() }}</div>@endif
    </section>
</div>
