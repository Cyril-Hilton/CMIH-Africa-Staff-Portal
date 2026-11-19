<?php

namespace App\Services;

use App\Models\CrmPipelineProject;
use App\Models\PerformanceRegister;
use App\Models\ProjectExecutionControl;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExecutionPlan
{
    public function validate(string $section, array $input, User $actor): array
    {
        abort_unless(in_array($section, ['schedule', 'process', 'resources', 'assets', 'compliance', 'tasks']), 422);
        $rules = [];
        if ($section === 'schedule') {
            foreach (['setup_at', 'starts_at', 'ends_at', 'teardown_at', 'recap_due_at', 'recap_submitted_at'] as $key) {
                $rules['data.'.$key] = 'nullable|date';
            }
            foreach (['venue', 'city', 'country', 'activation_type', 'brand', 'notes'] as $key) {
                $rules['data.'.$key] = 'nullable|string|max:2000';
            }
            foreach (['staff_required', 'staff_confirmed'] as $key) {
                $rules['data.'.$key] = 'nullable|integer|min:0|max:100000';
            }
            $rules['data.operations_owner_id'] = 'nullable|integer|exists:users,id';
            $rules['data.state'] = 'nullable|in:planning,live,completed,cancelled';
            $rules['data.priority'] = 'nullable|in:Low,Medium,High,Urgent';
        } elseif ($section === 'process') {
            foreach (config('executive_tracker.process') as $key => $label) {
                foreach (['received_at', 'due_at', 'submitted_at', 'completed_at'] as $field) {
                    $rules['data.'.$key.'.'.$field] = 'nullable|date';
                }$rules['data.'.$key.'.owner_id'] = 'nullable|integer|exists:users,id';
                $rules['data.'.$key.'.status'] = 'nullable|in:not_started,in_progress,incomplete,submitted,presented,approved,rejected';
                $rules['data.'.$key.'.evidence'] = 'nullable|string|max:2000';
                $rules['data.'.$key.'.notes'] = 'nullable|string|max:2000';
            }
            $rules['data.budget.budget_id'] = 'nullable|integer|exists:project_budgets,id';
        } elseif ($section === 'compliance') {
            $rules = ['data.owner_id' => 'nullable|integer|exists:users,id', 'data.due_at' => 'nullable|date', 'data.notes' => 'nullable|string|max:2000'];
            foreach (config('executive_tracker.checks') as $key => $label) {
                $rules['data.checks.'.$key.'.response'] = 'nullable|in:yes,no,in_progress,not_required';
                $rules['data.checks.'.$key.'.evidence'] = 'nullable|string|max:2000';
                $rules['data.checks.'.$key.'.reason'] = 'nullable|string|max:2000';
            }
        } else {
            $rules['data.rows'] = 'nullable|array|max:100';
            foreach (['id', 'description', 'party', 'category', 'source', 'location', 'notes', 'phase', 'condition', 'contact'] as $field) {
                $rules['data.rows.*.'.$field] = 'nullable|string|max:2000';
            }
            foreach (['owner_id', 'staff_id', 'approver_id'] as $field) {
                $rules['data.rows.*.'.$field] = 'nullable|integer|exists:users,id';
            }foreach (['required', 'confirmed'] as $field) {
                $rules['data.rows.*.'.$field] = 'nullable|integer|min:0|max:100000';
            }
            foreach (['start_at', 'end_at', 'due_at', 'dispatched_at', 'returned_at'] as $field) {
                $rules['data.rows.*.'.$field] = 'nullable|date';
            }
            $rules['data.rows.*.unit_rate'] = 'nullable|numeric|min:0|max:999999999.99';
            $rules['data.rows.*.exclusive'] = 'nullable|boolean';
            $rules['data.rows.*.task_id'] = 'nullable|integer|exists:tasks,id';
            $rules['data.rows.*.support_ids'] = 'nullable|array|max:50';
            $rules['data.rows.*.support_ids.*'] = 'integer|exists:users,id';
            $rules['data.rows.*.status'] = 'nullable|in:requested,confirmed,contracted,cancelled,sourcing,in_production,ready,dispatched,returned';
        }
        $data = \Illuminate\Support\Facades\Validator::make(['data' => $input], $rules)->validate()['data'] ?? [];
        if (in_array($section, ['resources', 'assets', 'tasks'])) {
            $data['rows'] ??= [];
        }if ($section === 'compliance') {
            $data['checks'] ??= [];
        }if (! empty($data['budget']['budget_id'])) {
            abort_unless(\App\Models\ProjectBudget::findOrFail($data['budget']['budget_id'])->canView($actor), 403);
        }
        $active = function ($id, $field) {
            if ($id && ! User::internalStaff()->where('status', 'active')->whereKey($id)->exists()) {
                throw ValidationException::withMessages([$field => 'Choose an active staff member.']);
            }
        };
        if ($section === 'schedule') {
            if (! empty($data['starts_at']) && ! empty($data['ends_at']) && strtotime($data['ends_at']) < strtotime($data['starts_at'])) {
                throw ValidationException::withMessages(['data.ends_at' => 'End must follow the start.']);
            }$active($data['operations_owner_id'] ?? null, 'data.operations_owner_id');
            if (! empty($data['recap_submitted_at']) && strtotime($data['recap_submitted_at']) > time()) {
                throw ValidationException::withMessages(['data.recap_submitted_at' => 'Actual submission cannot be future-dated.']);
            }
        }
        if ($section === 'process') {
            foreach ($data as $key => &$step) {
                if (($step['status'] ?? null) === 'submitted' && empty($step['completed_at']) && ! empty($step['submitted_at'])) {
                    $step['completed_at'] = $step['submitted_at'];
                }
                $active($step['owner_id'] ?? null, 'data.'.$key.'.owner_id');
                if (in_array($step['status'] ?? '', ['submitted', 'presented', 'approved', 'rejected'], true) && (empty($step['completed_at']) || empty($step['evidence']))) {
                    throw ValidationException::withMessages(['data.'.$key.'.evidence' => 'Record the outcome date and its evidence before closing a process step.']);
                }if (! empty($step['submitted_at']) && strtotime($step['submitted_at']) > time()) {
                    throw ValidationException::withMessages(['data.'.$key.'.submitted_at' => 'Actual submission cannot be future-dated.']);
                }if (! empty($step['completed_at']) && strtotime($step['completed_at']) > time()) {
                    throw ValidationException::withMessages(['data.'.$key.'.completed_at' => 'Actual completion cannot be future-dated.']);
                }
            }
        }
        unset($step);
        if ($section === 'compliance') {
            $active($data['owner_id'] ?? null, 'data.owner_id');
            foreach ($data['checks'] ?? [] as $key => $check) {
                if (($check['response'] ?? '') === 'yes' && empty($check['evidence'])) {
                    throw ValidationException::withMessages(['data.checks.'.$key.'.evidence' => 'Provide an evidence reference for this confirmed check.']);
                }if (($check['response'] ?? '') === 'not_required' && empty($check['reason'])) {
                    throw ValidationException::withMessages(['data.checks.'.$key.'.reason' => 'Explain why this check does not apply.']);
                }
            }
        }
        if (isset($data['rows'])) {
            $data['rows'] = array_values(array_filter($data['rows'], fn ($row) => filled($row['description'] ?? null)));
            foreach ($data['rows'] as &$row) {
                $row['id'] = ! empty($row['id']) ? $row['id'] : (string) Str::uuid();
                $active($row['owner_id'] ?? null, 'data.rows');
                $active($row['staff_id'] ?? null, 'data.rows');
                $active($row['approver_id'] ?? null, 'data.rows');
                foreach ($row['support_ids'] ?? [] as $support) {
                    $active($support, 'data.rows');
                }if ($section === 'assets' && in_array($row['status'] ?? '', ['ready', 'dispatched', 'returned']) && ((int) ($row['confirmed'] ?? 0) < (int) ($row['required'] ?? 0) || empty($row['condition']))) {
                    throw ValidationException::withMessages(['data.rows' => 'Ready assets require the full secured quantity and a condition check.']);
                }if (! empty($row['start_at']) && ! empty($row['end_at']) && strtotime($row['end_at']) < strtotime($row['start_at'])) {
                    throw ValidationException::withMessages(['data.rows' => 'A booking cannot end before it starts.']);
                }if (in_array($section, ['resources', 'assets']) && ($row['required'] ?? 0) < 1) {
                    throw ValidationException::withMessages(['data.rows' => 'Record a positive required quantity for every resource or asset.']);
                }if (($row['confirmed'] ?? 0) > ($row['required'] ?? 0)) {
                    throw ValidationException::withMessages(['data.rows' => 'Confirmed quantity cannot exceed required quantity.']);
                }foreach (['dispatched_at', 'returned_at'] as $actualField) {
                    if (! empty($row[$actualField]) && strtotime($row[$actualField]) > time()) {
                        throw ValidationException::withMessages(['data.rows' => 'Actual dispatch and return cannot be future-dated.']);
                    }
                }if (! empty($row['returned_at']) && (empty($row['dispatched_at']) || strtotime($row['returned_at']) < strtotime($row['dispatched_at']))) {
                    throw ValidationException::withMessages(['data.rows' => 'Record a dispatch before its return.']);
                }
            }unset($row);
        }
        if (isset($data['rows']) && count(array_unique(array_column($data['rows'], 'id'))) !== count($data['rows'])) {
            throw ValidationException::withMessages(['data.rows' => 'Each row must have its own identity.']);
        }
        if (in_array($section, ['resources', 'assets']) && ! FinanceDashboard::canRead($actor) && ! OperationsReporting::manager($actor)) {
            foreach ($data['rows'] as &$limitedRow) {
                unset($limitedRow['unit_rate']);
            }
        }unset($limitedRow);

        return $data;
    }

    public function apply(User $actor, CrmPipelineProject $project, ?PerformanceRegister $activation, array $sections, int $revision, string $reason, array $provenance = []): ProjectExecutionControl
    {
        return DB::transaction(function () use ($actor, $project, $activation, $sections, $revision, $reason, $provenance) {
            $record = ProjectExecutionControl::firstOrCreate($activation ? ['operations_activation_id' => $activation->id] : ['crm_project_id' => $project->id], ['created_by' => $actor->id, 'updated_by' => $actor->id, 'data' => [], 'revision' => 0]);
            $record = ProjectExecutionControl::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->revision === $revision, 409, 'Execution commitments changed. Reopen the entry, reconcile the latest plan and resubmit.');
            $before = $record->getAttributes();
            $all = $record->data;
            foreach ($sections as $section => $data) {
                if (in_array($section, ['resources', 'assets'])) {
                    foreach ($data['rows'] as &$line) {
                        $prior = collect($all[$section]['rows'] ?? [])->firstWhere('id', $line['id']);
                        if (! FinanceDashboard::canRead($actor) && ! OperationsReporting::manager($actor)) {
                            unset($line['unit_rate']);
                        }if (! array_key_exists('unit_rate', $line) && isset($prior['unit_rate'])) {
                            $line['unit_rate'] = $prior['unit_rate'];
                        }
                    }
                }unset($line);
                if ($section === 'tasks') {
                    foreach ($data['rows'] as &$item) {
                        $known = collect($all['tasks']['rows'] ?? [])->firstWhere('id', $item['id']);
                        $task = ! empty($known['task_id']) ? Task::findOrFail($known['task_id']) : new Task(['status' => 'open', 'assigned_by' => $actor->id]);
                        abort_if(! empty($item['task_id']) && ((int) $item['task_id'] !== (int) ($known['task_id'] ?? 0)), 422, 'Link only the original task.');
                        $owner = User::find($item['owner_id'] ?? null);
                        $task->fill(['title' => $item['description'], 'client_name' => $project->client_name, 'details' => $item['notes'] ?? null, 'assigned_to' => $owner?->id, 'department' => $owner ? User::normalizeDepartmentKey($owner->department) : null, 'due_on' => $item['due_at'] ?? null, 'supporting_staff_ids' => $item['support_ids'] ?? [], 'custom_fields' => array_merge($task->custom_fields ?? [], ['executive_control_project_id' => $project->id, 'executive_control_activation_id' => $activation?->id, 'executive_control_item_id' => $item['id'], 'executive_phase' => $item['phase'] ?? null, 'executive_starts_at' => $item['start_at'] ?? null, 'executive_approver_id' => $item['approver_id'] ?? null])]);
                        $task->save();
                        $item['task_id'] = $task->id;
                    }unset($item);
                }
                if ($section === 'compliance') {
                    foreach ($data['checks'] as $key => &$check) {
                        $prior = $all['compliance']['checks'][$key] ?? [];
                        if (array_intersect_key($prior, $check) !== $check) {
                            $check['recorded_by'] = $actor->id;
                            $check['recorded_at'] = now()->toIso8601String();
                        } else {
                            $check = $prior;
                        }
                    }
                }unset($check);
                $all[$section] = $data;
            }
            $participants = [];
            foreach ($all['process'] ?? [] as $step) {
                $participants[] = $step['owner_id'] ?? null;
            }foreach (['resources', 'assets', 'tasks'] as $kind) {
                foreach ($all[$kind]['rows'] ?? [] as $row) {
                    $participants[] = $row['owner_id'] ?? null;
                    $participants[] = $row['staff_id'] ?? null;
                    $participants = array_merge($participants, $row['support_ids'] ?? []);
                }
            }$participants[] = $all['compliance']['owner_id'] ?? null;
            $participants[] = $all['schedule']['operations_owner_id'] ?? null;
            $all['participant_ids'] = array_values(array_unique(array_map('intval', array_filter($participants))));
            $all['_source_entry'] = $provenance ?: ['type' => 'executive', 'entered_by' => $actor->id, 'entered_at' => now()->toIso8601String()];
            $record->update(['data' => $all, 'revision' => $record->revision + 1, 'updated_by' => $actor->id]);
            PerformanceWorkflow::record('project_control', $record->id, $actor->id, count($sections) === 1 ? 'update_'.array_key_first($sections) : 'update_execution_plan', $before, $record->getAttributes(), $reason);
            foreach (array_unique(array_merge($all['participant_ids'], [(int) $project->owner_id])) as $id) {
                PerformanceWorkflow::notify($id, 'project-control-'.$record->id.'-'.$record->revision, count($sections) === 1 ? 'Project '.array_key_first($sections).' updated' : 'Execution plan updated', $project->project_name.' · updated by '.$actor->name.'. '.$reason, route('portal.executive.controls.show', $record));
            }

            return $record;
        });
    }

    public function forActivation(?PerformanceRegister $activation): ?ProjectExecutionControl
    {
        if (! $activation) {
            return null;
        }$projectId = $activation->data['crm_project_id'] ?? $activation->data['_crm_project_id'] ?? null;

        return $projectId ? ProjectExecutionControl::where('crm_project_id', $projectId)->first() : ProjectExecutionControl::where('operations_activation_id', $activation->id)->first();
    }

    public function publishActivation(PerformanceRegister $activation, User $actor): void
    {
        $proposal = $activation->data['execution_controls'] ?? null;
        if (! $proposal) {
            return;
        }
        $sections = [];
        foreach ($proposal as $section => $data) {
            $sections[$section] = $this->validate($section, $data, $actor);
        }
        $projectId = $activation->data['crm_project_id'] ?? $activation->data['_crm_project_id'] ?? null;
        $project = $projectId ? CrmPipelineProject::findOrFail($projectId) : new CrmPipelineProject(['project_name' => $activation->project ?: $activation->title, 'client_name' => $activation->client, 'owner_id' => $activation->owner_id, 'source_period_confirmed' => true]);
        $this->apply($actor, $project, $projectId ? null : $activation, $sections, (int) ($activation->data['execution_revision'] ?? 0), 'Operations HOD published the execution plan with the departmental entry.', ['type' => 'operations_register', 'id' => $activation->id, 'entered_by' => $activation->data['execution_entered_by'] ?? $activation->submitted_by, 'entered_at' => $activation->data['execution_entered_at'] ?? $activation->created_at?->toIso8601String(), 'published_by' => $actor->id, 'published_at' => now()->toIso8601String()]);
    }
}
