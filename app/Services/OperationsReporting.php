<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetWarehouseRequest;
use App\Models\Campaign;
use App\Models\OperationsReportSubmission;
use App\Models\PerformanceRegister;
use App\Models\PosmLedger;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OperationsReporting
{
    public const DEPARTMENT = 'operations_projects';

    public const STATUSES = [
        'planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Completed',
        'cancelled' => 'Cancelled', 'submitted' => 'Submitted', 'approved' => 'Approved',
        'resolved' => 'Resolved', 'won' => 'Won', 'lost' => 'Lost', 'paid' => 'Paid',
        'on_hold' => 'On Hold', 'blocked' => 'Blocked',
    ];

    public static function member(User $user): bool
    {
        return $user->isActive() && ! $user->isServiceAccount()
            && (PerformanceAccess::department($user) === self::DEPARTMENT || PerformanceAccess::executive($user));
    }

    public static function manager(User $user): bool
    {
        return self::member($user) && (PerformanceAccess::manages($user, self::DEPARTMENT) || $user->isActingForOperationsDepartmentLead());
    }

    public static function schemas(): array
    {
        return config('department_reporting.'.self::DEPARTMENT);
    }

    public static function fields(string $kind, bool $private): array
    {
        $fields = self::schemas()[$kind] ?? [];
        return $private ? $fields : array_diff_key($fields, array_flip(config('operations_tracker.private_fields')));
    }

    public static function version(PerformanceRegister $record): string
    {
        return hash('sha256', json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public static function activations(): Builder
    {
        return PerformanceRegister::query()->whereIn('department', User::departmentAliases(self::DEPARTMENT))
            ->where('kind', 'activation')->whereNotNull('approved_at');
    }

    public function build(Request $request): array
    {
        if (! $request->attributes->get('dashboard_reporting_period')) {
            app(DashboardReportingPeriod::class)->apply($request);
        }
        $user = $request->user();
        $member = self::member($user);
        $manager = self::manager($user);
        $warehouseHistory = $member && $user->canExportWarehouseAssets();
        $filters = $request->validate([
            'ops_tab' => ['nullable', Rule::in(array_keys(config('operations_tracker.tabs')))],
            'ops_owner' => 'nullable|integer',
            'ops_q' => 'nullable|string|max:150',
            'ops_status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'ops_sort' => 'nullable|in:newest,oldest,title,owner,status',
            'ops_direction' => 'nullable|in:asc,desc',
            'ops_rows' => 'nullable|in:10,25,50',
            'ops_review' => 'nullable|integer',
            'ops_edit' => 'nullable|integer',
            'ops_add' => ['nullable', Rule::in(array_keys(self::schemas()))],
            'ops_warehouse' => 'nullable|in:inventory,movements',
        ]);
        $from = $request->query('weekly_from');
        $to = $request->query('weekly_to');
        $tab = $filters['ops_tab'] ?? 'weekly';
        $owner = isset($filters['ops_owner']) ? (int) $filters['ops_owner'] : null;
        $search = trim($filters['ops_q'] ?? '');
        $sort = $filters['ops_sort'] ?? 'newest';
        $direction = $filters['ops_direction'] ?? 'asc';
        $perPage = (int) ($filters['ops_rows'] ?? 10);
        $modules = $request->routeIs('portal.operations');
        $anchor = $modules ? 'operations-reporting' : 'staff-weekly-performance';
        $returnQuery = $request->only(['report_period', 'report_date', 'ops_tab', 'ops_owner', 'ops_q', 'ops_sort', 'ops_status', 'ops_direction', 'ops_rows', 'ops_warehouse']);
        $returnQuery['ops_tab'] = $tab;
        $returnQuery += $modules ? ['module_view' => 'overview'] : ['weekly_department' => self::DEPARTMENT];
        $workspaceUrl = fn (array $parameters = [], bool $keepPeriod = true) => route(
            $modules ? 'portal.operations' : 'dashboard',
            array_filter(array_replace($keepPeriod ? $returnQuery : array_diff_key($returnQuery, ['report_period' => true, 'report_date' => true]), $parameters), fn ($value) => $value !== null && $value !== '')
        ).'#'.(isset($parameters['ops_review']) ? 'operations-reviews' : (isset($parameters['ops_add']) || isset($parameters['ops_edit']) ? 'operations-entry' : $anchor));
        $approved = PerformanceRegister::query()->whereIn('department', User::departmentAliases(self::DEPARTMENT))->whereNotNull('approved_at');
        $published = (clone $approved)->whereBetween('period_start', [$from, $to]);
        $weekly = WeeklyConsolidatedItem::query()->whereIn('department', User::departmentAliases(self::DEPARTMENT))
            ->where('workflow_state', 'legacy')->whereBetween('week_start', [$from, $to]);
        $campaigns = Campaign::query()->where(function (Builder $query) use ($from, $to) {
            $query->where(function (Builder $dated) use ($from, $to) {
                $dated->whereNotNull('start_date')->whereDate('start_date', '<=', $to)
                    ->where(fn (Builder $end) => $end->whereDate('end_date', '>=', $from)
                        ->orWhere(fn (Builder $noEnd) => $noEnd->whereNull('end_date')->whereDate('start_date', '>=', $from)));
            })->orWhere(fn (Builder $undated) => $undated->whereNull('start_date')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to));
        });
        $historicalOwners = (clone $published)->distinct()->pluck('owner_id')
            ->merge((clone $weekly)->distinct()->pluck('lead_staff_id'))
            ->merge((clone $campaigns)->distinct()->pluck('project_lead_id'));
        $collaboratorIds = (clone $published)->get(['data'])->flatMap(fn ($row) => $row->data['collaborator_ids'] ?? [])
            ->merge((clone $weekly)->get(['supporting_staff_ids'])->flatMap(fn ($row) => $row->supporting_staff_ids ?? []))
            ->filter()->map(fn ($id) => (int) $id)->unique();
        $staff = User::internalStaff()->where(fn (Builder $users) => $users
            ->where(fn (Builder $active) => $active->where('status', 'active')->whereIn('department', User::departmentAliases(self::DEPARTMENT)))
            ->orWhereIn('id', $historicalOwners->merge($collaboratorIds)->filter()->unique()))->orderBy('name')->get();
        $registers = $weeklyRows = $campaignRows = $inventory = $movements = $summary = $breakdowns = [];
        $kpiRows = $deliveryRows = $executionTrend = [];
        $paginate = fn (Builder $query, string $page) => $query->paginate($perPage, ['*'], $page)->appends($returnQuery)->fragment($anchor);
        $filtered = (clone $published)->when($owner, fn (Builder $query) => $query->where(fn (Builder $person) => $person->where('owner_id', $owner)->orWhereJsonContains('data->collaborator_ids', (int) $owner)))
            ->when($filters['ops_status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($search, $tab, $manager, $modules) {
                $match->where('title', 'like', "%$search%")->orWhere('client', 'like', "%$search%")
                    ->orWhere('project', 'like', "%$search%")->orWhere('reference', 'like', "%$search%");
                $kinds = $tab === 'overview' ? array_keys(self::schemas()) : (config('operations_tracker.groups')[$tab] ?? []);
                foreach ($kinds as $kind) {
                    $match->orWhere(function (Builder $fields) use ($kind, $search, $manager, $modules) {
                        $fields->where('kind', $kind)->where(function (Builder $details) use ($kind, $search, $manager, $modules) {
                            foreach (array_keys(self::fields($kind, $manager && $modules)) as $key) {
                                $details->orWhere('data->'.$key, 'like', "%$search%");
                            }
                        });
                    });
                }
            }));
        if ($tab === 'overview') {
            $rows = $filtered->get();
            $summary = app(OperationsTracker::class)->summary($rows);
            $activationRows = $rows->where('kind', 'activation');
            $summary['activations_completed'] = $activationRows->filter(fn ($row) => $this->activationExecuted($row))->count();
            $executionTrend = $this->executionTrend($approved, $request->query('report_period', 'week'), $from, $to, $owner, $search);
            $summary['activation_reports_shared'] = $activationRows->filter(fn ($row) => filled($row->data['report_shared_on'] ?? null))->count();
            foreach (['brand' => 'Brand', 'location' => 'Location', 'lead' => 'Project Lead'] as $field => $label) {
                $breakdowns[$label] = $rows->where('kind', 'activation')->countBy(fn ($row) => $field === 'lead'
                    ? ($staff->firstWhere('id', $row->owner_id)?->name ?? 'Unassigned')
                    : strip_tags($row->data[$field] ?? 'Not recorded'))->sortDesc();
            }
        }
        foreach (config('operations_tracker.groups')[$tab] ?? [] as $kind) {
            $query = (clone $filtered)->where('kind', $kind);
            $this->sort($query, $sort, $direction, 'title', 'owner_id', 'period_start');
            $registers[$kind] = $paginate($query->orderByDesc('id'), 'ops_'.$kind.'_page');
        }
        if ($tab === 'weekly') {
            $weekly->with('leadStaff')->when($owner, fn (Builder $q) => $q->where(fn (Builder $person) => $person->where('lead_staff_id', $owner)->orWhereJsonContains('supporting_staff_ids', (int) $owner)->orWhereJsonContains('supporting_staff_ids', (string) $owner)))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $match
                    ->where('campaign_name', 'like', "%$search%")->orWhere('client_name', 'like', "%$search%")->orWhere('deliverables', 'like', "%$search%")))
                ->when($filters['ops_status'] ?? null, fn (Builder $q, $status) => $q->where('status', [
                    'planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Done', 'on_hold' => 'Deferred', 'blocked' => 'Blocked',
                ][$status] ?? self::STATUSES[$status]));
            $this->sort($weekly, $sort, $direction, 'campaign_name', 'lead_staff_id', 'week_start');
            $weeklyRows = $paginate($weekly->orderByDesc('id'), 'ops_weekly_page');
        }
        if ($tab === 'projects' && $member) {
            $campaigns->with('projectLead')->when($owner, fn (Builder $q) => $q->where('project_lead_id', $owner))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $match->where('name', 'like', "%$search%")->orWhere('client_name', 'like', "%$search%")))
                ->when($filters['ops_status'] ?? null, fn (Builder $q, $status) => $q->whereIn('status', [$status, self::STATUSES[$status], ...($status === 'completed' ? ['Done', 'done'] : [])]));
            $this->sort($campaigns, $sort, $direction, 'name', 'project_lead_id', 'start_date');
            $campaignRows = $paginate($campaigns->orderByDesc('id'), 'ops_campaign_page');
        }
        if ($tab === 'assets' && $member) {
            $assets = Asset::query()->where('is_warehouse_tracked', true)->with('assignee')
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $match
                    ->where('name', 'like', "%$search%")->orWhere('asset_tag', 'like', "%$search%")->orWhere('warehouse_location', 'like', "%$search%")));
            $this->sort($assets, $sort, $direction, 'name', 'assigned_to', 'updated_at');
            $inventory = $paginate($assets->orderBy('name')->orderBy('id'), 'ops_inventory_page');
            $movementQuery = $this->movements($user)->whereNotNull('issued_at')->where(fn (Builder $q) => $q
                ->whereBetween('issued_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->orWhereBetween('returned_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->orWhereBetween('closed_at', [$from.' 00:00:00', $to.' 23:59:59']))
                ->when($owner, fn (Builder $q) => $q->where('requested_by', $owner))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $match
                    ->where('destination_location', 'like', "%$search%")->orWhereHas('asset', fn ($asset) => $asset->where('name', 'like', "%$search%"))));
            if ($sort === 'title') {
                $movementQuery->orderBy(Asset::select('name')->whereColumn('assets.id', 'asset_warehouse_requests.asset_id'), $direction);
            } else {
                $this->sort($movementQuery, $sort, $direction, 'request_code', 'requested_by', 'issued_at');
            }
            $movements = $paginate($movementQuery->orderByDesc('id'), 'ops_movement_page');
        }

        $activationIds = isset($registers['activation']) ? $registers['activation']->pluck('id') : collect();
        $campaignIds = collect($campaignRows ? $campaignRows->pluck('id') : [])->merge(isset($registers['activation']) ? $registers['activation']->pluck('data.campaign_id')->filter() : [])->unique();
        $linkedMovements = $member && ($activationIds->isNotEmpty() || $campaignIds->isNotEmpty())
            ? $this->movements($user)->where(fn (Builder $q) => $q->whereIn('operations_activation_id', $activationIds)->orWhereIn('campaign_id', $campaignIds))->latest('id')->get() : collect();
        $linkedPosm = $warehouseHistory && $campaignIds->isNotEmpty()
            ? PosmLedger::whereIn('campaign_id', $campaignIds)->with('creator')->latest('occurred_on')->get()->groupBy('campaign_id') : collect();
        $checkoutLogs = $warehouseHistory && $campaignIds->isNotEmpty()
            ? \App\Models\AssetLog::whereIn('campaign_id', $campaignIds)->with(['asset','user'])->latest()->get()->groupBy('campaign_id') : collect();

        $submissions = $member && isset($filters['ops_review'])
            ? OperationsReportSubmission::with('submitter')->when(! $manager, fn ($q) => $q->where('submitted_by', $user->id))
                ->when($filters['ops_review'] > 0, fn ($q) => $q->whereKey($filters['ops_review']))->latest('id')->paginate(10, ['*'], 'ops_review_page')->withQueryString()->fragment('operations-reviews')
            : null;
        $edit = $member && isset($filters['ops_edit'])
            ? (clone $approved)->whereIn('kind', array_keys(self::schemas()))->when(! $manager, fn (Builder $q) => $q->where(fn (Builder $person) => $person->where('owner_id', $user->id)->orWhere('submitted_by', $user->id)))->findOrFail($filters['ops_edit'])
            : null;
        $newKind = $member ? ($filters['ops_add'] ?? null) : null;
        $campaignOptions = $member && ($newKind === 'activation' || $edit?->kind === 'activation' || $submissions?->contains('kind', 'activation'))
            ? Campaign::orderBy('name')->get(['id','name']) : collect();
        $showEditor = $newKind || $edit || ($submissions && $submissions->contains(fn ($entry) => ($manager && $entry->state === 'pending') || ($entry->submitted_by === $user->id && $entry->state === 'returned')));
        $collaborationStaff = $showEditor ? User::internalStaff()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'department']) : collect();
        $reviewCollaborators = $submissions ? User::internalStaff()->whereIn('id', $submissions->flatMap(fn ($entry) => $entry->payload['data']['collaborator_ids'] ?? [])->unique())->get(['id', 'name']) : collect();
        if ($tab === 'kpis') {
            $performance = app(SharedWeeklyPerformance::class)->build(self::DEPARTMENT, $from, $to)[self::DEPARTMENT] ?? [];
            $kpiRows = collect($performance['kpis'] ?? [])->when($owner, fn ($q) => $q->where('owner_id', $owner))
                ->filter(fn ($row) => $search === '' || mb_stripos($row['name'], $search) !== false);
            $deliveryRows = collect($performance['deliverables'] ?? [])->when($owner, fn ($q) => $q->where('owner_id', $owner))
                ->filter(fn ($row) => $search === '' || mb_stripos($row['project'] ?? '', $search) !== false);
        }

        return compact('tab', 'filters', 'from', 'to', 'staff', 'owner', 'member', 'manager', 'warehouseHistory', 'modules', 'anchor', 'registers', 'weeklyRows', 'campaignRows', 'inventory', 'movements', 'summary', 'breakdowns', 'linkedMovements', 'linkedPosm', 'checkoutLogs', 'submissions', 'edit', 'newKind', 'campaignOptions', 'kpiRows', 'deliveryRows', 'workspaceUrl', 'collaborationStaff', 'reviewCollaborators', 'executionTrend');
    }

    private function activationExecuted(PerformanceRegister $row): bool
    {
        $status = $row->data['activation_status'] ?? null;

        return $row->status !== 'cancelled' && ($status ? $status === 'Completed' : $row->status === 'completed');
    }

    private function executionTrend(Builder $approved, string $period, string $from, string $to, ?int $owner, string $search): array
    {
        $anchor = Carbon::parse($from);
        $previous = match ($period) {
            'month' => $anchor->copy()->subMonthNoOverflow()->startOfMonth(),
            'year' => $anchor->copy()->subYear()->startOfYear(),
            default => $anchor->copy()->subWeek(),
        };
        $previousEnd = match ($period) {
            'month' => $previous->copy()->endOfMonth(),
            'year' => $previous->copy()->endOfYear(),
            default => $previous->copy()->addDays($anchor->diffInDays(Carbon::parse($to))),
        };
        $records = $approved->where('kind', 'activation')->whereBetween('period_start', [$previous->toDateString(), $to])
            ->when($owner, fn (Builder $q) => $q->where(fn (Builder $person) => $person->where('owner_id', $owner)->orWhereJsonContains('data->collaborator_ids', $owner)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $fields) use ($search) {
                    foreach (['title', 'client', 'project', 'reference', ...array_map(fn ($key) => 'data->'.$key, array_keys(self::fields('activation', false)))] as $key) {
                        $fields->orWhere($key, 'like', "%$search%");
                    }
                });
            })->get()->filter(fn (PerformanceRegister $row) => $this->activationExecuted($row));

        $byDate = $records->filter(fn ($row) => $row->period_start->betweenIncluded($anchor, Carbon::parse($to)))
            ->countBy(fn ($row) => $period === 'year' ? $row->period_start->format('Y-m') : $row->period_start->toDateString());
        $points = [];
        if ($period === 'month') {
            for ($date = $anchor->copy(); $date->toDateString() <= $to; $date = $end->copy()->addDay()) {
                $end = $date->copy()->endOfWeek()->min(Carbon::parse($to));
                $points[$date->format('d M').'–'.$end->format('d M')] = $byDate->filter(fn ($count, $day) => $day >= $date->toDateString() && $day <= $end->toDateString())->sum();
            }
        } elseif ($period === 'year') {
            for ($date = $anchor->copy(); $date->toDateString() <= $to; $date->addMonth()) {
                $points[$date->format('M')] = $byDate->get($date->format('Y-m'), 0);
            }
        }
        return [
            'current' => $byDate->sum(),
            'previous' => $records->filter(fn ($row) => $row->period_start->betweenIncluded($previous, $previousEnd))->count(),
            'current_label' => $anchor->format('d M Y').' – '.Carbon::parse($to)->format('d M Y'),
            'previous_label' => $previous->format('d M Y').' – '.$previousEnd->format('d M Y'),
            'points' => $points,
        ];
    }

    private function movements(User $user): Builder
    {
        return AssetWarehouseRequest::with(['asset', 'requester'])
            ->when(! $user->canExportWarehouseAssets(), fn (Builder $q) => $q->where('requested_by', $user->id));
    }

    private function sort(Builder $query, string $sort, string $direction, string $title, string $owner, string $date): void
    {
        match ($sort) {
            'title' => $query->orderBy($title, $direction),
            'owner' => $query->orderBy(User::select('name')->whereColumn('users.id', $query->getModel()->qualifyColumn($owner)), $direction),
            'status' => $query->orderBy('status', $direction),
            'oldest' => $query->orderBy($date),
            default => $query->orderByDesc($date),
        };
    }
}
