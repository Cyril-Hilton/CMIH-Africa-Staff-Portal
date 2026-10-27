<?php

namespace App\Services;

use App\Models\Task;
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
