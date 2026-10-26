<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CrmAccountTarget;
use App\Models\CrmPipelineProject;
use App\Models\User;
use App\Services\ClientRelationsPipeline as Pipeline;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientRelationsPipelineController extends Controller
{
    private function authorizeMember(Request $r): void
    {
        abort_unless(Pipeline::member($r->user()), 403, 'Client Relations staff, CVO or Super Admin access is required.');
    }

    public function index(Request $r, Pipeline $service)
    {
        $this->authorizeMember($r);
        $filters = $r->validate(['q' => 'nullable|string|max:255', 'stage' => ['nullable', Rule::in(array_keys(config('crm_pipeline.stages')))], 'owner' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = CrmPipelineProject::with('owner')->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('client_name', 'like', '%'.$filters['q'].'%')->orWhere('project_name', 'like', '%'.$filters['q'].'%')))->when($r->filled('stage'), fn ($q) => $q->where('stage', $filters['stage']))->when($r->filled('owner'), fn ($q) => $q->where('owner_id', $filters['owner']))->when($r->filled('from'), fn ($q) => $q->whereDate('week_start', '>=', $filters['from']))->when($r->filled('to'), fn ($q) => $q->whereDate('week_start', '<=', $filters['to']));
        // History snapshots stay browsable; only one latest dated snapshot per owner/client/project contributes to the live scorecard.
        $scorecardProjects = (clone $q)->where('source_period_confirmed', true)->orderByDesc('week_start')->orderByDesc('source_row')->get()->unique(fn ($p) => $p->import_key ? $p->owner_id.'|'.mb_strtolower(trim($p->client_name)).'|'.mb_strtolower(trim($p->project_name)) : 'manual-'.$p->id)->values();
        $projects = $q->orderByDesc('week_start')->orderByDesc('source_row')->paginate(15)->withQueryString();
        $clientRecords = \App\Models\CrmClientRecord::with('owner')->when($r->filled('q'), fn ($q) => $q->where('client_name', 'like', '%'.$filters['q'].'%'))->when($r->filled('owner'), fn ($q) => $q->where('owner_id', $filters['owner']))->latest()->get();
        $scorecard = app(\App\Services\ClientRelationsScorecard::class)->build($scorecardProjects, $clientRecords);
        $staff = User::humanAccounts()->where('status', 'active')->whereIn('department', User::departmentAliases('client_relations'))->where('name','not like','Import custodian%')->orderBy('name')->get();
        $clientNames = CrmPipelineProject::query()->select('client_name')->distinct()->orderBy('client_name')->pluck('client_name')->merge(\App\Models\CrmFinancialRegister::pluck('client_name'))->merge(\App\Models\CrmAccountAllocation::pluck('client_name'))->unique()->sort()->values();
        $targets = CrmAccountTarget::orderByDesc('period_start')->get();

        return view('portal.client-relations.pipeline', compact('projects', 'staff', 'targets', 'service', 'scorecard', 'clientRecords', 'scorecardProjects','clientNames'));
    }

    public function history(Request $r)
    {
        $this->authorizeMember($r);
        $r->validate(['sheet' => 'nullable|string|max:255', 'q' => 'nullable|string|max:255', 'archives' => 'nullable|in:1']);
        $sheets = \App\Models\CrmSourceHistory::query()->select('sheet')->distinct()->orderBy('sheet')->pluck('sheet');
        $rows = \App\Models\CrmSourceHistory::query()->when(! $r->boolean('archives'), fn ($q) => $q->where('hidden_sheet', false))->when($r->filled('sheet'), fn ($q) => $q->where('sheet', $r->sheet))->when($r->filled('q'), fn ($q) => $q->where('cells', 'like', '%'.$r->q.'%'))->orderBy('sheet')->orderBy('source_row')->paginate(30)->withQueryString();

        return view('portal.client-relations.history', compact('rows', 'sheets'));
    }

    public function reveal(Request $r, Pipeline $service, \App\Models\CrmWeeklyProgress $progress)
    {
        abort_unless(Pipeline::canReveal($r->user()), 403);
        $project = CrmPipelineProject::findOrFail($progress->project_id);
        $metrics = $service->financials($project);
        PerformanceWorkflow::record('crm_project', $project->id, $r->user()->id, 'reveal_shared_amounts', [], [], 'Shared table amounts revealed for up to two minutes');

        return response()->json([
            'proposed_amount' => $project->proposed_amount === null ? 'Not Recorded' : 'GH₵ '.number_format((float) $project->proposed_amount, 2),
            'po_amount' => $project->po_amount === null ? 'Not Recorded' : 'GH₵ '.number_format((float) $project->po_amount, 2),
            'weighted_forecast' => $metrics['weighted_forecast'] === null ? 'Not Recorded' : 'GH₵ '.number_format($metrics['weighted_forecast'], 2),
        ])->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    public function store(Request $r, Pipeline $service)
    {
        return $this->save($r, $service);
    }

    public function update(Request $r, Pipeline $service, CrmPipelineProject $project)
    {
        return $this->save($r, $service, $project);
    }

    private function save(Request $r, Pipeline $service, ?CrmPipelineProject $project = null)
    {
        $this->authorizeMember($r);
        if ($project) {
            abort_unless($r->user()->isLineManager() || (int) $project->owner_id === $r->user()->id, 403);
        }
        $rules = ['client_name' => 'required|string|max:255', 'brand' => 'nullable|string|max:255', 'project_name' => 'required|string|max:255', 'owner_id' => 'required|integer|exists:users,id', 'stage' => ['required', Rule::in(array_keys(config('crm_pipeline.stages')))], 'expected_close_date' => 'nullable|date', 'week_start' => 'required|date', 'public_action' => ['nullable', Rule::in(array_keys(config('crm_pipeline.public_actions')))], 'new_business' => 'nullable|boolean', 'po_state' => 'nullable|in:unknown,not_requested,requested,received,not_required', 'confirm_source_period' => 'nullable|boolean', 'revision' => 'nullable|integer', 'reason' => 'required|string|max:2000'];
        foreach (['rate_card_value', 'execution_value', 'proposed_amount', 'po_amount', 'target_amount', 'actual_amount', 'invoiced_amount', 'collected_amount'] as $f) {
            $rules[$f] = 'nullable|numeric|min:0|max:9999999999.99';
        }
        foreach (['description', 'shared_bottlenecks', 'shared_next_action', 'comments', 'bottlenecks', 'next_action', 'contact_name', 'contact_position', 'contact_info', 'invoice_status', 'po_status', 'remarks', 'source_reference'] as $f) {
            $rules[$f] = 'nullable|string|max:5000';
        }
        $v = $r->validate($rules);
        $v['po_state'] = $v['po_state'] ?? 'unknown';
        $v['new_business'] = $r->boolean('new_business');
        $owner = User::findOrFail($v['owner_id']);
        abort_unless(Pipeline::staffMember($owner), 422, 'Choose an active Client Relations owner.');
        if (! $r->user()->isLineManager()) {
            abort_unless((int) $v['owner_id'] === $r->user()->id, 403);
        }
        if (isset($v['collected_amount'],$v['invoiced_amount']) && $v['collected_amount'] > $v['invoiced_amount']) {
            return back()->withErrors(['collected_amount' => 'Collections exceed invoicing. Reconcile the recorded values.'])->withInput();
        }
        if ($r->boolean('confirm_source_period')) {
            abort_unless($project?->import_key && $r->user()->isLineManager(), 403);
            $r->validate(['week_start' => 'required|date|after_or_equal:2000-01-01']);
        }
        DB::transaction(function () use ($v, $r, $project, $service) {
            $item = $project ? CrmPipelineProject::whereKey($project->id)->lockForUpdate()->firstOrFail() : new CrmPipelineProject;
            if ($project) {
                abort_if((int) $v['revision'] !== (int) $item->revision, 409, 'Pipeline changed. Refresh before saving.');
            }$before = $item->getAttributes();
            $item->fill(collect($v)->except(['revision', 'reason', 'confirm_source_period'])->all());
            if ($project?->import_key) {
                $item->source_reference = $before['source_reference'];
            }
            if ($r->boolean('confirm_source_period')) {
                $item->source_period_confirmed = true;
            }
            if (! $project) {
                $item->created_by = $r->user()->id;
            }$item->updated_by = $r->user()->id;
            $item->revision = ($project ? $item->revision + 1 : 1);
            $item->save();
            if ($project && (int)$before['owner_id'] !== (int)$item->owner_id) {
                \App\Models\CrmAccountRelationship::firstOrCreate(['client_name'=>$before['client_name'],'owner_id'=>$before['owner_id'],'relationship'=>'previous','ends_on'=>today()->toDateString()],['starts_on'=>null,'note'=>'Project ownership changed; original tenure start is not confirmed.','created_by'=>$r->user()->id]);
            }
            $service->publish($item);
            PerformanceWorkflow::record('crm_project', $item->id, $r->user()->id, $project ? 'update' : 'create', $before, $item->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Pipeline saved. The shared weekly table has been updated.');
    }

    public function updateRecord(Request $r, \App\Models\CrmClientRecord $record)
    {
        return $this->record($r, $record);
    }

    public function record(Request $r, ?\App\Models\CrmClientRecord $record = null)
    {
        $this->authorizeMember($r);
        if ($record) abort_unless($r->user()->isLineManager() || (int)$record->owner_id === $r->user()->id, 403);
        $change = $record ? $r->validate(['revision'=>'required|integer|min:1','reason'=>'required|string|min:5|max:2000']) : [];
        $v = $r->validate(['kind' => ['required', Rule::in(array_keys(config('crm_scorecard')))], 'reference' => 'required|string|max:255', 'client_name' => 'required|string|max:255', 'project_id' => 'nullable|integer|exists:crm_pipeline_projects,id', 'owner_id' => 'required|integer|exists:users,id', 'status' => 'required|string', 'notes' => 'nullable|string|max:5000', 'data' => 'required|array']);
        abort_unless(($record && (int)$record->owner_id === (int)$v['owner_id']) || Pipeline::staffMember(User::findOrFail($v['owner_id'])), 422);
        if (! $r->user()->isLineManager()) {
            abort_unless((int) $v['owner_id'] === $r->user()->id, 403);
        }
        if (! empty($v['project_id'])) {
            $p = CrmPipelineProject::findOrFail($v['project_id']);
            if (mb_strtolower(trim($p->client_name)) !== mb_strtolower(trim($v['client_name']))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['client_name' => 'Client must match the linked project.']);
            }
        }
        if ($record) abort_unless($v['kind'] === $record->kind, 422, 'The record type cannot be changed.');
        $schema = config('crm_scorecard.'.$v['kind']);
        $rules = ['status' => ['required', Rule::in($schema['statuses'])]];
        foreach ($schema['fields'] as $field => $type) {
            $rules['data.'.$field] = match ($type) {
                'number' => 'nullable|numeric|min:0|max:9999999999.99','date','datetime-local' => 'nullable|date',default => 'nullable|string|max:5000'
            };
        }
        $validated = $r->validate($rules);
        $v['data'] = $validated['data'] ?? [];
        if ($v['kind'] === 'satisfaction' && (! isset($v['data']['score'],$v['data']['maximum_score']) || $v['data']['maximum_score'] <= 0 || $v['data']['score'] > $v['data']['maximum_score'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.score' => 'Provide a score and positive maximum; score cannot exceed maximum.']);
        }
        if ($v['kind'] === 'invoice' && isset($v['data']['paid_amount'],$v['data']['amount']) && $v['data']['paid_amount'] > $v['data']['amount']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.paid_amount' => 'Payment cannot exceed the invoice value.']);
        }
        foreach ([['opened_on', 'resolved_on'], ['invoice_date', 'due_on']] as [$a,$b]) {
            if (! empty($v['data'][$a]) && ! empty($v['data'][$b]) && \Illuminate\Support\Carbon::parse($v['data'][$a])->gt(\Illuminate\Support\Carbon::parse($v['data'][$b]))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$b => 'End date cannot precede the start date.']);
            }
        }
        if (\App\Models\CrmClientRecord::where('kind', $v['kind'])->where('reference', $v['reference'])->when($record, fn($q)=>$q->whereKeyNot($record->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reference' => 'This source reference already exists.']);
        }
        DB::transaction(function () use ($v, $r, $record, $change) {
            if ($record) {
                $i = \App\Models\CrmClientRecord::lockForUpdate()->findOrFail($record->id);
                abort_unless($r->user()->isLineManager() || (int)$i->owner_id === $r->user()->id, 403);
                abort_if((int)$i->revision !== (int)$change['revision'],409,'This activity changed. Refresh before saving.');
                $before=$i->getAttributes();
                $i->fill($v + ['updated_by'=>$r->user()->id]);$i->revision++;$i->save();
            } else {
                $before=[];$i=\App\Models\CrmClientRecord::create($v + ['created_by'=>$r->user()->id,'updated_by'=>$r->user()->id]);
            }
            PerformanceWorkflow::record('crm_record',$i->id,$r->user()->id,$record?'update':'create',$before,$i->getAttributes(),$change['reason']??'Client scorecard source recorded');
        });
        return back()->with('status', $record ? 'Client activity updated. The shared view will show its latest status.' : 'Client scorecard source recorded.');
    }

    public function correctTarget(Request $r, CrmAccountTarget $target)
    {
        $this->authorizeMember($r);
        abort_unless($r->user()->isLineManager() || (int) $target->owner_id === $r->user()->id, 403);
        $v = $r->validate(['revision' => 'required|integer', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'frequency' => 'required|in:weekly,monthly,yearly,ytd,custom', 'target_amount' => 'required|numeric|min:0|max:9999999999.99', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $target, $v) {
            $item = CrmAccountTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->needs_reconciliation, 409, 'This target has already been reconciled.');
            abort_unless((int) $item->revision === (int) $v['revision'], 409, 'The record changed. Refresh before saving.');
            $before = $item->getAttributes();
            $item->update(['proposed_correction' => collect($v)->only(['period_start', 'period_end', 'frequency', 'target_amount'])->all(), 'correction_submitted_by' => $r->user()->id, 'correction_reason' => $v['reason'], 'revision' => $item->revision + 1]);
            PerformanceWorkflow::record('crm_target', $item->id, $r->user()->id, 'propose_correction', $before, $item->getAttributes(), $v['reason']);
            User::humanAccounts()->where('status', 'active')->get()->filter(fn ($user) => $user->id !== $r->user()->id && Pipeline::member($user) && $user->isLineManager())->each(function ($reviewer) use ($item) {
                PerformanceWorkflow::notify($reviewer->id, "crm-correction:{$item->id}:{$item->revision}", 'Client Relations Correction Awaiting Confirmation', 'An imported target needs independent confirmation. Open the private correction queue.', route('portal.crm.index').'#crm-target-corrections');
            });
        });

        return back()->with('status', 'Correction saved for independent manager confirmation. The original source is retained.');
    }

    public function reviewTargetCorrection(Request $r, CrmAccountTarget $target)
    {
        $this->authorizeMember($r);
        abort_unless($r->user()->isLineManager(), 403);
        $v = $r->validate(['revision' => 'required|integer', 'decision' => 'required|in:confirm,return', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $target, $v) {
            $item = CrmAccountTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->needs_reconciliation && $item->proposed_correction, 409);
            abort_if((int) $item->correction_submitted_by === $r->user()->id, 403, 'Another manager must confirm your correction.');
            abort_unless((int) $item->revision === (int) $v['revision'], 409, 'The record changed. Refresh before reviewing.');
            $before = $item->getAttributes();
            if ($v['decision'] === 'confirm') {
                $item->fill($item->proposed_correction);
                $item->needs_reconciliation = false;
            }
            $item->proposed_correction = null;
            $item->correction_reason = $v['reason'];
            $item->revision++;
            $item->save();
            PerformanceWorkflow::record('crm_target', $item->id, $r->user()->id, $v['decision'] === 'confirm' ? 'confirm_correction' : 'return_correction', $before, $item->getAttributes(), $v['reason']);
            PerformanceWorkflow::notify((int) $item->correction_submitted_by, "crm-correction-reviewed:{$item->id}:{$item->revision}", 'Client Relations Correction Reviewed', $v['decision'] === 'confirm' ? 'Your correction was confirmed.' : 'Your correction was returned. Check the note in the private module.', route('portal.crm.index').'#crm-target-corrections');
        });

        return back()->with('status', $v['decision'] === 'confirm' ? 'Target correction confirmed.' : 'Correction returned. Please check the review note.');
    }

    public function target(Request $r)
    {
        $this->authorizeMember($r);
        abort_unless($r->user()->isLineManager(), 403);
        $v = $r->validate(['client_name' => 'required|string|max:255', 'owner_id' => 'required|integer|exists:users,id', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'frequency' => 'required|in:weekly,monthly,yearly,ytd,custom', 'actual_amount' => 'nullable|numeric|min:0|max:9999999999.99', 'target_amount' => 'required|numeric|min:0|max:9999999999.99', 'reference' => 'required|string|max:255|unique:crm_account_targets,reference']);
        abort_unless(Pipeline::staffMember(User::findOrFail($v['owner_id'])), 422);
        DB::transaction(function () use ($v, $r) {
            $i = CrmAccountTarget::create($v);
            PerformanceWorkflow::record('crm_target', $i->id, $r->user()->id, 'create', [], $i->getAttributes(), 'Account allocation target established');
        });

        return back()->with('status', 'Private account target recorded.');
    }
}
