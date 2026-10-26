<?php

namespace App\Services;

use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevenueReview
{
    public static function finance(User $u): bool
    {
        return $u->isActive() && User::normalizeDepartmentKey($u->department) === 'finance' && \App\Services\Creative\CreativeAccess::staff($u);
    }

    public static function canSubmit(User $u, $row): bool
    {
        return ClientRelationsPipeline::member($u) && ($u->isLineManager() || (int) $row->owner_id === $u->id);
    }

    public static function label($row): string
    {
        return $row->actual_amount === null ? 'Revenue Not Reported' : ($row->confirmedRevenue() !== null ? 'Finance Confirmed' : ($row->revenue_state === 'awaiting_cr' ? 'Awaiting Client Relations Confirmation' : 'Awaiting Finance Confirmation'));
    }

    public function act(string $type, int $id, User $actor, array $v): void
    {
        $class = $type === 'project' ? CrmPipelineProject::class : CrmAccountTarget::class;
        DB::transaction(function () use ($class, $id, $type, $actor, $v) {
            $row = $class::lockForUpdate()->findOrFail($id);
            abort_unless((int) $row->revision === (int) $v['revision'], 409, 'This revenue entry changed. Refresh before reviewing.');
            abort_if($row->actual_amount === null, 422, 'Enter the reported revenue before requesting confirmation.');
            abort_if($type === 'project' ? ! $row->source_period_confirmed : $row->needs_reconciliation, 422, 'Confirm the source reporting period first.');
            $before = $row->getAttributes();
            $action = $v['action'];
            if (in_array($action, ['confirm', 'correct'])) {
                abort_unless(self::finance($actor), 403, 'Finance must independently review revenue.');
                abort_unless($row->revenue_state === 'awaiting_finance', 409, 'This entry is not awaiting Finance.');
                abort_if((int) $row->revenue_submitted_by === $actor->id, 403, 'A separate person must review this revenue.');
                $row->revenue_finance_by = $actor->id;
                if ($action === 'correct') {
                    abort_unless(isset($v['amount']), 422, 'Enter the corrected revenue amount.');
                    abort_if(number_format((float) $v['amount'], 2, '.', '') === number_format((float) $row->actual_amount, 2, '.', ''), 422, 'Use Confirm when the reported amount is correct.');
                    $row->revenue_proposed_amount = $v['amount'];
                    $row->revenue_state = 'awaiting_cr';
                } else {
                    $row->revenue_state = $row->revenue_submitted_by ? 'confirmed' : 'awaiting_cr';
                }
            } else {
                abort_unless(self::canSubmit($actor, $row), 403, 'The account owner or Client Relations manager must confirm.');
                abort_if((int) $row->revenue_finance_by === $actor->id, 403, 'Client Relations and Finance confirmations must come from separate people.');
                if ($action === 'submit') {
                    abort_unless($row->revenue_state === 'awaiting_finance', 409, 'This entry has already been submitted or reviewed.');
                    abort_if($row->revenue_submitted_by !== null, 409, 'This entry is already awaiting Finance.');
                } else {
                    abort_unless($row->revenue_state === 'awaiting_cr', 409, 'There is no Finance decision to respond to.');
                    if ($action === 'accept') {
                        if ($row->revenue_proposed_amount !== null) {
                            $row->actual_amount = $row->revenue_proposed_amount;
                        }
                        $row->revenue_state = 'confirmed';
                    } else {
                        $row->revenue_state = 'awaiting_finance';
                        $row->revenue_finance_by = null;
                    }
                }
                $row->revenue_submitted_by = $actor->id;
                $row->revenue_submitted_at = now();
            }
            $row->revenue_review_note = $v['reason'];
            $row->revenue_confirmed_at = $row->revenue_state === 'confirmed' ? now() : null;
            if ($row->revenue_state !== 'awaiting_cr') {
                $row->revenue_proposed_amount = null;
            }
            $row->revision++;
            $row->saveQuietly();
            PerformanceWorkflow::record('crm_revenue_'.$type, $row->id, $actor->id, $action, $before, $row->getAttributes(), $v['reason']);
            if ($row instanceof CrmPipelineProject) {
                app(ClientRelationsPipeline::class)->publish($row);
            }
            $recipients = in_array($action, ['submit', 'reject']) ? User::internalStaff()->whereIn('department', User::departmentAliases('finance'))->where('status', 'active')->pluck('id')->all() : array_filter([$row->owner_id, $row->revenue_submitted_by]);
            foreach (array_unique($recipients) as $recipient) {
                if ((int) $recipient !== $actor->id) {
                    PerformanceWorkflow::notify((int) $recipient, 'revenue-'.$type.'-'.$id.'-'.$row->revision, 'Revenue review updated', ($row->client_name ?? 'Account').': '.self::label($row), route('dashboard', ['weekly_department' => in_array($action, ['submit', 'reject']) ? 'finance' : 'client_relations', 'revenue_view' => 1]).'#staff-weekly-performance');
                }
            }
        });
    }

    /** A safe copy for analytics: reported revenue is retained separately and never silently counted. */
    public static function projection($row)
    {
        $copy = clone $row;
        $copy->setAttribute('revenue_label',self::label($row));
        $copy->setAttribute('reported_actual_amount',$row->reported_actual_amount ?? $row->actual_amount);
        $copy->setAttribute('actual_amount',$row->confirmedRevenue());

        return $copy;
    }
}
