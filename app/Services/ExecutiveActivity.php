<?php

namespace App\Services;

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
        $tasks = Task::query()->realWork()->with(['assignee:id,name,department,line_manager_id', 'creativeRequest'])
            ->select(['id', 'title', 'department', 'assigned_to', 'assigned_by', 'copied_manager_ids', 'status', 'completion_review_status', 'created_at', 'due_on', 'supporting_staff_ids']);
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
        foreach ($rows as $task) {
            $task->shared_title = $task->canBeViewedBy($request->user()) ? ($task->title ?: $task->creativeRequest?->title ?: 'Untitled task') : null;
            // Do not pass restricted titles or briefs to the dashboard view.
            $task->offsetUnset('title');
            $task->unsetRelation('creativeRequest');
        }
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
