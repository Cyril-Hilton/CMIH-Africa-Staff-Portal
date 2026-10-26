<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceDependency;
use App\Models\PerformanceEvent;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceKpi;
use App\Models\PerformanceObjective;
use App\Models\PerformanceObservation;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\PerformanceAccess as Access;
use App\Services\PerformanceHubReport;
use App\Services\PerformanceMath as Math;
use App\Services\PerformanceWorkflow as Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PerformanceHubController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate(['department' => ['nullable', Rule::in(array_keys(config('performance.departments')))], 'owner' => ['nullable', 'integer'], 'team_lead_id' => ['nullable', 'integer', 'exists:users,id'], 'team' => ['nullable', 'string', 'max:255'], 'client' => ['nullable', 'string', 'max:255'], 'project' => ['nullable', 'string', 'max:255'], 'kpi' => ['nullable', 'integer'], 'status' => ['nullable', 'string', 'max:30'], 'priority' => ['nullable', 'in:Critical,High,Medium,Low'], 'due_from' => ['nullable', 'date_format:Y-m-d'], 'due_to' => ['nullable', 'date_format:Y-m-d', ...($r->filled('due_from') ? ['after_or_equal:due_from'] : [])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($r->filled('from') ? ['after_or_equal:from'] : [])], 'overdue' => ['nullable', 'in:1'], 'view' => ['nullable', 'in:summary,commitments,kpis,exceptions,trends,dependencies,meeting,dictionary,registers']]);
        $user = $r->user();
        $department = $filters['department'] ?? (Access::executive($user) ? null : Access::department($user));
        if ($department === 'client_relations') {
            abort_unless(\App\Services\ClientRelationsPipeline::member($user), 403);
        }
        if ($department) {
            abort_unless(Access::executive($user) || $department === Access::department($user) || ($department === 'admin_drivers' && Access::department($user) === 'hr_admin'), 403);
        }
        $teamMemberIds = null;
        if (! empty($filters['team_lead_id'])) {
            $teamMemberIds = User::internalStaff()->where(fn ($q) => $q->whereKey($filters['team_lead_id'])->orWhere('line_manager_id', $filters['team_lead_id']))->pluck('id');
        }
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();
        $q = Access::scope(WeeklyConsolidatedItem::query(), $user)->when($department, fn ($q) => $q->where('department', $department))->whereDate('week_start', '>=', $from)->whereDate('week_start', '<=', $to);
        foreach (['due_from' => '>=', 'due_to' => '<='] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $q->whereDate('week_end', $operator, $filters[$filter]);
            }
        }
        if ($teamMemberIds !== null) {
            $q->whereIn('lead_staff_id', $teamMemberIds);
        }
        foreach (['owner' => 'lead_staff_id', 'team' => 'team', 'client' => 'client_name', 'project' => 'campaign_name', 'kpi' => 'performance_kpi_id', 'status' => 'workflow_state', 'priority' => 'priority'] as $input => $column) {
            if (! empty($filters[$input])) {
                $q->where($column, $filters[$input]);
            }
        }
        if (! empty($filters['overdue'])) {
            $q->whereNotIn('workflow_state', ['verified', 'closed'])->whereDate('week_end', '<', today());
        }
        $items = (clone $q)->with(['leadStaff', 'performanceKpi', 'actionOwner'])->orderBy('week_end')->get();
        $records = (clone $q)->with(['leadStaff', 'performanceKpi', 'actionOwner'])->orderBy('week_end')->paginate(15)->withQueryString();
        $kpis = PerformanceKpi::query()->when(! \App\Services\ClientRelationsPipeline::member($user), fn ($q) => $q->where('department', '!=', 'client_relations'))->when(! Access::executive($user), fn ($q) => $q->whereIn('department', [Access::department($user), Access::department($user) === 'hr_admin' ? 'admin_drivers' : Access::department($user)]))->when($department, fn ($q) => $q->where('department', $department))->with('objective')->get();
        $observations = PerformanceObservation::with(['kpi', 'owner'])->whereIn('kpi_id', $kpis->pluck('id'))->when(! Access::executive($user) && ! $user->isLineManager(), fn ($q) => $q->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)->orWhereHas('kpi', fn ($k) => $k->where('reviewer_id', $user->id));
        }))->whereDate('period_start', '>=', \Illuminate\Support\Carbon::parse($from)->startOfYear()->subYear()->toDateString())->whereDate('period_end', '<=', $to)->get();
        $registers = Access::scope(PerformanceRegister::query(), $user, 'owner_id')->when($department, fn ($q) => $q->where('department', $department))->whereDate('period_start', '>=', $from)->whereDate('period_end', '<=', $to)->latest()->get();
        $objectives = PerformanceObjective::where(function ($q) use ($user) {
            if (! \App\Services\ClientRelationsPipeline::member($user)) {
                $q->where('department', '!=', 'client_relations')->orWhereNull('department');
            }
        })->when(! Access::executive($user), fn ($q) => $q->whereIn('department', [Access::department($user), Access::department($user) === 'hr_admin' ? 'admin_drivers' : Access::department($user)]))->get();
        $visibleIds = Access::scope(WeeklyConsolidatedItem::query(), $user)->pluck('id');
        $dependencies = PerformanceDependency::with(['upstream.leadStaff', 'downstream.leadStaff'])->where(function ($query) use ($visibleIds, $user) {
            $query->whereIn('upstream_id', $visibleIds);
            if ($user->isLineManager()) {
                $query->orWhereIn('downstream_id', $visibleIds);
            } else {
                $query->whereIn('downstream_id', $visibleIds);
            }
        })->get();
        $historicalStaffIds = $items->pluck('lead_staff_id')->merge($observations->pluck('owner_id'))->merge($registers->pluck('owner_id'))->filter()->unique();
        $staff = User::internalStaff()->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $historicalStaffIds))->when(! Access::executive($user) && ! $user->isLineManager(), fn ($q) => $q->whereKey($user->id))->when($department, fn ($q) => $q->whereIn('department', User::departmentAliases($department)))->when(! empty($filters['owner']), fn ($q) => $q->whereKey($filters['owner']))->when(! Access::executive($user), fn ($q) => $q->whereIn('department', User::departmentAliases(Access::department($user))))->orderBy('name')->get();
        $targetPlans = \App\Models\PerformanceTargetPlan::with(['kpi', 'owner', 'observation'])->whereIn('kpi_id', $kpis->pluck('id'))->whereDate('period_start', '>=', $from)->whereDate('period_end', '<=', $to)->when(! Access::executive($user) && ! $user->isLineManager(), fn ($q) => $q->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('reviewer_id', $user->id)))->get();
        $kpiRevisions = \App\Models\PerformanceKpiRevision::with('kpi')->whereIn('kpi_id', $kpis->pluck('id'))->orderByDesc('id')->get();
        foreach (['owner' => 'owner_id', 'client' => 'client', 'project' => 'project'] as $input => $field) {
            if (! empty($filters[$input])) {
                $observations = $observations->where($field, $filters[$input]);
                $targetPlans = $targetPlans->where($field, $filters[$input]);
                $registers = $registers->where($field, $filters[$input]);
            }
        }
        if (! empty($filters['kpi'])) {
            $kpis = $kpis->where('id', (int) $filters['kpi']);
            $observations = $observations->where('kpi_id', (int) $filters['kpi']);
            $targetPlans = $targetPlans->where('kpi_id', (int) $filters['kpi']);
        }
        $leadIds = User::whereIn('id', $staff->pluck('id'))->whereHas('subordinates')->pluck('id')->merge($staff->pluck('line_manager_id')->filter())->unique();
        $teamLeads = User::whereIn('id', $leadIds)->orderBy('name')->get(['id', 'name']);
        if ($teamMemberIds !== null) {
            $observations = $observations->whereIn('owner_id', $teamMemberIds);
            $targetPlans = $targetPlans->whereIn('owner_id', $teamMemberIds);
            $registers = $registers->whereIn('owner_id', $teamMemberIds);
            $staff = $staff->whereIn('id', $teamMemberIds);
        }
        $summary = app(PerformanceHubReport::class)->build($items, $observations, $registers, $from, $to, $kpis->filter(fn ($k) => $k->objective && $k->objective->starts_on->toDateString() <= $to && $k->objective->ends_on->toDateString() >= $from && (! $k->effective_from || $k->effective_from->toDateString() <= $to) && (! $k->effective_until || $k->effective_until->toDateString() >= $from)), $targetPlans);
        $catalog = collect(app(\App\Services\DepartmentMetricCalculator::class)->calculate($registers))->when($department, fn ($c) => $c->where('department', $department));
        $teamScore = app(\App\Services\StaffPerformanceScore::class)->team($staff, \Illuminate\Support\Carbon::parse($from), \Illuminate\Support\Carbon::parse($to));
        $reportingSchemas = $department ? config('department_reporting.'.$department, []) : [];
        $registerKind = $r->query('register_kind', $department === 'operations_projects' ? 'project' : array_key_first($reportingSchemas));
        abort_if($registerKind !== null && ! array_key_exists($registerKind, $reportingSchemas), 422, 'Choose a valid reporting table for this department.');
        $sourceExceptions = app(\App\Services\PerformanceExceptions::class)->build($registers);
        $openCases = Access::scope(\App\Models\PerformanceCase::query(), $user, 'owner_id')->when($department, fn ($q) => $q->where('department', $department))->where('status', '!=', 'closed')->with('owner')->orderBy('due_on')->get();
        $dependencyOptions = collect();
        $dependencyRequests = collect();
        if ($user->isLineManager()) {
            $dependencyOptions = WeeklyConsolidatedItem::where('workflow_state', '!=', 'legacy')->whereBetween('week_start', [$from, $to])->orderByDesc('id')->limit(500)->get(['id', 'department']);
            $dependencyRequests = \App\Models\PerformanceDependencyRequest::with(['upstream:id,department', 'downstream:id,department'])->when(! Access::executive($user), fn ($q) => $q->where(fn ($w) => $w->whereHas('upstream', fn ($u) => $u->where('department', Access::department($user)))->orWhereHas('downstream', fn ($d) => $d->where('department', Access::department($user)))))->latest()->get();
        }
        $executiveSummary = Access::executive($user) ? app(\App\Services\ExecutivePerformanceSummary::class)->build($registers, $openCases) : [];
        $sourceTasks = collect();
        $sourceBudgets = collect();
        if ($department && in_array($department, ['creatives', 'operations_projects', 'brands_marketing']) && Access::manages($user, $department)) {
            $sourceTasks = \App\Models\Task::whereIn('department', User::departmentAliases($department))->orderByDesc('updated_at')->limit(200)->get();
            $sourceBudgets = \App\Models\ProjectBudget::whereIn('task_id', $sourceTasks->pluck('id'))->where('status', 'CVO Approved')->get()->filter(fn ($budget) => $budget->canView($user));
        }
        $tab = $filters['view'] ?? 'summary';

        return view('portal.performance.index', compact('user', 'department', 'from', 'to', 'items', 'records', 'kpis', 'observations', 'registers', 'objectives', 'dependencies', 'staff', 'summary', 'tab', 'catalog', 'teamScore', 'targetPlans', 'reportingSchemas', 'registerKind', 'sourceExceptions', 'openCases', 'kpiRevisions', 'sourceTasks', 'sourceBudgets', 'executiveSummary', 'dependencyOptions', 'dependencyRequests', 'teamLeads'));
    }

    public function export(Request $r, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx', 'json']), 404);
        $data = $this->index($r)->getData();
        $summary = $data['summary'];
        $rows = [['CMIH Performance & Accountability'], ['Reporting period', $data['from'], $data['to']], ['Function', $data['department'] ?? 'Enterprise'], ['Generated', now()->toIso8601String()]];
        foreach (['total', 'verified', 'overdue', 'on_time', 'completion', 'quality'] as $key) {
            $rows[] = [ucwords(str_replace('_', ' ', $key)), $summary[$key]];
        }
        foreach ($data['executiveSummary'] as $card) {
            $rows[] = [$card['label'], $card['value'] === null ? 'Not Measured' : $card['value'].' '.$card['unit'], 'Register IDs: '.implode(', ', $card['sources'])];
        }
        $rows[] = ['KPI', 'Unit', 'Target', 'Actual', 'Achievement %', 'Variance', 'Previous', 'YTD', 'RAG'];
        foreach ($summary['kpis'] as $k) {
            $rows[] = [$k['kpi']->name, $k['kpi']->unit, $k['target'], $k['actual'], $k['achievement'], $k['variance'], $k['previous'], $k['ytd'], $k['rag']];
        }
        $rows[] = ['Commitment ID', 'Function', 'Owner', 'Output', 'Deadline', 'State', 'Actual completion', 'Issue/impact', 'Corrective action', 'Action owner', 'Action deadline', 'Management decision'];
        foreach ($data['items'] as $i) {
            $rows[] = ['WCT-'.$i->id, $i->department, $i->leadStaff?->name, strip_tags($i->deliverables), $i->dueDate()?->toDateString(), $i->workflow_state, $i->completed_at?->toIso8601String(), $i->issue_impact, $i->corrective_action, $i->actionOwner?->name, $i->action_due_date?->toDateString(), $i->management_decision];
        }
        $rows[] = ['Methodology', 'Only independently approved KPI results; verified completion; unavailable values are not zero. Lower-is-better achievement is a linear distance-to-target index. Rates aggregate numerators/denominators; balances use latest scope snapshot.'];
        if ($format === 'json') {
            return response()->json(['period' => [$data['from'], $data['to']], 'rows' => $rows]);
        }
        $bytes = app(\App\Services\PerformanceExport::class)->{$format}($rows);

        return response($bytes)->header('Content-Type', $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="CMIH-performance-'.now()->format('Y-m-d').'.'.$format.'"');
    }

    private function manager(Request $r, string $department): void
    {
        abort_unless(Access::manages($r->user(), $department), 403);
    }

    private function activeUser(int $id, string $department): void
    {
        $user = User::whereKey($id)->where('status', 'active')->firstOrFail();
        abort_unless(Access::department($user) === ($department === 'admin_drivers' ? 'hr_admin' : $department) || Access::executive($user), 422, 'Choose an active owner/reviewer in this function.');
    }

    public function objective(Request $r)
    {
        $v = $r->validate(['department' => ['required', Rule::in(array_keys(config('performance.departments')))], 'title' => 'required|string|max:255', 'outcome' => 'required|string|max:5000', 'owner_id' => 'required|integer|exists:users,id', 'parent_id' => 'nullable|integer|exists:performance_objectives,id', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on']);
        $this->manager($r, $v['department']);
        $this->activeUser($v['owner_id'], $v['department']);
        if (! empty($v['parent_id'])) {
            $parent = PerformanceObjective::findOrFail($v['parent_id']);
            abort_unless(Access::executive($r->user()) || $parent->department === $v['department'], 403);
        }
        DB::transaction(function () use ($v, $r) {
            $item = PerformanceObjective::create($v);
            Workflow::record('objective', $item->id, $r->user()->id, 'create', [], $item->getAttributes(), 'Objective established');
        });

        return back()->with('status', 'Objective recorded.');
    }

    public function kpi(Request $r)
    {
        $v = $r->validate(['department' => ['required', Rule::in(array_keys(config('performance.departments')))], 'objective_id' => 'required|integer|exists:performance_objectives,id', 'name' => 'required|string|max:255', 'definition' => 'required|string|max:5000', 'formula' => 'required|string|max:2000', 'unit' => 'required|string|max:50', 'direction' => 'required|in:higher,lower', 'aggregation' => 'required|in:sum,balance,ratio,mean', 'frequency' => 'required|in:weekly,monthly,quarterly,yearly,project', 'source' => 'required|string|max:2000', 'owner_id' => 'required|integer|exists:users,id', 'reviewer_id' => 'required|integer|exists:users,id|different:owner_id', 'green_threshold' => 'required|numeric|min:0|max:1000', 'amber_threshold' => 'required|numeric|min:0|lt:green_threshold', 'escalation_days' => 'required|integer|min:1|max:365', 'required_action' => 'required|string|max:5000', 'dimension' => ['required', Rule::in(config('performance.dimensions'))], 'weight' => 'required|numeric|min:0.01|max:100', 'evidence_required' => 'required|boolean']);
        $this->manager($r, $v['department']);
        $this->activeUser($v['owner_id'], $v['department']);
        $this->activeUser($v['reviewer_id'], $v['department']);
        $obj = PerformanceObjective::findOrFail($v['objective_id']);
        abort_unless($obj->department === $v['department'] || ($obj->department === null && Access::executive($r->user())), 422);
        DB::transaction(function () use ($v, $r) {
            $item = PerformanceKpi::create($v);
            Workflow::record('kpi', $item->id, $r->user()->id, 'create', [], $item->getAttributes(), 'KPI definition established; immutable version 1');
        });

        return back()->with('status', 'KPI dictionary definition created.');
    }

    public function commitment(Request $r)
    {
        $v = $r->validate(['department' => ['required', Rule::in(array_keys(config('performance.departments')))], 'performance_kpi_id' => 'required|integer|exists:performance_kpis,id', 'source_task_id' => 'nullable|integer|exists:tasks,id|unique:weekly_consolidated_items,source_task_id', 'record_type' => 'required|in:deliverable,milestone,outcome', 'team' => 'nullable|string|max:255', 'lead_staff_id' => 'required|integer|exists:users,id', 'reviewer_id' => 'required|integer|exists:users,id|different:lead_staff_id', 'supporting_staff_ids' => 'nullable|array', 'supporting_staff_ids.*' => 'integer|exists:users,id', 'client_name' => 'nullable|string|max:255', 'campaign_name' => 'required|string|max:255', 'week_start' => 'required|date', 'week_end' => 'required|date|after_or_equal:week_start', 'deliverables' => 'required|string|max:15000', 'priority' => 'required|in:Critical,High,Medium,Low', 'risk_level' => 'required|in:low,medium,high,critical', 'target_value' => 'required|numeric|min:0|max:99999999999999']);
        $this->manager($r, $v['department']);
        $this->activeUser($v['lead_staff_id'], $v['department']);
        $this->activeUser($v['reviewer_id'], $v['department']);
        if (! empty($v['source_task_id'])) {
            $task = \App\Models\Task::query()->realWork()->whereKey($v['source_task_id'])->where('assigned_to', $v['lead_staff_id'])->first();
            if (! $task) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_task_id' => 'Link a real task assigned to this accountable owner.']);
            }
        }
        $kpi = PerformanceKpi::findOrFail($v['performance_kpi_id']);
        abort_unless($kpi->department === $v['department'], 422);
        DB::transaction(function () use ($v, $kpi, $r) {
            $item = WeeklyConsolidatedItem::create($v + ['objective' => $kpi->objective->title, 'kpi_name' => $kpi->name, 'measurement_unit' => $kpi->unit, 'measurement_direction' => $kpi->direction, 'workflow_state' => 'assigned', 'status' => 'Planned', 'progress_percent' => 0, 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            Workflow::record('commitment', $item->id, $r->user()->id, 'assign', [], $item->getAttributes(), 'New approved commitment');
            Workflow::notify($item->lead_staff_id, 'assignment:'.$item->id, 'New performance assignment', $item->campaign_name);
        });

        return back()->with('status', 'Commitment assigned for owner acceptance.');
    }

    public function update(Request $r, WeeklyConsolidatedItem $item)
    {
        abort_unless(Access::view($r->user(), $item), 403);
        abort_if($item->workflow_state === 'legacy', 422, 'Use the existing table for legacy records.');
        $v = $r->validate(['revision' => 'required|integer', 'progress_percent' => 'required|integer|min:0|max:99', 'actual_value' => 'nullable|numeric|min:0|max:99999999999999', 'evidence_url' => 'nullable|url:http,https|max:2000', 'issue_impact' => 'nullable|string|max:5000', 'corrective_action' => 'nullable|string|max:5000', 'root_cause' => ['nullable', Rule::in(config('performance.root_causes'))], 'action_owner_id' => 'nullable|integer|exists:users,id', 'action_due_date' => 'nullable|date', 'management_decision' => 'nullable|string|max:5000', 'reason' => 'required|string|max:2000', 'week_end' => 'nullable|date|after_or_equal:'.$item->week_start->toDateString(), 'target_value' => 'nullable|numeric|min:0|max:99999999999999']);
        $manager = Access::manages($r->user(), $item->department);
        if (! $manager) {
            abort_unless(in_array($item->workflow_state, ['accepted', 'executing'], true), 422, 'Accept and begin work before updating progress.');
        }
        if (! $manager) {
            abort_if($r->hasAny(['week_end', 'target_value', 'management_decision']), 403);
        }
        abort_unless($manager || (int) $item->lead_staff_id === $r->user()->id, 403);
        if (! empty($v['action_owner_id'])) {
            $this->activeUser($v['action_owner_id'], $item->department);
        }
        DB::transaction(function () use ($v, $item, $r) {
            $locked = WeeklyConsolidatedItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->revision != (int) $v['revision'], 409);
            abort_if(in_array($locked->workflow_state, ['verified', 'closed']), 422, 'Reopen before editing verified work.');
            $before = $locked->getAttributes();
            $locked->fill(collect($v)->except(['revision', 'reason'])->all());
            $locked->revision++;
            $locked->updated_by = $r->user()->id;
            $locked->save();
            Workflow::record('commitment', $locked->id, $r->user()->id, 'update', $before, $locked->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Progress and corrective actions recorded.');
    }

    public function adopt(Request $r, WeeklyConsolidatedItem $item)
    {
        $this->manager($r, $item->department);
        $v = $r->validate(['performance_kpi_id' => 'required|integer|exists:performance_kpis,id', 'lead_staff_id' => 'required|integer|exists:users,id', 'reviewer_id' => 'required|integer|exists:users,id|different:lead_staff_id', 'reason' => 'required|string|max:2000']);
        $kpi = PerformanceKpi::findOrFail($v['performance_kpi_id']);
        abort_unless($kpi->department === $item->department, 422);
        $this->activeUser($v['lead_staff_id'], $item->department);
        $this->activeUser($v['reviewer_id'], $item->department);
        DB::transaction(function () use ($item, $r, $v, $kpi) {
            $i = WeeklyConsolidatedItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($i->workflow_state === 'legacy', 409);
            $before = $i->getAttributes();
            $i->fill(collect($v)->except('reason')->all() + ['workflow_state' => 'assigned', 'status' => 'Planned', 'progress_percent' => 0, 'completed_at' => null, 'measurement_unit' => $kpi->unit, 'measurement_direction' => $kpi->direction, 'kpi_name' => $kpi->name, 'objective' => $kpi->objective->title]);
            $i->revision++;
            $i->save();
            Workflow::record('commitment', $i->id, $r->user()->id, 'adopt_legacy', $before, $i->getAttributes(), $v['reason'].'; historical completion retained in audit, not treated as verified.');
        });

        return back()->with('status', 'Existing commitment moved into governed review without duplicating the record.');
    }

    public function transition(Request $r, WeeklyConsolidatedItem $item, Workflow $workflow)
    {
        $v = $r->validate(['action' => 'required|in:accept,execute,submit,complete,verify,close,return,reopen', 'revision' => 'required|integer', 'reason' => 'required|string|max:2000', 'quality_score' => 'nullable|numeric|min:0|max:100']);
        $workflow->transition($item, $r->user(), $v['action'], (int) $v['revision'], $v['reason'], isset($v['quality_score']) ? (float) $v['quality_score'] : null);

        return back()->with('status', 'Workflow updated.');
    }

    public function observation(Request $r)
    {
        $v = $r->validate(['target_plan_id' => 'required|integer|exists:performance_target_plans,id', 'actual' => 'nullable|numeric|min:0|max:99999999999999', 'numerator' => 'nullable|numeric|min:0|max:99999999999999', 'denominator' => 'nullable|numeric|min:0|max:99999999999999', 'source_reference' => 'required|string|max:2000', 'evidence_url' => 'nullable|url:http,https|max:2000']);
        $plan = \App\Models\PerformanceTargetPlan::with('kpi')->findOrFail($v['target_plan_id']);
        $kpi = $plan->kpi;
        $this->manager($r, $plan->department);
        abort_unless($plan->approved_at && ! $plan->withdrawn_at, 422, 'Approve the target independently before recording the actual result.');
        if ((int) $plan->reviewer_id === $r->user()->id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['target_plan_id' => 'A different manager must submit the result so you can review it independently.']);
        }
        $v += ['kpi_id' => $plan->kpi_id, 'owner_id' => $plan->owner_id, 'period_start' => $plan->period_start->toDateString(), 'period_end' => $plan->period_end->toDateString(), 'client' => $plan->client, 'project' => $plan->project, 'target' => $plan->target];
        if ($kpi->aggregation === 'ratio' || $kpi->aggregation === 'mean') {
            $v['actual'] = Math::ratio($v['numerator'] ?? null, $v['denominator'] ?? null, $kpi->aggregation === 'ratio');
        }
        if ($kpi->evidence_required && empty($v['evidence_url'])) {
            return back()->withErrors(['evidence_url' => 'This KPI requires an evidence link.'])->withInput();
        }
        $v['scope_key'] = hash('sha256', 'target-plan:'.$plan->id);
        DB::transaction(function () use ($v, $kpi, $r) {
            $lockedPlan = \App\Models\PerformanceTargetPlan::whereKey($v['target_plan_id'])->lockForUpdate()->firstOrFail();
            abort_unless($lockedPlan->approved_at && ! $lockedPlan->withdrawn_at, 409, 'The baseline changed. Refresh before submitting results.');
            $overlap = PerformanceObservation::where('target_plan_id', $v['target_plan_id'])->exists();
            if ($overlap) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period_start' => 'An observation already overlaps this scope and period.']);
            }
            $item = PerformanceObservation::create($v + ['submitted_by' => $r->user()->id]);
            Workflow::record('observation', $item->id, $r->user()->id, 'submit', [], $item->getAttributes(), 'Period result submitted for independent review');
            Workflow::notify($kpi->reviewer_id, 'observation:'.$item->id, 'KPI review requested', $kpi->name);
        });

        return back()->with('status', 'KPI result submitted for review.');
    }

    public function approveObservation(Request $r, PerformanceObservation $observation)
    {
        $v = $r->validate(['reason' => 'required|string|max:2000']);
        $kpi = $observation->kpi;
        abort_if((int) $observation->submitted_by === $r->user()->id, 403, 'A different reviewer must approve the submitted result.');
        abort_unless((int) $kpi->reviewer_id === $r->user()->id && (int) $observation->owner_id !== $r->user()->id, 403);
        DB::transaction(function () use ($observation, $r, $v) {
            $o = PerformanceObservation::whereKey($observation->id)->lockForUpdate()->firstOrFail();
            abort_if($o->approved_at, 409);
            $before = $o->getAttributes();
            $o->update(['approved_at' => now(), 'approved_by' => $r->user()->id]);
            Workflow::record('observation', $o->id, $r->user()->id, 'approve', $before, $o->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'KPI result approved.');
    }

    public function dependency(Request $r)
    {
        $v = $r->validate(['upstream_id' => 'required|integer|exists:weekly_consolidated_items,id', 'downstream_id' => 'required|integer|different:upstream_id|exists:weekly_consolidated_items,id', 'impact' => 'required|string|max:5000']);
        foreach (['upstream_id', 'downstream_id'] as $key) {
            $this->manager($r, WeeklyConsolidatedItem::findOrFail($v[$key])->department);
        }
        app(\App\Services\PerformanceDependencies::class)->link($v, $r->user());

        return back()->with('status', 'Dependency linked.');
    }

    public function evidence(Request $r, WeeklyConsolidatedItem $item)
    {
        abort_unless(Access::view($r->user(), $item), 403);
        abort_unless(Access::manages($r->user(), $item->department) || (int) $item->lead_staff_id === $r->user()->id, 403);
        abort_if(in_array($item->workflow_state, ['verified', 'closed']), 422);
        $r->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,docx,xlsx|max:10240']);
        $f = $r->file('file');
        $path = $f->store('performance-evidence', 'local');
        try {
            DB::transaction(function () use ($f, $path, $r, $item) {
                $e = PerformanceEvidence::create(['commitment_id' => $item->id, 'uploaded_by' => $r->user()->id, 'path' => $path, 'original_name' => $f->getClientOriginalName(), 'mime' => $f->getMimeType(), 'bytes' => $f->getSize(), 'sha256' => hash_file('sha256', $f->getRealPath())]);
                Workflow::record('evidence', $e->id, $r->user()->id, 'upload', [], $e->getAttributes(), 'Evidence version attached');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Evidence attached.');
    }

    public function download(Request $r, PerformanceEvidence $evidence)
    {
        abort_unless(Access::view($r->user(), WeeklyConsolidatedItem::findOrFail($evidence->commitment_id)), 403);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function history(Request $r, WeeklyConsolidatedItem $item)
    {
        abort_unless(Access::view($r->user(), $item), 403);

        return response()->json(['events' => PerformanceEvent::where('entity_type', 'commitment')->where('entity_id', $item->id)->orderBy('id')->get(), 'evidence' => PerformanceEvidence::where('commitment_id', $item->id)->get()->map(fn ($e) => ['name' => $e->original_name, 'uploaded_at' => $e->created_at, 'url' => route('portal.performance.evidence.download', $e)])]);
    }

    public function register(Request $r)
    {
        $v = $r->validate(['department' => ['required', Rule::in(array_keys(config('performance.departments')))], 'kind' => 'required|string', 'reference' => 'required|string|max:255', 'title' => 'required|string|max:255', 'owner_id' => 'required|integer|exists:users,id', 'team' => 'nullable|string|max:255', 'client' => 'nullable|string|max:255', 'project' => 'nullable|string|max:255', 'cost_center' => 'nullable|string|max:255', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'status' => 'required|in:planned,in_progress,submitted,approved,won,lost,resolved,completed,paid,cancelled', 'evidence_url' => 'required|url:http,https|max:2000', 'data' => 'required|array']);
        $this->manager($r, $v['department']);
        $this->activeUser($v['owner_id'], $v['department']);
        $schema = config('department_reporting.'.$v['department'].'.'.$v['kind']);
        abort_unless(is_array($schema), 422);
        $rules = [];
        foreach ($schema as $key => $type) {
            $rules['data.'.$key] = match ($type) {
                'money','number' => 'nullable|numeric|min:0|max:99999999999999','date' => 'nullable|date',default => in_array($key,['insights','bottleneck','next_action','description','key_issue','action_required','resolution','mitigation','outstanding_actions'],true)?'nullable|string|max:15000':'nullable|string|max:2000'
            };
        }
        $details = $r->validate($rules);
        foreach (['satisfaction_score', 'quality_score', 'probability'] as $bounded) {
            if (isset($details['data'][$bounded]) && $details['data'][$bounded] > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$bounded => 'Use a value between 0 and 100.']);
            }
        }
        if (in_array($v['kind'], ['invoice', 'payable']) && isset($details['data']['value']) && (($details['data']['paid_amount'] ?? 0) + ($details['data']['credited_amount'] ?? 0) > $details['data']['value'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.credited_amount' => 'Payments and credits together cannot exceed the document value.']);
        }
        foreach ([['attended_days', 'eligible_days'], ['reviews_completed', 'reviews_due'], ['used_hours', 'scheduled_hours'], ['available_hours', 'scheduled_hours'], ['downtime_hours', 'scheduled_hours'], ['paid_amount', 'value']] as [$part,$total]) {
            if (isset($details['data'][$part],$details['data'][$total]) && $details['data'][$part] > $details['data'][$total]) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$part => 'Cannot exceed '.$total.'.']);
            }
        }
        foreach ([['opened_on', 'resolved_on'], ['approved_on', 'offer_accepted_on'], ['accepted_on', 'final_delivered_on'], ['started_on', 'completed_on']] as [$start,$end]) {
            if (! empty($details['data'][$start]) && ! empty($details['data'][$end]) && \Illuminate\Support\Carbon::parse($details['data'][$end])->lt(\Illuminate\Support\Carbon::parse($details['data'][$start]))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$end => 'End date cannot precede the start date.']);
            }
        }
        $v['data'] = array_intersect_key($details['data'] ?? [], $schema);
        if ($v['department'] === 'brands_marketing') {
            app(\App\Services\BrandsActivityValidation::class)->validate($v['data']);
        }
        if ($v['department'] === 'operations_projects') {
            app(\App\Services\OperationsTracker::class)->validate($v['kind'], $v['data']);
        }
        DB::transaction(function () use ($v, $r) {
            $i = PerformanceRegister::create($v + ['submitted_by' => $r->user()->id]);
            Workflow::record('register', $i->id, $r->user()->id, 'create', [], $i->getAttributes(), 'Department operational record submitted');
        });

        return back()->with('status', 'Operational record submitted for review.');
    }

    public function approveRegister(Request $r, PerformanceRegister $register)
    {
        $this->manager($r, $register->department);
        abort_if((int) $register->submitted_by === $r->user()->id, 403, 'A different reviewer must approve the submitted record.');
        abort_if((int) $register->owner_id === $r->user()->id, 403);
        $v = $r->validate(['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($register, $r, $v) {
            $i = PerformanceRegister::whereKey($register->id)->lockForUpdate()->firstOrFail();
            abort_if($i->approved_at, 409);
            $before = $i->getAttributes();
            $i->update(['approved_at' => now(), 'approved_by' => $r->user()->id]);
            Workflow::record('register', $i->id, $r->user()->id, 'approve', $before, $i->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Operational record approved.');
    }
}
