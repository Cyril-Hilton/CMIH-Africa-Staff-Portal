<?php

namespace App\Services;

use Illuminate\Support\Collection;

class WeeklyAccountabilityService
{
    public function summarize(Collection $items): array
    {
        $completed = $items->where('status', 'Done');
        $dated = $completed->filter(fn ($item) => $item->completed_at && $item->dueDate());
        $onTime = $dated->filter(fn ($item) => $item->completed_at->lte($item->dueDate()))->count();

        return [
            'total' => $items->count(),
            'completed' => $completed->count(),
            'overdue' => $items->filter(fn ($item) => $item->isOverdue())->count(),
            'blocked' => $items->whereIn('status', ['Blocked', 'Deferred'])->count(),
            'unassigned' => $items->whereNull('lead_staff_id')->count(),
            'measured' => $items->filter(fn ($item) => $item->achievementPercent() !== null)->count(),
            'on_time' => $dated->isEmpty() ? null : round(100 * $onTime / $dated->count(), 1),
            'dated_completions' => $dated->count(),
            'staff' => $items->whereNotNull('lead_staff_id')->groupBy('lead_staff_id')->map(function ($owned) {
                $done = $owned->where('status', 'Done')->count();

                return ['name' => $owned->first()->leadStaff?->name ?? 'Former staff member',
                    'total' => $owned->count(), 'completed' => $done,
                    'overdue' => $owned->filter(fn ($item) => $item->isOverdue())->count(),
                    'blocked' => $owned->whereIn('status', ['Blocked', 'Deferred'])->count(),
                    'completion_rate' => round(100 * $done / $owned->count(), 1)];
            })->sortBy('name')->values(),
            'exceptions' => $items->filter(fn ($item) => $item->isOverdue() || in_array($item->status, ['Blocked', 'Deferred'], true))->values(),
        ];
    }
}
