<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\FinanceDashboard;
use App\Services\PerformanceAccess;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceReportingController extends Controller
{
    public function save(Request $request)
    {
        abort_unless(FinanceDashboard::canWrite($request->user()), 403);
        $v = $request->validate(['crm_project_id' => 'nullable|integer|exists:crm_pipeline_projects,id', 'operations_handover_id' => 'nullable|integer|exists:operations_handovers,id', 'outside_crm' => 'sometimes|accepted', 'record_id' => 'nullable|integer', 'version' => 'nullable|string|max:64', 'kind' => ['required', Rule::in(array_keys(config('department_reporting.finance')))], 'reference' => 'required|string|max:255', 'title' => 'required|string|max:255', 'client' => 'nullable|string|max:255', 'project' => 'nullable|string|max:255', 'owner_id' => 'required|integer|exists:users,id', 'period_start' => 'required|date_format:Y-m-d', 'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start', 'status' => 'required|in:planned,in_progress,submitted,completed,paid,resolved,cancelled', 'evidence_url' => 'nullable|url:http,https|max:2000', 'reason' => 'required|string|max:2000', 'actions' => 'nullable|array|max:20', 'actions.*.task' => 'nullable|string|max:1000', 'actions.*.owner' => 'nullable|string|max:255', 'actions.*.due' => 'nullable|date_format:Y-m-d', 'actions.*.status' => 'nullable|in:planned,in_progress,completed']);
        $owner = User::findOrFail($v['owner_id']);
        abort_unless($owner->isActive() && PerformanceAccess::department($owner) === 'finance', 422, 'Choose an active Finance owner.');
        $schema = config('department_reporting.finance.'.$v['kind']);
        $rules = [];
        foreach ($schema as $field => $type) {
            $rules['data.'.$field] = match ($type) {
                'money','number' => 'nullable|numeric|min:0|max:999999999999.99','date' => 'nullable|date_format:Y-m-d',default => 'nullable|string|max:2000'
            };
        }
        $required = match ($v['kind']) {
            'revenue' => ['actual'],'revenue_target' => ['target'],'invoice','payable','receipt','payment','payroll','tax' => ['value', 'paid_amount'],'expense' => ['actual'],'commercial_risk' => ['risk_type'],default => []
        };
        foreach ($required as $field) {
            $rules['data.'.$field] = str_replace('nullable', 'required', $rules['data.'.$field]);
        }
        $data = $request->validate($rules)['data'] ?? [];
        foreach (['paid_amount', 'credited_amount'] as $field) {
            if (isset($data['value'],$data[$field]) && $data[$field] > $data['value']) {
                throw ValidationException::withMessages(['data.'.$field => 'Cannot exceed the document amount.']);
            }
        }
        if (isset($data['value']) && (float) ($data['paid_amount'] ?? 0) + (float) ($data['credited_amount'] ?? 0) > (float) $data['value']) {
            throw ValidationException::withMessages(['data.paid_amount' => 'Payments and credits together cannot exceed the document amount.']);
        }
        if ((float) ($data['paid_amount'] ?? 0) > 0 && empty($data['paid_on'])) {
            throw ValidationException::withMessages(['data.paid_on' => 'Record the actual payment or receipt date.']);
        }
        if ((float) ($data['credited_amount'] ?? 0) > 0 && empty($data['credited_on'])) {
            throw ValidationException::withMessages(['data.credited_on' => 'Record the actual credit date.']);
        }
        if (! empty($data['credited_on']) && $data['credited_on'] > today()->toDateString()) {
            throw ValidationException::withMessages(['data.credited_on' => 'Actual credits cannot be future-dated.']);
        }
        if ($v['status'] === 'paid' && isset($data['value']) && (float) ($data['paid_amount'] ?? 0) + (float) ($data['credited_amount'] ?? 0) < (float) $data['value']) {
            throw ValidationException::withMessages(['status' => 'A paid record must be fully settled.']);
        }
        if ($v['kind'] === 'revenue') {
            $data['revenue_type'] = $data['revenue_type'] ?? 'direct';
            if (! in_array($data['revenue_type'], ['direct', 'monthly_total'], true)) {
                throw ValidationException::withMessages(['data.revenue_type' => 'Choose a valid revenue source type.']);
            }
            if ($data['revenue_type'] === 'direct') {
                if (empty($v['outside_crm'])) {
                    throw ValidationException::withMessages(['outside_crm' => 'Confirm this revenue is additional to the amounts already reported by Client Relations.']);
                }if (empty($data['earned_on'])) {
                    throw ValidationException::withMessages(['data.earned_on' => 'Record the date this revenue was earned.']);
                }if ($data['earned_on'] > today()->toDateString()) {
                    throw ValidationException::withMessages(['data.earned_on' => 'Actual revenue cannot be future-dated.']);
                }$v['period_start'] = $v['period_end'] = $data['earned_on'];
                unset($data['target']);
            }
        }
        if (in_array($v['kind'], ['revenue_target', 'payroll', 'tax']) || ($v['kind'] === 'revenue' && ($data['revenue_type'] ?? '') === 'monthly_total')) {
            $start = \Carbon\Carbon::parse($v['period_start']);
            $end = \Carbon\Carbon::parse($v['period_end']);
            if (! $start->isStartOfMonth() || $end->toDateString() !== $start->copy()->endOfMonth()->toDateString()) {
                throw ValidationException::withMessages(['period_end' => 'Use one complete calendar month for revenue, payroll and statutory reporting.']);
            }
        }
        if (! empty($data['paid_on']) && $data['paid_on'] > today()->toDateString() && (float) ($data['paid_amount'] ?? 0) > 0) {
            throw ValidationException::withMessages(['data.paid_on' => 'Actual payments and receipts cannot be future-dated. Use the expected date for plans.']);
        }
        if (isset($data['headcount']) && floor((float) $data['headcount']) != (float) $data['headcount']) {
            throw ValidationException::withMessages(['data.headcount' => 'Headcount must be a whole number.']);
        }
        $data['_actions'] = array_values(array_filter($v['actions'] ?? [], fn ($a) => filled($a['task'] ?? null)));
        $data['_finance_currency'] = 'GHS';
        $data['_finance_workflow'] = 'manager_review';
        if (! empty($v['crm_project_id'])) {
            $project = \App\Models\CrmPipelineProject::where('source_period_confirmed', true)->findOrFail($v['crm_project_id']);
            $v['project'] = $project->project_name;
            $v['client'] = $project->client_name;
            $data['_crm_project_id'] = $project->id;
            if (array_key_exists('account_lead', $schema)) {
                $data['account_lead'] = $project->owner?->name ?? '';
            }
        }
        if (! empty($v['operations_handover_id'])) {
            $h = \App\Models\OperationsHandover::findOrFail($v['operations_handover_id']);
            abort_unless(! empty($data['_crm_project_id']) && (int) $h->project_id === (int) $data['_crm_project_id'] && $h->handover_approved_at, 422, 'Choose a signed Operations handover belonging to this project.');
            $data['_operations_handover_id'] = $h->id;
        }

        DB::transaction(function () use ($v, $data, $request) {
            $row = ! empty($v['record_id']) ? PerformanceRegister::where('department', 'finance')->lockForUpdate()->findOrFail($v['record_id']) : new PerformanceRegister;
            if ($row->exists) {
                abort_unless(((int) $row->submitted_by === $request->user()->id || PerformanceAccess::manages($request->user(), 'finance')), 403, 'Only the submitter or Finance manager may revise this entry.');
                abort_unless(hash_equals(hash('sha256', json_encode($row->getAttributes())), $v['version'] ?? ''), 409, 'This entry changed. Refresh before saving.');
                abort_unless($row->kind === $v['kind'], 422);
            }
            if (PerformanceRegister::where('department', 'finance')->where('kind', $v['kind'])->where('reference', $v['reference'])->when($row->exists, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
                throw ValidationException::withMessages(['reference' => 'This reference already exists. Edit the original entry instead.']);
            }
            if ((($v['kind'] === 'revenue' && ($data['revenue_type'] ?? '') === 'monthly_total') || $v['kind'] === 'revenue_target') && PerformanceRegister::where('department', 'finance')->where('kind', $v['kind'])->when($v['kind'] === 'revenue', fn ($q) => $q->where(fn ($type) => $type->whereNull('data->revenue_type')->orWhere('data->revenue_type', 'monthly_total')))->where('status', '!=', 'cancelled')->whereDate('period_start', '<=', $v['period_end'])->whereDate('period_end', '>=', $v['period_start'])->when($row->exists, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
                throw ValidationException::withMessages(['period_start' => 'A monthly revenue total already exists. Update that total to avoid counting it twice.']);
            }
            $before = $row->getAttributes();
            $fields = array_intersect_key($v, array_flip(['kind', 'reference', 'title', 'client', 'project', 'owner_id', 'period_start', 'period_end', 'status', 'evidence_url']));
            $row->fill($fields + ['department' => 'finance', 'submitted_by' => $request->user()->id, 'data' => $data, 'approved_at' => FinanceDashboard::manager($request->user()) ? now() : null, 'approved_by' => FinanceDashboard::manager($request->user()) ? $request->user()->id : null]);
            $row->save();
            PerformanceWorkflow::record('register', $row->id, $request->user()->id, $before ? 'finance_update' : 'finance_submit', $before, $row->getAttributes(), $v['reason']);
            FinanceDashboard::notify($row);
        });

        return back()->with('status',FinanceDashboard::manager($request->user()) ? 'Finance manager entry published.' : 'Finance entry sent to Precious for review. It will appear in published figures after approval.');
    }
}
