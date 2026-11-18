<?php

namespace App\Services;

use App\Models\PerformanceRegister;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Dashboard charts use non-overlapping populations and recorded approval dates. */
class DashboardInsights
{
    public function departments(array $departments, array $mapping, Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach ($departments as $key => $label) {
            $stats = TaskStatsService::forDepartments($mapping[$key] ?? [$key], $from, $to);
            $rows[] = ['department' => $label, 'completed' => $stats['completed'],
                'in_progress' => max(0, $stats['pending'] - $stats['overdue'] - $stats['awaiting_creative_review']),
                'delayed' => $stats['overdue'], 'review_waiting' => $stats['awaiting_creative_review']];
        }
        return $rows;
    }

    public function publishedKpis(User $viewer, Carbon $from, Carbon $to, array $departments): array
    {
        $start = $from->toDateString();
        $end = $to->toDateString();
        $published = app(SharedWeeklyPerformance::class)->build('all', $start, $end);
        $result = [];
        foreach ($departments as $key => $label) {
            $restricted = match ($key) {
                'hr_admin' => ! $viewer->isCvoOrSuperAdmin() && ! $viewer->hasFullHrAccess() && ! $viewer->canAuditHrAndFinance(),
                'finance' => ! $viewer->isCvoOrSuperAdmin() && PerformanceAccess::department($viewer) !== 'finance' && ! $viewer->canAuditHrAndFinance(),
                default => false,
            };
            $result[$key] = [
                'label' => $label,
                'link' => route('dashboard', ['weekly_department' => $key, 'report_period' => 'month', 'report_date' => $start], false).'#staff-weekly-performance',
                'items' => [],
            ];
            if ($restricted) {
                continue;
            }

            $source = $published[$key]['source_performance'] ?? null;
            if ($source) {
                $result[$key]['items'][] = ['label'=>'Reporting coverage', 'scope'=>$source['reported'].' / '.$source['expected'].' required reporting areas',
                    'result'=>$source['coverage']===null?'No reporting areas configured':$source['coverage'].'% · '.$source['missing'].' missing'];
                foreach ($source['metrics'] as $metric) {
                    $result[$key]['items'][] = ['label'=>$metric['name'],'scope'=>'Automatic from published department data',
                        'result'=>$metric['credited'].'% credit · '.$metric['state']];
                }
            }
            foreach ($published[$key]['kpis'] ?? [] as $kpi) {
                $result[$key]['items'][] = [
                    'label' => $kpi['name'],
                    'scope' => $kpi['owner'].' · '.$kpi['period'],
                    'result' => $kpi['achievement'] === null ? 'Awaiting verified result' : $kpi['achievement'].'% of target',
                ];
            }
        }

        $creative = app(\App\Services\Creative\CreativeReport::class)->build($viewer, 'monthly', $start);
        foreach (['verified' => 'Verified creative jobs', 'on_time_credit' => 'On-time creative delivery credit'] as $field => $label) {
            $value = $creative[$field];
            $result['creatives']['items'][] = [
                'label' => $label,
                'scope' => 'Creative Studio · work visible to you',
                'result' => $value === null ? '—' : $value.($field === 'on_time_credit' ? '%' : ''),
            ];
        }

        $activations = OperationsReporting::activations()
            ->whereBetween('period_start', [$start, $end])
            ->where(fn ($query) => $query->whereNull('data->import_undated')->orWhere('data->import_undated', false))
            ->get();
        foreach ([
            'Activations reported' => $activations->count(),
            'Completed activations' => $activations->filter(OperationsReporting::activationExecuted(...))->count(),
        ] as $label => $count) {
            $result['operations_projects']['items'][] = ['label' => $label, 'scope' => 'Published activation reports', 'result' => $count];
        }

        $brands = PerformanceRegister::whereIn('department', User::departmentAliases('brands_marketing'))
            ->whereNotNull('approved_at')->where('status', '!=', 'cancelled')
            ->whereIn('kind', ['perfect_store', 'campaign'])
            ->whereBetween('period_start', [$start, $end])->orderByDesc('period_start')->orderByDesc('id')->get();
        $reports = app(BrandsReports::class);
        foreach ($brands as $record) {
            $entry = $reports->payload($record, 'register');
            $data = $entry['data'];
            $partner = $data['partner'] ?? null;
            if (! in_array($partner, ['castel', 'unilever'], true) || ($data['breakdown'] ?? 'overall') !== 'overall') {
                continue;
            }
            foreach (['coverage' => 'Coverage', 'osa' => 'On-shelf availability', 'innovation' => 'Innovation', 'must_have' => 'Must-have assortment', 'npd' => 'New-product availability', 'perfect_store' => 'Perfect Store'] as $metric => $label) {
                $actual = $data[$metric.'_actual_percent'] ?? null;
                $target = $data[$metric.'_target_percent'] ?? null;
                if (is_numeric($actual)) {
                    $result['brands_marketing']['items'][] = [
                        'label' => $label,
                        'scope' => ucfirst($partner).' · '.Carbon::parse($entry['week'])->format('d M Y'),
                        'result' => $actual.'%'.(is_numeric($target) ? ' / '.$target.'% target' : ''),
                    ];
                }
            }
        }

        $revenue = app(RevenueDashboard::class)->build(['period' => 'monthly', 'date' => $start]);
        $result['client_relations']['items'][] = [
            'label' => 'Confirmed revenue', 'scope' => 'Client Relations and Finance confirmation',
            'result' => $revenue['confirmed'] === null ? 'Awaiting confirmation' : 'GH₵ '.number_format($revenue['confirmed'], 2),
        ];
        if (PerformanceAccess::department($viewer) === 'finance' || $viewer->canAuditHrAndFinance()) {
            $result['finance']['items'][] = ['label' => 'Project revenue confirmations', 'scope' => 'Shared revenue review', 'result' => $revenue['confirmed_count']];
        }
        if ($viewer->hasFullHrAccess() || $viewer->canAuditHrAndFinance()) {
            $request = \Illuminate\Http\Request::create('/dashboard', 'GET', ['hr_period' => 'month', 'hr_from' => $start]);
            $request->setUserResolver(fn () => $viewer);
            $hr = app(HrDashboard::class)->build($request);
            foreach (['On-time staff days', 'Late staff days', 'Fleet requests in period'] as $label) {
                if (isset($hr['stats'][$label])) {
                    $result['hr_admin']['items'][] = ['label' => $label, 'scope' => 'HR & Admin overview', 'result' => $hr['stats'][$label]];
                }
            }
        }

        return $result;
    }

    public function completionTrend(): array
    {
        $rows = [];
        for ($i = 3; $i >= 0; $i--) {
            $from = now()->subWeeks($i)->startOfWeek();
            $to = $from->copy()->endOfWeek();
            $rows[] = ['week' => $from->format('d M').'–'.$to->format('d M'),
                'completed' => Task::approvedForPerformance()->whereNotNull('completion_reviewed_at')
                    ->whereBetween('completion_reviewed_at', [$from, $to])->count()];
        }
        return $rows;
    }
}
