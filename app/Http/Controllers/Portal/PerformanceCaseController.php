<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceCase;
use App\Models\PerformanceKpi;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceAccess as Access;
use App\Services\PerformanceWorkflow as Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PerformanceCaseController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['department' => ['nullable', Rule::in(array_keys(config('performance.departments')))], 'kind' => 'nullable|in:issue,risk,action,decision', 'status' => 'nullable|in:open,in_progress,review,closed', 'overdue' => 'nullable|in:1']);
        $q = Access::scope(PerformanceCase::query(), $r->user(), 'owner_id');
        foreach (['department', 'kind', 'status'] as $key) {
            if (! empty($v[$key])) {
                $q->where($key, $v[$key]);
            }
        }
        if (! empty($v['overdue'])) {
            $q->where('status', '!=', 'closed')->whereDate('due_on', '<', today());
        }
        $cases = $q->with(['owner', 'reviewer', 'parent'])->orderBy('due_on')->paginate(20)->withQueryString();
        $staff = User::internalStaff()->where('status', 'active')->when(! Access::executive($r->user()), fn ($q) => $q->whereIn('department', User::departmentAliases(Access::department($r->user()))))->orderBy('name')->pluck('name', 'id');
        $departments = collect(config('performance.departments'))->filter(fn ($label, $key) => Access::manages($r->user(), $key));

        return view('portal.performance.cases', compact('cases', 'staff', 'departments'));
    }

    public function store(Request $r)
    {
        $v = $r->validate(['department' => ['required', Rule::in(array_keys(config('performance.departments')))], 'kind' => 'required|in:issue,risk,action,decision', 'title' => 'required|string|max:255', 'description' => 'required|string|max:5000', 'owner_id' => 'required|integer|exists:users,id', 'reviewer_id' => 'required|integer|exists:users,id|different:owner_id', 'parent_id' => 'nullable|integer|exists:performance_cases,id', 'commitment_id' => 'nullable|integer|exists:weekly_consolidated_items,id', 'kpi_id' => 'nullable|integer|exists:performance_kpis,id', 'root_cause' => ['nullable', Rule::in(config('performance.root_causes'))], 'business_impact' => 'required|string|max:5000', 'due_on' => 'required|date', 'review_on' => 'nullable|date', 'priority' => 'required|in:Critical,High,Medium,Low']);
        abort_unless(Access::manages($r->user(), $v['department']), 403);
        foreach (['owner_id', 'reviewer_id'] as $field) {
            $person = User::findOrFail($v[$field]);
            abort_unless($person->isActive() && (Access::department($person) === ($v['department'] === 'admin_drivers' ? 'hr_admin' : $v['department']) || Access::executive($person)), 422, 'Choose an active owner and reviewer in this function.');
        }
        $reviewer = User::findOrFail($v['reviewer_id']);
        abort_unless(Access::manages($reviewer, $v['department']), 422, 'Reviewer must be an authorised department manager or executive.');
        if (! empty($v['parent_id'])) {
            $parent = PerformanceCase::findOrFail($v['parent_id']);
            abort_unless($parent->department === $v['department'] && $parent->status !== 'closed', 422);
        }
        if (! empty($v['commitment_id'])) {
            $item = WeeklyConsolidatedItem::findOrFail($v['commitment_id']);
            abort_unless(Access::view($r->user(), $item) && $item->department === $v['department'], 403);
        }
        if (! empty($v['kpi_id'])) {
            abort_unless(PerformanceKpi::findOrFail($v['kpi_id'])->department === $v['department'], 422);
        }
        DB::transaction(function () use ($v, $r) {
            $i = PerformanceCase::create($v + ['created_by' => $r->user()->id]);
            Workflow::record('performance_case', $i->id, $r->user()->id, 'create', [], $i->getAttributes(), 'Accountability case opened');
        });

        return back()->with('status', 'Accountability record created.');
    }

    public function transition(Request $r, PerformanceCase $case)
    {
        $v = $r->validate(['action' => 'required|in:start,submit,return,close,reopen', 'revision' => 'required|integer', 'reason' => 'required|string|max:5000', 'evidence_url' => 'nullable|url:http,https|max:2000']);
        abort_unless(Access::executive($r->user()) || Access::manages($r->user(), $case->department) || (int) $case->owner_id === $r->user()->id, 403);
        DB::transaction(function () use ($r, $case, $v) {
            $i = PerformanceCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            $before = $i->getAttributes();
            if (in_array($v['action'], ['start', 'submit'])) {
                abort_unless((int) $i->owner_id === $r->user()->id, 403);
                abort_unless(in_array($i->status, ['open', 'in_progress']), 422);
                $i->status = $v['action'] === 'start' ? 'in_progress' : 'review';
                if ($v['action'] === 'submit') {
                    abort_unless(! empty($v['evidence_url']), 422, 'Provide the supporting evidence link.');
                    $i->resolution = $v['reason'];
                    $i->evidence_url = $v['evidence_url'];
                    $i->resolved_at = now();
                }
            } else {
                abort_unless((int) $i->reviewer_id === $r->user()->id && (int) $i->owner_id !== $r->user()->id, 403);
                if ($v['action'] === 'reopen') {
                    abort_unless($i->status === 'closed', 422);
                    $i->status = 'in_progress';
                    $i->closed_at = null;
                } else {
                    abort_unless($i->status === 'review', 422);
                    if ($v['action'] === 'close') {
                        abort_if($i->children()->where('status', '!=', 'closed')->exists(), 422, 'Close the linked corrective actions first.');
                        $i->status = 'closed';
                        $i->closed_at = now();
                    } else {
                        $i->status = 'in_progress';
                    }
                }
            }
            $i->revision++;
            $i->save();
            Workflow::record('performance_case', $i->id, $r->user()->id, $v['action'], $before, $i->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Accountability workflow updated.');
    }
}
