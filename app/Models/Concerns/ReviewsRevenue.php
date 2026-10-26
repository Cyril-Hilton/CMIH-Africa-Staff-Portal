<?php

namespace App\Models\Concerns;

use App\Services\RevenueReview;

trait ReviewsRevenue
{
    public static function bootReviewsRevenue(): void
    {
        static::saving(function ($row) {
            $fields = ['actual_amount', 'target_amount', 'client_name', 'owner_id', 'brand', 'project_name', 'week_start', 'period_start', 'period_end', 'source_period_confirmed', 'needs_reconciliation'];
            if (! $row->exists || $row->isDirty($fields)) {
                if ($row->exists && (int) $row->revision === (int) $row->getOriginal('revision')) {
                    $row->revision = (int) $row->revision + 1;
                }
                $actor = auth()->user();
                $canSubmit = $actor && RevenueReview::canSubmit($actor, $row);
                $row->revenue_state = 'awaiting_finance';
                $row->revenue_submitted_by = $canSubmit ? $actor->id : null;
                $row->revenue_submitted_at = $canSubmit ? now() : null;
                $row->revenue_finance_by = null;
                $row->revenue_confirmed_at = null;
                $row->revenue_proposed_amount = null;
                $row->revenue_review_note = null;
            }
        });
        static::saved(function ($row) {
            $changed = $row->wasRecentlyCreated || $row->wasChanged(['actual_amount', 'target_amount', 'client_name', 'owner_id', 'brand', 'project_name', 'week_start', 'period_start', 'period_end', 'source_period_confirmed', 'needs_reconciliation']);
            if ($changed && ($row->actual_amount !== null || $row->getOriginal('actual_amount') !== null)) {
                \App\Services\PerformanceWorkflow::record('crm_revenue_'.($row instanceof \App\Models\CrmPipelineProject ? 'project' : 'target'), $row->id, auth()->id(), $row->wasRecentlyCreated ? 'reported' : 'resubmitted', $row->wasRecentlyCreated ? [] : $row->getOriginal(), $row->getAttributes(), 'Revenue or account details recorded; independent Finance confirmation required.');
            }

            if ($row->revenue_state === 'awaiting_finance' && $row->revenue_submitted_by && $row->actual_amount !== null && ($row->wasRecentlyCreated || $row->wasChanged(['actual_amount', 'client_name', 'owner_id', 'brand', 'project_name', 'week_start', 'period_start', 'period_end', 'target_amount']))) {
                foreach (\App\Models\User::internalStaff()->whereIn('department', \App\Models\User::departmentAliases('finance'))->where('status', 'active')->pluck('id') as $id) {
                    \App\Services\PerformanceWorkflow::notify((int) $id, 'revenue-submitted-'.class_basename($row).'-'.$row->id.'-'.$row->revision, 'Revenue awaiting Finance confirmation', $row->client_name.': Client Relations has reported revenue for review.', route('dashboard', ['weekly_department' => 'finance', 'revenue_view' => 1]).'#staff-weekly-performance');
                }
            }
        });
    }

    public function revenueSubmitter()
    {
        return $this->belongsTo(\App\Models\User::class, 'revenue_submitted_by');
    }

    public function revenueReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'revenue_finance_by');
    }

    public function confirmedRevenue(): ?float
    {
        return $this->revenue_state === 'confirmed' && $this->revenue_confirmed_at && $this->revenue_submitted_by && $this->revenue_finance_by && (int) $this->revenue_submitted_by !== (int) $this->revenue_finance_by && $this->actual_amount !== null ? (float) $this->actual_amount : null;
    }
}
