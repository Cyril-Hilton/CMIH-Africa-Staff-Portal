<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(): View
    {
        $tasks = Task::with(['assignee', 'assigner'])->latest()->paginate(10);
        $staff = User::internalStaff()->where('status', 'active')->orderBy('name')->get();

        return view('admin.tasks', compact('tasks', 'staff'));
    }

    public function edit(Task $task): View
    {
        $staff = User::internalStaff()->where('status', 'active')->orderBy('name')->get();

        return view('admin.tasks-edit', compact('task', 'staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $departments = ['hr_admin', 'finance', 'client_relations', 'operations_projects', 'brands_marketing', 'creatives', 'operations', 'client_service', 'brands', 'admin', 'transport'];
        $priorities = ['high', 'medium', 'low'];

        $statusList = [
            'Open', 'In Progress', 'Awaiting Approval', 'Completed', 'Cancelled', 'Awaiting Feedback', 'Sent', 'Approved', 'Rejected', 'Paid', 'Overdue',
            'open', 'in_progress', 'awaiting_approval', 'completed', 'cancelled', 'awaiting_feedback', 'sent', 'approved', 'rejected', 'paid', 'overdue',
            'on_hold', 'failed', 'done'
        ];

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'department' => ['required', 'string', Rule::in($departments)],
            'status' => ['required', 'string', 'max:32', Rule::in($statusList)],
            'priority' => ['required', 'string', Rule::in($priorities)],
            'due_on' => ['nullable', 'date'],
            'supporting_staff_ids' => ['nullable', 'array'],
            'supporting_staff_ids.*' => ['integer', 'exists:users,id'],
            'supporting_roles' => ['nullable', 'string', 'max:500'],
        ]);

        $statusMap = [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'awaiting_approval' => 'Awaiting Approval',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'awaiting_feedback' => 'Awaiting Feedback',
            'sent' => 'Sent',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'on_hold' => 'Cancelled',
            'failed' => 'Rejected',
            'done' => 'Completed',
        ];
        $validated['status'] = $statusMap[strtolower($validated['status'])] ?? $validated['status'];

        $deptNormMap = [
            'admin'               => 'hr_admin',
            'hr_admin'            => 'hr_admin',
            'transport'           => 'hr_admin',
            'finance'             => 'finance',
            'client_service'      => 'client_relations',
            'client_relations'    => 'client_relations',
            'operations'          => 'operations_projects',
            'operations_projects' => 'operations_projects',
            'brands'              => 'brands_marketing',
            'brands_marketing'    => 'brands_marketing',
            'creatives'           => 'creatives',
            'creative'            => 'creatives',
        ];
        $validated['department'] = $deptNormMap[strtolower(trim($validated['department']))] ?? $validated['department'];

        Task::create([
            ...$validated,
            'assigned_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Task assigned.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $departments = ['hr_admin', 'finance', 'client_relations', 'operations_projects', 'brands_marketing', 'creatives', 'operations', 'client_service', 'brands', 'admin', 'transport'];
        $priorities = ['high', 'medium', 'low'];
        $statusList = [
            'Open', 'In Progress', 'Awaiting Approval', 'Completed', 'Cancelled', 'Awaiting Feedback', 'Sent', 'Approved', 'Rejected', 'Paid', 'Overdue',
            'open', 'in_progress', 'awaiting_approval', 'completed', 'cancelled', 'awaiting_feedback', 'sent', 'approved', 'rejected', 'paid', 'overdue',
            'on_hold', 'failed', 'done'
        ];

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'department' => ['required', 'string', Rule::in($departments)],
            'status' => ['required', 'string', 'max:32', Rule::in($statusList)],
            'priority' => ['required', 'string', Rule::in($priorities)],
            'due_on' => ['nullable', 'date'],
            'supporting_staff_ids' => ['nullable', 'array'],
            'supporting_staff_ids.*' => ['integer', 'exists:users,id'],
            'supporting_roles' => ['nullable', 'string', 'max:500'],
        ]);

        $statusMap = [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'awaiting_approval' => 'Awaiting Approval',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'awaiting_feedback' => 'Awaiting Feedback',
            'sent' => 'Sent',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'on_hold' => 'Cancelled',
            'failed' => 'Rejected',
            'done' => 'Completed',
        ];
        $validated['status'] = $statusMap[strtolower($validated['status'])] ?? $validated['status'];

        $deptNormMap = [
            'admin'               => 'hr_admin',
            'hr_admin'            => 'hr_admin',
            'transport'           => 'hr_admin',
            'finance'             => 'finance',
            'client_service'      => 'client_relations',
            'client_relations'    => 'client_relations',
            'operations'          => 'operations_projects',
            'operations_projects' => 'operations_projects',
            'brands'              => 'brands_marketing',
            'brands_marketing'    => 'brands_marketing',
            'creatives'           => 'creatives',
            'creative'            => 'creatives',
        ];
        $validated['department'] = $deptNormMap[strtolower(trim($validated['department']))] ?? $validated['department'];

        $task->update($validated);

        return redirect()->route('admin.tasks')->with('status', 'Task updated.');
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('admin.tasks')->with('status', 'Task deleted.');
    }
}
