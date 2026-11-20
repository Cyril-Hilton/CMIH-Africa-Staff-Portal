<?php

namespace App\Services;

use App\Models\AssetWarehouseRequest;
use App\Models\Attendance;
use App\Models\BoardroomRequest;
use App\Models\CreativeRequest;
use App\Models\CrmPipelineProject;
use App\Models\FleetRequest;
use App\Models\LeaveApplication;
use App\Models\OperationsHandover;
use App\Models\PerformanceEvent;
use App\Models\PerformanceRegister;
use App\Models\ProjectExecutionControl;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** Executive planning reads live sources; controls add missing facts, never parallel task/stock ledgers. */
class ExecutiveCommandCentre
{
    public function build(Request $r): array
    {
        abort_unless($r->user()->canViewExecutiveActivity(), 403);
        $f = $r->validate(['exec_view' => 'nullable|in:'.implode(',', array_keys(config('executive_tracker.tabs'))), 'exec_period' => 'nullable|in:day,week,month,year', 'exec_date' => 'nullable|date_format:Y-m-d', 'exec_department' => 'nullable|string|max:60', 'exec_person' => 'nullable|integer|exists:users,id', 'exec_project' => 'nullable|integer|exists:crm_pipeline_projects,id', 'exec_activation' => 'nullable|integer|exists:performance_registers,id', 'exec_brief' => 'nullable|integer|exists:creative_requests,id', 'exec_q' => 'nullable|string|max:150', 'exec_calendar' => 'nullable|in:week,month,year,agenda', 'exec_scope' => 'nullable|in:all,work,activity,meetings,availability,attendance', 'exec_attention' => 'nullable|boolean']);
        if (empty($f['exec_department']) && $r->filled('activity_department')) {
            $f['exec_department'] = $r->query('activity_department');
        }if (empty($f['exec_person']) && $r->filled('activity_staff')) {
            $f['exec_person'] = $r->integer('activity_staff');
        }$view = $f['exec_view'] ?? ($r->filled('weekly_department') || $r->filled('activity_department') ? 'reports' : 'overview');
        $period = $f['exec_period'] ?? ($view === 'calendar' ? 'week' : 'month');
        $anchor = Carbon::parse($f['exec_date'] ?? $r->query('report_date', today()));
        $from = match ($period) {
            'day' => $anchor->copy()->startOfDay(),'week' => $anchor->copy()->startOfWeek(),'year' => $anchor->copy()->startOfYear(),default => $anchor->copy()->startOfMonth()
        };
        $to = match ($period) {
            'day' => $anchor->copy()->endOfDay(),'week' => $from->copy()->endOfWeek(),'year' => $from->copy()->endOfYear(),default => $from->copy()->endOfMonth()
        };
        $staff = User::internalStaff()->orderBy('name')->get(['id', 'name', 'department', 'status', 'job_title', 'line_manager_id', 'profile_photo_path', 'work_email', 'email', 'phone']);
        $names = $staff->keyBy('id');
        $stock = \App\Models\Asset::where('is_warehouse_tracked', true)->with('assignee:id,name', 'lastHandler:id,name')->orderBy('name')->get(['id', 'name', 'asset_tag', 'warehouse_quantity', 'warehouse_location', 'condition', 'status', 'assigned_to', 'last_handled_by', 'last_handled_at']);
        $approvedDemand = AssetWarehouseRequest::where('status', AssetWarehouseRequest::STATUS_APPROVED_FOR_USE)->get()->groupBy('asset_id')->map(fn ($rows) => (int) $rows->sum('requested_quantity'));
        $stockConflicts = $stock->filter(fn ($asset) => $approvedDemand->get($asset->id, 0) > $asset->warehouse_quantity)->map(fn ($asset) => ['asset' => $asset, 'requested' => $approvedDemand->get($asset->id), 'shortfall' => $approvedDemand->get($asset->id) - $asset->warehouse_quantity]);
        $all = CrmPipelineProject::with('owner:id,name')->where('source_period_confirmed', true)->get();
        $groups = $all->groupBy(fn ($p) => ClientRelationsCentre::key($p->client_name).'|'.ClientRelationsCentre::key($p->project_name));
        $idMap = [];
        $projectRows = collect();
        $controls = Schema::hasTable('project_execution_controls') ? ProjectExecutionControl::with('recorder:id,name')->get()->keyBy('crm_project_id') : collect();
        foreach ($groups as $g) {
            $p = $g->sortByDesc(fn ($x) => $x->week_start->format('Y-m-d').sprintf('%012d', $x->id))->first();
            foreach ($g as $snapshot) {
                $idMap[$snapshot->id] = $p->id;
            }$control = $controls->get($p->id) ?? $controls->only($g->pluck('id')->all())->sortByDesc('updated_at')->first();
            $projectRows->put($p->id, ['project' => $p, 'ids' => $g->pluck('id')->all(), 'control' => $control, 'schedule' => $control?->data['schedule'] ?? [], 'process' => $control?->data['process'] ?? [], 'compliance' => $control?->data['compliance'] ?? []]);
        }
        $nativeActivations = PerformanceRegister::whereIn('department', User::departmentAliases('operations_projects'))->where('kind', 'activation')->whereNotNull('approved_at')->where('status', '!=', 'cancelled')->with('owner:id,name')->get();
        $activationControls = ProjectExecutionControl::with('recorder:id,name')->whereNotNull('operations_activation_id')->get()->keyBy('operations_activation_id');
        foreach ($nativeActivations as $activation) {
            $linked = $activation->data['_crm_project_id'] ?? $activation->data['crm_project_id'] ?? null;
            if ($linked && isset($idMap[$linked])) {
                continue;
            }$id = -$activation->id;
            $p = new CrmPipelineProject(['id' => $id, 'owner_id' => $activation->owner_id, 'client_name' => $activation->client, 'project_name' => $activation->project ?: $activation->title, 'stage' => $activation->status === 'completed' ? 'project_completed' : 'project_ongoing', 'week_start' => $activation->period_start, 'week_end' => $activation->period_end, 'created_at' => $activation->created_at]);
            $p->id = $id;
            $p->setRelation('owner', $activation->owner);
            $c = $activationControls->get($activation->id);
            $sourceSchedule = array_filter(['starts_at' => $activation->data['starts_on'] ?? null, 'ends_at' => ! empty($activation->data['ends_on']) ? Carbon::parse($activation->data['ends_on'])->endOfDay()->toIso8601String() : null, 'operations_owner_id' => $activation->owner_id, 'venue' => $activation->data['location'] ?? null, 'state' => $activation->status === 'completed' ? 'completed' : 'planning'], fn ($v) => $v !== null);
            $projectRows->put($id, ['project' => $p, 'ids' => [], 'source_kind' => 'activation', 'native_id' => $activation->id, 'source_url' => route('portal.executive.registers.show', $activation), 'control' => $c, 'schedule' => array_merge($sourceSchedule, $c?->data['schedule'] ?? []), 'process' => $c?->data['process'] ?? [], 'compliance' => $c?->data['compliance'] ?? []]);
        }
        $activationMap = $nativeActivations->mapWithKeys(function ($a) use ($idMap) {
            $linked = $a->data['crm_project_id'] ?? $a->data['_crm_project_id'] ?? null;

            return [$a->id => $linked ? ($idMap[$linked] ?? $linked) : -$a->id];
        });
        $briefs = CreativeRequest::visibleTo($r->user())->studioWork()->whereNull('parent_request_id')->with(['parts.assignee:id,name', 'assignee:id,name', 'requester:id,name', 'reviewer:id,name'])->get();
        $directBriefIds = [];
        foreach ($briefs as $brief) {
            if ($brief->crm_project_id) {
                continue;
            }$id = -1000000000 - $brief->id;
            $directBriefIds[$brief->id] = $id;
            $p = new CrmPipelineProject(['id' => $id, 'owner_id' => $brief->requester_id, 'client_name' => $brief->client, 'project_name' => $brief->title, 'stage' => $brief->stage === 'completed' ? 'project_completed' : 'project_ongoing', 'week_start' => $brief->received_at ?? $brief->created_at, 'created_at' => $brief->created_at]);
            $p->id = $id;
            $p->setRelation('owner', $brief->requester);
            $projectRows->put($id, ['project' => $p, 'ids' => [], 'source_kind' => 'creative', 'native_id' => $brief->id, 'source_url' => route('portal.creative', ['request' => $brief->id]), 'control' => null, 'schedule' => [], 'process' => [], 'compliance' => []]);
        }
        $handovers = OperationsHandover::with('lead:id,name', 'requester:id,name')->get();
        $events = collect();
        $add = function (string $key, string $kind, string $title, $start, $end, $owner, ?int $projectId, string $state, ?string $url, array $meta = [], array $support = []) use (&$events, $idMap) {
            if (! $start) {
                return;
            }$s = Carbon::parse($start);
            $dateOnly = (is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) || ($start instanceof \DateTimeInterface && $s->format('H:i:s') === '00:00:00');
            if (! $end && ((is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) || (in_array($kind, ['task', 'client', 'finance', 'setup_at', 'teardown_at', 'recap_due_at', 'assets', 'department'], true) && $s->format('H:i:s') === '00:00:00'))) {
                $s->endOfDay();
            }$e = $end ? Carbon::parse($end) : $s->copy();
            if ($e->lt($s)) {
                $e = $s->copy();
            }$done = in_array(strtolower($state), ['completed', 'approved', 'verified', 'closed', 'returned', 'paid', 'submitted', 'presented', 'rejected', 'cancelled'], true) || ($kind === 'resources' && in_array($state, ['confirmed', 'contracted'])) || ($kind === 'assets' && in_array($state, ['ready', 'dispatched', 'returned']));
            $actual = ! empty($meta['_actual_at']) ? Carbon::parse($meta['_actual_at']) : null;
            if ($actual) {
                $done = true;
            }$events->push(['date_only' => (bool) $dateOnly, 'key' => $key, 'kind' => $kind, 'title' => $title, 'start' => $s, 'end' => $e, 'owner' => (int) $owner, 'project_id' => $projectId ? ($idMap[$projectId] ?? $projectId) : null, 'state' => $state, 'done' => $done, 'late' => $kind === 'activity' ? false : ($actual ? $actual->gt(! empty($meta['_commitment_at']) ? Carbon::parse($meta['_commitment_at']) : $e) : (! $done && $e->lt(now()))), 'url' => $url, 'meta' => $meta, 'support' => array_values(array_unique(array_map('intval', $support)))]);
        };
        foreach ($projectRows as $id => $row) {
            $p = $row['project'];
            $s = $row['schedule'];
            $c = $row['control'];
            $url = $c ? route('portal.executive.controls.show', $c) : ($row['source_url'] ?? route('portal.project-journey', ['project' => $id]));
            $owner = $s['operations_owner_id'] ?? $handovers->first(fn ($h) => in_array($h->project_id, $row['ids']))?->lead_id ?? $p->owner_id;
            if ($s) {
                $add('plan:'.$id.':live', (($row['source_kind'] ?? 'crm') === 'crm' && $nativeActivations->contains(fn ($a) => in_array($a->data['crm_project_id'] ?? $a->data['_crm_project_id'] ?? 0, $row['ids'])) ? 'project_window' : 'activation'), $p->project_name, $s['starts_at'] ?? null, $s['ends_at'] ?? null, $owner, $id, $s['state'] ?? 'planning', $url, ['Account / source owner' => $p->owner?->name, 'Plan recorded by' => $c?->recorder?->name, 'Plan recorded at' => $c?->updated_at?->format('d M Y H:i'), 'Venue' => $s['venue'] ?? null, 'Staff planned' => $s['staff_required'] ?? null, 'Staff confirmed' => $s['staff_confirmed'] ?? null]);
            }
            foreach (['setup_at' => 'Setup / build', 'teardown_at' => 'Teardown', 'recap_due_at' => 'Recap report'] as $field => $label) {
                $add('plan:'.$id.':'.$field, $field, $label.' · '.$p->project_name, $s[$field] ?? null, null, $field === 'recap_due_at' ? $p->owner_id : $owner, $id, $field === 'recap_due_at' && ! empty($s['recap_submitted_at']) ? 'submitted' : ($s['state'] ?? 'planning'), $url, ['Recap actually submitted' => $s['recap_submitted_at'] ?? null, '_actual_at' => $field === 'recap_due_at' ? ($s['recap_submitted_at'] ?? null) : null]);
            }
            foreach (config('executive_tracker.process') as $key => $label) {
                $step = $row['process'][$key] ?? [];
                $add('process:'.$c?->id.':'.$key, 'process', $label.' · '.$p->project_name, $step['due_at'] ?? null, null, $step['owner_id'] ?? null, $id, $step['status'] ?? 'not_started', $url, ['_actual_at' => $step['completed_at'] ?? null, 'Completion reported' => $step['completed_at'] ?? null, 'Evidence' => $step['evidence'] ?? null, 'Recorded by' => $c?->recorder?->name, 'Recorded at' => $c?->updated_at?->format('d M Y H:i')]);
            }
            $compliance = $row['compliance'];
            $check = $this->compliance($compliance);
            $add('compliance:'.$c?->id, 'compliance', 'Compliance clearance · '.$p->project_name, $compliance['due_at'] ?? null, null, $compliance['owner_id'] ?? null, $id, $check['ready'] ? 'verified' : 'in_progress', $url, ['Readiness credit' => $check['score'].'%', 'Checks still needing attention' => $check['outstanding']]);
            foreach (['resources', 'assets'] as $type) {
                foreach ($c?->data[$type]['rows'] ?? [] as $item) {
                    $add($type.':'.$c->id.':'.$item['id'], $type, $item['description'].' · '.$p->project_name, $item[$type === 'resources' ? 'start_at' : 'due_at'] ?? null, $type === 'resources' ? ($item['end_at'] ?? null) : null, $item['owner_id'] ?? $item['staff_id'] ?? null, $id, $item['status'] ?? 'requested', $url, ['_availability_user_id' => $item['staff_id'] ?? null, 'Required' => $item['required'] ?? null, 'Confirmed / secured' => $item['confirmed'] ?? null, 'Party' => $item['party'] ?? null, 'Dispatched' => $item['dispatched_at'] ?? null, 'Returned' => $item['returned_at'] ?? null]);
                }
            }
            $add('crm:'.$id.':close', 'client', 'Client project commitment · '.$p->project_name, $p->expected_close_date, null, $p->owner_id, $id, $p->stage === 'project_completed' ? 'completed' : $p->stage, route('portal.project-journey', ['project' => $id]), ['Entered in portal' => $p->created_at->format('d M Y H:i'), 'Client brief received · staff reported' => $p->team_workflow['client_brief_received_at'] ?? null]);
        }
        foreach ($briefs as $parent) {
            foreach ($parent->is_group ? $parent->parts : [$parent] as $b) {
                $url = route('portal.creative', ['request' => $b->id]);
                $timeline = app(\App\Services\Creative\CreativeTimeline::class)->build($b);
                $meta = ['_commitment_at' => \App\Services\Creative\CreativeDeadlines::scoringDue($b)?->toIso8601String(), 'Client brief received - staff reported' => $timeline['client_brief_received_at'], 'Receipt recorded by' => $timeline['client_brief_received_recorded_by'], 'Creative request submitted' => $timeline['creative_requested_at'], 'Creative request routed' => $timeline['creative_routed_at'], 'Client Relations deadline' => $timeline['client_relations_deadline'], 'Client Relations deadline recorded by' => $timeline['client_relations_recorded_by'], 'Creative deadline recorded by' => $timeline['hod_recorded_by'] ?? null, '_actual_at' => $b->first_submitted_at?->toIso8601String(), 'Brief created' => $b->created_at?->format('d M Y H:i'), 'Requested deadline' => $b->requested_deadline?->format('d M Y H:i'), 'Creative HOD deadline' => $b->internal_eta?->format('d M Y H:i'), 'Brief requested by' => $parent->requester?->name, 'Client Relations' => $parent->crmProject?->owner?->name, 'First submission' => $b->first_submitted_at?->format('d M Y H:i'), 'Verified completion' => $b->completed_at?->format('d M Y H:i')];
                $add('creative:'.$b->id, $b->internal_eta ? 'creative' : 'activity', $b->title, $b->planned_start ?? $b->internal_eta ?? $b->received_at ?? $b->created_at, $b->internal_eta, $b->assignee_id, ($parent->crm_project_id ?? $directBriefIds[$parent->id] ?? null), ($b->stage === 'completed' && ! $b->completed_at ? 'reported_completed' : $b->stage), $url, $meta, $b->supportingStaffIds());
                if ($b->requested_deadline && (! $b->internal_eta || ! $b->requested_deadline->equalTo($b->internal_eta))) {
                    $add('creative:'.$b->id.':requested', 'requested', 'Requester deadline · '.$b->title, $b->requested_deadline, null, $parent->requester_id, ($parent->crm_project_id ?? $directBriefIds[$parent->id] ?? null), $b->first_submitted_at ? 'submitted' : ($b->stage === 'completed' ? 'reported_completed' : $b->stage), $url, array_merge($meta, ['_commitment_at' => $b->requested_deadline?->toIso8601String()]));
                }
            }
        }
        foreach ($handovers as $h) {
            $url = route('portal.operations.handovers.index', ['handover' => $h->id]);
            $meta = ['Client Relations' => $h->requester?->name, 'Handover recorded' => $h->created_at->format('d M Y H:i'), 'Meeting held' => $h->meeting_held_at?->format('d M Y H:i'), 'CR signed' => $h->cr_signoff_at?->format('d M Y H:i'), 'Operations signed' => $h->ops_signoff_at?->format('d M Y H:i'), 'HOD approved' => $h->handover_approved_at?->format('d M Y H:i'), 'Requested setup' => $h->setup_due_at?->format('d M Y H:i'), 'Committed setup' => $h->agreed_setup_due_at?->format('d M Y H:i'), 'Requested execution' => $h->execution_due_at?->format('d M Y H:i'), 'Committed execution' => $h->agreed_execution_due_at?->format('d M Y H:i')];
            foreach (['meeting_scheduled_at' => 'Handover meeting', 'event_at' => 'Execution starts', 'agreed_setup_due_at' => 'Operations setup commitment', 'agreed_execution_due_at' => 'Operations execution commitment'] as $field => $label) {
                $add('handover:'.$h->id.':'.$field, 'operations', $label.' · '.$h->title, $h->$field, null, $h->lead_id ?? $h->requester_id, $h->project_id, ($field === 'meeting_scheduled_at' && $h->meeting_held_at ? 'completed' : ($field === 'agreed_execution_due_at' && $h->execution_completed_at ? 'completed' : ($h->stage === 'completed' ? 'completed' : 'in_progress'))), $url, array_merge($meta, ['_actual_at' => $field === 'meeting_scheduled_at' ? $h->meeting_held_at?->toIso8601String() : ($field === 'agreed_execution_due_at' ? $h->execution_completed_at?->toIso8601String() : null)]), $h->task?->supporting_staff_ids ?? []);
            }
        }
        $sourceTasks = $briefs->pluck('task_id')->merge($handovers->pluck('task_id'))->filter();
        $projectTaskIds = $projectRows->flatMap(fn ($row) => collect($row['control']?->data['tasks']['rows'] ?? [])->pluck('task_id'))->filter()->unique();
        foreach (Task::realWork()->whereIn('id', $projectTaskIds)->whereNotIn('id', $sourceTasks)->where(fn ($q) => $q->whereBetween('due_on', [$from, $to])->orWhere(fn ($d) => $d->whereNull('due_on')->whereBetween('created_at', [$from, $to])))->with('assignee:id,name,department')->get() as $t) {
            $title = $t->canBeViewedBy($r->user()) ? $t->title : 'Restricted task';
            $add('task:'.$t->id, $t->due_on ? 'task' : 'activity', $t->due_on ? $title : 'Task logged - '.$title, $t->due_on ?? $t->created_at, null, $t->assigned_to, $t->custom_fields['executive_control_project_id'] ?? (! empty($t->custom_fields['executive_control_activation_id']) ? -(int) $t->custom_fields['executive_control_activation_id'] : null), $t->isApprovedForPerformance() ? 'verified' : (in_array(strtolower($t->status), ['completed', 'approved', 'paid', 'done']) ? 'awaiting_verification' : $t->status), $t->canBeViewedBy($r->user()) ? route('portal.tasks.show', ['task' => $t, 'return_to' => '/dashboard?'.http_build_query($r->query()).'#executive-command']) : null, ['_actual_at' => $t->isApprovedForPerformance() ? ($t->completion_review_requested_at ?? $t->completion_reviewed_at)?->toIso8601String() : null, 'Deadline' => $t->due_on?->format('d M Y H:i') ?? 'Deadline setup needed', 'Entered' => $t->created_at->format('d M Y H:i'), 'Progress' => $t->progress.'%', 'Completion review' => $t->completion_review_status ?? 'Awaiting completion'], $t->supporting_staff_ids ?? []);
        }
        $milestones = app(ExecutiveSourceMilestones::class);
        foreach (PerformanceRegister::where('department', 'finance')->where('status', '!=', 'cancelled')->whereNotNull('approved_at')->get() as $item) {
            foreach ($milestones->register($item) as $field => $milestone) {
                $add('finance:'.$item->id.':'.$field, 'finance', FinanceDashboard::canRead($r->user()) ? $item->title : 'Finance commitment', $milestone['due'], null, $item->owner_id, $item->data['_crm_project_id'] ?? null, $milestone['state'], route('portal.finance', ['finance_tab' => collect(FinanceDashboard::GROUPS)->search(fn ($v) => in_array($item->kind, $v)) ?: 'overview']), ['_actual_at' => $milestone['actual'], 'Actual outcome date' => $milestone['actual'], 'Date source' => 'Entered in Finance form', 'Published in portal' => $item->approved_at?->format('d M Y H:i'), 'Category' => ucwords(str_replace('_', ' ', $item->kind))]);
            }
        }
        foreach (PerformanceRegister::where('department', '!=', 'finance')->whereNotNull('approved_at')->where('status', '!=', 'cancelled')->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->get() as $item) {
            $private = in_array(User::normalizeDepartmentKey($item->department), ['hr_admin']) && ! $r->user()->canAuditHrAndFinance() && ! $r->user()->isCvoOrSuperAdmin();
            $title = $private ? 'HR commitment' : $item->title;
            $pid = $item->data['_crm_project_id'] ?? $item->data['crm_project_id'] ?? ($activationMap->get((int) ($item->data['activation_id'] ?? 0)) ?? ($item->kind === 'activation' && $projectRows->has(-$item->id) ? -$item->id : null));
            $url = route('portal.executive.registers.show', ['register' => $item, 'return_to' => '/dashboard?'.http_build_query($r->query()).'#executive-command']);
            $dated = false;
            $mapped = $milestones->register($item);
            foreach ($mapped as $field => $milestone) {
                $dated = true;
                $add('register:'.$item->id.':'.$field, 'department', $title.' · '.ucwords(str_replace('_', ' ', $field)), $milestone['due'], null, $item->owner_id, $pid, $milestone['state'], $private ? null : $url, ['_actual_at' => $milestone['actual'], 'Actual outcome date' => $milestone['actual'], 'Date source' => 'Entered in department form', 'Published in portal' => $item->approved_at?->format('d M Y H:i')], $item->data['collaborator_ids'] ?? []);
            }
            foreach (['starts_on', 'due_on', 'action_due_on', 'report_due_on', 'expected_on', 'deadline'] as $field) {
                if (isset($mapped[$field])) {
                    continue;
                }
                if (! empty($item->data[$field])) {
                    if ($item->kind === 'activation' && $field === 'starts_on' && $projectRows->has(-$item->id)) {
                        $dated = true;

                        continue;
                    }
                    $dated = true;
                    $add('register:'.$item->id.':'.$field, $field === 'starts_on' ? ($item->kind === 'activation' ? 'activation' : 'operations') : 'department', $title.' · '.ucwords(str_replace('_', ' ', $field)), $item->data[$field], $field === 'starts_on' && ! empty($item->data['ends_on']) ? Carbon::parse($item->data['ends_on'])->endOfDay() : null, $item->owner_id, $pid, $item->status, $url, ['Source' => 'Approved '.User::departmentLabel($item->department).' register', 'Recorded' => $item->created_at->format('d M Y H:i'), 'Approved' => $item->approved_at?->format('d M Y H:i')], $item->data['collaborator_ids'] ?? []);
                }
            }if (! $dated) {
                $add('register:'.$item->id, 'activity', 'Reporting period · '.$title, $item->period_start, $item->period_end?->endOfDay(), $item->owner_id, $pid, $item->status, $url, ['Source' => 'Approved departmental register · not a deadline'], $item->data['collaborator_ids'] ?? []);
            }
        }
        foreach (\App\Models\HrRegisterRecord::all() as $record) {
            $schema = app(HrLiveRegisters::class)->schema($record->kind);
            $canRead = $r->user()->isCvoOrSuperAdmin() || $r->user()->canAuditHrAndFinance();
            if (! $canRead) {
                continue;
            }
            foreach ($milestones->hr($record->kind, $record->data ?? []) as $field => $milestone) {
                $add('hrregister:'.$record->id.':'.$field, 'department', $canRead ? ($schema['label'] ?? 'HR register').' · '.$record->label : 'Restricted HR commitment', $milestone['due'], null, $record->data['responsible_id'] ?? $record->created_by, null, $milestone['state'], $canRead ? route('portal.hr-workspace', ['hr_tab' => $schema['tab'] ?? 'people', 'hr_record_q' => $record->label]) : null, ['_actual_at' => $milestone['actual'], 'Actual outcome date' => $milestone['actual'], 'Date source' => 'Entered in HR register', 'Source recorded in portal' => $record->created_at?->format('d M Y H:i')]);
            }
        }
        foreach (\App\Models\HrDashboardActivity::whereNotNull('deadline')->whereBetween('deadline', [$from->toDateString(), $to->toDateString()])->get() as $activity) {
            $private = ! $r->user()->isCvoOrSuperAdmin() && ! $r->user()->canAuditHrAndFinance();
            $add('hractivity:'.$activity->id, 'department', $private ? 'Restricted HR commitment' : $activity->activity, $activity->deadline, null, $activity->assigned_to, null, strtolower($activity->status), route('portal.hr-workspace'), ['_actual_at' => $activity->completed_at?->toIso8601String(), 'Source' => 'HR activity · private remarks excluded'], $activity->supporting_staff_ids ?? []);
        }
        foreach (AssetWarehouseRequest::with('asset:id,name')->whereNotIn('status', [AssetWarehouseRequest::STATUS_REJECTED])->whereBetween('requested_for', [$from->toDateString(), $to->toDateString()])->get() as $warehouse) {
            $add('warehouse:'.$warehouse->id, 'assets', 'Warehouse required · '.$warehouse->asset?->name, $warehouse->requested_for, null, $warehouse->custodian_id ?? $warehouse->requested_by, ($warehouse->crm_project_id ?? $activationMap->get((int) $warehouse->operations_activation_id)), (in_array($warehouse->status, [AssetWarehouseRequest::STATUS_ISSUED, AssetWarehouseRequest::STATUS_CLOSED]) || ($warehouse->status === AssetWarehouseRequest::STATUS_APPROVED_FOR_USE && ! $stockConflicts->contains(fn ($conflict) => $conflict['asset']->id === $warehouse->asset_id))) ? 'approved' : 'in_progress', route('portal.assets.warehouse.index'), ['Source request' => $warehouse->request_code, 'Quantity' => $warehouse->requested_quantity, 'Warehouse status' => $warehouse->status, 'Actually issued' => $warehouse->issued_at?->format('d M Y H:i'), 'Actually returned' => $warehouse->returned_at?->format('d M Y H:i')]);
        }
        foreach (WeeklyConsolidatedItem::with('leadStaff:id,name')->whereIn('workflow_state', ['legacy', 'approved', 'verified', 'closed'])->where(fn ($q) => $q->whereNull('report_status')->orWhereNotIn('report_status', ['draft', 'submitted', 'returned']))->where('department', '!=', 'creatives')->whereDate('week_start', '<=', $to)->whereDate('week_end', '>=', $from)->get() as $w) {
            $restricted = in_array(User::normalizeDepartmentKey($w->department), ['finance', 'hr_admin']) && ! $r->user()->canAuditHrAndFinance() && ! $r->user()->isCvoOrSuperAdmin();
            $title = $restricted ? User::departmentLabel($w->department).' report' : ($w->campaign_name ?: User::departmentLabel($w->department).' report');
            if ($w->project_ends_on) {
                $actual = in_array($w->workflow_state, ['verified', 'closed']) ? ($w->completed_at ?? $w->verified_at ?? $w->closed_at) : null;
                $add('weekly:'.$w->id.':project', 'project_window', 'Weekly project · '.$title, $w->project_starts_on ?? $w->project_ends_on, $w->project_ends_on->copy()->endOfDay(), $w->lead_staff_id, null, $actual ? 'verified' : 'in_progress', route('dashboard', ['weekly_department' => $w->department, 'report_date' => $w->week_start->toDateString()]), ['_actual_at' => $actual?->toIso8601String(), 'Date source' => 'Project window entered in weekly form', 'Actual completion' => $actual?->format('d M Y H:i')], $w->supporting_staff_ids ?? []);
            }
            $add('weekly:'.$w->id, 'activity', 'Reporting week - '.$title, $w->week_start, $w->week_end?->endOfDay(), $w->lead_staff_id, null, $w->status, route('dashboard', ['weekly_department' => $w->department, 'weekly_from' => $w->week_start->toDateString(), 'weekly_to' => $w->week_end?->toDateString()]), ['Source' => 'Weekly departmental report', 'Reporting status' => $w->status, 'Verification' => $w->workflow_state === 'legacy' ? 'Original reported status; not independent completion' : 'Source workflow: '.$w->workflow_state], $w->supporting_staff_ids ?? []);
        }
        foreach (BoardroomRequest::with('invitedStaff:id,name')->whereNotIn('status', ['rejected', 'cancelled'])->where('starts_at', '<=', $to)->where('ends_at', '>=', $from)->get() as $b) {
            $add('boardroom:'.$b->id, 'meeting', 'Boardroom · '.($b->title ?? $b->purpose ?? 'Meeting'), $b->starts_at, $b->ends_at, $b->user_id, null, $b->status, route('portal.boardroom-requests'), ['Attendees' => $b->invitedStaff->pluck('name')->implode(', ')], $b->invitedStaff->pluck('id')->all());
        }
        $leave = LeaveApplication::where('status', 'approved')->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get();
        foreach ($leave as $l) {
            $add('leave:'.$l->id, 'absence', 'Approved absence', $l->start_date, $l->end_date->copy()->endOfDay(), $l->user_id, null, 'approved', route('portal.leaves'), ['Availability' => 'Approved absence · reasons remain private']);
        }
        foreach (FleetRequest::where('status', 'approved')->whereBetween('requested_date', [$from, $to])->get() as $fleet) {
            $add('fleet:'.$fleet->id, 'fleet', 'Fleet · '.$fleet->optionLabel(), $fleet->requested_date->format('Y-m-d').' '.($fleet->requested_time ?? '00:00'), null, $fleet->user_id, null, $fleet->status, route('portal.fleet-requests'), ['Transport' => $fleet->optionLabel(), 'Passengers' => $fleet->passengers]);
        }
        if (($f['exec_scope'] ?? 'all') === 'attendance') {
            foreach (Attendance::whereBetween('clock_in_at', [$from, $to])->get() as $a) {
                $add('attendance:'.$a->id, 'attendance', 'Recorded attendance', $a->clock_in_at, $a->clock_out_at, $a->user_id, null, 'recorded', route('dashboard'), ['Clock-in' => $a->clock_in_at?->format('d M Y H:i'), 'Clock-out' => $a->clock_out_at?->format('d M Y H:i'), 'Status' => $a->status]);
            }
        }
        $availability = app(ApprovedAvailability::class)->windows($from, $to);
        foreach ($availability->filter(fn ($w) => str_starts_with($w['key'], 'passout:')) as $window) {
            $add($window['key'], 'absence', $window['label'], $window['start'], $window['end'], $window['user_id'], null, 'approved', route('portal.leaves'), ['Availability' => 'Approved afternoon release; reasons remain private']);
        }
        $events = $events->filter(fn ($e) => $e['start']->lte($to) && $e['end']->gte($from))->filter(function ($e) use ($f, $names, $projectRows) {
            $person = $names->get($e['owner']);
            $department = User::normalizeDepartmentKey($person?->department ?? '');
            if (! empty($f['exec_person']) && (int) $f['exec_person'] !== $e['owner'] && ! in_array((int) $f['exec_person'], $e['support'], true)) {
                return false;
            }if (! empty($f['exec_department']) && $department !== $f['exec_department']) {
                return false;
            }if ((! empty($f['exec_activation']) && $e['project_id'] !== -(int) $f['exec_activation']) || (! empty($f['exec_brief']) && $e['project_id'] !== -1000000000 - (int) $f['exec_brief'])) {
                return false;
            }if (! empty($f['exec_project']) && $e['project_id'] !== (int) $f['exec_project']) {
                return false;
            }if (! empty($f['exec_q']) && ! str_contains(mb_strtolower($e['title'].' '.($projectRows[$e['project_id']]['project']->client_name ?? '').' '.$person?->name), mb_strtolower($f['exec_q']))) {
                return false;
            }

            return match ($f['exec_scope'] ?? 'all') {
                'work' => ! in_array($e['kind'], ['absence', 'meeting', 'fleet', 'attendance', 'activity']),'activity' => $e['kind'] === 'activity','meetings' => in_array($e['kind'], ['meeting', 'fleet']),'availability' => $e['kind'] === 'absence','attendance' => $e['kind'] === 'attendance',default => $e['kind'] !== 'attendance'
            };
        })->map(function ($e) use ($names, $projectRows, $availability) {
            $availabilityOwner = (int) ($e['meta']['_availability_user_id'] ?? $e['owner']);
            $windows = $availability->where('user_id', $availabilityOwner);
            $e['leave_overlap'] = ! in_array($e['kind'], ['absence', 'activity', 'attendance']) && $windows->contains(fn ($w) => $w['start']->lte($e['end']) && $w['end']->gte($e['start']));
            $e['leave_deadline'] = ! in_array($e['kind'], ['absence', 'activity', 'attendance']) && $windows->contains(fn ($w) => $w['start']->lte($e['end']) && $w['end']->gte($e['end']));
            $e['person'] = $names->get($e['owner']);
            $e['project'] = $projectRows[$e['project_id']]['project'] ?? null;

            return $e;
        })->sortBy('start')->values();
        $dependencies = app(LinkedProjectDependencies::class)->work($r->user(), $events);
        $events = $events->map(function ($event) use ($dependencies) {
            $upstream = $dependencies->filter(fn ($d) => $d['blocked'] && in_array($event['key'], [$d['downstream_key'], $d['task_key']], true));
            $event['dependency_blocked'] = $upstream->isNotEmpty();
            if ($upstream->isNotEmpty()) {
                $event['meta']['Waiting on upstream owner'] = $upstream->pluck('upstream_owner')->filter()->unique()->implode(' · ');
                $event['meta']['Upstream assignment'] = $upstream->pluck('upstream')->implode(' · ');
                $event['meta']['Upstream verification state'] = $upstream->pluck('upstream_state')->unique()->implode(' · ');
            }

            return $event;
        });
        $resourceRows = collect();
        $assetRows = collect();
        $clashes = collect();
        foreach ($projectRows as $id => $row) {
            $c = $row['control'];
            $row['check'] = $this->compliance($row['compliance']);
            if ($view === 'projects') {
                $row['flow'] = app(ExecutiveProjectFlow::class)->build($row, $briefs, $handovers, $nativeActivations, $r->user());
            }
            $row['chart_key'] = $row['project']->client_name.' / '.$row['project']->project_name.(isset($row['source_kind']) ? ' · '.$row['source_kind'].' #'.$row['native_id'] : '');
            $row['events'] = $events->where('project_id', $id);
            $row['execution_required'] = ($row['source_kind'] ?? null) === 'activation' || ! empty($row['schedule']['activation_type']) || ! empty($row['schedule']['starts_at']) || ($row['project']->team_workflow['operations_required'] ?? null) === true || $handovers->contains(fn ($h) => in_array($h->project_id, $row['ids'])) || $nativeActivations->contains(fn ($a) => in_array($a->data['crm_project_id'] ?? $a->data['_crm_project_id'] ?? 0, $row['ids']));
            $row['closed'] = in_array($row['project']->stage, ['project_completed', 'lost', 'cancelled']) || in_array($row['schedule']['state'] ?? '', ['completed', 'cancelled']);
            $budgetId = $row['process']['budget']['budget_id'] ?? null;
            $row['budget'] = $budgetId ? \App\Models\ProjectBudget::find($budgetId) : null;
            $row['budget_visible'] = $row['budget']?->canView($r->user()) ?? false;
            $row['staff_gap'] = max(0, (int) ($row['schedule']['staff_required'] ?? 0) - (int) ($row['schedule']['staff_confirmed'] ?? 0));
            foreach (['resources', 'assets'] as $type) {
                foreach ($c?->data[$type]['rows'] ?? [] as $item) {
                    $eventKey = $type.':'.$c->id.':'.$item['id'];
                    if (! $events->contains('key', $eventKey) && ! (! empty($f['exec_project']) && in_array((int) $f['exec_project'], $row['ids']) && empty($f['exec_person']) && empty($f['exec_department']) && empty($f['exec_q']) && empty($f['exec_activation']) && empty($f['exec_brief']))) {
                        continue;
                    }$item['project'] = $row['project'];
                    $item['gap'] = max(0, (int) ($item['required'] ?? 0) - (int) ($item['confirmed'] ?? 0));
                    $item['person'] = $names->get($item['staff_id'] ?? $item['owner_id'] ?? null);
                    $item['leave_clash'] = ! empty($item['staff_id']) && ! empty($item['start_at']) && $availability->where('user_id', (int) $item['staff_id'])->contains(fn ($w) => $w['start']->lte(Carbon::parse($item['end_at'] ?? $item['start_at'])) && $w['end']->gte(Carbon::parse($item['start_at'])));
                    if ($type === 'resources') {
                        $resourceRows->push($item);
                    } else {
                        $assetRows->push($item);
                    }
                }
            }$projectRows->put($id, $row);
        }
        foreach ($resourceRows as $i => $a) {
            foreach ($resourceRows as $j => $b) {
                if ($i < $j && ! empty($a['exclusive']) && ! empty($b['exclusive']) && in_array($a['status'] ?? '', ['confirmed', 'contracted']) && in_array($b['status'] ?? '', ['confirmed', 'contracted']) && ! empty($a['start_at']) && ! empty($b['start_at']) && (($a['staff_id'] ?? null) ? (int) $a['staff_id'] === (int) ($b['staff_id'] ?? 0) : ClientRelationsCentre::key($a['party'] ?? '') !== '' && ClientRelationsCentre::key($a['party']) === ClientRelationsCentre::key($b['party'] ?? '')) && strtotime($a['start_at']) <= strtotime($b['end_at'] ?? $b['start_at']) && strtotime($b['start_at']) <= strtotime($a['end_at'] ?? $a['start_at'])) {
                    $clashes->push(['a' => $a, 'b' => $b]);
                }
            }
        }
        $activeProjects = $projectRows->filter(fn ($x) => $x['events']->isNotEmpty() || (! empty($f['exec_project']) && in_array((int) $f['exec_project'], $x['ids'])) || (! empty($f['exec_activation']) && ($x['source_kind'] ?? null) === 'activation' && $x['native_id'] == $f['exec_activation']) || (! empty($f['exec_brief']) && ($x['source_kind'] ?? null) === 'creative' && $x['native_id'] == $f['exec_brief']) || ($x['project']->week_start->betweenIncluded($from, $to) && empty($f['exec_person']) && empty($f['exec_department']) && empty($f['exec_q']) && empty($f['exec_activation']) && empty($f['exec_brief'])));
        foreach ($activeProjects as $id => $row) {
            $row['open_events'] = $row['events']->where('done', false)->reject(fn ($e) => in_array($e['kind'], ['activity', 'absence', 'attendance', 'meeting']));
            $row['next'] = $row['open_events']->sortBy('end')->first();
            $row['days_to_start'] = ! empty($row['schedule']['starts_at']) ? (int) today()->diffInDays(Carbon::parse($row['schedule']['starts_at'])->startOfDay(), false) : null;
            $row['process_complete'] = collect(config('executive_tracker.process'))->keys()->filter(fn ($key) => ! empty($row['process'][$key]['completed_at']) && ! empty($row['process'][$key]['evidence']) && in_array($row['process'][$key]['status'] ?? '', ['submitted', 'presented', 'approved']))->count();
            $row['health'] = $row['closed'] ? ($row['open_events']->isNotEmpty() ? 'Closure / downstream work mismatch' : 'Closed') : ($row['events']->contains(fn ($e) => $e['late'] && ! $e['done'] && ! $e['leave_deadline']) || collect($row['process'])->contains(fn ($step) => in_array($step['status'] ?? '', ['rejected', 'incomplete'])) || $row['staff_gap'] > 0 || ($row['execution_required'] && ! $row['check']['ready']) ? 'Attention needed' : ($row['execution_required'] && empty($row['schedule']['starts_at']) ? 'Setup needed' : 'No recorded blocker'));
            $activeProjects->put($id, $row);
        }
        if (! empty($f['exec_attention'])) {
            $activeProjects = $activeProjects->filter(fn ($p) => $p['events']->contains('late', true) || $p['staff_gap'] > 0 || ! $p['check']['ready']);
        }
        $warehousing = AssetWarehouseRequest::with('asset:id,name', 'custodian:id,name', 'requester:id,name')->where(fn ($q) => $q->whereIn('crm_project_id', $activeProjects->flatMap(fn ($p) => $p['ids']))->orWhereIn('operations_activation_id', $activationMap->filter(fn ($projectId) => $activeProjects->has($projectId))->keys()))->latest()->get();
        $campaignIds = $nativeActivations->filter(fn ($a) => $activeProjects->has($activationMap->get($a->id)))->pluck('data.campaign_id')->filter()->unique();
        $movementLogs = \App\Models\AssetLog::with('asset:id,name', 'user:id,name')->whereIn('campaign_id', $campaignIds)->whereBetween('created_at', [$from, $to])->latest()->limit(100)->get();
        $posmMovements = \App\Models\PosmLedger::with('creator:id,name', 'asset:id,name')->whereIn('campaign_id', $campaignIds)->where(fn ($q) => $q->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->orWhere(fn ($d) => $d->whereNull('occurred_on')->whereBetween('created_at', [$from, $to])))->latest()->limit(100)->get();
        $linkedFinance = app(LinkedProjectDependencies::class)->finance($activeProjects, $briefs, $handovers, $idMap, $r->user());
        $controlIds = $activeProjects->pluck('control.id')->filter();
        $projectIds = $activeProjects->flatMap(fn ($p) => $p['ids']);
        $briefIds = $briefs->filter(fn ($b) => $projectIds->contains($b->crm_project_id))->flatMap(fn ($b) => collect([$b->id])->merge($b->parts->pluck('id')));
        $handoverIds = $handovers->whereIn('project_id', $projectIds)->pluck('id');
        $history = PerformanceEvent::with('actor:id,name')->whereBetween('created_at', [$from, $to])->where(fn ($q) => $q->where(fn ($x) => $x->where('entity_type', 'project_control')->whereIn('entity_id', $controlIds))->orWhere(fn ($x) => $x->where('entity_type', 'crm_project')->whereIn('entity_id', $projectIds))->orWhere(fn ($x) => $x->where('entity_type', 'creative_request')->whereIn('entity_id', $briefIds))->orWhere(fn ($x) => $x->where('entity_type', 'operations_handover')->whereIn('entity_id', $handoverIds)))->latest('id')->paginate(20, ['*'], 'exec_history_page')->withQueryString();
        $holidays = \App\Models\HrRegisterRecord::where('kind', 'holiday')->get()->filter(fn ($h) => ! empty($h->data['date']) && $h->data['date'] >= $from->toDateString() && $h->data['date'] <= $to->toDateString())->groupBy(fn ($h) => $h->data['date']);
        $days = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }
        $summary = ['projects' => $activeProjects->count(), 'due' => $events->filter(fn ($e) => ! $e['done'] && ! in_array($e['kind'], ['absence', 'meeting', 'fleet', 'attendance', 'activity']))->count(), 'overdue' => $events->where('late', true)->where('done', false)->where('leave_deadline', false)->reject(fn ($e) => in_array($e['kind'], ['absence', 'meeting', 'fleet', 'attendance']))->count(), 'unassigned' => $events->where('owner', 0)->count(), 'staff_gaps' => $activeProjects->sum('staff_gap'), 'resource_gaps' => $resourceRows->where('gap', '>', 0)->count(), 'clashes' => $clashes->count(), 'leave_clashes' => $resourceRows->where('leave_clash', true)->count(), 'compliance_pending' => $activeProjects->filter(fn ($p) => $p['execution_required'] && ! $p['closed'] && ! $p['check']['ready'])->count(), 'live' => $events->where('kind', 'activation')->filter(fn ($e) => $e['start']->lte(now()) && $e['end']->gte(now()) && ! in_array($e['state'], ['completed', 'cancelled']))->count()];
        $summary['leave_review'] = $events->filter(fn ($e) => ! $e['done'] || $e['kind'] === 'resources')->where('leave_overlap', true)->reject(fn ($e) => in_array($e['kind'], ['activity', 'absence', 'attendance']))->count();
        $summary['staff_setup'] = $activeProjects->filter(fn ($p) => $p['execution_required'] && ! $p['closed'] && (! isset($p['schedule']['staff_required']) || ! isset($p['schedule']['staff_confirmed'])))->count();
        $summary['late_completed'] = $events->where('late', true)->where('done', true)->count();
        foreach ([5, 10, 15] as $horizon) {
            $summary['starting_'.$horizon] = $events->where('kind', 'activation')->where('done', false)->filter(fn ($e) => $e['start']->gte(today()) && $e['start']->lte(today()->addDays($horizon)->endOfDay()))->count();
        }$summary['reported_closed'] = $activeProjects->where('closed', true)->count();
        $summary['red_health'] = $activeProjects->where('health', 'Attention needed')->count();
        $charts = ['stages' => $activeProjects->countBy(fn ($p) => $p['closed'] ? 'Reported closed' : ($p['schedule']['state'] ?? 'Planning / source workflow')), 'clients' => $activeProjects->countBy(fn ($p) => $p['project']->client_name), 'departments' => $events->countBy(fn ($e) => User::departmentLabel($e['person']?->department ?? 'Unassigned')), 'budget' => $activeProjects->countBy(fn ($p) => ucwords(str_replace('_', ' ', $p['process']['budget']['status'] ?? 'Not assessed'))), 'workload' => $events->where('done', false)->reject(fn ($e) => in_array($e['kind'], ['absence', 'activity', 'attendance']))->countBy(fn ($e) => $e['person']?->name ?? 'Assignment needed'), 'due' => $events->where('done', false)->reject(fn ($e) => in_array($e['kind'], ['activity', 'absence', 'meeting', 'attendance']))->countBy(fn ($e) => $e['end']->format('d M')), 'process' => collect($activeProjects->flatMap(fn ($p) => collect($p['process'])->values())->all())->countBy(fn ($p) => ucwords(str_replace('_', ' ', $p['status'] ?? 'not_started'))), 'compliance' => $activeProjects->where('execution_required', true)->mapWithKeys(fn ($p) => [$p['chart_key'] => $p['check']['score']]), 'resource_gaps' => $resourceRows->where('gap', '>', 0)->mapWithKeys(fn ($p) => [$p['description'].' / '.$p['project']->project_name => $p['gap']]), 'asset_ready' => collect()];
        foreach ($activeProjects as $p) {
            $external = $assetRows->filter(fn ($a) => $a['project']->id === $p['project']->id);
            $warehouse = $warehousing->filter(fn ($w) => in_array($w->crm_project_id, $p['ids']) || $activationMap->get((int) $w->operations_activation_id) === $p['project']->id)->whereNotIn('status', [AssetWarehouseRequest::STATUS_REJECTED]);
            $required = $external->sum('required') + $warehouse->sum('requested_quantity');
            $ready = $external->filter(fn ($a) => in_array($a['status'] ?? '', ['ready', 'dispatched', 'returned']) && ! empty($a['condition']))->sum('confirmed') + $warehouse->filter(fn ($a) => in_array($a->status, [AssetWarehouseRequest::STATUS_ISSUED, AssetWarehouseRequest::STATUS_CLOSED]) || ($a->status === AssetWarehouseRequest::STATUS_APPROVED_FOR_USE && ! $stockConflicts->contains(fn ($conflict) => $conflict['asset']->id === $a->asset_id)))->sum('requested_quantity');
            if ($required) {
                $charts['asset_ready']->put($p['chart_key'], round(min(100, 100 * $ready / $required), 1));
            }
        }
        $activationEvents = $events->where('kind', 'activation');
        $charts['countdown'] = $activationEvents->countBy(fn ($e) => $e['done'] ? 'Reported completed' : ($e['start']->lte(now()) && $e['end']->gte(now()) ? 'Live window' : ($e['start']->lt(today()) ? 'Past start / outcome needed' : ((int) today()->diffInDays($e['start']->copy()->startOfDay(), false) <= 5 ? 'Starts within 5 days' : ((int) today()->diffInDays($e['start']->copy()->startOfDay(), false) <= 10 ? 'Starts in 6–10 days' : ((int) today()->diffInDays($e['start']->copy()->startOfDay(), false) <= 15 ? 'Starts in 11–15 days' : 'Starts after 15 days'))))));
        $charts['starts_week'] = $activationEvents->countBy(fn ($e) => $e['start']->copy()->startOfWeek()->format('d M Y'));
        $charts['task_status'] = $events->where('kind', 'task')->countBy(fn ($e) => ucwords(str_replace('_', ' ', $e['state'])));
        $charts['asset_status'] = $assetRows->countBy(fn ($a) => ucwords(str_replace('_', ' ', $a['status'] ?? 'Assessment needed')));
        foreach ($warehousing->countBy('status') as $state => $count) {
            $charts['asset_status']->put('Warehouse · '.ucwords(str_replace('_', ' ', $state)), $count);
        }
        $charts['process'] = $activeProjects->countBy(function ($p) {
            foreach (config('executive_tracker.process') as $key => $label) {
                if (empty($p['process'][$key]['completed_at']) || empty($p['process'][$key]['evidence']) || ! in_array($p['process'][$key]['status'] ?? '', ['submitted', 'presented', 'approved'])) {
                    return $label;
                }
            }

            return 'Process complete';
        });
        $charts['approved_budget_client'] = collect();
        foreach ($activeProjects->filter(fn ($p) => $p['budget'] && $p['budget_visible'])->groupBy(fn ($p) => $p['budget']->id) as $linked) {
            $p = $linked->first();
            if ($p['budget']->status !== 'CVO Approved' || ! in_array($p['budget']->currency, ['GHS', 'GH₵', 'GH¢'])) {
                continue;
            }$clients = $linked->pluck('project.client_name')->unique();
            $client = $clients->count() === 1 ? $clients->first() : 'Shared across clients';
            $charts['approved_budget_client']->put($client, ($charts['approved_budget_client']->get($client, 0) + (float) $p['budget']->total_amount));
        }
        $summary['proposals_due_15'] = $events->filter(fn ($e) => $e['kind'] === 'process' && str_ends_with($e['key'], ':proposal') && ! $e['done'] && $e['end']->gte(today()) && $e['end']->lte(today()->addDays(15)->endOfDay()))->count();
        $summary['process_overdue'] = $events->where('kind', 'process')->where('late', true)->where('done', false)->count();
        $summary['tasks_overdue'] = $events->where('kind', 'task')->where('late', true)->where('done', false)->count();
        $summary['compliance_critical'] = $events->where('kind', 'compliance')->where('done', false)->filter(fn ($e) => $e['end']->lte(today()->addDays(5)->endOfDay()))->count();

        return ['command' => compact('view', 'period', 'anchor', 'from', 'to', 'staff', 'names', 'f', 'events', 'activeProjects', 'projectRows', 'resourceRows', 'assetRows', 'clashes', 'warehousing', 'history', 'days', 'summary', 'charts', 'holidays', 'stock', 'stockConflicts', 'movementLogs', 'posmMovements', 'dependencies', 'linkedFinance')];
    }

    public function compliance(array $data): array
    {
        $yes = 0;
        $excluded = 0;
        $total = count(config('executive_tracker.checks'));
        foreach (config('executive_tracker.checks') as $key => $label) {
            $c = $data['checks'][$key] ?? [];
            if (($c['response'] ?? '') === 'not_required' && ! empty($c['reason'])) {
                $excluded++;
            } elseif (($c['response'] ?? '') === 'yes' && ! empty($c['evidence'])) {
                $yes++;
            }
        }$applicable = $total - $excluded;

        return ['score' => $applicable ? round(100 * $yes / $applicable, 1) : 0, 'yes' => $yes, 'applicable' => $applicable, 'excluded' => $excluded, 'outstanding' => $applicable - $yes, 'ready' => $applicable > 0 && $yes === $applicable];
    }
}
