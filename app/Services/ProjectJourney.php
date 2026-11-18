<?php

namespace App\Services;

use App\Models\{CreativeRequest, CrmPipelineProject, OperationsHandover, PerformanceRegister, User};
use App\Services\Creative\CreativeAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\{Carbon, Collection, Str};

class ProjectJourney
{
    public const STAGES = [
        'client' => ['Client project', 'Client Relations'],
        'creative' => ['Creative brief & design', 'Creative'],
        'approval' => ['Creative sign-off', 'Creative & Client Relations'],
        'handover' => ['Operations handover', 'Client Relations & Operations'],
        'execution' => ['Execution', 'Operations'],
        'closeout' => ['Closeout', 'Operations & Client Relations'],
    ];

    public function canAccess(User $viewer): bool
    {
        return $viewer->canViewExecutiveActivity() || CreativeAccess::staff($viewer);
    }

    public function scopeVisible(Builder $query, User $viewer): Builder
    {
        if($viewer->canViewExecutiveActivity() || CreativeAccess::lead($viewer) || OperationsHandoverWorkflow::lead($viewer)
            || (ClientRelationsPipeline::staffMember($viewer)&&$viewer->isLineManager())) return $query;
        return $query->where(fn($q)=>$q->where('owner_id',$viewer->id)->orWhereJsonContains('supporting_staff_ids',$viewer->id)
            ->orWhereIn('id',CreativeRequest::where(fn($q)=>$q->where('requester_id',$viewer->id)->orWhere('assignee_id',$viewer->id)->orWhere('reviewer_id',$viewer->id)->orWhere(fn($support)=>$support->withSupportingStaff($viewer->id))->orWhereHas('parts',fn($parts)=>$parts->withParticipant($viewer->id)))->whereNotNull('crm_project_id')->select('crm_project_id'))
            ->orWhereIn('id',OperationsHandover::where(fn($q)=>$q->where('requester_id',$viewer->id)->orWhere('lead_id',$viewer->id))->select('project_id'))
            ->orWhereIn('id',PerformanceRegister::whereIn('department',User::departmentAliases('operations_projects'))->whereNotNull('approved_at')->where('owner_id',$viewer->id)->whereNotNull('data->crm_project_id')->select('data->crm_project_id')));
    }

    public function canLink(User $viewer, CrmPipelineProject $project): bool
    {
        return CreativeAccess::lead($viewer) || (ClientRelationsPipeline::staffMember($viewer)
            && ($viewer->isLineManager() || $viewer->id === (int) $project->owner_id));
    }

    public function build(Request $request): array
    {
        $viewer = $request->user();
        abort_unless($this->canAccess($viewer), 403);
        $filters = $request->validate([
            'project' => 'nullable|integer|min:1',
            'q' => 'nullable|string|max:100',
            'period' => 'nullable|in:week,month,year',
            'date' => 'nullable|date_format:Y-m-d',
            'view' => 'nullable|in:journeys,upcoming,insights',
            'stage' => 'nullable|in:client,creative,approval,handover,execution,closeout,complete,cancelled',
        ]);
        $period = $filters['period'] ?? 'month';
        $date = Carbon::parse($filters['date'] ?? today());
        $from = match ($period) {
            'year' => $date->copy()->startOfYear(), 'week' => $date->copy()->startOfWeek(), default => $date->copy()->startOfMonth(),
        };
        $to = match ($period) {
            'year' => $from->copy()->endOfYear(), 'week' => $from->copy()->endOfWeek(), default => $from->copy()->endOfMonth(),
        };
        $view = $filters['view'] ?? 'journeys';
        $focus = $filters['project'] ?? null;
        $projects = $this->scopeVisible(CrmPipelineProject::with('owner:id,name')->where('source_period_confirmed', true),$viewer)
            ->when($focus, fn ($q) => $q->whereKey($focus), fn ($q) => $this->inPeriod($q, $from, $to, $viewer))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($search) => $search
                ->where('client_name', 'like', '%'.$term.'%')->orWhere('project_name', 'like', '%'.$term.'%')->orWhere('brand', 'like', '%'.$term.'%')))
            ->latest('week_start')->orderByDesc('id')->get();
        abort_if($focus && $projects->isEmpty(), 404);

        $ids = $projects->pluck('id');
        $visibility = fn (Builder $q) => $q->visibleTo($viewer);
        $briefs = CreativeRequest::visibleTo($viewer)->whereIn('crm_project_id', $ids)->whereNull('parent_request_id')
            ->with(['task', 'requester:id,name', 'assignee:id,name', 'parts.task', 'parts' => fn ($query) => $visibility($query->getQuery()), 'parts.assignee:id,name'])
            ->orderBy('id')->get()->groupBy('crm_project_id');
        $handovers = OperationsHandover::whereIn('project_id', $ids)->with(['task', 'lead:id,name', 'requester:id,name'])
            ->orderBy('event_at')->get()->groupBy('project_id');
        $activations = OperationsReporting::activations()->whereIn('data->crm_project_id', $ids)
            ->orderBy('period_start')->get()->groupBy(fn ($row) => (int) ($row->data['crm_project_id'] ?? 0));

        $rows = $projects->map(fn ($project) => $this->project($project, $briefs->get($project->id, collect()),
            $handovers->get($project->id, collect()), $activations->get($project->id, collect()), $viewer));
        $distribution = $rows->countBy('stage');
        $upcoming = $rows->whereNotIn('stage', ['complete', 'cancelled'])->filter(fn ($row) => $row['date'] && $row['date']->gte(today()))
            ->filter(fn ($row) => $row['date']->betweenIncluded($from, $to))->sortBy('date')->values();
        $attention = $rows->filter(fn ($row) => $row['needs_attention'])->count();
        $schedule = $upcoming->countBy(fn ($row) => $row['date']->copy()->startOfWeek()->toDateString())->sortKeys();
        $filtered = $view === 'upcoming' ? $upcoming : $rows;
        if (!empty($filters['stage'])) {
            $filtered = $filtered->where('stage', $filters['stage']);
        }
        $page = max(1, $request->integer('page', 1));
        $page = min($page, max(1, (int) ceil($filtered->count() / 10)));
        $records = new LengthAwarePaginator($filtered->forPage($page, 10)->values(), $filtered->count(), 10, $page, [
            'path' => route('portal.project-journey', [], false), 'query' => $request->except(['fragment', 'page']),
        ]);
        $linkOptions = $focus && $this->canLink($viewer, $projects->first())
            ? CreativeRequest::visibleTo($viewer)->whereNull('parent_request_id')->whereNull('crm_project_id')
                ->when(!CreativeAccess::lead($viewer), fn ($q) => $q->where('requester_id', $viewer->id))
                ->latest('id')->limit(100)->get(['id', 'title', 'reference', 'revision'])
            : collect();

        return compact('records', 'view', 'period', 'date', 'from', 'to', 'filters', 'distribution', 'attention', 'upcoming', 'schedule', 'linkOptions')
            + ['rows' => $rows, 'total' => $rows->count(), 'computedAt' => now(), 'focus' => $focus];
    }

    private function inPeriod(Builder $query, Carbon $from, Carbon $to, User $viewer): Builder
    {
        return $query->where(fn ($dates) => $dates->whereBetween('week_start', [$from->toDateString(), $to->toDateString()])
            ->orWhereBetween('expected_close_date', [$from->toDateString(), $to->toDateString()])
            ->orWhereIn('id', OperationsHandover::whereBetween('event_at', [$from, $to])->select('project_id'))
            ->orWhereIn('id', CreativeRequest::visibleTo($viewer)->whereNotNull('crm_project_id')->whereBetween('requested_deadline', [$from, $to])->select('crm_project_id'))
            ->orWhereIn('id', OperationsReporting::activations()->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])->select('data->crm_project_id')));
    }

    private function project(CrmPipelineProject $project, Collection $briefs, Collection $handovers, Collection $activations, User $viewer): array
    {
        $requirements=$project->team_workflow??[];
        $skipCreative=($requirements['creative_required']??null)===false && $briefs->isEmpty();
        $skipOperations=($requirements['operations_required']??null)===false && $handovers->isEmpty() && $activations->isEmpty();
        $skipped=['client'=>false,'creative'=>$skipCreative,'approval'=>$skipCreative,'handover'=>$skipOperations,'execution'=>$skipOperations,'closeout'=>false];
        $liveHandovers = $handovers->where('stage', '!=', 'cancelled');
        $liveActivations = $activations->where('status', '!=', 'cancelled');
        $finishedHandovers = $liveHandovers->filter(fn ($h) => $h->stage === 'completed' && $h->verified_at);
        $signedHandovers = $liveHandovers->filter(fn ($h) => $h->cr_signoff_at && $h->ops_signoff_at);
        $packages = $briefs->flatMap(fn ($b) => $b->is_group && $b->parts->isNotEmpty() ? $b->parts : [$b]);
        $packages = $packages->reject(fn ($b) => $b->stage === 'archived' && !$b->completed_at);
        $signedBriefs = $packages->filter(fn ($b) => in_array($b->stage, ['completed', 'archived'], true) && $b->completed_at);
        $reviewingBriefs = $packages->filter(fn ($b) => in_array($b->stage, ['in_review', 'delivered'], true));
        $submitted = $liveHandovers->filter(fn ($h) => in_array($h->stage, ['awaiting_acceptance', 'accepted', 'completed'], true) && $h->submitted_at);
        $date = $liveHandovers->pluck('event_at')->filter()->merge($liveActivations->map(fn (PerformanceRegister $r) =>
            !empty($r->data['starts_on']) ? Carbon::parse($r->data['starts_on']) : null)->filter())->sort()->values();
        $nextEvent = $date->first(fn ($dt) => $dt->gte(today())) ?? $date->last();
        $targetDate = $nextEvent ?? $project->expected_close_date;
        $finished = ($skipOperations && $project->stage==='project_completed') || $liveHandovers->isNotEmpty() && $finishedHandovers->count() === $liveHandovers->count()
            && $liveActivations->every(fn ($record) => $record->status === 'completed');
        $stage = match (true) {
            in_array($project->stage, ['closed_lost', 'cancelled', 'lost'], true) => 'cancelled',
            $finished && $packages->count() === $signedBriefs->count() => 'complete',
            $skipOperations && ($skipCreative || ($packages->isNotEmpty() && $signedBriefs->count()===$packages->count())) => 'closeout',
            $packages->isNotEmpty() && $signedBriefs->count() !== $packages->count()
                && $reviewingBriefs->count() + $signedBriefs->count() !== $packages->count() => 'creative',
            $packages->isNotEmpty() && $signedBriefs->count() !== $packages->count() => 'approval',
            $submitted->isNotEmpty() && $submitted->count() === $liveHandovers->count() => 'closeout',
            $signedHandovers->isNotEmpty() => 'execution',
            $liveHandovers->isNotEmpty() => 'handover',
            $signedBriefs->isNotEmpty() && $signedBriefs->count() === $packages->count() => 'handover',
            $reviewingBriefs->isNotEmpty() && $reviewingBriefs->count() + $signedBriefs->count() === $packages->count() => 'approval',
            $briefs->isNotEmpty() => 'creative',
            default => 'client',
        };
        $done = [
            'client' => true,
            'creative' => $packages->isNotEmpty() && $reviewingBriefs->count() + $signedBriefs->count() === $packages->count(),
            'approval' => $packages->isNotEmpty() && $signedBriefs->count() === $packages->count(),
            'handover' => $liveHandovers->isNotEmpty() && $signedHandovers->count() === $liveHandovers->count(),
            'execution' => $liveHandovers->isNotEmpty() && $submitted->count() === $liveHandovers->count(),
            'closeout' => $stage === 'complete',
        ];
        foreach($skipped as $key=>$skip) if($skip)$done[$key]=true;
        $notes = [
            'client' => $project->week_start?->format('d M Y').' · '.Str::headline($project->stage),
            'creative' => $packages->count().' linked work '.Str::plural('package', $packages->count()),
            'approval' => $signedBriefs->count().' of '.$packages->count().' confirmed',
            'handover' => $signedHandovers->count().' of '.$liveHandovers->count().' signed by both teams',
            'execution' => $submitted->count().' of '.$liveHandovers->count().' delivery records submitted',
            'closeout' => $finishedHandovers->count().' of '.$liveHandovers->count().' handovers verified',
        ];
        if($skipOperations)$notes['closeout']=$project->stage==='project_completed'?'Client Relations project completion recorded':'Client Relations final project closeout pending';
        foreach($skipped as $key=>$skip) if($skip)$notes[$key]='Not required for this project · stage skipped';
        $needsAttention = !in_array($stage, ['complete','cancelled'], true) && (
            ($targetDate && $targetDate->lt(today())) || $packages->contains(fn ($item) => filled($item->blocker))
            || $liveActivations->contains(fn ($item) => in_array($item->status, ['blocked', 'on_hold'], true))
        );

        return ['project' => $project, 'stage' => $stage, 'done' => $done, 'skipped'=>$skipped, 'notes' => $notes, 'briefs' => $briefs, 'handovers' => $handovers,
            'activations' => $activations, 'date' => $targetDate, 'date_label' => $nextEvent ? 'Execution date' : 'Expected close',
            'needs_attention' => $needsAttention, 'can_link' => $this->canLink($viewer, $project),
            'can_unlink' => fn (CreativeRequest $brief) => $this->canLink($viewer, $project)
                && (CreativeAccess::lead($viewer) || $viewer->id === (int) $brief->requester_id)];
    }
}
