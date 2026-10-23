<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceKpi;
use App\Models\PerformanceKpiRevision;
use App\Services\PerformanceAccess;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceKpiRevisionController extends Controller
{
    public function store(Request $r, PerformanceKpi $kpi)
    {
        abort_unless(PerformanceAccess::manages($r->user(), $kpi->department), 403);
        abort_if((int) $kpi->reviewer_id === $r->user()->id, 403, 'A separate proposer is required.');
        $v = $r->validate([
            'effective_on' => 'required|date|after:today', 'reason' => 'required|string|max:3000',
            'definition' => 'required|string|max:5000', 'formula' => 'required|string|max:2000',
            'source' => 'required|string|max:2000', 'green_threshold' => 'required|numeric|min:0|max:1000',
            'amber_threshold' => 'required|numeric|min:0|lt:green_threshold',
            'required_action' => 'required|string|max:5000', 'escalation_days' => 'required|integer|min:1|max:365',
        ]);
        abort_unless($kpi->objective && $v['effective_on'] <= $kpi->objective->ends_on->toDateString(), 422, 'The effective date must fall within the objective period.');
        abort_if($v['effective_on'] < $kpi->objective->starts_on->toDateString() || ($kpi->effective_from && $v['effective_on'] <= $kpi->effective_from->toDateString()), 422, 'Choose a later date within the objective period.');
        DB::transaction(function () use ($r, $kpi, $v) {
            $locked = PerformanceKpi::whereKey($kpi->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->effective_until || PerformanceKpiRevision::where('kpi_id', $kpi->id)->where('status', 'pending')->exists(), 409, 'This definition already has a successor or a pending change.');
            $revision = PerformanceKpiRevision::create(['kpi_id' => $kpi->id, 'submitted_by' => $r->user()->id, 'reviewer_id' => $kpi->reviewer_id, 'effective_on' => $v['effective_on'], 'reason' => $v['reason'], 'changes' => collect($v)->except(['effective_on', 'reason'])->all()]);
            PerformanceWorkflow::record('kpi_revision', $revision->id, $r->user()->id, 'propose', [], $revision->getAttributes(), $v['reason']);
            PerformanceWorkflow::notify($kpi->reviewer_id, 'kpi-revision:'.$revision->id, 'KPI definition review requested', $kpi->name);
        });

        return back()->with('status', 'New definition submitted for independent review. The current version remains unchanged.');
    }

    public function review(Request $r, PerformanceKpiRevision $revision)
    {
        $v = $r->validate(['decision' => 'required|in:approve,return', 'reason' => 'required|string|max:2000']);
        abort_unless((int) $revision->reviewer_id === $r->user()->id && PerformanceAccess::manages($r->user(), $revision->kpi->department), 403);
        abort_if(in_array($r->user()->id, [(int) $revision->submitted_by, (int) $revision->kpi->owner_id], true), 403);
        DB::transaction(function () use ($r, $revision, $v) {
            $item = PerformanceKpiRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $old = PerformanceKpi::whereKey($item->kpi_id)->lockForUpdate()->firstOrFail();
            abort_unless($item->status === 'pending', 409);
            $before = $item->getAttributes();
            if ($v['decision'] === 'approve') {
                abort_if($item->effective_on->lte(today()) || $old->effective_until, 409, 'The effective date has passed or the definition was replaced. Return and resubmit.');
                $next = $old->replicate();
                $next->fill($item->changes);
                $next->fill(['supersedes_kpi_id' => $old->id, 'version' => $old->version + 1, 'effective_from' => $item->effective_on, 'effective_until' => null])->save();
                $old->update(['effective_until' => $item->effective_on->copy()->subDay()]);
                $item->new_kpi_id = $next->id;
                PerformanceWorkflow::record('kpi', $next->id, $r->user()->id, 'approve_version', $old->getAttributes(), $next->getAttributes(), $v['reason']);
            }
            $item->fill(['status' => $v['decision'] === 'approve' ? 'approved' : 'returned', 'review_notes' => $v['reason'], 'reviewed_at' => now()])->save();
            PerformanceWorkflow::record('kpi_revision', $item->id, $r->user()->id, $v['decision'], $before, $item->getAttributes(), $v['reason']);
            PerformanceWorkflow::notify($item->submitted_by, 'kpi-reviewed:'.$item->id, 'KPI definition review completed', $old->name.' · '.$item->status.' · '.$v['reason']);
        });

        return back()->with('status', 'Definition review recorded. Historical results retain their original version.');
    }
}
