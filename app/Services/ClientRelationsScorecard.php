<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientRelationsScorecard
{
    public function build(Collection $projects, Collection $records): array
    {
        $active = $projects->whereNotIn('stage', ['lost', 'cancelled']);
        $opportunities = $active->whereNotIn('stage', ['project_ongoing', 'project_completed']);
        $financial = fn ($field) => ['value' => $projects->whereNotNull($field)->isEmpty() ? null : (float) $projects->whereNotNull($field)->sum($field), 'coverage' => $projects->whereNotNull($field)->count(), 'total' => $projects->count()];
        $paired = $projects->whereNotNull('actual_amount')->whereNotNull('target_amount');
        $target = $paired->sum('target_amount');
        $actual = $paired->sum('actual_amount');
        $clientRevenue = $projects->groupBy(fn ($p) => mb_strtolower(trim($p->client_name)))->map(function ($group) {
            $known = $group->whereNotNull('actual_amount');

            return ['client' => $group->first()->client_name, 'revenue' => $known->isEmpty() ? null : (float) $known->sum('actual_amount'), 'projects' => $group->count(), 'coverage' => $known->count(), 'project_ids' => $group->pluck('id')->all()];
        })->values();
        $issues = $records->where('kind', 'issue')->whereIn('status', ['open', 'in_progress']);
        $meetings = $records->where('kind', 'meeting');
        $satisfaction = $records->where('kind', 'satisfaction')->where('status', 'recorded')->filter(fn ($r) => isset($r->data['score'],$r->data['maximum_score']) && $r->data['maximum_score'] > 0);
        $responses = $satisfaction->map(fn ($r) => 100 * $r->data['score'] / $r->data['maximum_score']);
        $invoices = $records->where('kind', 'invoice')->whereIn('status', ['issued', 'part_paid', 'paid']);
        $measuredInvoices = $invoices->filter(fn ($r) => isset($r->data['amount'],$r->data['paid_amount']));
        $outstandingInvoices = $measuredInvoices->filter(fn ($r) => $r->data['amount'] > $r->data['paid_amount']);
        $pos = $active->whereIn('po_state', ['not_requested', 'requested'])->filter(fn ($p) => $p->target_amount !== null && ($p->po_amount === null || (float) $p->po_amount < (float) $p->target_amount));
        $rateRisk = $active->whereNotNull('rate_card_value')->whereNotNull('po_amount');
        $executionRisk = $active->whereNotNull('execution_value')->whereNotNull('invoiced_amount');

        return [
            'total_clients' => $projects->pluck('client_name')->merge($records->pluck('client_name'))->map(fn ($s) => mb_strtolower(trim($s)))->filter()->unique()->count(),
            'revenue' => $financial('actual_amount'), 'revenue_by_client' => $clientRevenue,
            'target' => $paired->isEmpty() ? null : (float) $target, 'actual' => $paired->isEmpty() ? null : (float) $actual, 'achievement' => PerformanceMath::ratio($paired->isEmpty() ? null : (float) $actual, $paired->isEmpty() ? null : (float) $target), 'target_coverage' => $paired->count(),
            'pipeline_count' => $opportunities->count(), 'pipeline_value' => $opportunities->whereNotNull('proposed_amount')->isEmpty() ? null : (float) $opportunities->sum('proposed_amount'),
            'weighted_pipeline' => $opportunities->whereNotNull('proposed_amount')->isEmpty() ? null : $opportunities->sum(fn ($p) => (float) $p->proposed_amount * config('crm_pipeline.stages.'.$p->stage.'.probability', 0) / 100),
            'new_business' => $opportunities->where('new_business', true)->values(), 'issues' => $issues->values(),
            'client_satisfaction' => $responses->isEmpty() ? null : round($responses->avg(), 2), 'satisfaction_responses' => $responses->count(),
            'upcoming_meetings' => $meetings->where('status', 'upcoming')->filter(fn ($r) => ! empty($r->data['scheduled_at']) && Carbon::parse($r->data['scheduled_at'])->gte(now()))->values(),
            'overdue_meetings' => $meetings->where('status', 'upcoming')->filter(fn ($r) => ! empty($r->data['scheduled_at']) && Carbon::parse($r->data['scheduled_at'])->lt(now()))->values(),
            'completed_meetings' => $meetings->where('status', 'completed')->values(),
            'outstanding_pos' => $pos->values(), 'po_exposure' => $pos->isEmpty() ? null : $pos->sum(fn ($p) => max(0, (float) $p->target_amount - (float) ($p->po_amount ?? 0))),
            'outstanding_invoices' => $outstandingInvoices->values(), 'invoice_balance' => $outstandingInvoices->isEmpty() ? ($measuredInvoices->isEmpty() ? null : 0) : $outstandingInvoices->sum(fn ($r) => $r->data['amount'] - $r->data['paid_amount']),
            'invoice_coverage' => $measuredInvoices->count(), 'invoice_total' => $invoices->count(),
            'rate_card_gap' => $rateRisk->isEmpty() ? null : $rateRisk->sum(fn ($p) => max(0, (float) $p->rate_card_value - (float) $p->po_amount)),
            'execution_gap' => $executionRisk->isEmpty() ? null : $executionRisk->sum(fn ($p) => max(0, (float) $p->execution_value - (float) $p->invoiced_amount)),
            'rate_coverage' => $rateRisk->count(), 'execution_coverage' => $executionRisk->count(),
            'stages' => $active->countBy('stage'),
        ];
    }
}
