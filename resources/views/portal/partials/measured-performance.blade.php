@php
    $percentage = fn ($value) => $value === null ? 'Not measured' : round($value, 2).'%';
@endphp
<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-brand-white">Task Completion Rankings</h3>
        <p class="mt-1 text-sm text-brand-ash">Ranked by measured task completion. Reporting gaps are shown separately from business KPI achievement. Missing results against approved targets receive zero credit. Official awards use the full performance score.</p>
    </div>
    @foreach(['measured_employees' => 'Staff', 'measured_departments' => 'Departments'] as $key => $label)
        <div class="overflow-x-auto rounded-xl border border-brand-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <caption class="bg-brand-white/5 px-4 py-3 text-left font-semibold text-brand-white">{{ $label }}</caption>
                <thead class="text-brand-ash">
                    <tr>
                        <th scope="col" class="p-3">Rank</th>
                        <th scope="col" class="p-3">{{ $key === 'measured_employees' ? 'Staff member' : 'Department' }}</th>
                        <th scope="col" class="p-3">Task completion</th>
                        <th scope="col" class="p-3">Punctuality</th>
                        <th scope="col" class="p-3">Assigned KPI achievement</th>@if($key==='measured_departments')<th scope="col" class="p-3">Reporting coverage</th>@endif
                        <th scope="col" class="p-3">Full score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-white/10">
                    @forelse($standings[$key] as $row)
                        <tr>
                            <td class="p-3 tabular-nums">{{ $row['rank'] ?? '—' }}</td>
                            <th scope="row" class="p-3 font-semibold">
                                {{ $row['label'] }}
                                @if($row['department'])<span class="block text-xs font-normal text-brand-ash">{{ $row['department'] }}</span>@endif
                            </th>
                            <td class="p-3 font-semibold tabular-nums">{{ $percentage($row['task_rate']) }}</td>
                            <td class="p-3 tabular-nums">
                                {{ $percentage($row['punctuality']) }}
                                @if($row['expected_workdays'] > 0)
                                    <span class="block text-xs text-brand-ash">{{ $row['attendance_days'] }}/{{ $row['expected_workdays'] }} days clocked</span>
                                @endif
                            </td>
                            <td class="p-3 tabular-nums">{{ $row['kpi_rate']===null && ($row['kpi_expected']??0)===0 ? 'No assigned KPI target' : $percentage($row['kpi_rate']) }}</td>
                            @if($key==='measured_departments')<td class="p-3 tabular-nums">{{ $percentage($row['reporting_coverage']??null) }}<span class="block text-xs text-brand-ash">{{ $row['reporting_missing']??0 }} reporting areas missing</span></td>@endif
                            <td class="p-3 tabular-nums">{{ $row['score']===null ? 'Awaiting required setup / data' : $percentage($row['score']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-brand-ash">No measured activity for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>
