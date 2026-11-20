<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecutiveExport
{
    public const SECTIONS = [
        'overview' => 'Overview statistics', 'projects' => 'Projects & workflow stages',
        'calendar' => 'Calendar & commitments', 'resources' => 'Resources, assets & custody',
        'compliance' => 'Compliance checklists', 'accountability' => 'Accountability & dependencies',
        'reports' => 'Department source reports', 'charts' => 'Charts & chart data',
        'team' => 'Team directory & availability', 'history' => 'Recorded change history',
        'reminders' => 'Reminder settings & delivery trail', 'attendance' => 'Recorded attendance',
    ];

    public function validate(Request $request): array
    {
        abort_unless($request->user()?->canViewExecutiveActivity(), 403);

        if ($request->filled('selected_records')) {
            try {
                $selected = json_decode($request->input('selected_records'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw \Illuminate\Validation\ValidationException::withMessages(['records' => 'The selection could not be read. Refresh the preview.']);
            }
            if (! is_array($selected) || ! array_is_list($selected)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['records' => 'Choose a valid record selection.']);
            }
            $request->merge(['records' => $selected]);
        }

        return $request->validate([
            'format' => 'required|in:xlsx,pdf', 'scope' => 'required|in:current,custom',
            'dates' => 'required|in:current,range,all', 'date_from' => 'required_if:dates,range|nullable|date_format:Y-m-d',
            'date_to' => 'required_if:dates,range|nullable|date_format:Y-m-d|after_or_equal:date_from',
            'sections' => 'required|array|min:1', 'sections.*' => 'required|distinct|in:'.implode(',', array_keys(self::SECTIONS)),
            'people_scope' => 'required|in:all,selected', 'people' => 'required_if:people_scope,selected|array',
            'people.*' => 'integer|distinct|exists:users,id',
            'departments_scope' => 'required|in:all,selected', 'departments' => 'required_if:departments_scope,selected|array',
            'departments.*' => 'string|distinct|in:'.implode(',', array_keys(config('performance.departments'))),
            'projects_scope' => 'required|in:all,selected', 'projects' => 'required_if:projects_scope,selected|array',
            'projects.*' => ['string', 'distinct', 'regex:/^(crm|activation|creative):[1-9][0-9]*$/'],
            'context' => 'nullable|array', 'context.exec_view' => 'nullable|in:'.implode(',', array_keys(config('executive_tracker.tabs'))),
            'context.exec_period' => 'nullable|in:day,week,month,year', 'context.exec_date' => 'nullable|date_format:Y-m-d',
            'context.exec_department' => 'nullable|string|max:60', 'context.exec_person' => 'nullable|integer|exists:users,id',
            'context.exec_project' => 'nullable|integer|exists:crm_pipeline_projects,id',
            'context.exec_activation' => 'nullable|integer|exists:performance_registers,id',
            'context.exec_brief' => 'nullable|integer|exists:creative_requests,id',
            'context.exec_q' => 'nullable|string|max:150', 'context.exec_scope' => 'nullable|in:all,work,activity,meetings,availability,attendance',
            'context.exec_attention' => 'nullable|boolean', 'context.activity_section' => 'nullable|string|max:80',
            'selection_mode' => 'nullable|in:all,selected', 'records' => 'required_if:selection_mode,selected|array',
            'records.*' => 'string|distinct|max:240',
        ]);
    }

    public function build(Request $request, array $input): array
    {
        $context = array_filter($input['context'] ?? [], fn ($value) => $value !== null && $value !== '');
        $context['exec_view'] = 'projects'; // Populate the same original, role-controlled department journeys.
        if ($input['scope'] === 'custom') {
            $context = Arr::only($context, ['exec_view', 'exec_period', 'exec_date']);
        }
        $source = Request::create('/dashboard', 'GET', $context);
        $source->setUserResolver(fn () => $request->user());
        $source->setLaravelSession($request->session());
        $options = ['attendance' => in_array('attendance', $input['sections'], true)];
        foreach (['people', 'departments', 'projects'] as $key) {
            $options[$key] = ($input[$key.'_scope'] ?? 'all') === 'selected' ? ($input[$key] ?? []) : [];
            if (($input[$key.'_scope'] ?? 'all') === 'selected' && ! $options[$key]) {
                throw \Illuminate\Validation\ValidationException::withMessages([$key => 'Choose at least one item, or select all.']);
            }
        }
        $options['people'] = array_map('intval', $options['people']);
        $projectTokens = $options['projects'];
        $options['projects'] = array_map(fn ($token) => $this->projectId($token), $projectTokens);
        if ($input['dates'] === 'range') {
            $options['window'] = [Carbon::parse($input['date_from'])->startOfDay(), Carbon::parse($input['date_to'])->endOfDay()];
        } elseif ($input['dates'] === 'all') {
            // Export-only bounds: do not construct the dashboard's daily calendar cells for this range.
            $options['window'] = [Carbon::parse('0001-01-01')->startOfDay(), Carbon::parse('9999-12-31')->endOfDay()];
        }
        $command = app(ExecutiveCommandCentre::class)->build($source, $options)['command'];
        $effectivePeople = $options['people'] ?: (! empty($command['f']['exec_person']) ? [(int) $command['f']['exec_person']] : []);
        $effectiveProjects = $options['projects'] ?: (! empty($command['f']['exec_project']) ? [(int) $command['f']['exec_project']] : (! empty($command['f']['exec_activation']) ? [-(int) $command['f']['exec_activation']] : (! empty($command['f']['exec_brief']) ? [-1000000000 - (int) $command['f']['exec_brief']] : [])));
        foreach ($options['projects'] as $id) {
            if (! $command['projectRows']->has($id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['projects' => 'A selected project is unavailable. Refresh the export selection.']);
            }
        }
        $records = collect();
        $add = function (string $section, string $key, string $title, array $fields, ?string $owner = null, ?string $url = null, array $extra = []) use ($records, $input, $request, $command) {
            if (! in_array($section, $input['sections'], true)) {
                return;
            }
            $records->put($section.':'.$key, ['key' => $section.':'.$key, 'section' => $section, 'title' => $title, 'owner' => $owner, 'fields' => $this->fields($fields, $request->user(), $command['names']), 'url' => $url] + $extra);
        };
        foreach ($command['summary'] as $key => $value) {
            $add('overview', $key, Str::headline($key), ['Value' => $value, 'Basis' => 'Same filtered executive dashboard calculation']);
        }
        foreach ($command['activeProjects'] as $id => $row) {
            $p = $row['project'];
            $url = $row['source_url'] ?? route('portal.project-journey', ['project' => $p->id]);
            $add('projects', (string) $id, $p->project_name, ['Source' => $row['source_kind'] ?? 'Client Relations', 'Client' => $p->client_name, 'Original project ID' => $id, 'Recorded stage' => $p->stage, 'Health' => $row['health'], 'Created in portal' => $p->created_at, 'Schedule' => $row['schedule'], 'Process commitments' => $row['process'], 'Execution required' => $row['execution_required'], 'Staff gap' => $row['staff_gap']], $p->owner?->name, $url);
            foreach ($row['flow']['sections'] ?? [] as $stage => $flow) {
                $add('projects', $id.':flow:'.$stage, $p->project_name.' · '.$flow['label'], ['Team' => $flow['team'], 'State' => $flow['state'], 'Detail' => $flow['detail'], 'Department workflow' => $flow['records']], $p->owner?->name, $url);
            }
            $add('compliance', (string) $id, $p->project_name, ['Client' => $p->client_name, 'Execution required' => $row['execution_required'], 'Credit (%)' => $row['check']['score'], 'Confirmed checks' => $row['check']['yes'], 'Applicable checks' => $row['check']['applicable'], 'Justified exclusions' => $row['check']['excluded'], 'Outstanding' => $row['check']['outstanding'], 'Checklist' => $row['compliance']], $command['names']->get((int) ($row['compliance']['owner_id'] ?? 0))?->name, $url);
        }
        foreach ($command['events'] as $event) {
            $fields = ['Source reference' => $event['key'], 'Kind' => $event['kind'], 'Project' => $event['project']?->project_name, 'Starts' => $event['start'], 'Ends / deadline' => $event['end'], 'State' => $event['state'], 'Recorded outcome' => $event['done'], 'Past recorded deadline' => $event['late'], 'Approved absence overlap' => $event['leave_overlap'], 'Deadline during approved absence' => $event['leave_deadline'], 'Collaborators' => collect($event['support'])->map(fn ($id) => $command['names']->get($id)?->name ?? 'Retained staff #'.$id)->implode(', '), 'Source details' => collect($event['meta'])->reject(fn ($v, $key) => str_starts_with($key, '_'))->all()];
            $add($event['kind'] === 'attendance' ? 'attendance' : 'calendar', $event['key'], $event['title'], $fields, $event['person']?->name, $event['url']);
            if ($event['late'] || $event['leave_overlap'] || ! $event['owner']) {
                $add('accountability', $event['key'], $event['title'], $fields, $event['person']?->name, $event['url']);
            }
        }
        foreach (['resourceRows' => 'resource', 'assetRows' => 'external'] as $key => $kind) {
            foreach ($command[$key] as $index => $row) {
                $add('resources', $kind.':'.$row['project']->id.':'.($row['id'] ?? $index), $row['description'], Arr::except($row, ['project', 'person']) + ['Project' => $row['project']->project_name], $row['person']?->name);
            }
        }
        $stockIds = $command['warehousing']->pluck('asset_id')->unique();
        foreach ($command['stock'] as $asset) {
            if ($effectiveProjects && ! $stockIds->contains($asset->id)) {
                continue;
            }
            if ($effectivePeople && ! in_array($asset->assigned_to, $effectivePeople, true) && ! in_array($asset->last_handled_by, $effectivePeople, true) && ! $command['warehousing']->contains(fn ($w) => (int) $w->asset_id === $asset->id && in_array((int) $w->custodian_id, $effectivePeople, true))) {
                continue;
            }
            $add('resources', 'stock:'.$asset->id, $asset->name, ['Snapshot' => 'Current master inventory · not reconstructed historical stock', 'Asset tag' => $asset->asset_tag, 'Available units' => $asset->warehouse_quantity, 'Location' => $asset->warehouse_location, 'Condition' => $asset->condition, 'Status' => $asset->status, 'Last handler' => $asset->lastHandler?->name, 'Last handled' => $asset->last_handled_at], $asset->assignee?->name);
        }
        foreach ($command['warehousing'] as $row) {
            $add('resources', 'warehouse:'.$row->id, $row->asset?->name ?? 'Warehouse request', ['Request' => $row->request_code, 'Quantity' => $row->requested_quantity, 'Required date' => $row->requested_for, 'Status' => $row->status, 'Issued' => $row->issued_at, 'Returned' => $row->returned_at, 'Requested by' => $row->requester?->name], $row->custodian?->name);
        }
        foreach ($command['movementLogs'] as $row) {
            $add('resources', 'movement:'.$row->id, $row->asset?->name ?? 'Asset movement', ['Action' => $row->action, 'Recorded' => $row->created_at], $row->user?->name);
        }
        foreach ($command['posmMovements'] as $row) {
            $add('resources', 'posm:'.$row->id, $row->asset?->name ?? $row->item_name, ['Quantity in' => $row->quantity_in, 'Quantity out' => $row->quantity_out, 'Date' => $row->occurred_on], $row->creator?->name);
        }
        foreach ($command['dependencies'] as $index => $row) {
            $add('accountability', 'dependency:'.$row['id'], 'Linked dependency', $row);
        }
        foreach ($command['linkedFinance'] as $index => $row) {
            $add('projects', 'finance:'.$row['kind'].':'.$row['id'], $row['title'], $row);
        }
        $involved = $command['events']->flatMap(fn ($e) => array_merge([$e['owner']], $e['support']))->merge($command['activeProjects']->pluck('project.owner_id'))->unique();
        foreach ($command['staff'] as $person) {
            if ($options['people'] && ! in_array($person->id, $options['people'], true)) {
                continue;
            }
            if ($options['departments'] && ! in_array(User::normalizeDepartmentKey($person->department), $options['departments'], true)) {
                continue;
            }
            if ($effectiveProjects && ! $involved->contains($person->id)) {
                continue;
            }
            if (! empty($command['f']['exec_person']) && $person->id != (int) $command['f']['exec_person']) {
                continue;
            }
            if (! empty($command['f']['exec_department']) && User::normalizeDepartmentKey($person->department) !== $command['f']['exec_department']) {
                continue;
            }
            $add('team', (string) $person->id, $person->name, ['Department' => User::departmentLabel($person->department), 'Role' => $person->job_title, 'Status' => $person->status, 'Manager' => $command['names']->get($person->line_manager_id)?->name, 'Work email' => $person->work_email ?: $person->email, 'Contact' => $person->phone, 'Source entries' => $command['events']->where('owner', $person->id)->count(), 'Supporting entries' => $command['events']->filter(fn ($e) => in_array($person->id, $e['support'], true))->count()]);
        }
        foreach ($command['history'] as $event) {
            if ($options['people'] && ! in_array((int) $event->actor_id, $options['people'], true)) {
                continue;
            }
            $add('history', (string) $event->id, Str::headline($event->action), ['Recorded' => $event->created_at, 'Entity' => $event->entity_type, 'Source ID' => $event->entity_id, 'Reason' => $event->reason] + ($event->entity_type === 'project_control' ? ['Before' => $this->historyData($event->before['data'] ?? []), 'After' => $this->historyData($event->after['data'] ?? [])] : []), $event->actor?->name);
        }
        $reportGroups = [];
        if (in_array('reports', $input['sections'], true) || in_array('charts', $input['sections'], true)) {
            $departments = $options['departments'] ?: (! empty($command['f']['exec_department']) ? [$command['f']['exec_department']] : array_keys(config('performance.departments')));
            $people = $options['people'] ?: (! empty($command['f']['exec_person']) ? [(int) $command['f']['exec_person']] : [null]);
            $projectIds = collect($effectiveProjects)->flatMap(fn ($id) => array_merge([$id], $command['projectRows'][$id]['ids'] ?? []))->all();
            if (! empty($command['f']['exec_project'])) {
                $projectIds = array_merge([(int) $command['f']['exec_project']], $command['projectRows'][(int) $command['f']['exec_project']]['ids'] ?? []);
            }
            foreach ($departments as $department) {
                foreach (array_keys(ExecutiveDepartmentRecords::SECTIONS[$department] ?? []) as $category) {
                    if ($input['scope'] === 'current' && ! empty($input['context']['activity_section']) && $category !== $input['context']['activity_section']) {
                        continue;
                    }
                    $groupRecords = collect();
                    foreach ($people as $person) {
                        $reportRequest = $source->duplicate();
                        $reportRequest->query->set('weekly_from', $command['from']->toDateString());
                        $reportRequest->query->set('weekly_to', $command['to']->toDateString());
                        $built = app(ExecutiveDepartmentRecords::class)->build($reportRequest, $department, $person, $category, true);
                        if (! $built['sections'] || $built['section'] !== $category) {
                            continue;
                        }
                        foreach ($built['groups'] as $group) {
                            foreach ($group['rows'] as $index => $row) {
                                $meta = $row['_source'] ?? [];
                                if ($projectIds && ! array_intersect($projectIds, $meta['projects'] ?? [])) {
                                    continue;
                                }
                                if (! empty($command['f']['exec_q']) && ! str_contains(mb_strtolower($row['title'].' '.$row['owner'].' '.json_encode($row['fields'])), mb_strtolower($command['f']['exec_q']))) {
                                    continue;
                                }
                                $key = $department.':'.$category.':'.$group['label'].':'.($meta['key'] ?? $index);
                                $groupRecords->put($key, $row);
                                $add('reports', $key, User::departmentLabel($department).' · '.$group['label'].' · '.$row['title'], ['Status' => $row['status'], 'Recorded fields' => $row['fields'], 'Linked source records' => $row['linked']], $row['owner'], $row['url']);
                            }
                        }
                    }
                    $reportGroups[] = ['label' => User::departmentLabel($department).' · '.(ExecutiveDepartmentRecords::SECTIONS[$department][$category] ?? $category), 'rows' => new \Illuminate\Pagination\LengthAwarePaginator([], $groupRecords->count(), 1)];
                }
            }
        }
        if (in_array('charts', $input['sections'], true)) {
            foreach (array_keys(config('executive_tracker.tabs')) as $tab) {
                $chartCommand = $command;
                $chartCommand['view'] = $tab;
                if ($input['dates'] === 'all') {
                    $chartCommand['period'] = 'year';
                    $chartCommand['from'] = $command['events']->min('start')?->copy()->startOfMonth() ?? today()->startOfMonth();
                    $chartCommand['to'] = $command['events']->max('end')?->copy()->endOfMonth() ?? today()->endOfMonth();
                }
                $chart = app(ExecutiveTabCharts::class)->forTab($chartCommand, ['groups' => $reportGroups]);
                foreach ($chart['rows'] as $index => $row) {
                    $add('charts', $tab.':'.sha1($row['label']), config('executive_tracker.tabs.'.$tab).' · '.$row['label'], array_combine($chart['series'], $row['values']) + ['Unit' => $chart['unit'], 'Basis' => $chart['note']], extra: ['chart' => ['tab' => $tab, 'title' => $chart['subtitle'], 'label' => $row['label'], 'series' => $chart['series'], 'values' => $row['values'], 'unit' => $chart['unit']]]);
                }
            }
        }
        if (in_array('reminders', $input['sections'], true)) {
            $add('reminders', 'settings', 'Reminder configuration', app(ExecutiveReminders::class)->settings());
            $logs = DB::table('executive_reminder_logs')->leftJoin('performance_outbox', 'performance_outbox.id', '=', 'executive_reminder_logs.outbox_id')->whereBetween('executive_reminder_logs.created_at', [$command['from'], $command['to']])->select('executive_reminder_logs.*', 'performance_outbox.dashboard_at', 'performance_outbox.emailed_at', 'performance_outbox.last_error')->orderBy('executive_reminder_logs.id')->get();
            foreach ($logs as $log) {
                if ($options['people'] && ! in_array((int) $log->user_id, $options['people'], true)) {
                    continue;
                }
                if (($options['projects'] || $options['departments'] || ! empty($context['exec_person']) || ! empty($context['exec_department']) || ! empty($context['exec_project'])) && ! $command['events']->contains('key', $log->source_key)) {
                    continue;
                }
                $add('reminders', 'log:'.$log->id, $log->source_key, ['Recorded' => $log->created_at, 'Due' => $log->due_at, 'Rule' => $log->window, 'Delivery' => $log->last_error ? 'Delivery needs attention' : ($log->emailed_at ? 'Email delivered' : ($log->dashboard_at ? 'Portal delivered' : 'Queued'))], $command['names']->get($log->user_id)?->name);
            }
        }

        return ['records' => $records, 'input' => $input, 'command' => $command, 'period' => $input['dates'] === 'all' ? 'All recorded dates' : $command['from']->format('d M Y').' – '.$command['to']->format('d M Y'), 'generated' => now()->format('d M Y, H:i').' Africa/Accra', 'requestedBy' => $request->user()->name];
    }

    private function historyData(mixed $data): array
    {
        return is_array($data) ? $data : (is_string($data) ? (json_decode($data, true) ?: []) : []);
    }

    private function projectId(string $token): int
    {
        [$kind,$id] = explode(':', $token);

        return match ($kind) {
            'activation' => -(int) $id,'creative' => -1000000000 - (int) $id,default => (int) $id
        };
    }

    private function fields(array $fields, User $viewer, Collection $names): array
    {
        $result = [];
        foreach (Arr::dot($fields) as $key => $value) {
            if (preg_match('/password|token|secret|bank|salary/i', $key)) {
                continue;
            }
            if (str_contains($key, 'unit_rate') && ! FinanceDashboard::canRead($viewer) && ! OperationsReporting::manager($viewer)) {
                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('d M Y, H:i');
            } elseif (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_object($value)) {
                continue;
            }
            if (preg_match('/(?:owner_id|staff_id|user_id|assigned_to)$/', $key) && is_numeric($value)) {
                $value = ($names->get((int) $value)?->name ?? 'Retained staff identity').' (#'.$value.')';
            }
            $result[Str::headline(str_replace('.', ' / ', $key))] = $value ?? 'Setup needed';
        }

        return $result;
    }
}
