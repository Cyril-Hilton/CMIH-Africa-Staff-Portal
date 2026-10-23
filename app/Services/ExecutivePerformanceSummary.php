<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ExecutivePerformanceSummary
{
    public function build(Collection $registers, Collection $cases): array
    {
        $rows = $registers->whereNotNull('approved_at')->where('status', '!=', 'cancelled');
        $revenue = $rows->where('department', 'finance')->where('kind', 'revenue')->filter(fn ($r) => isset($r->data['actual']));
        $projects = $rows->where('department', 'operations_projects')->where('kind', 'project')->filter(fn ($r) => isset($r->data['revenue'],$r->data['actual_cost']));
        $invoices = $rows->where('department', 'finance')->where('kind', 'invoice')->filter(fn ($r) => isset($r->data['value'],$r->data['paid_amount']));
        $cash = $rows->where('department', 'finance')->where('kind', 'cash')->filter(fn ($r) => isset($r->data['closing_balance']) && ! empty($r->data['account_ref']));
        $cash = $cash->groupBy(fn ($r) => $r->data['account_ref'])->map(fn ($group) => $group->sortByDesc('period_end')->first());
        $satisfaction = $rows->where('kind', 'client')->filter(fn ($r) => isset($r->data['satisfaction_score']));
        $card = fn ($label, $value, $unit, $source, $note) => ['label' => $label, 'value' => $value, 'unit' => $unit, 'sources' => $source->pluck('id')->values()->all(), 'coverage' => $source->count(), 'note' => $note];

        return [
            $card('Recognised Revenue', $revenue->isEmpty() ? null : $revenue->sum(fn ($r) => (float) $r->data['actual']), 'GHS', $revenue, 'Approved Finance revenue records. Pipeline, POs and forecasts are excluded.'),
            $card('Project Gross Profit', $projects->isEmpty() ? null : $projects->sum(fn ($r) => (float) $r->data['revenue'] - (float) $r->data['actual_cost']), 'GHS', $projects, 'Recognised project revenue minus actual cost; only complete source pairs.'),
            $card('Receivables', $invoices->isEmpty() ? null : $invoices->sum(fn ($r) => max(0, (float) $r->data['value'] - (float) $r->data['paid_amount'] - (float) ($r->data['credited_amount'] ?? 0))), 'GHS', $invoices, 'Invoice values less payments and credits.'),
            $card('Cash Position', $cash->isEmpty() ? null : $cash->sum(fn ($r) => (float) $r->data['closing_balance']), 'GHS', $cash, 'Latest approved closing balance per account. Net cash movement is not a bank balance.'),
            $card('Client Satisfaction', $satisfaction->isEmpty() ? null : $satisfaction->avg(fn ($r) => (float) $r->data['satisfaction_score']), '%', $satisfaction, 'Mean of recorded 0–100 client satisfaction results; sample count shown.'),
            $card('Critical Issues', $cases->where('kind', 'issue')->where('priority', 'Critical')->where('status', '!=', 'closed')->count(), 'cases', collect(), 'Open critical issues in the selected reporting scope.'),
            $card('Open Risks', $cases->where('kind', 'risk')->where('status', '!=', 'closed')->count(), 'cases', collect(), 'Open risk records in the selected reporting scope.'),
        ];
    }
}
