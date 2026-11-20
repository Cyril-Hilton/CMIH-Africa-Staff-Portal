<?php

namespace Tests\Unit;

use App\Services\ExecutiveTabCharts;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExecutiveTabChartsTest extends TestCase
{
    private function command(string $view): array
    {
        return ['view' => $view, 'period' => 'week', 'from' => Carbon::parse('2026-11-16')->startOfDay(), 'to' => Carbon::parse('2026-11-22')->endOfDay(), 'events' => collect(), 'activeProjects' => collect(), 'charts' => ['stages' => collect(), 'process' => collect(), 'asset_status' => collect(), 'asset_ready' => collect()]];
    }

    public function test_calendar_keeps_empty_dates_and_counts_spanning_entries_once_without_attendance_or_reporting_activity(): void
    {
        $command = $this->command('calendar');
        $command['events'] = collect([
            ['kind' => 'activation', 'start' => Carbon::parse('2026-11-13')],
            ['kind' => 'creative', 'start' => Carbon::parse('2026-11-17')],
            ['kind' => 'absence', 'start' => Carbon::parse('2026-11-17')],
            ['kind' => 'meeting', 'start' => Carbon::parse('2026-11-17')],
            ['kind' => 'fleet', 'start' => Carbon::parse('2026-11-17')],
            ['kind' => 'attendance', 'start' => Carbon::parse('2026-11-17')],
            ['kind' => 'activity', 'start' => Carbon::parse('2026-11-17')],
        ]);
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertSame(5, $chart['total']);
        $this->assertCount(7, $chart['rows']);
        $this->assertSame([1, 0, 0], $chart['rows'][0]['values']);
        $this->assertSame([1, 2, 1], $chart['rows'][1]['values']);
        $this->assertSame([0, 0, 0], $chart['rows'][6]['values']);
        $command['period'] = 'year';
        $command['from'] = Carbon::parse('2026-01-01');
        $command['to'] = Carbon::parse('2026-12-31')->endOfDay();
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertCount(12, $chart['rows']);
        $this->assertSame('Jan 2026', $chart['rows'][0]['label']);
        $this->assertSame([2, 2, 1], $chart['rows'][10]['values']);
    }

    public function test_accountability_buckets_do_not_double_count_leave_or_completed_work(): void
    {
        $command = $this->command('accountability');
        $event = ['kind' => 'creative', 'done' => false, 'owner' => 1, 'person' => (object) ['name' => 'Lead Creative'], 'late' => false, 'leave_overlap' => false];
        $command['events'] = collect([$event, array_replace($event, ['late' => true]), array_replace($event, ['late' => true, 'leave_overlap' => true]), array_replace($event, ['done' => true]), array_replace($event, ['kind' => 'absence']), array_replace($event, ['kind' => 'activity']), array_replace($event, ['kind' => 'attendance']), array_replace($event, ['owner' => 0, 'person' => null])]);
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertSame(4, $chart['total']);
        $this->assertSame([1, 1, 1], $chart['rows'][0]['values']);
        $this->assertSame('Assignment needed', $chart['rows'][1]['label']);
        $this->assertSame(4, collect($chart['rows'])->sum(fn ($row) => array_sum($row['values'])));
    }

    public function test_report_chart_uses_full_pagination_totals_instead_of_visible_page_size(): void
    {
        $reports = ['groups' => [['label' => 'Activations', 'rows' => new LengthAwarePaginator(range(1, 8), 42, 8)], ['label' => 'Reports', 'rows' => new LengthAwarePaginator([], 0, 8)]]];
        $chart = app(ExecutiveTabCharts::class)->forTab($this->command('reports'), $reports);
        $this->assertSame(42, $chart['total']);
        $this->assertSame([42], $chart['rows'][0]['values']);
        $private = app(ExecutiveTabCharts::class)->forTab($this->command('reports'), ['groups' => []]);
        $this->assertSame(0, $private['total']);
        $this->assertSame([], $private['rows']);
    }

    public function test_readiness_preserves_zero_credit_for_missing_plan_and_excludes_closed_or_non_execution_projects(): void
    {
        $command = $this->command('compliance');
        $project = ['execution_required' => true, 'closed' => false, 'schedule' => ['starts_at' => '2026-11-20'], 'project' => (object) ['project_name' => 'Launch', 'client_name' => 'Client'], 'chart_key' => 'launch', 'check' => ['score' => 50]];
        $command['activeProjects'] = collect([$project, array_replace($project, ['closed' => true]), array_replace($project, ['execution_required' => false])]);
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertSame(1, $chart['total']);
        $this->assertSame([0.0, 50.0], $chart['rows'][0]['values']);
        $this->assertSame('%', $chart['unit']);
    }

    public function test_process_does_not_require_activation_controls_for_direct_creative_work(): void
    {
        $command = $this->command('projects');
        $command['activeProjects'] = collect([['execution_required' => false, 'process' => []], ['execution_required' => true, 'process' => ['brief' => ['completed_at' => '2026-11-17', 'evidence' => 'Original brief', 'status' => 'approved']]]]);
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertSame(1, $chart['total']);
        $this->assertSame('Proposal submission', $chart['rows'][0]['label']);
    }

    public function test_every_empty_tab_has_a_real_zero_without_placeholder_values(): void
    {
        foreach (array_keys(config('executive_tracker.tabs')) as $tab) {
            $chart = app(ExecutiveTabCharts::class)->forTab($this->command($tab));
            $this->assertSame(0, $chart['total']);
            $this->assertSame('executive-chart-'.$tab, $chart['id']);
        }
    }

    public function test_unknown_execution_stage_is_not_presented_as_recorded_planning(): void
    {
        $command = $this->command('overview');
        $command['charts']['stages'] = collect(['Planning / source workflow' => 7, 'planning' => 3]);
        $chart = app(ExecutiveTabCharts::class)->forTab($command);
        $this->assertSame(10, $chart['total']);
        $this->assertSame('Source workflow · execution stage not recorded', $chart['rows'][0]['label']);
        $this->assertSame('Planning', $chart['rows'][1]['label']);
    }
}
