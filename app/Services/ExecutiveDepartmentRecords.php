<?php

namespace App\Services;

use App\Models\{Asset, AssetLog, AssetWarehouseRequest, Campaign, CreativeRequest, CrmAccountAllocation, CrmAccountRelationship, CrmAccountTarget, CrmClientRecord, CrmFinancialRegister, CrmPipelineProject, FleetRequest, HrDashboardActivity, LeaveApplication, OperationsHandover, PerformanceRegister, PettyCashClaim, PosmLedger, SalaryAdvance, SupplierInvoice, User, WarehouseAssetSuggestion, WeeklyConsolidatedItem};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExecutiveDepartmentRecords
{
    public const SECTIONS = [
        'operations_projects' => ['activations' => 'Activations', 'projects' => 'Projects & Handovers', 'assets' => 'Assets & POSM', 'follow_up' => 'Vendors, Risks & Follow-up', 'weekly' => 'Weekly Reports'],
        'client_relations' => ['pipeline' => 'Projects & Pipeline', 'accounts' => 'Accounts', 'revenue' => 'Revenue & Gaps', 'follow_up' => 'Client Follow-up', 'weekly' => 'Weekly Reports'],
        'creatives' => ['briefs' => 'Creative Briefs', 'weekly' => 'Weekly Reports'],
        'brands_marketing' => ['registers' => 'Brands Reports', 'weekly' => 'Weekly Workstreams'],
        'finance' => ['spend' => 'Claims, Invoices & Budgets', 'registers' => 'Finance Registers', 'revenue' => 'Revenue & Gaps', 'weekly' => 'Weekly Reports'],
        'hr_admin' => ['activities' => 'HR & Admin Activities', 'people' => 'Leave & Appraisals', 'office' => 'Fleet & Office Assets', 'registers' => 'HR & Admin Registers', 'weekly' => 'Weekly Reports'],
        'executive' => ['weekly' => 'Weekly Reports'],
    ];

    public function build(Request $request, string $department, ?int $staff, ?string $section): array
    {
        $viewer = $request->user();
        abort_unless($viewer->canViewExecutiveActivity(), 403);
        $private = $viewer->isCvoOrSuperAdmin() || $viewer->canAuditHrAndFinance();
        $sections = self::SECTIONS[$department] ?? [];
        if (!$private && in_array($department, ['finance', 'hr_admin'], true)) {
            $sections = [];
        }
        if (!$private && $department === 'client_relations') {
            unset($sections['accounts'], $sections['revenue'], $sections['follow_up']);
        }
        if (!$sections) {
            return ['sections' => [], 'section' => null, 'groups' => []];
        }

        $section = isset($sections[$section]) ? $section : array_key_first($sections);
        $from = $request->query('weekly_from') ?? today()->startOfWeek()->toDateString();
        $to = $request->query('weekly_to') ?? today()->endOfWeek()->toDateString();
        $groups = match ($section) {
            'activations' => $this->activations($from, $to, $staff, $private, $request->integer('activity_activation')),
            'projects' => $this->operationsProjects($from, $to, $staff),
            'assets' => $this->operationsAssets($from, $to, $staff, $private),
            'pipeline', 'accounts', 'revenue' => $this->clientRelations($section, $from, $to, $staff, $private),
            'follow_up' => $department === 'client_relations'
                ? $this->clientRelations($section, $from, $to, $staff, $private)
                : $this->registers($department, $from, $to, $staff, $private, ['supplier', 'procurement', 'fabrication', 'client_approval', 'project_report', 'risk_issue', 'risk']),
            'briefs' => $this->creative($viewer, $from, $to, $staff),
            'spend' => $this->finance($from, $to, $staff),
            'people' => $this->people($from, $to, $staff),
            'office' => $this->office($from, $to, $staff),
            'activities' => $this->hrActivities($from, $to, $staff),
            'registers' => $this->registers($department, $from, $to, $staff, $private),
            default => $this->weekly($department, $from, $to, $staff),
        };
        if($department==='hr_admin'&&$section==='registers'&&$private)$groups[]=$this->hrLiveRegisters($viewer,$staff);
        return compact('sections', 'section', 'groups');
    }

    private function page(string $label, string $key, Builder $query, callable $project): array
    {
        $rows = $this->paginate($key, $query);

        return ['label' => $label, 'rows' => $rows->through($project)];
    }

    private function paginate(string $key, Builder $query)
    {
        return $query->orderByDesc($query->getModel()->qualifyColumn('id'))
            ->paginate(8, ['*'], 'exec_'.$key.'_page')
            ->withPath(route('dashboard', [], false))->withQueryString()->fragment('executive-source-reports');
    }

    private function row(string $title, ?string $owner, ?string $status, array $fields = [], array $linked = [], ?string $url = null): array
    {
        return compact('title', 'owner', 'status', 'fields', 'linked', 'url');
    }

    private function reportQuery(string $department, string $from, string $to, ?int $staff, bool $approved = true): Builder
    {
        return PerformanceRegister::query()->whereIn('department', User::departmentAliases($department))
            ->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)
            ->when($approved, fn ($q) => $q->whereNotNull('approved_at'))
            ->when($staff, fn ($q) => $q->where(fn ($owner) => $owner
                ->where('owner_id', $staff)->orWhereJsonContains('data->collaborator_ids', $staff)
                ->orWhereJsonContains('data->collaborator_ids', (string) $staff)
                ->orWhereJsonContains('data->collaborators', $staff)->orWhereJsonContains('data->collaborators', (string) $staff)));
    }

    private function activations(string $from, string $to, ?int $staff, bool $private, int $focus): array
    {
        $rows = $this->paginate('activations', $this->reportQuery('operations_projects', $from, $to, $staff)->where('kind', 'activation')->when($focus, fn ($query) => $query->whereKey($focus))->latest('period_start'));
        $items = $rows->getCollection();
        $crmProjects = CrmPipelineProject::with('owner:id,name')->whereIn('id', $items->pluck('data.crm_project_id')->filter())->get()->keyBy('id');
        $campaigns = $items->pluck('data.campaign_id')->filter()->unique();
        $people = User::query()->whereIn('id', $items->pluck('owner_id')->merge($items->flatMap(fn ($r) => $r->data['collaborator_ids'] ?? []))->unique())->pluck('name', 'id');
        $movements = AssetWarehouseRequest::with(['asset:id,name,asset_tag,warehouse_location,warehouse_quantity', 'requester:id,name', 'campaign:id,name', 'operationsActivation:id,title'])
            ->where(fn ($q) => $q->whereIn('operations_activation_id', $items->pluck('id'))
                ->orWhere(fn ($campaign) => $campaign->whereNull('operations_activation_id')->whereIn('campaign_id', $campaigns)))
            ->latest('id')->get();
        $approvedAssets = WarehouseAssetSuggestion::with('asset:id,name,asset_tag,warehouse_location,warehouse_quantity')
            ->where('status', 'approved')->whereIn('activation_id', $items->pluck('id'))->whereNotNull('asset_id')->get()->groupBy('activation_id');
        $movementLogs = AssetLog::with(['asset:id,name,asset_tag', 'user:id,name'])->whereIn('campaign_id', $campaigns)->latest()->get()->groupBy('campaign_id');
        $posm = PosmLedger::whereIn('campaign_id', $campaigns)->latest()->get()->groupBy('campaign_id');
        $related = PerformanceRegister::whereIn('department', User::departmentAliases('operations_projects'))
            ->whereNotNull('approved_at')->whereNotIn('kind', ['activation', 'weekly_update'])
            ->where(fn ($scope) => $scope->whereIn('data->activation_id', $items->pluck('id'))
                ->orWhereIn('data->campaign_id', $campaigns))->latest('id')->get();

        $accountKeys = $items->flatMap(fn ($r) => [self::nameKey($r->client), self::nameKey($r->data['brand'] ?? '')])
            ->filter()->unique()->values();
        $relationships = CrmAccountRelationship::with('owner:id,name')
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(client_name))'), $accountKeys)->get();
        $accountProjects = CrmPipelineProject::with('owner:id,name')->where('source_period_confirmed', true)
            ->where(fn ($q) => $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(brand))'), $accountKeys)
                ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(client_name))'), $accountKeys))
            ->whereDate('week_start', '<=', $to)->orderByDesc('week_start')->get();

        $rows->through(function (PerformanceRegister $record) use ($people, $movements, $movementLogs, $posm, $related, $relationships, $accountProjects, $crmProjects, $approvedAssets, $private) {
            $data = $record->data ?? [];
            $campaignId = (int) ($data['campaign_id'] ?? 0);
            $crmProject = $crmProjects->get((int) ($data['crm_project_id'] ?? 0));
            $planned = $data['target_quantity'] ?? null;
            $actual = $data['actual_quantity'] ?? null;
            $date = $data['starts_on'] ?? $record->period_start->toDateString();
            $keys = array_filter([self::nameKey($data['brand'] ?? ''), self::nameKey($record->client)]);
            $relationshipOwners = $relationships->filter(fn ($r) => in_array(self::nameKey($r->client_name), $keys, true)
                && (!$r->starts_on || $r->starts_on->toDateString() <= $date) && (!$r->ends_on || $r->ends_on->toDateString() >= $date))
                ->map(fn ($r) => $r->owner?->name)->filter()->unique()->values();
            $matchingProjects = $accountProjects->filter(fn ($p) => $p->week_start?->toDateString() <= $date
                && (in_array(self::nameKey($p->brand), $keys, true) || in_array(self::nameKey($p->client_name), $keys, true)));
            if ($relationshipOwners->isEmpty() && $matchingProjects->isNotEmpty()) {
                $latestWeek = $matchingProjects->first()->week_start?->toDateString();
                $relationshipOwners = $matchingProjects->filter(fn ($p) => $p->week_start?->toDateString() === $latestWeek)
                    ->map(fn ($p) => $p->owner?->name)->filter()->unique()->values();
            }
            $linked = $movements->filter(fn ($m) => (int) $m->operations_activation_id === $record->id
                || ($campaignId && !$m->operations_activation_id && (int) $m->campaign_id === $campaignId))
                ->map(fn ($m) => $this->movement($m))->values()->all();
            foreach ($approvedAssets->get($record->id, collect()) as $suggestion) {
                if ($suggestion->asset) {
                    $linked[] = $this->row($suggestion->asset->name, null, 'Approved inventory link', [
                        'Asset tag' => $suggestion->asset->asset_tag, 'Warehouse' => $suggestion->asset->warehouse_location,
                        'Current available units' => $suggestion->asset->warehouse_quantity, 'Source' => $suggestion->source_reference,
                    ]);
                }
            }
            foreach ($movementLogs->get($campaignId, []) as $log) {
                $linked[] = $this->row($log->asset?->name ?? 'Asset movement', $log->user?->name, Str::headline($log->action), ['Logged on' => $log->created_at]);
            }
            foreach ($posm->get($campaignId, []) as $item) {
                $linked[] = $this->row($item->item_name, null, 'POSM ledger', ['Quantity out' => $item->quantity_out, 'Quantity in' => $item->quantity_in, 'Date' => $item->occurred_on]);
            }
            foreach ($related as $item) {
                $activationId = (int) ($item->data['activation_id'] ?? 0);
                if ($activationId === $record->id || ($campaignId && !$activationId && (int) ($item->data['campaign_id'] ?? 0) === $campaignId)) {
                    $linked[] = $this->row($item->title, null, Str::headline($item->kind),
                        $this->labelFields(array_intersect_key($item->data ?? [], OperationsReporting::fields($item->kind, $private))));
                }
            }
            return $this->row($record->title, empty($data['import_no_owner']) ? $people->get($record->owner_id) : ($data['source_project_lead'] ?? null), Str::headline($record->status), [
                'Client' => $record->client, 'Project' => $record->project, 'Brand' => $data['brand'] ?? null,
                'Supporting staff' => collect($data['collaborator_ids'] ?? [])->map(fn ($id) => $people->get($id))->filter()->implode(', '),
                'Client Relations project' => $crmProject?->project_name,
                'Client Relations owner' => $crmProject?->owner?->name ?? $data['client_service'] ?? ($relationshipOwners->count() === 1 ? $relationshipOwners->first() : null),
                'Possible account owners' => (!$crmProject && empty($data['client_service']) && $relationshipOwners->count() > 1) ? $relationshipOwners->implode(', ') : null,
                'Location' => $data['location'] ?? null, 'Outlet' => $data['outlet'] ?? null,
                'Starts' => $data['starts_on'] ?? null, 'Ends' => $data['ends_on'] ?? null,
                'Target' => $planned, 'Actual' => $actual, 'Measure' => $data['target_measure'] ?? null,
                'Shortfall (target minus actual)' => is_numeric($planned) && is_numeric($actual) ? (float) $planned - (float) $actual : null,
                'Assets planned' => $data['planned_assets'] ?? null, 'Vendors' => $data['vendors'] ?? null,
                'Challenges' => $data['challenges'] ?? null, 'Risks' => $data['risks'] ?? null, 'Comments' => $data['remarks'] ?? null, 'Reported duration' => $data['duration_as_reported'] ?? null,
                'Report shared on' => $data['report_shared_on'] ?? null, 'Report shared (source)' => $data['import_report_shared'] ?? null, 'Reference' => $record->reference,
            ], $linked, $crmProject?->source_period_confirmed ? route('portal.project-journey', ['project' => $crmProject->id]) : null);
        });

        return [['label' => 'Activations', 'rows' => $rows]];
    }

    private static function nameKey(?string $name): string
    {
        return mb_strtolower(trim(strip_tags($name ?? '')));
    }

    private function operationsProjects(string $from, string $to, ?int $staff): array
    {
        return [
            $this->page('Projects', 'campaign', Campaign::with('projectLead:id,name')->whereDate('start_date', '<=', $to)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
                ->when($staff, fn ($q) => $q->where('project_lead_id', $staff)), fn ($p) => $this->row($p->name, $p->projectLead?->name, $p->status, ['Client' => $p->client_name, 'Start' => $p->start_date, 'End' => $p->end_date, 'Description' => $p->description])),
            $this->page('Client Relations handovers', 'handover', OperationsHandover::with(['project.owner:id,name', 'lead:id,name', 'requester:id,name'])
                ->where(fn ($q) => $q->whereBetween('event_at', [$from.' 00:00:00', $to.' 23:59:59'])->orWhereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']))
                ->when($staff, fn ($q) => $q->where(fn ($who) => $who->where('lead_id', $staff)->orWhere('requester_id', $staff))),
                fn ($h) => $this->row($h->title, $h->lead?->name, Str::headline($h->stage), ['Client' => $h->project?->client_name, 'Project' => $h->project?->project_name, 'Client Relations owner' => $h->project?->owner?->name, 'Requested by' => $h->requester?->name, 'Location' => $h->location, 'Event date' => $h->event_at, 'Plan' => $h->plan, 'Acceptance criteria' => $h->acceptance_criteria, 'Resources' => $h->resources, 'Meeting notes' => $h->meeting_minutes, 'Verified at' => $h->verified_at])),
        ];
    }

    private function operationsAssets(string $from, string $to, ?int $staff, bool $private): array
    {
        $movement = AssetWarehouseRequest::with(['asset', 'requester:id,name', 'operationsActivation', 'campaign:id,name'])
            ->where(fn ($q) => $q->whereBetween('requested_for', [$from, $to])
                ->orWhereBetween('issued_at', [$from.' 00:00:00', $to.' 23:59:59'])->orWhereBetween('returned_at', [$from.' 00:00:00', $to.' 23:59:59']))->when($staff, fn ($q) => $q->where('requested_by', $staff));
        $inventory = Asset::with('assignee:id,name')->where('is_warehouse_tracked', true)
            ->when($staff, fn ($q) => $q->where('assigned_to', $staff));
        return [
            $this->page('Warehouse inventory · current snapshot', 'inventory', $inventory, fn ($a) => $this->row($a->name, $a->assignee?->name, $a->status, [
                'Asset tag' => $a->asset_tag, 'Warehouse' => $a->warehouse_location, 'Available units' => $a->warehouse_quantity, 'Procured units' => $a->quantity_procured,
            ])),
            $this->page('Warehouse requests and movements', 'moves', $movement, fn ($m) => $this->movement($m)),
            ...$this->registers('operations_projects', $from, $to, $staff, $private, ['posm_deployment']),
            $this->page('POSM records with no deployment date', 'undated_posm',
                PerformanceRegister::where('department', 'operations_projects')->where('kind', 'posm_deployment')->whereNotNull('approved_at')
                    ->where('data->import_undated', true)->when($staff, fn ($q) => $q->where('owner_id', $staff)->where(fn ($owned) => $owned->whereNull('data->import_no_owner')->orWhere('data->import_no_owner', false))),
                fn ($r) => $this->row($r->title, $r->data['source_project_lead'] ?? null, 'Date not recorded',
                    $this->labelFields(array_intersect_key($r->data ?? [], OperationsReporting::fields('posm_deployment', $private))))),

        ];
    }

    private function movement(AssetWarehouseRequest $move): array
    {
        return $this->row($move->asset?->name ?? 'Warehouse asset', $move->requester?->name, AssetWarehouseRequest::statusLabel($move->status), [
            'Request' => $move->request_code, 'Quantity requested' => $move->requested_quantity,
            'Requested for' => $move->requested_for, 'Issued at' => $move->issued_at, 'Returned at' => $move->returned_at,
            'Destination' => $move->destination_location, 'Warehouse' => $move->asset?->warehouse_location,
            'Current available warehouse units' => $move->asset?->warehouse_quantity, 'Asset tag' => $move->asset?->asset_tag,
            'Activation' => $move->operationsActivation?->title, 'Campaign' => $move->campaign?->name,
        ]);
    }

    private function clientRelations(string $section, string $from, string $to, ?int $staff, bool $private): array
    {
        $owned = fn ($query) => $query->with('owner:id,name')->when($staff, fn ($q) => $q->where('owner_id', $staff));
        if ($section === 'accounts') {
            return [
                $this->page('Account ownership', 'accounts', $owned(CrmAccountRelationship::query())->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $to))->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)),
                    fn ($r) => $this->row($r->client_name, $r->owner?->name, Str::headline($r->relationship), ['Starts' => $r->starts_on, 'Ends' => $r->ends_on, 'Note' => $r->note])),
                $this->page('Account allocations', 'allocations', $owned(CrmAccountAllocation::query()), fn ($r) => $this->row($r->client_name, $r->owner?->name, null, ['Monthly target' => $r->monthly_target, 'Year-to-date target' => $r->ytd_target, 'Budget target' => $r->budget_target, 'Reported target' => $r->reported_target])),
            ];
        }
        if ($section === 'follow_up') {
            return [$this->page('Client meetings, issues and follow-up', 'client_followup', $owned(CrmClientRecord::query())->with('project:id,project_name')
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']), fn ($r) => $this->row($r->client_name.' · '.Str::headline($r->kind), $r->owner?->name, Str::headline($r->status),
                    ['Project' => $r->project?->project_name, 'Reference' => $r->reference, 'Notes' => $r->notes] + $this->labelFields($r->data ?? [])))];
        }
        $projects = $owned(CrmPipelineProject::query())->where('source_period_confirmed', true)->whereBetween('week_start', [$from, $to])->latest('week_start');
        $projectRows = $this->page($section === 'revenue' ? 'Project revenue snapshots' : 'Pipeline projects', 'pipeline', $projects,
            fn ($p) => $this->row($p->project_name, $p->owner?->name, Str::headline($p->stage), ['Client' => $p->client_name, 'Brand' => $p->brand, 'Week' => $p->week_start, 'Expected close' => $p->expected_close_date, 'Description' => $p->description, 'Bottlenecks' => $private ? ($p->bottlenecks ?: $p->shared_bottlenecks) : $p->shared_bottlenecks, 'Next action' => $private ? ($p->next_action ?: $p->shared_next_action) : $p->shared_next_action] + ($private ? $this->revenue($p) + ['Proposal' => $p->proposed_amount, 'PO value' => $p->po_amount, 'Invoiced' => $p->invoiced_amount, 'Collected' => $p->collected_amount, 'Remarks' => $p->remarks] : [])));
        if ($section !== 'revenue') {
            return [$projectRows];
        }
        $targets = $owned(CrmAccountTarget::query())->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from);
        return [
            $this->page('Account targets and progress', 'targets', $targets, fn ($r) => $this->row($r->client_name, $r->owner?->name, $r->needs_reconciliation ? 'Needs reconciliation' : Str::headline($r->revenue_state), ['From' => $r->period_start, 'To' => $r->period_end] + $this->revenue($r))),
            $projectRows,
            $this->page('Purchase orders and invoice register', 'crm_financial', $owned(CrmFinancialRegister::query())->whereBetween('as_of', [$from, $to]), fn ($r) => $this->row($r->client_name, $r->owner?->name, null, ['As of' => $r->as_of, 'PO amount' => $r->po_amount, 'PO balance' => $r->po_balance, 'Invoiced' => $r->invoiced_amount])),
        ];
    }

    private function revenue(CrmPipelineProject|CrmAccountTarget $row): array
    {
        $confirmed = $row->confirmedRevenue();
        return [
            'Target (GH₵)' => $row->target_amount, 'Reported (GH₵)' => $row->actual_amount,
            'Confirmed (GH₵)' => $confirmed, 'Revenue status' => Str::headline($row->revenue_state ?? 'Unrecorded'),
            'Shortfall vs. confirmed (GH₵)' => $confirmed !== null && $row->target_amount !== null ? (float) $row->target_amount - $confirmed : null,
        ];
    }

    private function creative(User $viewer, string $from, string $to, ?int $staff): array
    {
        $briefs = CreativeRequest::visibleTo($viewer)->with(['requester:id,name', 'assignee:id,name'])
            ->where(fn ($q) => $q->whereBetween('received_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->orWhere(fn ($work) => $work->whereDate('planned_start', '<=', $to)->whereDate('internal_eta', '>=', $from)))
            ->when($staff, fn ($q) => $q->where(fn ($who) => $who->where('assignee_id', $staff)->orWhere('requester_id', $staff)
                ->orWhere(fn ($support) => $support->withSupportingStaff($staff))));
        return [$this->page('Creative briefs', 'briefs', $briefs, fn ($r) => $this->row($r->title, $r->assignee?->name, Str::headline($r->stage), [
            'Requested by' => $r->requester?->name, 'Planned start' => $r->planned_start, 'Internal ETA' => $r->internal_eta,
            'Requested deadline' => $r->requested_deadline, 'Completed' => $r->completed_at,
        ]))];
    }

    private function finance(string $from, string $to, ?int $staff): array
    {
        $claims = PettyCashClaim::with('user:id,name')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($staff, fn ($q) => $q->where('user_id', $staff));
        $invoices = SupplierInvoice::with('submitter:id,name')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($staff, fn ($q) => $q->where('submitted_by', $staff));
        $budgets = \App\Models\ProjectBudget::with('creator:id,name')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($staff, fn ($q) => $q->where('created_by', $staff));
        $advances = SalaryAdvance::with('user:id,name')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($staff, fn ($q) => $q->where('user_id', $staff));
        return [
            $this->page('Expense claims', 'claims', $claims, fn ($r) => $this->row($r->description ?: 'Expense claim', $r->user?->name, $r->status, [
                'Amount' => $r->currency.' '.$r->amount, 'Submitted' => $r->created_at,
            ], url: $r->receipt_path ? route('portal.finance.claims.receipt', $r) : null)),
            $this->page('Supplier invoices', 'invoices', $invoices, fn ($r) => $this->row($r->supplier_name ?: 'Supplier invoice', $r->submitter?->name, $r->status, [
                'Invoice number' => $r->invoice_number, 'Amount' => $r->currency.' '.$r->amount, 'Submitted' => $r->created_at,
            ], url: $r->attachment_path ? route('portal.finance.invoices.attachment', $r) : null)),
            $this->page('Project budgets', 'budgets', $budgets, fn ($r) => $this->row($r->title, $r->creator?->name, $r->status, [
                'Total' => $r->currency.' '.$r->total_amount, 'Notes' => $r->notes,
            ], url: route('portal.finance.budgets.show', $r))),
            $this->page('Salary advances', 'advances', $advances, fn ($r) => $this->row('Salary advance', $r->user?->name, Str::headline($r->status), [
                'Amount' => $r->currency.' '.$r->amount, 'Repayment starts' => $r->repayment_start_date, 'Submitted' => $r->created_at,
            ], url: route('portal.finance.advances.index'))),
        ];
    }

    private function people(string $from, string $to, ?int $staff): array
    {
        $leaves = LeaveApplication::with('user:id,name')->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->when($staff, fn ($q) => $q->where('user_id', $staff));
        $appraisals = \App\Models\Appraisal::with('employee:id,name')->whereDate('created_at', '<=', $to)->whereDate('created_at', '>=', $from)
            ->when($staff, fn ($q) => $q->where('user_id', $staff));
        return [
            $this->page('Leave requests', 'leaves', $leaves, fn ($r) => $this->row(Str::headline($r->leave_type).' leave', $r->user?->name, Str::headline($r->status), [
                'Starts' => $r->start_date, 'Ends' => $r->end_date,
            ], url: route('portal.leaves.show', $r))),
            $this->page('Staff appraisals', 'appraisals', $appraisals, fn ($r) => $this->row($r->quarter.' '.$r->year.' appraisal', $r->employee?->name, $r->status_label, [
                'Final score' => $r->status === 'approved' ? $r->final_score : null,
            ], url: auth()->user()->canAuditHrAndFinance() ? route('portal.appraisals.audit.form', $r) : ($r->employee ? route('portal.appraisals.report', $r->employee) : null))),
        ];
    }

    private function office(string $from, string $to, ?int $staff): array
    {
        $fleet = FleetRequest::with('user:id,name')->whereBetween('requested_date', [$from, $to])->when($staff, fn ($q) => $q->where('user_id', $staff));
        $assets = Asset::with('assignee:id,name')->where(fn ($q) => $q->where('is_warehouse_tracked', false)->orWhereNull('is_warehouse_tracked'))
            ->when($staff, fn ($q) => $q->where('assigned_to', $staff));
        return [
            $this->page('Fleet requests', 'fleet', $fleet, fn ($r) => $this->row($r->optionLabel(), $r->user?->name, Str::headline($r->status), [
                'Requested date' => $r->requested_date, 'Destination' => $r->destination, 'Purpose' => $r->purpose,
            ], url: route('portal.fleet-requests', ['fleet_request' => $r->id]))),
            $this->page('Office assets · current snapshot', 'office_assets', $assets, fn ($r) => $this->row($r->name, $r->assignee?->name, $r->status, [
                'Asset tag' => $r->asset_tag_or_code, 'Location' => $r->location, 'Condition' => $r->condition,
            ])),
        ];
    }

    private function hrLiveRegisters(User $viewer,?int $staff): array
    {
        $service=app(HrLiveRegisters::class);
        $q=\App\Models\HrRegisterRecord::query()->when($staff,fn($q)=>$q->where(fn($q)=>$q->where('created_by',$staff)->orWhere('updated_by',$staff)))->latest('updated_at');
        return $this->page('HR live registers · current snapshot','hr_live_registers',$q,function($r)use($viewer,$service){
            $schema=$service->schema($r->kind);$fields=[];foreach($schema['fields']??[] as $key=>$f)$fields[$f['label']]=$service->value($r->data[$key]??null,$f,$viewer);
            return $this->row($r->label,User::find($r->updated_by??$r->created_by)?->name,$schema['label']??$r->kind,$fields,url:route('portal.hr-workspace',['hr_tab'=>$schema['tab']??'overview','hr_record_q'=>$r->label]));
        });
    }

    private function hrActivities(string $from, string $to, ?int $staff): array
    {
        $rows = HrDashboardActivity::with(['staff:id,name', 'assignee:id,name'])
            ->whereDate('opened_on', '<=', $to)->where(fn ($q) => $q->whereDate('opened_on', '>=', $from)
                ->orWhereDate('deadline', '>=', $from)->orWhereNotIn('status', ['Completed', 'Cancelled']))
            ->when($staff, fn ($q) => $q->where(fn ($who) => $who->where('assigned_to', $staff)->orWhere('staff_id', $staff)));
        return [$this->page('HR & Admin activities', 'hr', $rows, fn ($r) => $this->row($r->activity, $r->assignee?->name, $r->status.($r->isOverdue(min($to,today()->toDateString()))?' · Overdue':''), [
            'Category' => $r->category, 'Staff member' => $r->staff?->name, 'Opened' => $r->opened_on, 'Deadline' => $r->deadline, 'Remarks' => $r->remarks,'Completed at'=>$r->completed_at,'Priority'=>$r->priority,
        ],url:route('portal.hr-dashboard.activities.show',$r)))];
    }

    private function registers(string $department, string $from, string $to, ?int $staff, bool $private, array $kinds = []): array
    {
        $query = $this->reportQuery($department, $from, $to, $staff, !$private || in_array($department, ['operations_projects', 'brands_marketing'], true))
            ->when($kinds, fn ($q) => $q->whereIn('kind', $kinds))->latest('period_start');
        if ($department === 'hr_admin') {
            $query->orWhere(fn ($q) => $q->whereIn('id', $this->reportQuery('admin_drivers', $from, $to, $staff, !$private)->select('id')));
        }
        $rows = $this->paginate('registers', $query);
        $people = User::whereIn('id', $rows->pluck('owner_id'))->pluck('name', 'id');
        return [['label' => 'Department source records', 'rows' => $rows->through(function ($r) use ($people, $private, $department) {
            $fields = $department === 'operations_projects' ? OperationsReporting::fields($r->kind, $private) : config('department_reporting.'.$r->department.'.'.$r->kind, []);
            if ($department === 'brands_marketing') {
                $fields += config('brands_dashboard.schemas.'.$r->kind, []);
                unset($fields['collaborators']);
            }
            return $this->row($r->title, empty($r->data['import_no_owner']) ? $people->get($r->owner_id) : ($r->data['source_project_lead'] ?? null), Str::headline($r->status),
                ['Category' => Str::headline($r->kind), 'Client' => $r->client, 'Project' => $r->project, 'Reporting week' => $r->period_start, 'Reference' => $r->reference] + $this->labelFields(array_intersect_key($r->data ?? [], $fields)));
        })]];
    }

    private function weekly(string $department, string $from, string $to, ?int $staff): array
    {
        $rows = WeeklyConsolidatedItem::with('leadStaff:id,name')->whereIn('department', User::departmentAliases($department))
            ->whereIn('workflow_state', ['legacy', 'approved', 'verified', 'closed'])
            ->where(fn ($q) => $q->whereNull('report_status')->orWhereNotIn('report_status', ['draft', 'submitted', 'returned']))->whereBetween('week_start', [$from, $to])
            ->when($staff, fn ($q) => $q->where(fn ($who) => $who->where('lead_staff_id', $staff)
                ->orWhereJsonContains('supporting_staff_ids', $staff)->orWhereJsonContains('supporting_staff_ids', (string) $staff)))->latest('week_start');
        return [$this->page('Weekly source reports', 'weekly', $rows, fn ($r) => $this->row($r->campaign_name ?: 'Weekly update', $r->leadStaff?->name, $r->status, [
            'Client' => $r->client_name, 'Week starts' => $r->week_start, 'Deliverables' => $r->deliverables,
        ]))];
    }

    private function labelFields(array $fields): array
    {
        return collect($fields)->mapWithKeys(fn ($value, $name) => [Str::headline($name) => $value])->all();
    }
}
