<?php

namespace App\Services;

use App\Models\CreativeRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExecutiveActivity
{
    public function build(Request $request, array $departments): array
    {
        abort_unless($request->user()->canViewExecutiveActivity(), 403);

        $departments += ['executive' => 'Executive'];
        $filters = $request->validate([
            'activity_department' => ['nullable', Rule::in(array_keys($departments))],
            'activity_staff' => 'nullable|integer|min:1',
            'activity_date' => 'nullable|date_format:Y-m-d',
            'activity_section' => 'nullable|string|max:30',
            'activity_activation' => 'nullable|integer|min:1',
        ]);
        $tasks = Task::query()->realWork()->with(['assignee:id,name,department'])
            ->select(['id', 'department', 'assigned_to', 'status', 'completion_review_status', 'created_at', 'due_on', 'supporting_staff_ids']);
        // Expose work titles only for ordinary shared tasks in operational departments.
        $sharedDepartmentAliases = array_merge(...array_map([User::class, 'departmentAliases'],
            ['brands_marketing', 'client_relations', 'operations_projects', 'creatives']));
        $tasks->selectSub(Task::query()->from('tasks as shared_tasks')->select('shared_tasks.title')
            ->whereColumn('shared_tasks.id', 'tasks.id')->whereIn('shared_tasks.department', $sharedDepartmentAliases)
            ->where(fn (Builder $q) => $q->whereNull('shared_tasks.completion_review_status')->orWhere('shared_tasks.completion_review_status', 'approved'))
            ->whereNotIn('shared_tasks.id', CreativeRequest::query()->whereNotNull('task_id')->select('task_id')), 'shared_title');
        if (! empty($filters['activity_staff'])) {
            $id = (int) $filters['activity_staff'];
            $tasks->where(fn (Builder $q) => $q->where('assigned_to', $id)
                ->orWhereJsonContains('supporting_staff_ids', $id)
                ->orWhereJsonContains('supporting_staff_ids', (string) $id));
        }
        if (! empty($filters['activity_department'])) {
            $aliases = User::departmentAliases($filters['activity_department']);
            $tasks->where(fn (Builder $q) => $q->whereIn('department', $aliases)
                ->orWhere(fn (Builder $fallback) => $fallback->where(fn (Builder $blank) => $blank->whereNull('department')->orWhere('department', ''))
                    ->whereHas('assignee', fn (Builder $owner) => $owner->whereIn('department', $aliases))));
        }
        if (! empty($filters['activity_date'])) {
            $tasks->whereDate('created_at', $filters['activity_date']);
        }

        $rows = $tasks->latest('created_at')->orderByDesc('id')->paginate(15, ['*'], 'activity_page')
            ->withPath(route('dashboard', [], false))->withQueryString()->fragment('executive-activity');
        $supportIds = $rows->getCollection()->flatMap(fn (Task $task) => $task->supporting_staff_ids ?? [])->unique();

        $department = $filters['activity_department'] ?? '';
        $staff = isset($filters['activity_staff']) ? (int) $filters['activity_staff'] : null;
        $records = app(ExecutiveDepartmentRecords::class)->build($request, $department, $staff, $filters['activity_section'] ?? null);

        return [
            'records' => $records,
            'departments' => $departments,
            'staff' => User::internalStaff()->where(fn (Builder $users) => $users->where('status', 'active')
                ->orWhereIn('id', Task::query()->realWork()->whereNotNull('assigned_to')->select('assigned_to')))
                ->when($department, fn (Builder $users) => $users->whereIn('department', User::departmentAliases($department)))
                ->orderBy('name')->get(['id', 'name']),
            'rows' => $rows,
            'support' => $supportIds->isEmpty() ? collect() : User::internalStaff()->whereIn('id', $supportIds)->pluck('name', 'id'),
            'filters' => $filters,
        ];
    }
}
