<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PerformanceExceptions
{
    public function build(Collection $registers): array
    {
        $out = [];
        $policy = PerformancePolicyRules::snapshot();
        foreach ($registers->whereNotNull('approved_at')->where('status', '!=', 'cancelled') as $r) {
            $d = $r->data;
            $rule = null;
            $due = null;
            $value = null;
            $dateField = match ($r->kind) {
                'invoice','payable' => 'due_on','client_issue' => 'sla_due','vacancy' => 'target_fill_on','maintenance' => 'scheduled_on','milestone' => 'planned_on','client_approval' => 'next_follow_up_on','project_report' => 'due_on',default => null
            };
            $doneField = match ($r->kind) {
                'client_issue' => 'resolved_on','vacancy' => 'offer_accepted_on','maintenance' => 'completed_on','milestone' => 'delivered_on',default => null
            };
            $finished = match ($r->kind) {
                // Approval authorises an invoice; it does not prove settlement.
                'invoice', 'payable' => in_array($r->status, ['paid', 'cancelled']),
                'client_approval' => ! empty($d['decided_on']) && in_array(strtolower($d['approval_status'] ?? ''), ['approved', 'rejected']),
                'project_report' => ! empty($d['shared_on']),
                default => in_array($r->status, ['completed', 'closed', 'resolved', 'filled']) || ($doneField && ! empty($d[$doneField])),
            };
            if ($dateField && ! empty($d[$dateField]) && ! $finished) {
                $due = Carbon::parse($d[$dateField]);
                if ($due->lt(today())) {
                    $rule = ucwords(str_replace('_', ' ', $r->kind)).' overdue';
                }
                if (in_array($r->kind, ['invoice', 'payable'])) {
                    $value = isset($d['value'],$d['paid_amount']) ? max(0, (float) $d['value'] - (float) $d['paid_amount'] - (float) ($d['credited_amount'] ?? 0)) : null;
                    if ($value !== null && $value <= 0) {
                        $rule = null;
                    }
                }
            }
            if ($r->kind === 'project' && isset($d['budget'],$d['actual_cost']) && (float) $d['budget'] > 0) {
                $variance = 100 * ((float) $d['actual_cost'] - (float) $d['budget']) / (float) $d['budget'];
                if ($variance > (float) $policy['rules']['budget_tolerance_percent']) {
                    $rule = 'Project cost exceeds budget tolerance';
                    $value = round($variance, 2).'%';
                }
            }
            if ($rule) {
                $days = $due ? max(0, (int) $due->diffInDays(today())) : 0;
                $out[] = ['policy_id' => $policy['policy_id'], 'source_id' => $r->id, 'department' => $r->department, 'reference' => $r->reference, 'title' => $r->title, 'rule' => $rule, 'due' => $due?->toDateString(), 'days' => $days, 'exposure' => $value, 'level' => $days >= (int) $policy['rules']['executive_after_days'] ? 2 : 1];
            }
        }

        return $out;
    }
}
