<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Presentation only: charts reuse the same authorized, filtered rows as their tab. */
class ExecutiveTabCharts
{
    public function forTab(array $command, ?array $reports = null): array
    {
        $view = $command['view'];
        $projects = $command['activeProjects'];
        $events = $command['events'];
        $charts = $command['charts'];
        $spec = match ($view) {
            'overview' => $this->distribution('Portfolio in motion', 'Project & activation records', $charts['stages'], 'Each connected project is counted once. Reported closure remains distinct from verified delivery.', 'donut'),
            'projects' => $this->process($projects),
            'calendar' => $this->calendar($command),
            'resources' => $this->distribution('Assets, ready for action', 'Asset checklist & request status', $charts['asset_status'], 'Counts external asset checklist lines and warehouse requests, not stock units. Current warehouse inventory remains in its own table.', 'donut'),
            'compliance' => $this->readiness($projects, $charts),
            'accountability' => $this->accountability($events),
            'reports' => $this->distribution('The reporting picture', 'Source records by register', collect($reports['groups'] ?? [])->mapWithKeys(fn ($group) => [$group['label'] => $group['rows']->total()]), 'Full matching register totals, including records on later pages. Department, team member, reporting period and source-category selection follow the reports below; current registers retain their existing date scope.'),
        };

        return $spec + ['id' => 'executive-chart-'.$view, 'unit' => 'records'];
    }

    private function distribution(string $title, string $subtitle, Collection $data, string $note, string $type = 'bars'): array
    {
        $data = $data->sortDesc();
        $rows = $data->map(fn ($value, $label) => ['label' => (string) $label, 'values' => [(int) $value]])->values()->all();

        return ['title' => $title, 'subtitle' => $subtitle, 'note' => $note, 'type' => $type, 'series' => ['Records'], 'rows' => $rows, 'total' => (int) $data->sum(), 'headline' => 'matching records'];
    }

    private function readiness(Collection $projects, array $charts): array
    {
        $eligible = $projects->where('execution_required', true)->where('closed', false)->sortBy(fn ($p) => $p['schedule']['starts_at'] ?? '9999');
        $rows = $eligible->take(10)->map(fn ($p) => ['label' => $p['project']->project_name.' · '.$p['project']->client_name, 'values' => [(float) ($charts['asset_ready']->get($p['chart_key']) ?? 0), (float) $p['check']['score']]])->values()->all();

        return ['title' => 'Ready when it matters', 'subtitle' => 'Next 10 · assets & compliance', 'note' => 'Open execution projects ordered by scheduled start; undated projects follow. Percentages use recorded requirements and evidence. Missing asset plans earn 0% readiness; closure is not implied.', 'type' => 'bars', 'series' => ['Asset readiness', 'Compliance credit'], 'rows' => $rows, 'total' => $eligible->count(), 'headline' => 'open execution projects', 'unit' => '%'];
    }

    private function process(Collection $projects): array
    {
        $data = $projects->where('execution_required', true)->countBy(function ($project) {
            foreach (config('executive_tracker.process') as $key => $label) {
                $step = $project['process'][$key] ?? [];
                if (empty($step['completed_at']) || empty($step['evidence']) || ! in_array($step['status'] ?? '', ['submitted', 'presented', 'approved'])) {
                    return $label;
                }
            }

            return 'Process complete';
        });

        return $this->distribution('From brief to budget', 'Execution projects by next process step', $data, 'Execution projects only, counted once at the next process step needing evidence. Direct Creative work is not treated as an incomplete activation plan. Department workflow stages remain in the live tracker below.');
    }

    private function accountability(Collection $events): array
    {
        $work = $events->where('done', false)->reject(fn ($e) => in_array($e['kind'], ['activity', 'absence', 'attendance', 'meeting', 'fleet']));
        $rows = $work->groupBy('owner')->map(function ($owned) {
            $review = $owned->where('leave_overlap', true);
            $remaining = $owned->reject(fn ($e) => $e['leave_overlap']);

            return ['label' => $owned->first()['person']?->name ?? 'Assignment needed', 'values' => [$remaining->where('late', false)->count(), $remaining->where('late', true)->count(), $review->count()]];
        })->sortByDesc(fn ($row) => array_sum($row['values']))->values()->all();

        return ['title' => 'Ownership in focus', 'subtitle' => 'Open commitments by owner', 'note' => 'Each open work commitment is counted once. Approved-leave overlaps are separated for coverage review rather than treated as unexplained employee failure. Daily Mega Table tasks are excluded.', 'type' => 'bars', 'series' => ['Within deadline', 'Past deadline', 'Leave / coverage review'], 'rows' => $rows, 'total' => $work->count(), 'headline' => 'open commitments'];
    }

    private function calendar(array $command): array
    {
        $period = $command['period'];
        $from = $command['from'];
        $to = $command['to'];
        $rows = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $period === 'year' ? $cursor->format('Y-m') : $cursor->toDateString();
            $rows[$key] = ['label' => $cursor->format($period === 'year' ? 'M Y' : 'd M'), 'values' => [0, 0, 0]];
            $period === 'year' ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }
        foreach ($command['events']->reject(fn ($event) => in_array($event['kind'], ['activity', 'attendance'])) as $event) {
            $date = Carbon::parse($event['start'])->max($from);
            $key = $period === 'year' ? $date->format('Y-m') : $date->toDateString();
            if (! isset($rows[$key])) {
                continue;
            }
            $series = match ($event['kind']) {
                'absence' => 2,
                'meeting', 'fleet' => 1,
                default => 0,
            };
            $rows[$key]['values'][$series]++;
        }

        return ['title' => 'The organisation, in rhythm', 'subtitle' => $period === 'year' ? 'Calendar entries by month' : 'Calendar entries by day', 'note' => 'Each work, meeting, travel or approved-absence entry is counted once on its first visible day. Spanning entries are not repeated. Reporting activity and clock-ins remain outside these planning totals.', 'type' => 'columns', 'series' => ['Work & commitments', 'Meetings & transport', 'Approved absences'], 'rows' => array_values($rows), 'total' => array_sum(array_map(fn ($row) => array_sum($row['values']), $rows)), 'headline' => 'calendar entries'];
    }
}
