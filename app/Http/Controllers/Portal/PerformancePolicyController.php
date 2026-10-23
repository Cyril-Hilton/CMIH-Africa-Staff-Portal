<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformancePolicy;
use App\Services\PerformanceAccess;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformancePolicyController extends Controller
{
    public function store(Request $r)
    {
        abort_unless(PerformanceAccess::executive($r->user()), 403);
        $v = $r->validate(['effective_on' => 'required|date|after:today', 'reason' => 'required|string|max:2000', 'rules.budget_tolerance_percent' => 'required|numeric|min:0|max:100', 'rules.executive_after_days' => 'required|integer|min:1|max:365', 'rules.repeated_failure_periods' => 'required|integer|min:2|max:24']);
        DB::transaction(function () use ($v, $r) {
            $p = PerformancePolicy::create($v + ['submitted_by' => $r->user()->id]);
            PerformanceWorkflow::record('performance_policy', $p->id, $r->user()->id, 'propose', [], $p->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Policy proposed for independent executive review.');
    }

    public function review(Request $r, PerformancePolicy $policy)
    {
        abort_unless(PerformanceAccess::executive($r->user()), 403);
        $v = $r->validate(['decision' => 'required|in:approve,return', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $policy, $v) {
            $p = PerformancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->status === 'pending', 409);
            abort_if((int) $p->submitted_by === $r->user()->id, 403, 'Another executive must review this policy.');
            $before = $p->getAttributes();
            if ($v['decision'] === 'approve') {
                abort_unless($p->effective_on->isAfter(today()), 422, 'Choose a future effective date in a new proposal.');
                abort_if(PerformancePolicy::where('effective_key', $p->effective_on->toDateString())->exists(), 409, 'A policy is already approved for that date.');
                $p->effective_key = $p->effective_on->toDateString();
                $p->approved_at = now();
            }
            $p->status = $v['decision'] === 'approve' ? 'approved' : 'returned';
            $p->reviewed_by = $r->user()->id;
            $p->review_note = $v['reason'];
            $p->save();
            PerformanceWorkflow::record('performance_policy', $p->id, $r->user()->id, $v['decision'], $before, $p->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Policy review recorded. Approved rules apply from their effective date.');
    }
}
