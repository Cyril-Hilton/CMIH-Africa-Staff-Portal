<?php

namespace App\Services;

use App\Models\PerformanceTargetPlan;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Support\Facades\Schema;

class SharedWeeklyPerformance
{
    /** Shared projection only: Client Relations amounts are public internally; other financial amounts, HR cases and private notes stay private. */
    public function build(string $department, string $from, string $to): array
    {
        $result = [];
        foreach (config('performance.departments') as $key => $label) {
            if ($department !== 'all' && $department !== $key) {
                continue;
            }
            $result[$key] = ['label' => $label, 'kpis' => [], 'deliverables' => [], 'summary' => ['total' => 0, 'verified' => 0, 'overdue' => 0]];
        }
        if (Schema::hasTable('performance_target_plans')) {
            $plans = PerformanceTargetPlan::with(['kpi', 'owner', 'observation'])->whereIn('department', array_keys($result))->whereNotNull('approved_at')->whereNull('withdrawn_at')->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->orderBy('department')->orderBy('period_start')->get();
            foreach ($plans as $plan) {
                $kpi = $plan->kpi;
                if (! $kpi) {
                    continue;
                }
                $observation = $plan->observation;
                $actual = $observation?->approved_at ? $observation->actual : null;
                $achievement = PerformanceMath::achievement($plan->target, $actual, $kpi->direction);
                $money = $kpi->dimension === 'financial' || (bool) preg_match('/GHS|GHC|GH₵|₵|USD|GBP|EUR|currency|money|cedi|dollar|£|€|\$/i', $kpi->unit);
                $displayValue = fn($value) => $plan->department==='client_relations' && preg_match('/^(GHS|GHC|GH₵|₵)$/ui', $kpi->unit) ? 'GH₵ '.number_format((float)$value,2) : $value.' '.$kpi->unit;
                $result[$plan->department]['kpis'][] = [
                    'id' => $plan->id, 'name' => $kpi->name, 'scope' => $plan->scope_type, 'owner' => $plan->owner?->name ?? 'Unassigned',
                    'client' => $plan->client, 'project' => $plan->project, 'period' => $plan->period_start->format('d M').'–'.$plan->period_end->format('d M Y'),
                    'target' => $money && $plan->department!=='client_relations' ? '100% of agreed target' : $displayValue($plan->target),
                    'actual' => $actual === null ? 'Not reported / not approved' : ($money && $plan->department!=='client_relations' ? ($achievement === null ? 'Not measured' : $achievement.'% of target') : $displayValue($actual)),
                    'achievement' => $achievement, 'gap' => $achievement === null ? null : round($achievement - 100, 2),
                    'rag' => PerformanceMath::rag($achievement, $kpi->green_threshold, $kpi->amber_threshold),
                    'financial' => $money,
                ];
            }
        }
        if (Schema::hasTable('weekly_consolidated_items')) {
            $items = WeeklyConsolidatedItem::with('leadStaff')->whereIn('department', array_keys($result))->where('workflow_state', '!=', 'legacy')->whereDate('week_start', '<=', $to)->whereDate('week_end', '>=', $from)->orderBy('week_end')->get();
            foreach ($items as $item) {
                $verified = in_array($item->workflow_state, ['verified', 'closed']);
                $overdue = ! $verified && $item->dueDate()?->isBefore(today());
                $result[$item->department]['deliverables'][] = ['id' => $item->id, 'project' => $item->campaign_name, 'client' => $item->client_name, 'owner' => $item->leadStaff?->name ?? 'Unassigned', 'state' => $item->workflow_state, 'progress' => $item->progress_percent, 'due' => $item->dueDate()?->format('d M Y'), 'overdue' => (bool) $overdue, 'quality' => $verified ? $item->quality_score : null, 'root_cause' => $item->root_cause];
                $result[$item->department]['summary']['total']++;
                $result[$item->department]['summary']['verified'] += (int) $verified;
                $result[$item->department]['summary']['overdue'] += (int) $overdue;
            }
        }
        if (isset($result['hr_admin']) && Schema::hasTable('hr_periodic_reports') && Schema::hasTable('admin_weekly_reports')) {
            $result['hr_admin']['people_reporting'] = app(SharedPeopleReporting::class)->build($from, $to);
        }

        return $result;
    }
}
