<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceKpi;
use App\Models\PerformanceTargetPlan;
use App\Models\User;
use App\Services\PerformanceAccess;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerformanceTargetController extends Controller
{
    public function store(Request $request)
    {
        $v = $request->validate(['kpi_id' => 'required|integer|exists:performance_kpis,id', 'scope_type' => 'required|in:individual,department', 'owner_id' => 'required|integer|exists:users,id', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'target' => 'required|numeric|min:0|max:99999999999999', 'weight' => 'required|numeric|min:0.01|max:100', 'client' => 'nullable|string|max:255', 'project' => 'nullable|string|max:255', 'rationale' => 'required|string|max:3000']);
        $kpi = PerformanceKpi::with('objective')->findOrFail($v['kpi_id']);
        abort_unless(PerformanceAccess::manages($request->user(), $kpi->department), 403);
        $owner = User::whereKey($v['owner_id'])->where('status', 'active')->firstOrFail();
        abort_unless(PerformanceAccess::department($owner) === $kpi->department, 422, 'The accountable owner must belong to this department.');
        $reviewer = User::whereKey($kpi->reviewer_id)->where('status', 'active')->firstOrFail();
        if (! PerformanceAccess::manages($reviewer, $kpi->department)) {
            throw ValidationException::withMessages(['kpi_id' => 'This KPI needs an active departmental manager as target reviewer.']);
        }
        if ((int) $kpi->reviewer_id === $request->user()->id || (int) $kpi->reviewer_id === $owner->id) {
            throw ValidationException::withMessages(['kpi_id' => 'The KPI reviewer must be different from the target proposer and accountable owner.']);
        }
        if ($v['period_start'] < $kpi->objective->starts_on->toDateString() || $v['period_end'] > $kpi->objective->ends_on->toDateString()) {
            throw ValidationException::withMessages(['period_start' => 'The target period must fall within the objective period.']);
        }
        if (($kpi->effective_from && $v['period_start'] < $kpi->effective_from->toDateString()) || ($kpi->effective_until && $v['period_end'] > $kpi->effective_until->toDateString())) {
            throw ValidationException::withMessages(['kpi_id' => 'Choose the KPI version effective for the entire target period.']);
        }
        $v['scope_key'] = hash('sha256', implode('|', [$kpi->id, $v['scope_type'], $owner->id, $v['period_start'], $v['period_end'], $v['client'] ?? '', $v['project'] ?? '', (string) \Illuminate\Support\Str::uuid()]));
        DB::transaction(function () use ($v, $kpi, $request) {
            // Lock the definition to serialise competing overlapping target proposals.
            $lockedKpi = PerformanceKpi::whereKey($kpi->id)->lockForUpdate()->firstOrFail();
            if (($lockedKpi->effective_from && $v['period_start'] < $lockedKpi->effective_from->toDateString()) || ($lockedKpi->effective_until && $v['period_end'] > $lockedKpi->effective_until->toDateString())) {
                throw ValidationException::withMessages(['kpi_id' => 'The KPI version changed. Refresh and choose the effective version.']);
            }
            $existing = PerformanceTargetPlan::whereNull('withdrawn_at')->where('kpi_id', $kpi->id)->where('scope_type', $v['scope_type'])->when($v['scope_type'] === 'individual', fn ($q) => $q->where('owner_id', $v['owner_id']))->where('client', $v['client'] ?? null)->where('project', $v['project'] ?? null)->whereDate('period_start', '<=', $v['period_end'])->whereDate('period_end', '>=', $v['period_start'])->exists();
            if ($existing) {
                throw ValidationException::withMessages(['period_start' => 'This scope already has an overlapping target. Preserve the existing baseline rather than creating a duplicate.']);
            }
            $plan = PerformanceTargetPlan::create($v + ['department' => $kpi->department, 'reviewer_id' => $kpi->reviewer_id, 'submitted_by' => $request->user()->id]);
            PerformanceWorkflow::record('target_plan', $plan->id, $request->user()->id, 'propose', [], $plan->getAttributes(), $v['rationale']);
            PerformanceWorkflow::notify($kpi->reviewer_id, 'target-plan:'.$plan->id, 'KPI target approval requested', $kpi->name);
        });

        return back()->with('status', 'Target proposed. It becomes a scoring baseline after independent approval.');
    }

    public function amend(Request $request, PerformanceTargetPlan $plan)
    {
        abort_unless(PerformanceAccess::manages($request->user(), $plan->department), 403);
        abort_if((int) $plan->reviewer_id === $request->user()->id, 403, 'A separate proposer is required.');
        $v = $request->validate(['target' => 'required|numeric|min:0|max:99999999999999', 'weight' => 'required|numeric|min:0.01|max:100', 'reason' => 'required|string|max:3000']);
        DB::transaction(function () use ($request, $plan, $v) {
            $original = PerformanceTargetPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_unless($original->approved_at && ! $original->withdrawn_at, 409, 'Only an active approved baseline can be amended.');
            abort_if($original->observation()->exists(), 409, 'Results already exist. Keep this baseline and set a target for the next period.');
            abort_if(PerformanceTargetPlan::where('amends_plan_id', $original->id)->whereNull('withdrawn_at')->exists(), 409, 'An amendment is already awaiting review.');
            $replacement = $original->replicate(['approved_at', 'approved_by', 'review_notes', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason']);
            $replacement->fill(['amends_plan_id' => $original->id, 'target' => $v['target'], 'weight' => $v['weight'], 'rationale' => $v['reason'], 'submitted_by' => $request->user()->id, 'scope_key' => hash('sha256', (string) \Illuminate\Support\Str::uuid())])->save();
            PerformanceWorkflow::record('target_plan', $replacement->id, $request->user()->id, 'propose_amendment', $original->getAttributes(), $replacement->getAttributes(), $v['reason']);
            PerformanceWorkflow::notify($replacement->reviewer_id, 'target-amendment:'.$replacement->id, 'Target amendment approval requested', $replacement->kpi->name);
        });

        return back()->with('status', 'Amendment submitted. The original target remains active until independent approval.');
    }

    public function approve(Request $request, PerformanceTargetPlan $plan)
    {
        $v = $request->validate(['reason' => 'required|string|max:2000']);
        abort_unless(PerformanceAccess::manages($request->user(), $plan->department) && (int) $plan->reviewer_id === $request->user()->id, 403);
        abort_if(in_array($request->user()->id, [(int) $plan->owner_id, (int) $plan->submitted_by], true), 403);
        DB::transaction(function () use ($request, $plan, $v) {
            $item = PerformanceTargetPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_if($item->approved_at || $item->withdrawn_at, 409);
            if ($item->amends_plan_id) {
                $original = PerformanceTargetPlan::whereKey($item->amends_plan_id)->lockForUpdate()->firstOrFail();
                abort_unless($original->approved_at && ! $original->withdrawn_at, 409);
                abort_if($original->observation()->exists(), 409, 'Results were recorded while this amendment was pending. Keep the original baseline.');
                $old = $original->getAttributes();
                $original->update(['withdrawn_at' => now(), 'withdrawn_by' => $request->user()->id, 'withdrawal_reason' => 'Superseded by independently approved amendment #'.$item->id]);
                PerformanceWorkflow::record('target_plan', $original->id, $request->user()->id, 'supersede', $old, $original->getAttributes(), $v['reason']);
            }
            $before = $item->getAttributes();
            $item->update(['approved_at' => now(), 'approved_by' => $request->user()->id, 'review_notes' => $v['reason']]);
            PerformanceWorkflow::record('target_plan', $item->id, $request->user()->id, 'approve', $before, $item->getAttributes(), $v['reason']);
            PerformanceWorkflow::notify($item->owner_id, 'target-approved:'.$item->id, 'Your KPI target was approved', $item->kpi->name);
        });

        return back()->with('status', 'KPI target approved. Results will be judged against this baseline.');
    }

    public function withdraw(Request $request, PerformanceTargetPlan $plan)
    {
        $v = $request->validate(['reason' => 'required|string|max:2000']);
        abort_unless(PerformanceAccess::manages($request->user(), $plan->department) && in_array($request->user()->id, [(int) $plan->reviewer_id, (int) $plan->submitted_by], true), 403);
        DB::transaction(function () use ($request, $plan, $v) {
            $item = PerformanceTargetPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_if($item->approved_at || $item->withdrawn_at, 409, 'An approved target cannot be removed or silently changed.');
            $before = $item->getAttributes();
            $item->update(['withdrawn_at' => now(), 'withdrawn_by' => $request->user()->id, 'withdrawal_reason' => $v['reason']]);
            PerformanceWorkflow::record('target_plan', $item->id, $request->user()->id, 'withdraw', $before, $item->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Unapproved target returned / withdrawn. Its history is retained; propose the corrected target.');
    }
}
