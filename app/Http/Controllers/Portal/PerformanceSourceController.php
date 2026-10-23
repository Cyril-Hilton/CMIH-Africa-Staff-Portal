<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceRegister;
use App\Models\ProjectBudget;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceAccess;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceSourceController extends Controller
{
    public function correct(Request $request, PerformanceRegister $register)
    {
        abort_unless(PerformanceAccess::manages($request->user(), $register->department), 403);
        abort_unless(isset($register->data['_source_task_id']), 422);
        $schema = config('department_reporting.'.$register->department.'.'.$register->kind, []);
        $rules = ['token' => 'required|string', 'reason' => 'required|string|max:2000'];
        foreach ($schema as $field => $type) {
            $rules['data.'.$field] = match ($type) {
                'money','number' => 'nullable|numeric|min:0|max:99999999999999', 'date' => 'nullable|date', default => 'nullable|string|max:2000'
            };
        }
        $v = $request->validate($rules);
        $details = array_intersect_key($v['data'] ?? [], $schema);
        if ($register->department === 'brands_marketing') {
            app(\App\Services\BrandsActivityValidation::class)->validate($details);
        }
        if (isset($register->data['_source_budget_id'])) {
            $details['budget'] = $register->data['budget'];
        }
        foreach (['quality_score', 'satisfaction_score', 'probability'] as $field) {
            if (isset($details[$field]) && $details[$field] > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$field => 'Use a value between 0 and 100.']);
            }
        }
        foreach ([['accepted_on', 'final_delivered_on'], ['started_on', 'completed_on']] as [$start,$end]) {
            if (! empty($details[$start]) && ! empty($details[$end]) && \Illuminate\Support\Carbon::parse($details[$end])->lt($details[$start])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.'.$end => 'End date cannot precede the start date.']);
            }
        }
        if ($register->department === 'operations_projects') {
            app(\App\Services\OperationsTracker::class)->validate($register->kind, $details);
        }
        DB::transaction(function () use ($request, $register, $v, $details) {
            $record = PerformanceRegister::whereKey($register->id)->lockForUpdate()->firstOrFail();
            abort_if($record->approved_at, 409, 'Approved source records are locked.');
            abort_unless(hash_equals(hash('sha256', json_encode($record->getAttributes())), $v['token']), 409, 'This source record changed. Refresh before saving.');
            $before = $record->getAttributes();
            $metadata = array_filter($record->data, fn ($key) => str_starts_with($key, '_source_'), ARRAY_FILTER_USE_KEY);
            $record->data = $details + $metadata;
            $record->submitted_by = $request->user()->id;
            $record->status = 'submitted';
            $record->save();
            PerformanceWorkflow::record('register', $record->id, $request->user()->id, 'correct_source', $before, $record->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Source details updated and sent for independent review.');
    }

    public function task(Request $request)
    {
        $v = $request->validate(['task_id' => 'required|integer|exists:tasks,id', 'budget_id' => 'nullable|integer|exists:project_budgets,id', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $task = Task::findOrFail($v['task_id']);
        $department = User::normalizeDepartmentKey($task->department);
        abort_unless(PerformanceAccess::manages($request->user(), $department), 403);
        $kind = match ($department) {
            'creatives' => 'brief','operations_projects' => 'project','brands_marketing' => 'campaign',default => null
        };
        abort_unless($kind, 422, 'This department uses its dedicated source module.');
        $owner = User::findOrFail($task->assigned_to);
        abort_unless($owner->isActive() && User::normalizeDepartmentKey($owner->department) === $department, 422, 'Confirm an active owner in the task department first.');
        $data = ['_source_task_id' => $task->id, '_source_task_updated_at' => $task->updated_at->toIso8601String()];
        if (! empty($v['budget_id'])) {
            $budget = ProjectBudget::findOrFail($v['budget_id']);
            abort_unless($budget->canView($request->user()), 403);
            abort_unless((int) $budget->task_id === $task->id && in_array($budget->status, ['CVO Approved']) && in_array(strtoupper($budget->currency), ['GHC', 'GHS', 'GH₵']), 422, 'Choose a CVO-approved GHS budget linked to this task.');
            abort_if($department === 'creatives', 422, 'Creative brief records do not contain a budget field.');
            $data['budget'] = (float) $budget->total_amount;
            $data['_source_budget_id'] = $budget->id;
            $data['_source_budget_updated_at'] = $budget->updated_at->toIso8601String();
        }
        $record = DB::transaction(function () use ($task, $department, $kind, $owner, $v, $data, $request) {
            $record = PerformanceRegister::firstOrCreate(['department' => $department, 'kind' => $kind, 'reference' => 'TASK-'.$task->id], ['title' => $task->title, 'owner_id' => $owner->id, 'client' => $task->client_name, 'project' => $task->title, 'period_start' => $v['period_start'], 'period_end' => $v['period_end'], 'status' => 'submitted', 'data' => $data, 'evidence_url' => route('portal.tasks.edit', $task), 'submitted_by' => $request->user()->id]);
            if ($record->wasRecentlyCreated) {
                PerformanceWorkflow::record('register', $record->id, $request->user()->id, 'link_source', [], $record->getAttributes(), 'Linked existing task; outcomes remain unmeasured until independently reviewed.');
            }

            return $record;
        });

        return back()->with('status', $record->wasRecentlyCreated ? 'Source record linked for independent review. No completion or revenue was inferred.' : 'This task is already linked. The existing record was retained.');
    }
}
