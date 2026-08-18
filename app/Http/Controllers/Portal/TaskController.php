<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TaskStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $view = $request->query('view', 'my-tasks'); // 'my-tasks' | 'pending' | 'create'

        //  My Tasks view 
        if ($view === 'my-tasks') {
            $filter = $request->query('filter', 'all');
            $filter = $filter === 'mine' ? 'all' : $filter;
            $allowedFilters = ['all', 'pending', 'completed', 'overdue', 'awaiting_approval', 'sent_back', 'high_priority'];
            $filter = in_array($filter, $allowedFilters, true) ? $filter : 'all';
            $sort = $request->string('sort')->toString();
            $allowedSorts = ['updated', 'created', 'due', 'priority', 'status', 'title'];
            $sort = in_array($sort, $allowedSorts, true) ? $sort : 'updated';
            $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

            // Keep the list broad so users can see work they created or support,
            // while the KPI cards below use the narrower definitions shown on screen.
            $query = Task::with(['assigner'])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('assigned_by', $user->id)
                      ->orWhereJsonContains('supporting_staff_ids', $user->id)
                      ->orWhereJsonContains('supporting_staff_ids', (string) $user->id);
                })
                ->realWork();

            $this->applyPersonalTaskFilter($query, $filter);
            $this->applyPersonalTaskSort($query, $sort, $direction);

            $myTasks = $query->paginate(8, ['*'], 'my_page')->withQueryString();

            // Quick stats using the same definitions as the card labels.
            $taskStats = TaskStatsService::forUser($user);
            $myTotal = $taskStats['accountable_total'];
            $myCreatedTotal = $taskStats['created_total'];
            $myCompleted = $taskStats['completed'];
            $myApproved = $taskStats['approved'];
            $myApprovalLabel = $taskStats['approval_label'];
            $myInProgress = $taskStats['pending'];
            $myOverdue = $taskStats['overdue'];

            return view('portal.tasks-my', compact(
                'user',
                'myTasks',
                'myTotal',
                'myCreatedTotal',
                'myCompleted',
                'myApproved',
                'myApprovalLabel',
                'myInProgress',
                'myOverdue',
                'filter',
                'sort',
                'direction'
            ));
        }

        //  Pending Tasks view 
        if ($view === 'pending') {
            $sort      = $request->string('sort')->toString();
            $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
            $pendingFilter = $request->query('filter', 'all');
            $allowedPendingFilters = ['all', 'overdue', 'high_priority', 'awaiting_approval', 'sent_back'];
            $pendingFilter = in_array($pendingFilter, $allowedPendingFilters, true) ? $pendingFilter : 'all';

            $pendingQuery = Task::with(['assigner', 'assignee'])
                ->pendingFinalSignOff();

            match ($pendingFilter) {
                'overdue' => $pendingQuery->whereNotNull('due_on')->where('due_on', '<', now()),
                'high_priority' => $pendingQuery->where('priority', 'High'),
                'awaiting_approval' => $pendingQuery->where(function ($approvalQuery) {
                    $approvalQuery->where('status', 'Awaiting Approval')
                        ->orWhere('completion_review_status', 'pending');
                }),
                'sent_back' => $pendingQuery->where(function ($sentBackQuery) {
                    $sentBackQuery->where('status', 'Awaiting Feedback')
                        ->orWhere('completion_review_status', 'reverted');
                }),
                default => null,
            };

            switch ($sort) {
                case 'task':       $pendingQuery->orderBy('title', $direction); break;
                case 'staff':      $pendingQuery->orderBy(User::select('name')->whereColumn('users.id', 'tasks.assigned_to'), $direction); break;
                case 'timeline':   $pendingQuery->orderBy('due_on', $direction); break;
                case 'department': $pendingQuery->orderBy('department', $direction); break;
                case 'priority':   $pendingQuery->orderBy('priority', $direction); break;
                case 'status':     $pendingQuery->orderBy('status', $direction); break;
                default:           $pendingQuery->orderByRaw("CASE WHEN due_on IS NULL THEN 1 ELSE 0 END, due_on ASC");
            }

            $pendingTasks = $pendingQuery->paginate(15, ['*'], 'p_page')->withQueryString();

            // Find tasks awaiting completion review specifically by the logged-in line manager
            $allApprovals = Task::with(['assigner', 'assignee'])
                ->realWork()
                ->where(function ($q) {
                    $q->where('status', 'Awaiting Approval')
                      ->orWhere('completion_review_status', 'pending');
                })
                ->get()
                ->filter(fn (Task $task) => $this->canReviewCompletion($task, $user))
                ->values();

            $approvalPage = (int) $request->input('approval_page', 1);
            $perPageApprovals = 10;
            $myPendingApprovals = new \Illuminate\Pagination\LengthAwarePaginator(
                $allApprovals->slice(($approvalPage - 1) * $perPageApprovals, $perPageApprovals)->values(),
                $allApprovals->count(),
                $perPageApprovals,
                $approvalPage,
                ['path' => $request->url(), 'pageName' => 'approval_page', 'query' => $request->query()]
            );

            // Overdue breakdown
            $overdueCount = Task::query()
                ->pendingFinalSignOff()
                ->whereNotNull('due_on')
                ->where('due_on', '<', now())
                ->count();
            $highPrioCount = Task::query()
                ->pendingFinalSignOff()
                ->where('priority', 'High')
                ->count();
            $totalPending = Task::query()->pendingFinalSignOff()->count();

            return view('portal.tasks-pending', compact(
                'user', 'pendingTasks', 'sort', 'direction', 'pendingFilter', 'overdueCount', 'highPrioCount', 'totalPending', 'myPendingApprovals'
            ));
        }

        //  Create Task view 
        // Also serves as the fallback default if ?view=create
        $hasTodayTask = Task::where('assigned_to', $user->id)
            ->whereDate('created_at', today())->exists();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', today())
            ->first();

        // Team members list so creator can assign to others (managers+)
        $canAssignOthers = in_array($user->access_role, ['admin', 'super_admin', 'manager'])
            || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah']);
        $teamMembers = $canAssignOthers ? User::internalStaff()->orderBy('name')->get() : collect([$user]);
        $campaigns   = \App\Models\Campaign::orderBy('name')->get();
        $allStaff    = User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $managers = $this->taskApprovalManagers();

        $requiresCompletionManager = $user->mustRouteTaskCompletionToManager();

        return view('portal.tasks-create', compact(
            'user',
            'hasTodayTask',
            'todayAttendance',
            'teamMembers',
            'canAssignOthers',
            'campaigns',
            'allStaff',
            'managers',
            'requiresCompletionManager'
        ));
    }

    /**
     * Store a newly created task in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $requiresCompletionManager = $user->mustRouteTaskCompletionToManager();

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'due_on' => ['nullable', 'date'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'supporting_staff_ids' => ['nullable', 'array'],
            'supporting_staff_ids.*' => ['integer', 'exists:users,id'],
            'copied_manager_ids' => ['nullable', 'array'],
            'copied_manager_ids.*' => ['integer', 'exists:users,id'],
            'completion_manager_id' => [$requiresCompletionManager ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'supporting_roles' => ['nullable', 'string', 'max:500'],
        ]);

        $isDeveloper  = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
        $canAssign    = $isDeveloper || in_array($user->access_role, ['admin', 'super_admin', 'manager']);
        $assignTo     = $canAssign && $request->filled('assign_to') ? (int) $request->input('assign_to') : $user->id;

        // Derive department from the person being assigned
        $assignee = $assignTo !== $user->id ? \App\Models\User::find($assignTo) : $user;
        $deptRaw  = ($assignee && ($assignee->access_role === 'super_admin' || $assignee->job_level === 'super_admin' || !$assignee->department)) 
            ? 'executive' 
            : ($assignee?->department ?: ($user->department ?: 'operations_projects'));

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
        $dept = $deptNormMap[strtolower(trim($deptRaw))] ?? $deptRaw;

        $copiedManagerIds = $this->normalizedIds($request->input('copied_manager_ids', []));
        $requiredManager = null;
        if ($requiresCompletionManager) {
            $requiredManager = $this->resolveTaskApprovalManager($user, $assignee, (int) $request->input('completion_manager_id'));

            if (! $requiredManager) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'completion_manager_id' => 'Please select a valid active line manager for completion approval.',
                    ]);
            }

            $copiedManagerIds[] = (int) $requiredManager->id;
            $copiedManagerIds = array_values(array_unique($copiedManagerIds));
        }

        $dueOn = $request->input('due_on');
        if ($dueOn) {
            $carbonDue = \Illuminate\Support\Carbon::parse($dueOn);
            if ($carbonDue->hour === 0 && $carbonDue->minute === 0 && $carbonDue->second === 0) {
                $carbonDue->endOfDay();
            }
            $dueOn = $carbonDue;
        }

        $task = Task::create([
            'title'       => $request->input('title'),
            'details'     => $request->input('details'),
            'assigned_to' => $assignTo,
            'assigned_by' => $user->id,
            'department'  => $dept,
            'status'      => 'Open',
            'priority'    => ucfirst($request->input('priority')),
            'due_on'      => $dueOn,
            'progress'    => 10, // open is 10%
            'client_name' => $request->input('client_name'),
            'campaign_id' => $request->input('campaign_id'),
            'supporting_staff_ids' => $this->normalizedIds($request->input('supporting_staff_ids', [])),
            'copied_manager_ids' => $copiedManagerIds,
            'supporting_roles' => $request->input('supporting_roles'),
            'custom_fields' => $requiredManager ? ['completion_manager_id' => (int) $requiredManager->id] : [],
        ]);

        // Send notifications
        $dashUrl = route('dashboard', ['tab' => $dept]);

        // 1. Notify Assignee
        if ($assignTo !== $user->id) {
            \App\Services\NotificationService::send(
                $assignTo,
                'New Task Assigned',
                "You have been assigned the task: {$task->title} by {$user->name}",
                $dashUrl
            );
        }

        // 2. Notify Supporting Staff
        if ($task->supporting_staff_ids && is_array($task->supporting_staff_ids)) {
            foreach ($task->supporting_staff_ids as $staffId) {
                if ((int)$staffId !== $user->id) {
                    \App\Services\NotificationService::send(
                        (int)$staffId,
                        'Added as Supporting Staff',
                        "You have been added as supporting staff on task: {$task->title} by {$user->name}",
                        $dashUrl
                    );
                }
            }
        }

        // 3. Notify Copied Managers
        if ($task->copied_manager_ids && is_array($task->copied_manager_ids)) {
            foreach ($task->copied_manager_ids as $managerId) {
                if ((int)$managerId !== $user->id) {
                    \App\Services\NotificationService::send(
                        (int)$managerId,
                        'Copied on Task',
                        "You have been copied as a manager on task: {$task->title} by {$user->name}",
                        $dashUrl
                    );
                }
            }
        }

        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', today())
            ->first();
        $message = $todayAttendance
            ? 'Task created successfully. You are already clocked in for today.'
            : 'Task created! Head to the dashboard to clock in.';

        return redirect()->route('portal.tasks', ['view' => 'create'])->with('status', $message);
    }


    /**
     * Show the form for editing the specified task.
     */
    public function edit(Task $task, Request $request): View
    {
        $user = $request->user();
        if (! $task->canBeEditedBy($user)) {
            abort(403);
        }

        $campaigns = \App\Models\Campaign::orderBy('name')->get();
        $allStaff  = User::internalStaff()->where('status', 'active')->orderBy('name')->get();
        $managers = $this->taskApprovalManagers();

        $requiresCompletionManager = $user->mustRouteTaskCompletionToManager();
        $selectedCompletionManagerId = old(
            'completion_manager_id',
            $task->custom_fields['completion_manager_id'] ?? collect($task->copied_manager_ids ?? [])->first()
        );
        $canReviewCompletion = $this->canReviewCompletion($task, $user);

        return view('portal.tasks-edit', compact(
            'task',
            'campaigns',
            'allStaff',
            'managers',
            'requiresCompletionManager',
            'selectedCompletionManagerId',
            'canReviewCompletion'
        ));
    }

    /**
     * Update the specified task in storage.
     */
    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        if (! $task->canBeEditedBy($user)) {
            abort(403);
        }
        $requiresCompletionManager = $user->mustRouteTaskCompletionToManager();

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', 'string', 'in:High,Medium,Low,high,medium,low'],
            'status' => ['required', 'string', 'in:Open,In Progress,Awaiting Approval,Completed,Cancelled,Awaiting Feedback,Sent,Approved,Rejected,Paid,Overdue,open,in_progress,awaiting_approval,completed,cancelled,awaiting_feedback,sent,approved,rejected,paid,overdue'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_on' => ['nullable', 'date'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'supporting_staff_ids' => ['nullable', 'array'],
            'supporting_staff_ids.*' => ['integer', 'exists:users,id'],
            'copied_manager_ids' => ['nullable', 'array'],
            'copied_manager_ids.*' => ['integer', 'exists:users,id'],
            'completion_manager_id' => [$requiresCompletionManager ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'supporting_roles' => ['nullable', 'string', 'max:500'],
        ]);

        $oldTitle = $task->title;
        $newTitle = $request->input('title');

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
        ];

        $priorityMap = [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ];

        $status = $request->input('status');
        $status = $statusMap[strtolower($status)] ?? ucfirst($status);

        $priority = $request->input('priority');
        $priority = $priorityMap[strtolower($priority)] ?? ucfirst($priority);

        $progress = Task::progressForStatus($status, (int) ($task->progress ?? 0));
        $copiedManagerIds = $this->normalizedIds($request->input('copied_manager_ids', []));
        $supportingStaffIds = $this->normalizedIds($request->input('supporting_staff_ids', []));

        $completionRequested = in_array($status, ['Completed', 'Approved', 'Paid', 'Done'], true);
        $requiredManager = null;
        if ($requiresCompletionManager) {
            $requiredManager = $this->resolveTaskApprovalManager($user, $task->assignee, (int) $request->input('completion_manager_id'));

            if ($requiredManager) {
                $copiedManagerIds[] = (int) $requiredManager->id;
                $copiedManagerIds = array_values(array_unique($copiedManagerIds));
            } else {
                return back()
                    ->withInput()
                    ->withErrors([
                        'completion_manager_id' => 'Please select a valid active line manager for completion approval.',
                    ]);
            }
        }

        $needsCompletionReview = $completionRequested && $requiresCompletionManager;
        if ($needsCompletionReview) {
            $status = 'Awaiting Approval';
            $progress = 95;
        }

        $dueOn = $request->input('due_on');
        if ($dueOn) {
            $carbonDue = \Illuminate\Support\Carbon::parse($dueOn);
            if ($carbonDue->hour === 0 && $carbonDue->minute === 0 && $carbonDue->second === 0) {
                $carbonDue->endOfDay();
            }
            $dueOn = $carbonDue;
        }

        // Keep track of old ids for notification checking
        $oldAssignee = $task->assigned_to;
        $oldCollabs = $this->normalizedIds($task->supporting_staff_ids ?? []);
        $oldManagers = $this->normalizedIds($task->copied_manager_ids ?? []);
        $oldStatus = $task->status;
        $customFields = $task->custom_fields ?? [];
        if ($requiredManager) {
            $customFields['completion_manager_id'] = (int) $requiredManager->id;
        }

        $inlineCompletionApproval = ! $needsCompletionReview
            && $completionRequested
            && $task->completion_review_status === 'pending'
            && $this->canReviewCompletion($task, $user);

        $updatePayload = [
            'title' => $newTitle,
            'details' => $request->input('details'),
            'priority' => $priority,
            'status' => $status,
            'progress' => $progress,
            'due_on' => $dueOn,
            'client_name' => $request->input('client_name'),
            'campaign_id' => $request->input('campaign_id'),
            'supporting_staff_ids' => $supportingStaffIds,
            'copied_manager_ids' => $copiedManagerIds,
            'supporting_roles' => $request->input('supporting_roles'),
            'custom_fields' => $customFields,
        ];

        if ($inlineCompletionApproval) {
            $auditNote = "\n[Completion review by {$user->name} on " . now()->format('d M Y H:i') . '] Approved from task editor.';
            $updatePayload['completion_review_status'] = 'approved';
            $updatePayload['completion_reviewed_at'] = now();
            $updatePayload['completion_reviewed_by'] = $user->id;
            $updatePayload['completion_review_note'] = 'Approved from task editor.';
            $updatePayload['notes_feedback'] = ($task->notes_feedback ?? '') . $auditNote;
        }

        $task->update($updatePayload);

        if ($inlineCompletionApproval && $task->completionReviewTask) {
            $task->completionReviewTask->forceFill([
                'status' => 'Completed',
                'progress' => 100,
                'completion_review_status' => 'audit_task',
                'completion_reviewed_at' => now(),
                'completion_reviewed_by' => $user->id,
                'completion_review_note' => 'Completion approved from task editor.',
            ])->save();
        }

        // Send notifications to NEW/UPDATED assignees, collaborators, and managers
        $dashUrl = route('dashboard', ['tab' => $task->department]);

        if ($task->assigned_to !== $oldAssignee && $task->assigned_to !== $user->id) {
            \App\Services\NotificationService::send(
                $task->assigned_to,
                'New Task Assigned',
                "You have been assigned the task: {$task->title} by {$user->name}",
                $dashUrl
            );
        }

        $newCollabs = array_diff($task->supporting_staff_ids ?? [], $oldCollabs);
        foreach ($newCollabs as $collabId) {
            if ((int)$collabId !== $user->id) {
                \App\Services\NotificationService::send(
                    (int)$collabId,
                    'Added as Supporting Staff',
                    "You have been added as supporting staff on task: {$task->title} by {$user->name}",
                    $dashUrl
                );
            }
        }

        $newManagers = array_diff($task->copied_manager_ids ?? [], $oldManagers);
        foreach ($newManagers as $managerId) {
            if ((int)$managerId !== $user->id) {
                \App\Services\NotificationService::send(
                    (int)$managerId,
                    'Copied on Task',
                    "You have been copied as a manager on task: {$task->title} by {$user->name}",
                    $dashUrl
                );
            }
        }

        if ($needsCompletionReview) {
            $this->routeCompletionReview($task->refresh(), $user, $requiredManager);
        } elseif ($inlineCompletionApproval) {
            NotificationService::send(
                (int) $task->assigned_to,
                'Task Completion Approved',
                "{$user->name} approved your completed task: {$task->title}.",
                route('portal.tasks.edit', $task)
            );
        } elseif ($oldStatus !== 'Awaiting Approval' && $task->status === 'Awaiting Approval') {
            $approvalIds = array_merge(
                [(int) $task->assigned_by],
                array_map('intval', $task->copied_manager_ids ?? [])
            );

            NotificationService::sendApprovalNeededToMany(
                $approvalIds,
                'Task Approval Needed',
                "{$user->name} marked task '{$task->title}' as awaiting approval.",
                route('portal.tasks', ['view' => 'pending']),
                $user->id
            );
        }

        // Sync with today's attendance record if matching daily objective
        $taskDate = $task->created_at ?? now();
        $attendance = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('created_at', $taskDate)
            ->where('daily_objective', $oldTitle)
            ->first();

        if ($attendance) {
            $attendance->update([
                'daily_objective' => $newTitle,
            ]);
        }

        return redirect()->route('portal.tasks')->with('status', 'Task updated successfully!');
    }

    public function completionReview(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canReviewCompletion($task, $user)) {
            abort(403);
        }

        if ($task->completion_review_status !== 'pending') {
            return back()->withErrors([
                'review_comment' => 'This task is not currently waiting for completion approval.',
            ]);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,revert'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $comment = trim((string) ($validated['review_comment'] ?? ''));

        // Build "on behalf of" label when a peer or acting line manager is reviewing
        $onBehalfLabel = '';
        $designatedId  = $this->designatedReviewerIdForTask($task);
        if ($designatedId > 0 && (int) $user->id !== $designatedId) {
            $designatedManager = User::find($designatedId);
            if ($designatedManager) {
                if ($user->isActingLineManagerFor($designatedId)) {
                    $onBehalfLabel = " (Acting Line Manager on behalf of {$designatedManager->name})";
                } elseif ($user->isPeerLineManagerOf($designatedId)) {
                    $onBehalfLabel = " (on behalf of Line Manager {$designatedManager->name})";
                }
            }
        }

        $auditNote = "\n[Completion review by {$user->name}{$onBehalfLabel} on " . now()->format('d M Y H:i') . '] '
            . ($validated['action'] === 'approve' ? 'Approved.' : 'Sent back for rework.')
            . ($comment !== '' ? " Comment: {$comment}" : '');

        if ($validated['action'] === 'approve') {
            $task->forceFill([
                'status' => 'Completed',
                'progress' => 100,
                'completion_review_status' => 'approved',
                'completion_reviewed_at' => now(),
                'completion_reviewed_by' => $user->id,
                'completion_review_note' => $comment ?: null,
                'notes_feedback' => ($task->notes_feedback ?? '') . $auditNote,
            ])->save();

            if ($task->completionReviewTask) {
                $task->completionReviewTask->forceFill([
                    'status' => 'Completed',
                    'progress' => 100,
                    'completion_review_status' => 'audit_task',
                    'completion_reviewed_at' => now(),
                    'completion_reviewed_by' => $user->id,
                    'completion_review_note' => $comment ?: 'Completion approved.',
                ])->save();
            }

            NotificationService::send(
                (int) $task->assigned_to,
                'Task Completion Approved',
                "{$user->name} approved your completed task: {$task->title}.",
                route('portal.tasks.edit', $task)
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task completion approved. It is now visible on the Mega Table.',
                    'task_id' => $task->id,
                ]);
            }

            return back()->with('status', 'Task completion approved. It is now visible on the Mega Table.');
        }

        $task->forceFill([
            'status' => 'Awaiting Feedback',
            'progress' => 70,
            'completion_review_status' => 'reverted',
            'completion_reviewed_at' => now(),
            'completion_reviewed_by' => $user->id,
            'completion_review_note' => $comment ?: 'Please review and redo this task before resubmitting.',
            'notes_feedback' => ($task->notes_feedback ?? '') . $auditNote,
        ])->save();

        if ($task->completionReviewTask) {
            $task->completionReviewTask->forceFill([
                'status' => 'Completed',
                'progress' => 100,
                'completion_review_status' => 'audit_task',
                'completion_reviewed_at' => now(),
                'completion_reviewed_by' => $user->id,
                'completion_review_note' => $comment ?: 'Completion sent back for rework.',
            ])->save();
        }

        NotificationService::send(
            (int) $task->assigned_to,
            'Task Sent Back for Rework',
            "{$user->name} reviewed '{$task->title}' and sent it back for rework.",
            route('portal.tasks.edit', $task)
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Task sent back to staff for rework.',
                'task_id' => $task->id,
            ]);
        }

        return back()->with('status', 'Task sent back. It will stay off the Mega Table until it is completed and approved.');
    }

    /**
     * Reassign a task to another staff member.
     */
    public function reassign(Request $request, Task $task)
    {
        $user = $request->user();
        if (! $task->canBeEditedBy($user)) {
            abort(403, 'Only active internal CMIH staff can reassign tasks.');
        }

        $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        $newAssignee = \App\Models\User::internalStaff()->findOrFail($request->input('assigned_to'));
        $oldAssignee = $task->assignee?->name ?? 'Unassigned';

        // Derive new department
        $newDeptRaw = ($newAssignee->access_role === 'super_admin' || $newAssignee->job_level === 'super_admin' || !$newAssignee->department)
            ? 'executive'
            : $newAssignee->department;

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
        $newDept = $deptNormMap[strtolower(trim($newDeptRaw))] ?? $newDeptRaw;

        // Append audit trail to notes_feedback
        $audit = "\n[Reassigned by {$user->name} on " . now()->format('d M Y H:i') . "]"
            . " From: {$oldAssignee} -> To: {$newAssignee->name}"
            . ($request->input('reason') ? ". Reason: " . $request->input('reason') : '');

        $task->update([
            'assigned_to'    => $newAssignee->id,
            'department'     => $newDept,
            'notes_feedback' => ($task->notes_feedback ?? '') . $audit,
        ]);

        return back()->with('status', "Task reassigned to {$newAssignee->name} successfully.");
    }

    private function normalizedIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            $ids = $ids ? [$ids] : [];
        }

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function applyPersonalTaskFilter($query, string $filter): void
    {
        match ($filter) {
            'pending' => $query->pendingFinalSignOff(),
            'completed' => $query->approvedForPerformance(),
            'overdue' => $query->pendingFinalSignOff()
                ->whereNotNull('due_on')
                ->where('due_on', '<', now()),
            'awaiting_approval' => $query->where(function ($approvalQuery) {
                $approvalQuery->where('status', 'Awaiting Approval')
                    ->orWhere('completion_review_status', 'pending');
            }),
            'sent_back' => $query->where(function ($sentBackQuery) {
                $sentBackQuery->where('status', 'Awaiting Feedback')
                    ->orWhere('completion_review_status', 'reverted');
            }),
            'high_priority' => $query->where('priority', 'High'),
            default => null,
        };
    }

    private function applyPersonalTaskSort($query, string $sort, string $direction): void
    {
        match ($sort) {
            'due' => $query
                ->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_on', $direction)
                ->orderByDesc('updated_at'),
            'created' => $query->orderBy('created_at', $direction)->orderBy('id', $direction),
            'priority' => $query
                ->orderByRaw("CASE priority WHEN 'High' THEN 3 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 1 ELSE 0 END " . ($direction === 'asc' ? 'asc' : 'desc'))
                ->orderByDesc('updated_at'),
            'status' => $query->orderBy('status', $direction)->orderByDesc('updated_at'),
            'title' => $query->orderBy('title', $direction)->orderByDesc('updated_at'),
            default => $query->orderBy('updated_at', $direction)->orderBy('id', $direction),
        };
    }

    private function taskApprovalManagers()
    {
        return User::internalStaff()->where('status', 'active')
            ->where(function ($q) {
                $q->whereIn('access_role', ['manager', 'admin', 'super_admin'])
                    ->orWhere('job_level', 'super_admin')
                    ->orWhere('position_title', 'like', '%Manager%')
                    ->orWhere('position_title', 'like', '%Director%')
                    ->orWhere('name', 'like', '%Cyril Hilton%');
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (User $manager) => $manager->isLineManager() || $manager->isCvoOrSuperAdmin())
            ->values();
    }

    private function resolveTaskApprovalManager(User $user, ?User $assignee = null, ?int $selectedManagerId = null): ?User
    {
        if (! $selectedManagerId) {
            return null;
        }

        return User::internalStaff()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->get()
            ->first(fn (User $candidate) => (int) $candidate->id === (int) $selectedManagerId
                && ($candidate->isLineManager() || $candidate->isCvoOrSuperAdmin()));
    }

    private function routeCompletionReview(Task $task, User $actor, ?User $manager): void
    {
        if (! $manager) {
            return;
        }

        $details = "Please audit whether {$actor->name} actually completed this task before it appears on the Mega Table."
            . "\n\nTask: {$task->title}";

        if ($task->details) {
            $details .= "\n\nOriginal details:\n" . strip_tags((string) $task->details);
        }

        $reviewTask = $task->completionReviewTask;
        $reviewPayload = [
            'title' => "Audit completion: {$task->title}",
            'details' => $details,
            'assigned_to' => $manager->id,
            'assigned_by' => $actor->id,
            'department' => $manager->department ?: $task->department,
            'status' => 'Open',
            'priority' => 'High',
            'due_on' => now()->endOfDay(),
            'progress' => 10,
            'completion_review_status' => 'audit_task',
            'custom_fields' => [
                'review_type' => 'task_completion',
                'linked_task_id' => $task->id,
            ],
        ];

        if ($reviewTask) {
            $reviewTask->forceFill($reviewPayload)->save();
        } else {
            $reviewTask = Task::create($reviewPayload);
        }

        $task->forceFill([
            'status' => 'Awaiting Approval',
            'progress' => 95,
            'completion_review_status' => 'pending',
            'completion_review_requested_at' => now(),
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_task_id' => $reviewTask->id,
            'completion_review_note' => null,
        ])->save();

        NotificationService::sendApprovalNeededToMany(
            [$manager->id],
            'Task Completion Audit Needed',
            "{$actor->name} marked '{$task->title}' as complete. Please review before it appears on the Mega Table.",
            route('portal.tasks.edit', $task),
            $actor->id
        );
    }

    private function canReviewCompletion(Task $task, User $user): bool
    {
        if ($user->isCvoOrSuperAdmin()) {
            return true;
        }

        if ($this->canSelfApproveAssociatedCompletion($task, $user)) {
            return true;
        }

        $copiedManagerIds = $this->normalizedIds($task->copied_manager_ids ?? []);
        if (in_array((int) $user->id, $copiedManagerIds, true)) {
            return true;
        }

        $assigneeLineManagerId = (int) ($task->assignee?->line_manager_id ?? 0);
        if ($assigneeLineManagerId > 0 && $assigneeLineManagerId === (int) $user->id) {
            return true;
        }

        // Acting relief line manager for the assignee's line manager
        if ($assigneeLineManagerId > 0 && $user->isActingLineManagerFor($assigneeLineManagerId)) {
            return true;
        }

        // Peer line manager in same department as the assignee's line manager
        if ($assigneeLineManagerId > 0 && $user->isPeerLineManagerOf($assigneeLineManagerId)) {
            return true;
        }

        // Acting relief line manager for custom completion manager, assigner, or copied manager
        $customCompletionManagerId = (int) ($task->custom_fields['completion_manager_id'] ?? 0);
        if ($customCompletionManagerId > 0) {
            if ((int) $user->id === $customCompletionManagerId
                || $user->isActingLineManagerFor($customCompletionManagerId)
                || $user->isPeerLineManagerOf($customCompletionManagerId)) {
                return true;
            }
        }

        $assignedById = (int) $task->assigned_by;
        if ($assignedById > 0 && ($user->isActingLineManagerFor($assignedById) || $user->isPeerLineManagerOf($assignedById))) {
            return true;
        }

        foreach ($copiedManagerIds as $copiedManagerId) {
            if ($user->isActingLineManagerFor($copiedManagerId) || $user->isPeerLineManagerOf($copiedManagerId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the ID of the primary designated reviewer for a task (used to build "on behalf of" labels).
     */
    private function designatedReviewerIdForTask(Task $task): int
    {
        $customCompletionManagerId = (int) ($task->custom_fields['completion_manager_id'] ?? 0);
        if ($customCompletionManagerId > 0) {
            return $customCompletionManagerId;
        }

        $assigneeLineManagerId = (int) ($task->assignee?->line_manager_id ?? 0);
        if ($assigneeLineManagerId > 0) {
            return $assigneeLineManagerId;
        }

        $copiedManagerIds = $this->normalizedIds($task->copied_manager_ids ?? []);
        if (! empty($copiedManagerIds)) {
            return (int) $copiedManagerIds[0];
        }

        return 0;
    }

    private function canSelfApproveAssociatedCompletion(Task $task, User $user): bool
    {
        if (! $user->isCyrilHilton()) {
            return false;
        }

        $supportingStaffIds = $this->normalizedIds($task->supporting_staff_ids ?? []);

        return (int) $task->assigned_to === (int) $user->id
            || in_array((int) $user->id, $supportingStaffIds, true);
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(Task $task, Request $request)
    {
        $user = $request->user();
        if (! $task->canBeEditedBy($user)) {
            abort(403);
        }

        $task->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Task deleted successfully.']);
        }

        return redirect()->route('portal.tasks')->with('status', 'Task deleted successfully.');
    }
}
