<?php

namespace App\Services;

use App\Models\CrmPipelineProject;
use App\Models\CrmWeeklyProgress;
use App\Models\User;

class ClientRelationsPipeline
{
    public static function member(User $user): bool
    {
        return $user->isActive() && ($user->isCvoOrSuperAdmin() || self::staffMember($user));
    }

    public static function staffMember(User $user): bool
    {
        return $user->isActive() && !$user->isServiceAccount() && User::normalizeDepartmentKey($user->department) === 'client_relations';
    }

    /** Historical attribution never reactivates a former staff member or grants access. */
    public static function historicalOwner(User $user): bool
    {
        return User::normalizeDepartmentKey($user->department) === 'client_relations'
            && User::internalStaff()->whereKey($user->id)->exists();
    }

    public static function canReveal(User $user): bool
    {
        return \App\Services\Creative\CreativeAccess::staff($user);
    }

    public function stagePercent(string $stage): ?float
    {
        return config('crm_pipeline.stages.'.$stage.'.probability');
    }

    public function percent(mixed $actual, mixed $target): ?float
    {
        return $actual !== null && $target !== null && (float) $target > 0 ? round(100 * (float) $actual / (float) $target, 2) : null;
    }

    public function financials(CrmPipelineProject $p): array
    {
        $probability = $this->stagePercent($p->stage);

        return ['probability' => $probability, 'weighted_forecast' => $p->proposed_amount !== null && $probability !== null ? round((float) $p->proposed_amount * $probability / 100, 2) : null, 'po_balance' => $p->po_amount !== null && $p->invoiced_amount !== null ? round((float) $p->po_amount - (float) $p->invoiced_amount, 2) : null, 'outstanding' => $p->invoiced_amount !== null && $p->collected_amount !== null ? round((float) $p->invoiced_amount - (float) $p->collected_amount, 2) : null, 'achievement_percent' => $this->percent($p->confirmedRevenue(), $p->target_amount), 'po_percent' => $this->percent($p->po_amount, $p->target_amount), 'billing_percent' => $this->percent($p->invoiced_amount, $p->po_amount), 'collection_percent' => $this->percent($p->collected_amount, $p->invoiced_amount)];
    }

    public function publish(CrmPipelineProject $p): void
    {
        if ($p->source_period_confirmed === false) {
            return;
        }
        $m = $this->financials($p);
        $existing = CrmWeeklyProgress::where('project_id', $p->id)->whereDate('week_start', $p->week_start->toDateString())->first();
        $projection = $existing ?? new CrmWeeklyProgress(['project_id' => $p->id, 'week_start' => $p->week_start->toDateString()]);
        $projection->fill([
            'client_name' => $p->client_name, 'project_name' => $p->project_name, 'owner_id' => $p->owner_id, 'stage' => $p->stage, 'stage_percent' => $m['probability'],
            'achievement_percent' => $m['achievement_percent'], 'po_percent' => $m['po_percent'], 'billing_percent' => $m['billing_percent'], 'collection_percent' => $m['collection_percent'],
            'description' => $p->description, 'shared_bottlenecks' => $p->shared_bottlenecks,
            'shared_next_action' => $p->shared_next_action, 'comments' => $p->comments,
            'has_proposed_amount' => $p->proposed_amount !== null, 'has_po_amount' => $p->po_amount !== null,
            'has_weighted_forecast' => $m['weighted_forecast'] !== null, 'po_state' => $p->po_state ?? 'unknown',
            'public_action' => $p->public_action, 'expected_close_date' => $p->expected_close_date,
        ])->save();
    }

    /** Dedicated allow-list: shared business amounts are visible; contact details and private notes stay excluded. */
    public static function publicRows(?string $search = null)
    {
        return CrmWeeklyProgress::query()->with('owner:id,name')->leftJoin('crm_pipeline_projects as business','business.id','=','crm_weekly_progress.project_id')->select('crm_weekly_progress.*','business.proposed_amount','business.po_amount','business.target_amount','business.actual_amount as reported_actual_amount','business.revenue_state','business.invoiced_amount','business.collected_amount')->selectRaw("CASE WHEN business.revenue_state = 'confirmed' AND business.revenue_confirmed_at IS NOT NULL AND business.revenue_submitted_by IS NOT NULL AND business.revenue_finance_by IS NOT NULL AND business.revenue_submitted_by != business.revenue_finance_by THEN business.actual_amount ELSE NULL END AS actual_amount")->when($search, fn ($q) => $q->where(fn ($w) => $w->where('crm_weekly_progress.client_name', 'like', '%'.$search.'%')->orWhere('crm_weekly_progress.project_name', 'like', '%'.$search.'%')))->orderByDesc('crm_weekly_progress.week_start')->orderBy('crm_weekly_progress.client_name');
    }
}
