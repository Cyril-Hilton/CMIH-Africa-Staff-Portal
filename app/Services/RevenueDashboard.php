<?php

namespace App\Services;

use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\PerformanceEvent;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class RevenueDashboard
{
    public function build(array $f): array
    {
        $period = $f['period'] ?? 'weekly';
        $anchor = Carbon::parse($f['date'] ?? today());
        $from = match ($period) {
            'monthly' => $anchor->copy()->startOfMonth(),'yearly' => $anchor->copy()->startOfYear(),default => $anchor->copy()->startOfWeek()
        };
        $to = match ($period) {
            'monthly' => $anchor->copy()->endOfMonth(),'yearly' => $anchor->copy()->endOfYear(),default => $anchor->copy()->endOfWeek()
        };
        $projects = CrmPipelineProject::with(['owner', 'revenueSubmitter', 'revenueReviewer'])->where('source_period_confirmed', true)->whereBetween('week_start', [$from->toDateString(), $to->toDateString()])->get();
        // These are snapshot balances, never a sum of repeated weekly declarations.
        $projects = app(ClientRelationsCentre::class)->latest($projects)->filter(fn ($p) => $p->actual_amount !== null);
        $targets = CrmAccountTarget::with(['owner', 'revenueSubmitter', 'revenueReviewer'])->where('needs_reconciliation', false)->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->whereNotNull('actual_amount')->get();
        $filter = fn ($p) => (empty($f['q']) || str_contains(mb_strtolower($p->client_name.' '.($p->project_name ?? '').' '.($p->brand ?? '').' '.$p->owner?->name), mb_strtolower($f['q']))) && (empty($f['state']) || $p->revenue_state === $f['state']);
        $projects = $projects->filter($filter);
        $targets = $targets->filter($filter);
        $confirmed = $projects->filter(fn ($p) => $p->confirmedRevenue() !== null);
        $rows = $projects->map(fn ($p) => ['type' => 'project', 'row' => $p])->values()->concat($targets->map(fn ($t) => ['type' => 'target', 'row' => $t]));
        $rows = match ($f['sort'] ?? 'pending') {
            'amount' => $rows->sortByDesc(fn ($r) => (float) $r['row']->actual_amount),'client' => $rows->sortBy(fn ($r) => mb_strtolower($r['row']->client_name)),default => $rows->sortBy(fn ($r) => ($r['row']->confirmedRevenue() !== null ? '1' : '0').mb_strtolower($r['row']->client_name))
        };
        $page = max(1, min((int) ($f['page'] ?? 1), max(1, (int) ceil($rows->count() / 10))));
        $pager = new LengthAwarePaginator($rows->slice(($page - 1) * 10, 10)->values(), $rows->count(), 10, $page, ['path' => route('dashboard'), 'pageName' => 'revenue_page']);
        $pager->appends(array_merge(request()->except('revenue_page'), ['revenue_view' => 1]))->fragment('staff-weekly-performance');
        $events = PerformanceEvent::with('actor')->where(function ($q) use ($pager) {
            foreach (['project', 'target'] as $type) {
                $q->orWhere(fn ($x) => $x->where('entity_type', 'crm_revenue_'.$type)->whereIn('entity_id', collect($pager->items())->where('type', $type)->pluck('row.id')));
            }
        })->orderByDesc('id')->get()->groupBy(fn ($e) => $e->entity_type.':'.$e->entity_id);

        return compact('period', 'from', 'to', 'pager', 'events') + ['confirmed' => $confirmed->isEmpty() ? null : round($confirmed->sum(fn ($p) => $p->confirmedRevenue()), 2), 'confirmed_count' => $confirmed->count(), 'pending' => $projects->count() - $confirmed->count(), 'targets_count' => $targets->count(), 'accounts' => $projects->groupBy(fn ($p) => ClientRelationsCentre::key($p->client_name).'|'.$p->owner_id)->map(fn ($g) => ['client' => $g->first()->client_name, 'owner' => $g->first()->owner?->name ?? 'Unassigned', 'brands' => $g->pluck('brand')->filter()->unique()->implode(', '), 'confirmed' => $g->filter(fn ($p) => $p->confirmedRevenue() !== null)->sum(fn ($p) => $p->confirmedRevenue()), 'coverage' => $g->filter(fn ($p) => $p->confirmedRevenue() !== null)->count(), 'total' => $g->count()])->values()];
    }
}
