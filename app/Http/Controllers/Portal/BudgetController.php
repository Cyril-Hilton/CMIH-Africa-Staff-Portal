<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetController extends Controller
{
    private const ALLOWED_CURRENCIES = ['GHC', 'GH₵', 'USD', 'EUR', 'GBP', 'NGN', 'ZAR', 'XOF', 'SLE'];

    private function validateCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === 'GHC') {
            return 'GH₵';
        }
        return in_array($currency, self::ALLOWED_CURRENCIES, true)
            ? $currency : 'GH₵';
    }

    private function isCVO(User $user): bool
    {
        return $user->job_level === 'super_admin'
            || $user->access_role === 'super_admin';
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        // 1. My Budgets (created by the user)
        $myBudgets = ProjectBudget::where('created_by', $user->id)
            ->with(['creator', 'items'])
            ->latest()
            ->paginate(10, ['*'], 'my_page')
            ->withQueryString();

        // 2. Shared Budgets (where user is a collaborator)
        $sharedBudgets = ProjectBudget::whereHas('collaborators', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->with(['creator', 'items'])
        ->latest()
        ->paginate(10, ['*'], 'shared_page')
        ->withQueryString();

        // 3. Review Queue (CVO and Finance department can see submitted budgets)
        $pendingBudgets = collect();
        $historyBudgets = collect();
        if ($isFinance || $this->isCVO($user)) {
            $pendingQuery = ProjectBudget::query();
            $historyQuery = ProjectBudget::query();

            // Determine pending review statuses
            $pendingStatuses = [];
            if ($isFinance) {
                $pendingStatuses = array_merge($pendingStatuses, ['Submitted', 'Submitted to Finance', 'Returned to Finance']);
            }
            if ($this->isCVO($user)) {
                $pendingStatuses = array_merge($pendingStatuses, ['Finance Approved', 'Submitted to CVO']);
            }
            $pendingStatuses = array_unique($pendingStatuses);

            $pendingBudgets = $pendingQuery->whereIn('status', $pendingStatuses)
                ->with(['creator', 'items'])
                ->latest()
                ->paginate(10, ['*'], 'pending_page')
                ->withQueryString();

            // History includes all other submitted/actioned states
            $historyStatuses = ['CVO Approved', 'Rejected', 'Returned for Correction', 'Updated'];
            $historyBudgets = $historyQuery->whereIn('status', $historyStatuses)
                ->with(['creator', 'items'])
                ->latest()
                ->paginate(10, ['*'], 'history_page')
                ->withQueryString();
        }

        return view('portal.budgets.index', compact('user', 'myBudgets', 'sharedBudgets', 'pendingBudgets', 'historyBudgets', 'isFinance'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $tasks = Task::where('assigned_to', $user->id)
            ->realWork()
            ->whereNotIn('status', Task::COMPLETED_STATUSES)
            ->orderBy('title')
            ->get();

        $campaigns = \App\Models\Campaign::orderBy('name')->get();

        $allStaff = User::internalStaff()->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('portal.budgets.create', compact('tasks', 'campaigns', 'allStaff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'task_id'  => ['nullable', 'exists:tasks,id'],
            'currency' => ['nullable', 'string'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'content'  => ['nullable', 'string'],
            'collaborators' => ['nullable', 'array'],
            'collaborators.*.user_id' => ['required', 'exists:users,id'],
            'collaborators.*.permission' => ['required', 'in:view,edit'],
            'imported_items' => ['nullable', 'string'],
        ]);

        $currency = $this->validateCurrency($request->input('currency', 'GHC'));

        $budget = ProjectBudget::create([
            'created_by' => $request->user()->id,
            'task_id'    => $request->input('task_id'),
            'title'      => $request->input('title'),
            'currency'   => $currency,
            'notes'      => $request->input('notes'),
            'content'    => $request->input('content'),
            'status'     => 'Draft',
        ]);

        // Sync Collaborators
        $syncData = [];
        if ($request->filled('collaborators')) {
            foreach ($request->input('collaborators') as $collab) {
                $syncData[$collab['user_id']] = ['permission' => $collab['permission']];
            }
        }
        $budget->collaborators()->sync($syncData);

        // Process Imported Items
        if ($request->filled('imported_items')) {
            $items = json_decode($request->input('imported_items'), true);
            if (is_array($items)) {
                $insertData = [];
                $now = now();
                foreach ($items as $item) {
                    $qty = max((int)($item['quantity'] ?? 1), 1);
                    $price = (float)($item['unit_price'] ?? 0.0);
                    $insertData[] = [
                        'budget_id'   => $budget->id,
                        'description' => trim($item['description'] ?? ''),
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'total'       => $qty * $price,
                        'category'    => isset($item['category']) ? trim($item['category']) : null,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                if (!empty($insertData)) {
                    ProjectBudgetItem::insert($insertData);
                }
                $budget->recalculateTotal();
            }
        }

        return redirect()->route('portal.finance.budgets.show', $budget)
            ->with('status', 'Project budget created! You can now add line items, manage collaborators, or submit it.');
    }

    public function show(ProjectBudget $budget, Request $request): View
    {
        $user = $request->user();
        if (!$budget->canView($user)) {
            abort(403, '🔒 Access Denied. You are not authorized to view this budget.');
        }

        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        $isCVO = $this->isCVO($user);

        // Fetch other active staff for collaborator selection
        $allStaff = User::internalStaff()->where('status', 'active')
            ->where('id', '!=', $budget->created_by)
            ->orderBy('name')
            ->get();

        return view('portal.budgets.show', compact('budget', 'allStaff', 'isFinance', 'isCVO'));
    }

    public function edit(ProjectBudget $budget, Request $request): View
    {
        $user = $request->user();
        if (!$budget->canEdit($user)) {
            abort(403, '🔒 Access Denied. You are not authorized to edit this budget.');
        }

        $tasks = Task::where('assigned_to', $budget->created_by)
            ->realWork()
            ->whereNotIn('status', Task::COMPLETED_STATUSES)
            ->orderBy('title')
            ->get();

        $campaigns = \App\Models\Campaign::orderBy('name')->get();

        $allStaff = User::internalStaff()->where('status', 'active')
            ->where('id', '!=', $budget->created_by)
            ->orderBy('name')
            ->get();

        return view('portal.budgets.edit', compact('budget', 'tasks', 'campaigns', 'allStaff'));
    }

    public function update(Request $request, ProjectBudget $budget): RedirectResponse
    {
        $user = $request->user();
        if (!$budget->canEdit($user)) {
            abort(403, '🔒 Access Denied. You are not authorized to edit this budget.');
        }

        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'task_id'  => ['nullable', 'exists:tasks,id'],
            'currency' => ['nullable', 'string'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'content'  => ['nullable', 'string'],
            'collaborators' => ['nullable', 'array'],
            'collaborators.*.user_id' => ['required', 'exists:users,id'],
            'collaborators.*.permission' => ['required', 'in:view,edit'],
            'imported_items' => ['nullable', 'string'],
        ]);

        $currency = $this->validateCurrency($request->input('currency', 'GHC'));

        $newStatus = $budget->status;
        if ($budget->status !== 'Draft') {
            $newStatus = 'Updated';
        }

        $budget->update([
            'task_id'  => $request->input('task_id'),
            'title'    => $request->input('title'),
            'currency' => $currency,
            'notes'    => $request->input('notes'),
            'content'  => $request->input('content'),
            'status'   => $newStatus,
        ]);

        // Sync Collaborators
        if ($budget->created_by === $user->id || $user->hasRole('super_admin')) {
            $syncData = [];
            if ($request->has('sync_collaborators') || $request->has('collaborators')) {
                foreach ($request->input('collaborators', []) as $collab) {
                    if (isset($collab['user_id']) && isset($collab['permission'])) {
                        $syncData[$collab['user_id']] = ['permission' => $collab['permission']];
                    }
                }
                $budget->collaborators()->sync($syncData);
            }
        }

        // Process Imported Items
        if ($request->filled('imported_items')) {
            $items = json_decode($request->input('imported_items'), true);
            if (is_array($items)) {
                $insertData = [];
                $now = now();
                foreach ($items as $item) {
                    $qty = max((int)($item['quantity'] ?? 1), 1);
                    $price = (float)($item['unit_price'] ?? 0.0);
                    $insertData[] = [
                        'budget_id'   => $budget->id,
                        'description' => trim($item['description'] ?? ''),
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'total'       => $qty * $price,
                        'category'    => isset($item['category']) ? trim($item['category']) : null,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                if (!empty($insertData)) {
                    ProjectBudgetItem::insert($insertData);
                }
                $budget->recalculateTotal();
            }
        }

        return redirect()->route('portal.finance.budgets.show', $budget)
            ->with('status', 'Project budget updated successfully.');
    }

    public function destroy(ProjectBudget $budget, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($budget->created_by !== $user->id) {
            abort(403, 'Only the creator can delete this budget.');
        }

        if (!in_array($budget->status, ['Draft', 'Rejected'])) {
            return back()->withErrors(['error' => 'Cannot delete a budget that has been submitted and is in review.']);
        }

        $budget->delete();

        return redirect()->route('portal.finance.budgets.index')
            ->with('status', 'Project budget deleted successfully.');
    }

    public function updateCollaborators(Request $request, ProjectBudget $budget): RedirectResponse
    {
        $user = $request->user();
        if ($budget->created_by !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, 'Only the creator or Super Admin can manage collaborators.');
        }

        $request->validate([
            'collaborators' => ['nullable', 'array'],
            'collaborators.*.user_id' => ['required', 'exists:users,id'],
            'collaborators.*.permission' => ['required', 'in:view,edit'],
        ]);

        $syncData = [];
        if ($request->has('sync_collaborators') || $request->has('collaborators')) {
            foreach ($request->input('collaborators', []) as $collab) {
                if (isset($collab['user_id']) && isset($collab['permission'])) {
                    $syncData[$collab['user_id']] = ['permission' => $collab['permission']];
                }
            }
            $budget->collaborators()->sync($syncData);
        }

        return back()->with('status', 'Collaborators updated successfully.');
    }

    public function submit(ProjectBudget $budget, Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($budget->created_by !== $user->id && !$this->isCVO($user)) {
            abort(403, 'Unauthorized action.');
        }

        $target = $request->input('submit_target', 'finance');
        $url = route('portal.finance.budgets.show', $budget->id);

        if ($target === 'cvo') {
            $budget->update(['status' => 'Submitted to CVO']);
            
            // Notify CVO/Super Admin
            $cvoUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('access_role', 'super_admin')
                      ->orWhere('job_level', 'super_admin');
                })
                ->get();
            foreach ($cvoUsers as $cvo) {
                \App\Services\NotificationService::send(
                    $cvo->id,
                    'New Budget Submitted to CVO',
                    "{$user->name} submitted budget: {$budget->title} directly to CVO.",
                    $url
                );
            }

            return back()->with('status', '⚡ Budget submitted directly to CVO for approval.');
        } else {
            $budget->update(['status' => 'Submitted to Finance']);

            // Notify Finance Users
            $financeUsers = User::internalStaff()->where('status', 'active')
                ->where(function($q) {
                    $q->where('department', 'finance')
                      ->orWhere('access_role', 'super_admin');
                })
                ->get();
            foreach ($financeUsers as $fin) {
                \App\Services\NotificationService::send(
                    $fin->id,
                    'New Budget Submitted to Finance',
                    "{$user->name} submitted budget: {$budget->title} to Finance for verification.",
                    $url
                );
            }

            return back()->with('status', '📤 Budget submitted to Finance for approval.');
        }
    }

    public function action(ProjectBudget $budget, string $action, Request $request): RedirectResponse
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        if (!$isFinance) {
            abort(403, 'Only the Finance department can action budgets.');
        }

        if ($action === 'approve') {
            $budget->update(['status' => 'Finance Approved']);

            NotificationService::sendApprovalNeededToMany(
                NotificationService::activeCvoApproverIds($user->id),
                'Budget Approval Needed',
                "Finance approved budget '{$budget->title}'. CVO approval is needed.",
                route('portal.finance.budgets.show', $budget),
                $user->id
            );
            return back()->with('status', '✅ Budget verified and approved by Finance. Sent to CVO.');
        }

        if ($action === 'reject') {
            $budget->update(['status' => 'Rejected']);
            return back()->with('status', 'Budget rejected by Finance.');
        }

        if ($action === 'send_back') {
            $budget->update(['status' => 'Returned for Correction']);
            return back()->with('status', 'Budget sent back to the creator for correction.');
        }

        return back()->withErrors(['error' => 'Invalid finance action.']);
    }

    public function cvoAction(ProjectBudget $budget, string $action, Request $request): RedirectResponse
    {
        if (! $this->isCVO($request->user())) {
            abort(403, 'Only the CVO or Super Admin can approve budgets.');
        }

        if ($action === 'approve') {
            $budget->update(['status' => 'CVO Approved']);
            return back()->with('status', '✅ Budget approved by CVO.');
        }

        if ($action === 'reject') {
            $budget->update(['status' => 'Rejected']);
            return back()->with('status', '✗ Budget rejected by CVO.');
        }

        if ($action === 'send_back_finance') {
            $budget->update(['status' => 'Returned to Finance']);

            NotificationService::sendApprovalNeededToMany(
                NotificationService::activeFinanceApproverIds($request->user()->id),
                'Budget Re-Verification Needed',
                "CVO returned budget '{$budget->title}' to Finance for correction.",
                route('portal.finance.budgets.show', $budget),
                $request->user()->id
            );
            return back()->with('status', 'Budget sent back to Finance for correction.');
        }

        return back()->withErrors(['error' => 'Invalid CVO action.']);
    }

    public function storeItem(ProjectBudget $budget, Request $request): RedirectResponse
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        // Check if user is creator or an assigned collaborator with edit permissions
        if ($budget->created_by !== $user->id && !$isFinance && !$budget->canEdit($user)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'unit_price'  => ['required', 'numeric', 'min:0'],
            'category'    => ['nullable', 'string', 'max:100'],
        ]);

        $qty   = (int) $request->input('quantity');
        $price = (float) $request->input('unit_price');

        ProjectBudgetItem::create([
            'budget_id'   => $budget->id,
            'description' => $request->input('description'),
            'quantity'    => $qty,
            'unit_price'  => $price,
            'total'       => $qty * $price,
            'category'    => $request->input('category'),
        ]);

        $budget->recalculateTotal();

        if ($budget->status !== 'Draft') {
            $budget->update(['status' => 'Updated']);
        }

        return back()->with('status', 'Budget line item added.');
    }

    public function destroyItem(ProjectBudget $budget, ProjectBudgetItem $item, Request $request): RedirectResponse
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        if ($budget->created_by !== $user->id && !$isFinance && !$budget->canEdit($user)) {
            abort(403, 'Unauthorized action.');
        }

        $item->delete();
        $budget->recalculateTotal();

        if ($budget->status !== 'Draft') {
            $budget->update(['status' => 'Updated']);
        }

        return back()->with('status', 'Budget line item removed.');
    }

    public function export(ProjectBudget $budget, Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!$budget->canView($user)) {
            abort(403, '🔒 Access Denied.');
        }

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="budget_' . $budget->id . '_export.csv"',
        ];

        $callback = function () use ($budget) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Description', 'Quantity', 'Unit Price', 'Category', 'Total (' . $budget->currency . ')']);

            foreach ($budget->items as $item) {
                fputcsv($file, [
                    $item->description,
                    $item->quantity,
                    $item->unit_price,
                    $item->category ?? '',
                    $item->total,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(ProjectBudget $budget, Request $request): RedirectResponse
    {
        $user = $request->user();
        $isFinance = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';

        if ($budget->created_by !== $user->id && !$isFinance && !$budget->canEdit($user)) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $file = fopen($path, 'r');

        // Parse header and check columns
        $header = fgetcsv($file);
        // Standard expected order: description, quantity, unit_price, category
        // We'll clean quotes/whitespaces to make it robust
        if ($header) {
            $header = array_map(fn($col) => strtolower(trim(str_replace(['"', "'"], '', $col))), $header);
        }

        $rowNum = 0;
        $insertData = [];
        $now = now();
        while (($row = fgetcsv($file)) !== false) {
            $rowNum++;
            if (empty($row) || count($row) < 3) {
                continue;
            }

            // Fallback column positions
            $desc  = $row[0] ?? '';
            $qty   = isset($row[1]) ? (int) $row[1] : 1;
            $price = isset($row[2]) ? (float) $row[2] : 0.0;
            $cat   = $row[3] ?? null;

            if (empty(trim($desc))) {
                continue;
            }

            $insertData[] = [
                'budget_id'   => $budget->id,
                'description' => trim($desc),
                'quantity'    => max($qty, 1),
                'unit_price'  => $price,
                'total'       => max($qty, 1) * $price,
                'category'    => $cat ? trim($cat) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (!empty($insertData)) {
            ProjectBudgetItem::insert($insertData);
        }

        fclose($file);
        $budget->recalculateTotal();

        if ($budget->status !== 'Draft') {
            $budget->update(['status' => 'Updated']);
        }

        return back()->with('status', "CSV imported successfully! Loaded {$rowNum} line items.");
    }
}
