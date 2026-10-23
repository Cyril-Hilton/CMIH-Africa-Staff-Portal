<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceDependencyRequest;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceAccess;
use App\Services\PerformanceDependencies;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceDependencyRequestController extends Controller
{
    public function store(Request $r)
    {
        $v = $r->validate(['upstream_id' => 'required|integer|exists:weekly_consolidated_items,id', 'downstream_id' => 'required|integer|different:upstream_id|exists:weekly_consolidated_items,id', 'impact' => 'required|string|max:5000']);
        $up = WeeklyConsolidatedItem::findOrFail($v['upstream_id']);
        $down = WeeklyConsolidatedItem::findOrFail($v['downstream_id']);
        abort_unless($up->department !== $down->department, 422, 'Use the existing link control for same-department dependencies.');
        abort_unless(PerformanceAccess::manages($r->user(), $up->department) || PerformanceAccess::manages($r->user(), $down->department), 403);
        DB::transaction(function () use ($v, $r) {
            $request = PerformanceDependencyRequest::firstOrCreate(['upstream_id' => $v['upstream_id'], 'downstream_id' => $v['downstream_id']], ['impact' => $v['impact'], 'submitted_by' => $r->user()->id]);
            $created = $request->wasRecentlyCreated;
            $request = PerformanceDependencyRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status === 'returned') {
                $before = $request->getAttributes();
                $request->update(['impact' => $v['impact'], 'submitted_by' => $r->user()->id, 'status' => 'pending', 'reviewed_by' => null, 'review_note' => null]);
                PerformanceWorkflow::record('dependency_request', $request->id, $r->user()->id, 'resubmit', $before, $request->getAttributes(), $v['impact']);
            }
            if ($created) {
                PerformanceWorkflow::record('dependency_request', $request->id, $r->user()->id, 'propose', [], $request->getAttributes(), $v['impact']);
            }
        });

        return back()->with('status', 'Dependency request recorded. The other department must confirm it.');
    }

    public function review(Request $r, PerformanceDependencyRequest $dependencyRequest)
    {
        $v = $r->validate(['decision' => 'required|in:approve,return', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $dependencyRequest, $v) {
            $request = PerformanceDependencyRequest::whereKey($dependencyRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->status === 'pending', 409);
            abort_if((int) $request->submitted_by === $r->user()->id, 403);
            $proposer = \App\Models\User::findOrFail($request->submitted_by);
            $up = $request->upstream;
            $down = $request->downstream;
            $other = PerformanceAccess::manages($proposer, $up->department) ? $down->department : $up->department;
            abort_unless(PerformanceAccess::manages($r->user(), $other), 403, 'The other department head or an executive must review this request.');
            $before = $request->getAttributes();
            if ($v['decision'] === 'approve') {
                app(PerformanceDependencies::class)->link($request->only(['upstream_id', 'downstream_id', 'impact']), $r->user());
            }
            $request->update(['status' => $v['decision'] === 'approve' ? 'approved' : 'returned', 'reviewed_by' => $r->user()->id, 'review_note' => $v['reason']]);
            PerformanceWorkflow::record('dependency_request', $request->id, $r->user()->id, $v['decision'], $before, $request->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Dependency review recorded.');
    }
}
