<?php

namespace App\Services;

use App\Models\{CreativeRequest, PerformanceRegister, Task, User, WeeklyConsolidatedItem};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\{Carbon, Collection, Str};

/** Read-only projection of recorded work. Names alone never establish a handover. */
class ExecutiveProjectTracker
{
    public function build(Request $request): array
    {
        $viewer = $request->user();
        abort_unless($viewer->canViewExecutiveActivity(), 403);
        $filters = $request->validate([
            'report_period' => 'nullable|in:week,month,year',
            'report_date' => 'nullable|date_format:Y-m-d',
            'tracker_type' => 'nullable|in:all,projects,weekly,tasks',
            'tracker_status' => 'nullable|in:active,attention,complete,all',
            'tracker_q' => 'nullable|string|max:100',
            'tracker_page' => 'nullable|integer|min:1',
        ]);
        $period = in_array($request->query('report_period'), ['week', 'month', 'year'], true) ? $request->query('report_period') : 'week';
        $date = Carbon::parse($request->query('report_date') ?: today())->startOfDay();
        $from = match ($period) { 'month' => $date->copy()->startOfMonth(), 'year' => $date->copy()->startOfYear(), default => $date->copy()->startOfWeek() };
        $to = match ($period) { 'month' => $from->copy()->endOfMonth(), 'year' => $from->copy()->endOfYear(), default => $from->copy()->endOfWeek() };
        $journeyRequest = Request::create('/portal/project-journey', 'GET', ['period'=>$period, 'date'=>$date->toDateString()]);
        $journeyRequest->setUserResolver(fn () => $viewer);
        $journeys = app(ProjectJourney::class)->build($journeyRequest)['rows'];
        $rows = collect();
        $linkedBriefs = collect();
        $linkedRegisters = collect();
        $linkedTasks = collect();
        $activationIds = $journeys->flatMap(fn ($journey) => $journey['activations']->pluck('id'));
        $relatedRegisters = PerformanceRegister::whereNotNull('approved_at')->whereIn('data->activation_id', $activationIds)
            ->whereIn('department', User::departmentAliases('operations_projects'))->get()
            ->groupBy(fn ($record) => (int) ($record->data['activation_id'] ?? 0));
        foreach ($journeys as $journey) {
            $project = $journey['project'];
            $people = collect([$project->owner_id])->merge($project->supporting_staff_ids ?? []);
            $sources = collect([['label'=>'Client Relations project', 'title'=>$project->project_name, 'url'=>route('portal.project-journey', ['project'=>$project->id])]]);
            $teams = collect(['client_relations']);
            foreach ($journey['briefs'] as $brief) {
                $linkedBriefs->push($brief->id);
                $teams->push('creatives');
                foreach (collect([$brief])->merge($brief->parts) as $package) {
                    $people = $people->merge([$package->requester_id, $package->assignee_id, $package->reviewer_id])->merge($package->supportingStaffIds());
                    if ($package->task_id) $linkedTasks->push($package->task_id);
                }
                $sources->push(['label'=>'Creative brief', 'title'=>$brief->title, 'url'=>route('portal.creative', ['request'=>$brief->id])]);
            }
            foreach ($journey['handovers'] as $handover) {
                $teams->push('operations_projects');
                $people = $people->merge([$handover->lead_id, $handover->requester_id])->merge($handover->task?->supporting_staff_ids ?? []);
                if ($handover->task_id) $linkedTasks->push($handover->task_id);
                $sources->push(['label'=>'Handover · '.Str::headline($handover->stage), 'title'=>$handover->title, 'url'=>route('portal.project-journey', ['project'=>$project->id])]);
            }
            foreach ($journey['activations'] as $activation) {
                $linkedRegisters->push($activation->id);
                $teams->push('operations_projects');
                $people = $people->merge([$activation->owner_id])->merge($activation->data['collaborator_ids'] ?? []);
                $sources->push(['label'=>'Activation · '.Str::headline($activation->status), 'title'=>$activation->title, 'url'=>$this->sourceUrl('operations_projects', 'activations', $activation->period_start, $activation->id)]);
                foreach ($relatedRegisters->get($activation->id, collect()) as $related) {
                    $linkedRegisters->push($related->id);
                    $people = $people->merge([$related->owner_id])->merge($related->data['collaborator_ids'] ?? []);
                    $sources->push(['label'=>Str::headline($related->kind).' · '.Str::headline($related->status),
                        'title'=>$related->title, 'url'=>$this->sourceUrl('operations_projects','activations',$activation->period_start,$activation->id)]);
                }
            }
            $creativeOwner = $journey['briefs']->first()?->assignee_id;
            $operationsOwner = $journey['handovers']->first()?->lead_id ?: $journey['activations']->first()?->owner_id;
            $stageOwners = ['client'=>$project->owner_id, 'creative'=>$creativeOwner, 'approval'=>$project->owner_id,
                'handover'=>$operationsOwner, 'execution'=>$operationsOwner, 'closeout'=>$operationsOwner];
            $currentOwner = $stageOwners[$journey['stage']] ?? $project->owner_id;
            $packages = $journey['briefs']->flatMap(fn ($brief) => $brief->is_group && $brief->parts->isNotEmpty() ? $brief->parts : [$brief]);
            $stageDates = [
                'client'=>$project->week_start,
                'creative'=>$packages->pluck('submitted_at')->filter()->max(),
                'approval'=>$packages->pluck('completed_at')->filter()->max(),
                'handover'=>$journey['handovers']->flatMap(fn($h)=>[$h->cr_signoff_at,$h->ops_signoff_at])->filter()->max(),
                'execution'=>$journey['handovers']->pluck('submitted_at')->filter()->max(),
                'closeout'=>$journey['handovers']->pluck('verified_at')->filter()->max(),
            ];
            $steps = collect(ProjectJourney::STAGES)->map(function ($definition, $key) use ($journey, $stageOwners, $stageDates) {
                return ['label'=>$definition[0], 'team'=>$definition[1], 'state'=>$journey['done'][$key] ? 'done' : ($journey['stage']===$key ? 'current' : 'waiting'), 'detail'=>$journey['notes'][$key], 'owner_id'=>$stageOwners[$key], 'date'=>$journey['done'][$key] ? $stageDates[$key] : null];
            })->values()->all();
            $gaps = [];
            if ($journey['briefs']->isEmpty()) $gaps[] = 'No linked Creative brief. Creative work may be unnecessary or still needs linking.';
            if ($journey['handovers']->isEmpty()) $gaps[] = 'No recorded Operations handover.';
            $current = ProjectJourney::STAGES[$journey['stage']][0] ?? ($journey['stage']==='complete' ? 'Verified complete' : 'Closed / cancelled');
            $rows->push($this->row('crm-'.$project->id, 'projects', $project->project_name, $project->client_name,
                $teams->all(), $people->all(), $currentOwner, $current, $steps, $sources->all(),
                $journey['date'], $journey['needs_attention'], $journey['stage']==='complete', $journey['stage']==='cancelled',
                $project->updated_at, 'Connected project', $gaps));
        }

        // A brief can originate directly in Creative, without a Client Relations project.
        $briefs = CreativeRequest::visibleTo($viewer)->whereNull('parent_request_id')->whereNotIn('id', $linkedBriefs)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('received_at', [$from, $to])->orWhereBetween('requested_deadline', [$from, $to])
                    ->orWhereBetween('completed_at', [$from, $to])->orWhereBetween('created_at', [$from, $to])
                    ->orWhere(fn ($q) => $q->where('planned_start', '<=', $to)->where('internal_eta', '>=', $from));
            })->with(['task', 'parts.task'])->get();
        foreach ($briefs as $brief) {
            // Recurring system/developer assignments belong in Daily tasks, rather than project journeys.
            if ($brief->legacy && in_array($brief->task?->title, Task::LEGACY_AUTO_COMPLETED_TITLES, true)) continue;
            $packages = $brief->is_group && $brief->parts->isNotEmpty() ? $brief->parts : collect([$brief]);
            $complete = $packages->every(fn ($part) => in_array($part->stage, ['completed','archived'], true) && $part->completed_at);
            $reportedComplete = $packages->every(fn ($part) => in_array($part->stage, ['completed','archived'], true));
            $review = $packages->every(fn ($part) => in_array($part->stage, ['in_review','delivered','completed','archived'], true));
            $working = $packages->contains(fn ($part) => !in_array($part->stage, ['brief_received','backlog','needs_info'], true));
            $people = collect();
            foreach ($packages as $part) {
                $people = $people->merge([$part->requester_id,$part->assignee_id,$part->reviewer_id])->merge($part->supportingStaffIds());
                if ($part->task_id) $linkedTasks->push($part->task_id);
            }
            if ($brief->task_id) $linkedTasks->push($brief->task_id);
            $steps = [
                $this->step('Brief received', 'Brief originator', 'done', $brief->received_at?->format('d M Y H:i') ?? 'Recorded creative brief'),
                $this->step('Design & collaboration', 'Creatives + supporting staff', $review ? 'done' : 'current', $packages->count().' work packages · '.Str::headline($brief->stage)),
                $this->step('Review / client feedback', 'Creative lead / Client Relations', $reportedComplete ? 'done' : ($review ? 'current' : 'waiting'), 'Submission and review recorded in the creative brief'),
                $this->step('Creative completion', 'Creative lead', $complete ? 'done' : ($reportedComplete ? 'current' : 'waiting'), $complete ? 'Completion verified' : ($reportedComplete ? 'Completion reported; verification timestamp not recorded' : 'Awaiting verified completion')),
            ];
            $rows->push($this->row('creative-'.$brief->id, 'projects', $brief->title, $brief->client,
                ['creatives'], $people->all(), $review && !$complete ? ($brief->reviewer_id ?: $brief->requester_id) : ($brief->assignee_id ?: $brief->requester_id),
                $complete ? 'Creative complete' : ($reportedComplete ? 'Completion verification' : ($review ? 'Creative review / feedback' : ($working ? 'Design & collaboration' : 'Creative planning'))),
                $steps, [['label'=>'Creative brief', 'title'=>$brief->reference, 'url'=>route('portal.creative', ['request'=>$brief->id])]],
                $brief->internal_eta ?: $brief->requested_deadline, filled($brief->blocker) || ($reportedComplete && !$complete), $complete, false, $brief->updated_at,
                'Creative-origin / unlinked brief', ['No connected end-to-end project is recorded. Operations handover and execution are not inferred.'], $brief->blocker, null, !$reportedComplete));
        }

        $private = $viewer->isCvoOrSuperAdmin() || $viewer->canAuditHrAndFinance();
        $registers = PerformanceRegister::whereNotNull('approved_at')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
            ->whereNotIn('id', $linkedRegisters)->when(!$private, fn ($q) => $q->whereNotIn('department', array_merge(User::departmentAliases('hr_admin'),User::departmentAliases('finance'))))->get();
        foreach ($registers as $record) {
            $data = $record->data ?? [];
            $department = User::normalizeDepartmentKey($record->department);
            $status = strtolower($record->status ?? '');
            $complete = in_array($status, ['completed','done','closed','verified','paid','resolved'], true);
            $active = in_array($status, ['in_progress','in progress','submitted','approved'], true);
            $cancelled = in_array($status, ['cancelled','lost'], true);
            $activation = $department==='operations_projects' && $record->kind==='activation';
            if ($activation) $complete = OperationsReporting::activationExecuted($record);
            $progress = $data['progress_percent'] ?? null;
            $steps = [
                $this->step('Plan published', User::departmentLabel($department), 'done', 'Approved weekly report · '.$record->period_start?->format('d M Y')),
                $this->step($activation ? 'Activation execution' : 'Work & collaboration', User::departmentLabel($department), $complete ? 'done' : ($active ? 'current' : 'waiting'), Str::headline($record->status ?: 'Planned')),
                $this->step($activation ? 'Execution reported' : 'Completion reported', 'Reporting owner', $complete ? 'done' : 'waiting', $complete ? 'Completed in the approved department report' : 'Awaiting a completion report'),
            ];
            $gaps = $activation ? ['No connected project journey is recorded. Client brief, Creative sign-off and handover remain unconfirmed.'] : [];
            $rows->push($this->row('register-'.$record->id, $activation ? 'projects' : 'weekly', $record->title, $record->client,
                [$department], array_merge([$record->owner_id], $data['collaborator_ids'] ?? [], $data['collaborators'] ?? []),
                empty($data['import_no_owner']) ? $record->owner_id : null, $complete ? 'Completion reported' : ($active ? ($activation ? 'Execution' : 'In progress') : Str::headline($record->status ?: 'Planned')),
                $steps, [['label'=>Str::headline($record->kind), 'title'=>'Open department report', 'url'=>$this->sourceUrl($department, $activation ? 'activations' : 'registers', $record->period_start, $activation ? $record->id : null)]],
                $data['ends_on'] ?? $data['due_on'] ?? $record->period_end, in_array($status, ['blocked','on_hold','on hold'], true), $complete, $cancelled,
                $record->updated_at, $activation ? 'Operations activation' : 'Published weekly record', $gaps,
                $data['challenges'] ?? $data['key_issue'] ?? null, $progress));
        }

        $weekly = WeeklyConsolidatedItem::whereIn('workflow_state',['legacy','approved','verified','closed'])
            ->where(fn ($q) => $q->whereNull('report_status')->orWhereNotIn('report_status',['draft','submitted','returned']))
            ->whereBetween('week_start',[$from->toDateString(),$to->toDateString()])
            ->when(!$private, fn ($q) => $q->whereNotIn('department',array_merge(User::departmentAliases('hr_admin'),User::departmentAliases('finance'))))->get();
        foreach ($weekly as $record) {
            if ($record->source_task_id) $linkedTasks->push($record->source_task_id);
            $verified = in_array($record->workflow_state, ['verified','closed'],true);
            $reportedComplete = $record->status==='Done' || $verified;
            $department = User::normalizeDepartmentKey($record->department);
            $steps = [
                $this->step('Weekly plan published', User::departmentLabel($department),'done',$record->week_start?->format('d M Y')),
                $this->step('Team delivery', 'Owner + supporting staff',$reportedComplete ? 'done' : 'current',$record->status ?: 'In progress'),
                $this->step('Completion reported', 'Reporting owner',$reportedComplete ? 'done' : 'waiting',$reportedComplete ? 'Completion reported' : 'Awaiting completion'),
                $this->step('Verified / closed','Line manager',$verified ? 'done' : 'waiting',$verified ? 'Verified in the workflow' : 'Verification not recorded'),
            ];
            $rows->push($this->row('weekly-'.$record->id,'weekly',$record->campaign_name ?: $record->objective ?: 'Weekly workstream', $record->client_name,
                [$department], array_merge([$record->lead_staff_id],$record->supporting_staff_ids ?? []),$record->lead_staff_id,
                $verified ? 'Verified complete' : ($reportedComplete ? 'Completion reported' : 'Team delivery'),$steps,
                [['label'=>'Weekly workstream', 'title'=>'Open weekly report','url'=>$this->sourceUrl($department,'weekly',$record->week_start)]],
                $record->project_ends_on ?: $record->week_end,$record->risk_level==='high',$verified,false,$record->updated_at,'Published weekly workstream',[],null,$record->progress_percent));
        }

        $tasks = Task::realWork()->with(['assignee','creativeRequest'])->whereNotIn('id',$linkedTasks)->where(function ($q) use ($from,$to) {
            $q->whereBetween('created_at',[$from,$to])->orWhereBetween('due_on',[$from,$to]);
        })->get();
        foreach ($tasks as $task) {
            if (!$task->canBeViewedBy($viewer)) continue;
            // Creative briefs already provide the richer task journey, including parent packages.
            $verified = $task->completion_review_status==='approved';
            $reported = in_array(strtolower($task->status ?? ''),['completed','done','approved','paid'],true);
            $department = User::normalizeDepartmentKey($task->department ?: $task->assignee?->department);
            $steps = [
                $this->step('Assigned',User::departmentLabel($department),'done',$task->created_at?->format('d M Y H:i')),
                $this->step('Work & collaboration','Owner + supporting staff',$reported ? 'done' : 'current',$task->status),
                $this->step('Completion review','Line manager',$verified ? 'done' : ($task->completion_review_status==='pending' ? 'current' : 'waiting'),$verified ? 'Completion approved' : 'Awaiting approved completion'),
            ];
            $rows->push($this->row('task-'.$task->id,'tasks',$task->title ?: 'Untitled task',$task->client_name,[$department],
                array_merge([$task->assigned_to,$task->assigned_by],$task->supporting_staff_ids ?? []),$task->assigned_to,
                $verified ? 'Verified complete' : ($task->completion_review_status==='pending' ? 'Completion review' : ($task->status ?: 'Assigned')),
                $steps,[['label'=>'Daily task','title'=>'Open task details','url'=>route('portal.tasks.show',$task)]],
                $task->due_on,$task->completion_review_status==='reverted',$verified,false,$task->updated_at,'Daily task'));
        }

        $department = $request->query('activity_department');
        $staff = $request->integer('activity_staff');
        $rows = $rows->filter(fn ($row) => (!$department || in_array($department,$row['departments'],true)) && (!$staff || in_array($staff,$row['people_ids'],true)));
        if ($term=trim($filters['tracker_q'] ?? '')) $rows=$rows->filter(fn ($row)=>Str::contains(mb_strtolower($row['title'].' '.$row['client']),mb_strtolower($term)));
        $counts = ['all'=>$rows->count(),'projects'=>$rows->where('type','projects')->count(),'weekly'=>$rows->where('type','weekly')->count(),'tasks'=>$rows->where('type','tasks')->count()];
        $type=$filters['tracker_type'] ?? 'projects';
        $selected=$type==='all' ? $rows : $rows->where('type',$type);
        $summary=['total'=>$selected->count(),'active'=>$selected->where('complete',false)->where('cancelled',false)->count(),
            'attention'=>$selected->where('attention',true)->count(),'complete'=>$selected->where('complete',true)->count(),
            'unlinked'=>$selected->filter(fn($r)=>count($r['gaps'])>0)->count()];
        $status=$filters['tracker_status'] ?? 'all';
        $selected=$selected->filter(fn($r)=>match($status){'active'=>!$r['complete']&&!$r['cancelled'],'attention'=>$r['attention'],'complete'=>$r['complete'],default=>true});
        $distribution=$selected->countBy('current');
        $selected=$selected->sortByDesc(fn($r)=>($r['attention'] ? '2' : ($r['complete'] ? '0' : '1')).($r['updated_at']?->format('YmdHis') ?? ''))->values();
        $page=min(max(1,$request->integer('tracker_page',1)),max(1,(int)ceil($selected->count()/6)));
        $records=new LengthAwarePaginator($selected->forPage($page,6)->values(),$selected->count(),6,$page,
            ['path'=>route('dashboard',[],false),'pageName'=>'tracker_page','query'=>$request->except(['fragment','tracker_page'])]);
        $records->fragment('executive-project-tracker');
        $names=User::whereIn('id',$records->getCollection()->flatMap(fn($r)=>$r['people_ids'])->unique())->pluck('name','id');
        return compact('records','summary','counts','distribution','type','status','from','to','period','names')+['updatedAt'=>now()];
    }

    private function row(string $key,string $type,string $title,?string $client,array $departments,array $people,?int $owner,string $current,array $steps,array $sources,$due,bool $attention,bool $complete,bool $cancelled,$updated,string $origin,array $gaps=[],?string $blocker=null,$progress=null,bool $overdueEligible=true): array
    {
        try { $due=$due ? Carbon::parse($due) : null; } catch (\Throwable) { $due=null; }
        $overdue=$overdueEligible&&!$complete&&!$cancelled&&$due&&$due->copy()->endOfDay()->isPast();
        return ['key'=>$key,'type'=>$type,'title'=>$title,'client'=>$client,'departments'=>array_values(array_unique($departments)),
            'people_ids'=>collect($people)->filter(fn($id)=>is_numeric($id)&&(int)$id>0)->map(fn($id)=>(int)$id)->unique()->values()->all(),'owner_id'=>$owner,'current'=>$current,'steps'=>$steps,
            'sources'=>$sources,'due'=>$due,'attention'=>!$cancelled&&($attention||$overdue),'overdue'=>$overdue,'complete'=>$complete,'cancelled'=>$cancelled,
            'updated_at'=>$updated,'origin'=>$origin,'gaps'=>$gaps,'blocker'=>$blocker,'progress'=>is_numeric($progress) ? max(0,min(100,(float)$progress)) : null];
    }

    private function step(string $label,string $team,string $state,?string $detail): array
    {
        return compact('label','team','state','detail');
    }

    private function sourceUrl(string $department,string $section,?Carbon $date,?int $activation=null): string
    {
        return route('dashboard',array_filter(['activity_department'=>$department,'activity_section'=>$section,'report_period'=>'week',
            'report_date'=>$date?->toDateString(),'activity_activation'=>$activation])).'#executive-source-reports';
    }
}
